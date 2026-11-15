<?php
declare(strict_types=1);

namespace IMS\Firebase;

/**
 * Firestore-backed PDOStatement drop-in.
 * Returned by FirestoreDB::prepare() and FirestoreDB::query().
 * All public methods match the PDOStatement interface.
 */
class Statement
{
    private array          $ast;
    private QueryExecutor  $executor;
    private array          $rows      = [];
    private int            $cursor    = 0;
    private array          $boundParams = [];
    public  string         $queryString = '';

    public function __construct(array $ast, QueryExecutor $executor, string $sql = '')
    {
        $this->ast       = $ast;
        $this->executor  = $executor;
        $this->queryString = $sql;
    }

    /* ------------------------------------------------------------------ */

    public function execute(?array $params = null): bool
    {
        $p = $params ?? $this->boundParams;

        // Re-index if named params
        if (!empty($p) && array_keys($p) !== range(0, count($p) - 1)) {
            $p = array_values($p);
        }

        try {
            $this->rows   = $this->executor->execute($this->ast, $p);
            $this->cursor = 0;
            return true;
        } catch (\Throwable $e) {
            error_log('[FirestoreDB] execute error: ' . $e->getMessage() . ' | SQL: ' . $this->queryString);
            return false;
        }
    }

    /* ------------------------------------------------------------------ */

    public function fetch(int $mode = \PDO::FETCH_ASSOC): mixed
    {
        if ($this->cursor >= count($this->rows)) {
            return false;
        }
        return $this->rows[$this->cursor++];
    }

    public function fetchAll(int $mode = \PDO::FETCH_ASSOC): array
    {
        return $this->rows;
    }

    public function fetchColumn(int $column = 0): mixed
    {
        if (empty($this->rows)) return false;
        $row = $this->rows[0];
        $values = array_values($row);
        return $values[$column] ?? false;
    }

    public function fetchObject(string $class = \stdClass::class): mixed
    {
        $row = $this->fetch();
        if ($row === false) return false;
        return (object)$row;
    }

    /* ------------------------------------------------------------------ */

    public function rowCount(): int
    {
        if (in_array($this->ast['type'], ['INSERT', 'UPDATE', 'DELETE'])) {
            return $this->executor->affectedRows;
        }
        return count($this->rows);
    }

    public function columnCount(): int
    {
        return empty($this->rows) ? 0 : count($this->rows[0]);
    }

    /* ------------------------------------------------------------------ */

    public function bindParam(
        string|int $param,
        mixed &$var,
        int $type = \PDO::PARAM_STR,
        int $maxLength = 0
    ): bool {
        $key = is_int($param) ? $param - 1 : $param;
        $this->boundParams[$key] = $var;
        return true;
    }

    public function bindValue(string|int $param, mixed $value, int $type = \PDO::PARAM_STR): bool
    {
        $key = is_int($param) ? $param - 1 : $param;
        $this->boundParams[$key] = $value;
        return true;
    }

    /* ------------------------------------------------------------------ */

    public function closeCursor(): bool
    {
        $this->rows   = [];
        $this->cursor = 0;
        return true;
    }

    public function setFetchMode(int $mode, mixed ...$args): bool { return true; }
    public function getColumnMeta(int $column): array|false { return false; }
    public function nextRowset(): bool { return false; }
    public function errorCode(): ?string { return null; }
    public function errorInfo(): array { return ['', null, null]; }

    /* Allow foreach iteration */
    public function __debugInfo(): array { return ['rows' => count($this->rows), 'sql' => $this->queryString]; }
}
