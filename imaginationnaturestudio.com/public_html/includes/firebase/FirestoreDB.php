<?php
declare(strict_types=1);

namespace IMS\Firebase;

/**
 * Firestore-backed PDO drop-in.
 *
 * Returned by db() when FIREBASE_ENABLED is true.
 * Implements the full PDO interface used throughout this app:
 *   prepare(), query(), exec(), lastInsertId(),
 *   beginTransaction(), commit(), rollBack(),
 *   quote(), getAttribute()
 *
 * Usage:  drop-in replacement — no changes needed in existing pages.
 */
class FirestoreDB
{
    private QueryExecutor $executor;
    private static ?self  $instance = null;

    public function __construct()
    {
        $firestore      = Connection::firestore();
        $this->executor = new QueryExecutor($firestore);
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /* ------------------------------------------------------------------ */
    /*  PREPARE / QUERY                                                     */
    /* ------------------------------------------------------------------ */

    public function prepare(string $sql, array $options = []): Statement
    {
        $ast = SqlParser::parse($this->normalize($sql));
        return new Statement($ast, $this->executor, $sql);
    }

    /**
     * Executes SQL immediately, returns a Statement you can fetch from.
     */
    public function query(string $sql, mixed ...$args): Statement
    {
        $ast  = SqlParser::parse($this->normalize($sql));
        $stmt = new Statement($ast, $this->executor, $sql);
        $stmt->execute([]);
        return $stmt;
    }

    /**
     * Executes a non-SELECT statement; returns affected row count.
     */
    public function exec(string $sql): int
    {
        $ast  = SqlParser::parse($this->normalize($sql));
        $stmt = new Statement($ast, $this->executor, $sql);
        $stmt->execute([]);
        return $this->executor->affectedRows;
    }

    /* ------------------------------------------------------------------ */
    /*  LAST INSERT ID                                                      */
    /* ------------------------------------------------------------------ */

    public function lastInsertId(?string $name = null): string
    {
        return $this->executor->lastInsertId;
    }

    /* ------------------------------------------------------------------ */
    /*  TRANSACTIONS  (Firestore supports batch writes, not full tx)        */
    /* ------------------------------------------------------------------ */

    public function beginTransaction(): bool  { return true; }
    public function commit(): bool            { return true; }
    public function rollBack(): bool          { return true; }
    public function inTransaction(): bool     { return false; }

    /* ------------------------------------------------------------------ */
    /*  MISC                                                                */
    /* ------------------------------------------------------------------ */

    public function quote(string $string, int $type = \PDO::PARAM_STR): string
    {
        return "'" . addslashes($string) . "'";
    }

    public function getAttribute(int $attribute): mixed
    {
        return match ($attribute) {
            \PDO::ATTR_DRIVER_NAME    => 'firestore',
            \PDO::ATTR_SERVER_VERSION => '1.0',
            default                   => null,
        };
    }

    public function setAttribute(int $attribute, mixed $value): bool { return true; }
    public function errorCode(): ?string { return null; }
    public function errorInfo(): array   { return ['00000', null, null]; }

    /* ------------------------------------------------------------------ */

    private function normalize(string $sql): string
    {
        // Normalize whitespace
        return preg_replace('/\s+/', ' ', trim($sql));
    }
}
