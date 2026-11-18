<?php
function ensureChatTable(mysqli $conn): bool
{
    $createTableSql = "CREATE TABLE IF NOT EXISTS chat_messages (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        user_id INT UNSIGNED NOT NULL,
        username VARCHAR(100) NOT NULL,
        sender ENUM('customer', 'admin') NOT NULL DEFAULT 'customer',
        is_read TINYINT(1) NOT NULL DEFAULT 0,
        message_type ENUM('text', 'image', 'file') NOT NULL DEFAULT 'text',
        message LONGTEXT NOT NULL,
        product_id INT UNSIGNED NULL,
        attachment_path VARCHAR(255) NULL,
        attachment_name VARCHAR(255) NULL,
        attachment_mime VARCHAR(127) NULL,
        attachment_size INT UNSIGNED NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_chat_messages_user_created (user_id, created_at),
        CONSTRAINT fk_chat_messages_user
            FOREIGN KEY (user_id) REFERENCES users (id)
            ON UPDATE CASCADE ON DELETE CASCADE
    ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";

    $tableResult = $conn->query("SELECT COUNT(*) AS table_exists
                                 FROM information_schema.tables
                                 WHERE table_schema = DATABASE() AND table_name = 'chat_messages'");
    if (!$tableResult) {
        error_log('Unable to check chat_messages table: ' . $conn->error);
        return false;
    }
    $tableExists = (int) ($tableResult->fetch_assoc()['table_exists'] ?? 0) > 0;
    if (!$tableExists && !$conn->query($createTableSql)) {
        error_log('Unable to create chat_messages table: ' . $conn->error);
        return false;
    }

    $columnsResult = $conn->query('SHOW COLUMNS FROM chat_messages');
    if (!$columnsResult) {
        error_log('Unable to inspect chat_messages columns: ' . $conn->error);
        return false;
    }
    $columns = [];
    while ($column = $columnsResult->fetch_assoc()) {
        $columns[$column['Field']] = strtolower((string) $column['Type']);
    }

    $schemaUpdates = [];
    if (isset($columns['message']) && strpos($columns['message'], 'longtext') === false) {
        $schemaUpdates[] = 'ALTER TABLE chat_messages MODIFY COLUMN message LONGTEXT NOT NULL';
    }
    if (!isset($columns['message_type'])) {
        $schemaUpdates[] = "ALTER TABLE chat_messages ADD COLUMN message_type ENUM('text', 'image', 'file') NOT NULL DEFAULT 'text'";
    } elseif (strpos($columns['message_type'], "'file'") === false) {
        $schemaUpdates[] = "ALTER TABLE chat_messages MODIFY COLUMN message_type ENUM('text', 'image', 'file') NOT NULL DEFAULT 'text'";
    }
    if (!isset($columns['is_read'])) {
        $schemaUpdates[] = 'ALTER TABLE chat_messages ADD COLUMN is_read TINYINT(1) NOT NULL DEFAULT 0';
        $schemaUpdates[] = "UPDATE chat_messages incoming
                            JOIN chat_messages answer
                              ON answer.user_id = incoming.user_id
                             AND answer.sender = 'admin'
                             AND answer.id > incoming.id
                            SET incoming.is_read = 1
                            WHERE incoming.sender = 'customer'";
    }

    $missingAttachments = [
        'product_id' => 'INT UNSIGNED NULL',
        'attachment_path' => 'VARCHAR(255) NULL',
        'attachment_name' => 'VARCHAR(255) NULL',
        'attachment_mime' => 'VARCHAR(127) NULL',
        'attachment_size' => 'INT UNSIGNED NULL',
    ];
    $addColumns = [];
    foreach ($missingAttachments as $name => $definition) {
        if (!isset($columns[$name])) {
            $addColumns[] = 'ADD COLUMN ' . $name . ' ' . $definition;
        }
    }
    if ($addColumns) {
        $schemaUpdates[] = 'ALTER TABLE chat_messages ' . implode(', ', $addColumns);
    }

    foreach ($schemaUpdates as $sql) {
        if (!$conn->query($sql)) {
            error_log('Unable to update chat_messages schema: ' . $conn->error);
            return false;
        }
    }

    return true;
}

function chatAttachmentDirectory(): string
{
    return __DIR__ . '/../../storage/chat';
}
?>
