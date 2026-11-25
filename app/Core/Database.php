<?php

namespace App\Core;

use App\Database\QuanLyCauTruc;
use mysqli;
use mysqli_result;
use mysqli_stmt;
use Throwable;

/**
 * Lớp Database - quản lý một kết nối MySQL duy nhất (Singleton) và cung cấp
 * các phương thức truy vấn an toàn bằng prepared statement.
 *
 * Ví dụ:
 *   $db = Database::getInstance();
 *   $rows = $db->fetchAll('SELECT * FROM products WHERE category_id = ?', [$categoryId]);
 *   $user = $db->fetchOne('SELECT * FROM users WHERE id = ?', [$id]);
 *   $id   = $db->insert('INSERT INTO categories (name, slug) VALUES (?, ?)', [$name, $slug]);
 *
 * Kiểu tham số (bind types) được suy ra tự động: int => i, float => d, còn lại => s.
 * Có thể truyền chuỗi $types để chỉ định rõ.
 */
final class Database
{
    private static ?Database $instance = null;

    private mysqli $connection;

    private function __construct(array $config)
    {
        $connection = @new mysqli(
            (string) ($config['host'] ?? 'localhost'),
            (string) ($config['username'] ?? 'root'),
            (string) ($config['password'] ?? ''),
            (string) ($config['database'] ?? '')
        );

        if ($connection->connect_error) {
            die(json_encode(
                ['status' => 'error', 'message' => 'Kết nối DB thất bại: ' . $connection->connect_error],
                JSON_UNESCAPED_UNICODE
            ));
        }

        $connection->set_charset((string) ($config['charset'] ?? 'utf8mb4'));
        $this->connection = $connection;
    }

    private function __clone()
    {
    }

    /**
     * Lấy thể hiện duy nhất của Database. Lần đầu khởi tạo sẽ tự đồng bộ cấu trúc CSDL.
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            $config = require APP_ROOT . '/config/database.php';
            self::$instance = new self(is_array($config) ? $config : []);
            $hasOrders = (new QuanLyCauTruc(self::$instance))->migrate();

            // Mỗi request web tự hủy các đơn đã hết thời gian giữ tồn kho (CLI dùng scripts/het_han_giu_ton_kho.php).
            if ($hasOrders && PHP_SAPI !== 'cli') {
                (new \App\Services\TonKhoDonHangService(self::$instance))->expireReservations();
            }
        }

        return self::$instance;
    }

    /**
     * Trả về đối tượng mysqli gốc (dùng khi cần thao tác đặc biệt).
     */
    public function connection(): mysqli
    {
        return $this->connection;
    }

    /**
     * Thực thi câu lệnh SQL. Nếu có tham số sẽ dùng prepared statement.
     *
     * @return mysqli_result|bool kết quả SELECT hoặc true khi thành công
     */
    public function query(string $sql, array $params = [], ?string $types = null)
    {
        if ($params === []) {
            $result = $this->connection->query($sql);
            if ($result === false) {
                throw new DatabaseException($this->connection->error, $this->connection->errno, $sql);
            }

            return $result;
        }

        $stmt = $this->prepare($sql, $params, $types);
        $this->run($stmt, $sql);
        $result = $stmt->get_result();
        $stmt->close();

        return $result === false ? true : $result;
    }

    /**
     * Chuẩn bị và bind tham số cho một câu lệnh. Người gọi chịu trách nhiệm execute()/close().
     */
    public function prepare(string $sql, array $params = [], ?string $types = null): mysqli_stmt
    {
        $stmt = $this->connection->prepare($sql);
        if ($stmt === false) {
            throw new DatabaseException($this->connection->error, $this->connection->errno, $sql);
        }

        if ($params !== []) {
            $params = array_values($params);
            $types = $types ?? self::inferTypes($params);
            $stmt->bind_param($types, ...$params);
        }

        return $stmt;
    }

    /** Lấy tất cả dòng kết quả dạng mảng kết hợp. */
    public function fetchAll(string $sql, array $params = [], ?string $types = null): array
    {
        $result = $this->query($sql, $params, $types);
        if (!$result instanceof mysqli_result) {
            return [];
        }

        $rows = $result->fetch_all(MYSQLI_ASSOC);
        $result->free();

        return $rows;
    }

