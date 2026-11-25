<?php

namespace App\Models;

use App\Core\Database;

/**
 * Lớp trừu tượng BaseModel - lớp cha của mọi Model.
 * Mỗi Model đại diện cho một bảng; lớp con khai báo tên bảng qua thuộc tính $table
 * và kế thừa sẵn các thao tác CRUD cơ bản.
 */
abstract class BaseModel
{
    /** Tên bảng trong CSDL (lớp con bắt buộc khai báo). */
    protected string $table;

    /** Tên khóa chính. */
    protected string $primaryKey = 'id';

    protected Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    public function getTable(): string
    {
        return $this->table;
    }

    /** Tìm một bản ghi theo khóa chính. */
    public function find(int $id): ?array
    {
        return $this->db->fetchOne(
            "SELECT * FROM `{$this->table}` WHERE `{$this->primaryKey}` = ? LIMIT 1",
            [$id]
        );
    }

    /** Lấy toàn bộ bản ghi (có thể sắp xếp). */
    public function all(string $orderBy = ''): array
    {
        $sql = "SELECT * FROM `{$this->table}`";
        if ($orderBy !== '') {
            $sql .= ' ORDER BY ' . $orderBy;
        }

        return $this->db->fetchAll($sql);
    }

    /** Lấy bản ghi đầu tiên khớp điều kiện cột = giá trị. */
    public function findBy(string $column, $value): ?array
    {
        $column = $this->safeColumn($column);

        return $this->db->fetchOne("SELECT * FROM `{$this->table}` WHERE `{$column}` = ? LIMIT 1", [$value]);
    }

    /** Lấy mọi bản ghi khớp điều kiện cột = giá trị. */
    public function where(string $column, $value, string $orderBy = ''): array
    {
        $column = $this->safeColumn($column);
        $sql = "SELECT * FROM `{$this->table}` WHERE `{$column}` = ?";
        if ($orderBy !== '') {
            $sql .= ' ORDER BY ' . $orderBy;
        }

        return $this->db->fetchAll($sql, [$value]);
    }

    public function exists(int $id): bool
    {
        return $this->db->fetchValue(
            "SELECT 1 FROM `{$this->table}` WHERE `{$this->primaryKey}` = ? LIMIT 1",
            [$id]
        ) !== null;
    }

    public function count(): int
    {
        return (int) $this->db->fetchValue("SELECT COUNT(*) FROM `{$this->table}`");
    }

    /**
     * Thêm bản ghi mới từ mảng [cột => giá trị], trả về ID vừa tạo.
     */
    public function create(array $data): int
    {
        $columns = array_map([$this, 'safeColumn'], array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        $sql = "INSERT INTO `{$this->table}` (`" . implode('`, `', $columns) . "`) VALUES ({$placeholders})";

        return $this->db->insert($sql, array_values($data));
    }

    /**
     * Cập nhật bản ghi theo khóa chính, trả về số dòng bị ảnh hưởng.
     */
    public function update(int $id, array $data): int
    {
        if ($data === []) {
            return 0;
        }

        $assignments = [];
        foreach (array_keys($data) as $column) {
            $assignments[] = '`' . $this->safeColumn($column) . '` = ?';
        }
        $sql = "UPDATE `{$this->table}` SET " . implode(', ', $assignments) . " WHERE `{$this->primaryKey}` = ?";
        $params = array_values($data);
        $params[] = $id;

        return $this->db->execute($sql, $params);
    }

    /** Xóa bản ghi theo khóa chính. */
    public function delete(int $id): int
    {
        return $this->db->execute("DELETE FROM `{$this->table}` WHERE `{$this->primaryKey}` = ?", [$id]);
    }

    public function getDb(): Database
    {
        return $this->db;
    }

    protected function safeColumn(string $column): string
    {
        return preg_replace('/[^A-Za-z0-9_]/', '', $column);
    }
}