    /** Lấy một dòng đầu tiên hoặc null. */
    public function fetchOne(string $sql, array $params = [], ?string $types = null): ?array
    {
        $result = $this->query($sql, $params, $types);
        if (!$result instanceof mysqli_result) {
            return null;
        }

        $row = $result->fetch_assoc();
        $result->free();

        return $row ?: null;
    }

    /** Lấy giá trị cột đầu tiên của dòng đầu tiên. */
    public function fetchValue(string $sql, array $params = [], ?string $types = null)
    {
        $row = $this->fetchOne($sql, $params, $types);

        return $row === null ? null : reset($row);
    }

    /** Lấy danh sách giá trị của cột đầu tiên. */
    public function fetchColumn(string $sql, array $params = [], ?string $types = null): array
    {
        return array_map(static fn(array $row) => reset($row), $this->fetchAll($sql, $params, $types));
    }

    /** Thực thi INSERT/UPDATE/DELETE và trả về số dòng bị ảnh hưởng. */
    public function execute(string $sql, array $params = [], ?string $types = null): int
    {
        if ($params === []) {
            $this->query($sql);

            return $this->connection->affected_rows;
        }

        $stmt = $this->prepare($sql, $params, $types);
        $this->run($stmt, $sql);
        $affected = $stmt->affected_rows;
        $stmt->close();

        return $affected;
    }

    /** Thực thi INSERT và trả về ID vừa tạo. */
    public function insert(string $sql, array $params = [], ?string $types = null): int
    {
        $this->execute($sql, $params, $types);

        return (int) $this->connection->insert_id;
    }

    public function lastInsertId(): int
    {
        return (int) $this->connection->insert_id;
    }

    public function affectedRows(): int
    {
        return (int) $this->connection->affected_rows;
    }

    public function error(): string
    {
        return $this->connection->error;
    }

    public function escape(string $value): string
    {
        return $this->connection->real_escape_string($value);
    }

    public function beginTransaction(): void
    {
        $this->connection->begin_transaction();
    }

    public function commit(): void
    {
        $this->connection->commit();
    }

    public function rollback(): void
    {
        $this->connection->rollback();
    }

    /**
     * Chạy một khối lệnh trong transaction; tự rollback nếu có lỗi.
     *
     * @return mixed giá trị trả về của $callback
     */
    public function transaction(callable $callback)
    {
        $this->beginTransaction();
        try {
            $result = $callback($this);
            $this->commit();

            return $result;
        } catch (Throwable $e) {
            $this->rollback();
            throw $e;
        }
    }

    public function tableExists(string $table): bool
    {
        // MariaDB không cho dùng tham số "?" trong lệnh SHOW, nên escape thủ công.
        return $this->fetchOne("SHOW TABLES LIKE '" . $this->escape($table) . "'") !== null;
    }

    public function columnExists(string $table, string $column): bool
    {
        $table = str_replace('`', '', $table);

        return $this->fetchOne("SHOW COLUMNS FROM `{$table}` LIKE '" . $this->escape($column) . "'") !== null;
    }

    /** @return array<string, array> danh sách cột của bảng (key = tên cột) */
    public function columns(string $table): array
    {
        $table = str_replace('`', '', $table);
        $columns = [];
        foreach ($this->fetchAll("SHOW COLUMNS FROM `{$table}`") as $column) {
            $columns[$column['Field']] = $column;
        }

        return $columns;
    }

    public function close(): void
    {
        // Kết nối được dùng chung trong suốt request, PHP sẽ tự đóng khi kết thúc.
    }

    private function run(mysqli_stmt $stmt, string $sql): void
    {
        if (!$stmt->execute()) {
            $message = $stmt->error;
            $code = $stmt->errno;
            $stmt->close();
            throw new DatabaseException($message, $code, $sql);
        }
    }

    private static function inferTypes(array $params): string
    {
        $types = '';
        foreach ($params as $param) {
            if (is_int($param) || is_bool($param)) {
                $types .= 'i';
            } elseif (is_float($param)) {
                $types .= 'd';
            } else {
                $types .= 's';
            }
        }

        return $types;
    }
}
