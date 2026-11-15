<?php
declare(strict_types=1);

namespace IMS\Firebase;

/**
 * Lightweight SQL → AST parser covering the patterns used in this app.
 *
 * Supported:
 *   SELECT [cols] FROM table [JOIN ...] [WHERE ...] [ORDER BY ...] [LIMIT n] [OFFSET n]
 *   INSERT INTO table (cols) VALUES (?)
 *   UPDATE table SET col=? [WHERE ...]
 *   DELETE FROM table [WHERE ...]
 *   SELECT COUNT(*) / SUM(col) ...
 */
class SqlParser
{
    public static function parse(string $sql): array
    {
        $sql   = trim($sql);
        $upper = strtoupper($sql);

        if (str_starts_with($upper, 'SELECT'))  return self::parseSelect($sql);
        if (str_starts_with($upper, 'INSERT'))  return self::parseInsert($sql);
        if (str_starts_with($upper, 'UPDATE'))  return self::parseUpdate($sql);
        if (str_starts_with($upper, 'DELETE'))  return self::parseDelete($sql);

        return ['type' => 'RAW', 'sql' => $sql];
    }

    /* ------------------------------------------------------------------ */

    private static function parseSelect(string $sql): array
    {
        $ast = ['type' => 'SELECT', 'columns' => [], 'table' => '', 'joins' => [],
                'where' => [], 'orderBy' => [], 'limit' => null, 'offset' => null,
                'aggregate' => null];

        // LIMIT … OFFSET
        if (preg_match('/\bLIMIT\s+(\d+)(?:\s+OFFSET\s+(\d+))?\s*$/i', $sql, $m)) {
            $ast['limit']  = (int)$m[1];
            $ast['offset'] = isset($m[2]) ? (int)$m[2] : null;
            $sql = preg_replace('/\bLIMIT\s+\d+(?:\s+OFFSET\s+\d+)?\s*$/i', '', $sql);
        }
        // LIMIT n, offset
        if (preg_match('/\bLIMIT\s+(\d+)\s*,\s*(\d+)\s*$/i', $sql, $m)) {
            $ast['offset'] = (int)$m[1];
            $ast['limit']  = (int)$m[2];
            $sql = preg_replace('/\bLIMIT\s+\d+\s*,\s*\d+\s*$/i', '', $sql);
        }

        // ORDER BY
        if (preg_match('/\bORDER\s+BY\s+(.+?)(?:\bLIMIT|\bWHERE|\bGROUP|$)/i', $sql, $m)) {
            foreach (explode(',', trim($m[1])) as $part) {
                $part = trim($part);
                if (preg_match('/^(.+?)\s+(ASC|DESC)$/i', $part, $om)) {
                    $ast['orderBy'][] = ['col' => self::bareCol($om[1]), 'dir' => strtoupper($om[2])];
                } else {
                    $ast['orderBy'][] = ['col' => self::bareCol($part), 'dir' => 'ASC'];
                }
            }
            $sql = preg_replace('/\bORDER\s+BY\s+.+?(?=\bLIMIT|\bWHERE|\bGROUP|$)/i', '', $sql);
        }

        // GROUP BY (strip for now — handle via post-processing)
        $sql = preg_replace('/\bGROUP\s+BY\s+.+?(?=\bHAVING|\bORDER|\bLIMIT|$)/i', '', $sql);

        // WHERE
        if (preg_match('/\bWHERE\s+(.+?)(?:\bORDER|\bGROUP|\bLIMIT|\bHAVING|$)/is', $sql, $m)) {
            $ast['where'] = self::parseWhere(trim($m[1]));
            $sql = preg_replace('/\bWHERE\s+.+?(?=\bORDER|\bGROUP|\bLIMIT|\bHAVING|$)/is', '', $sql);
        }

        // JOINs
        $joinPattern = '/\b(LEFT\s+(?:OUTER\s+)?JOIN|INNER\s+JOIN|RIGHT\s+JOIN|JOIN)\s+`?(\w+)`?\s+(?:\w+\s+)?ON\s+([^\bJOIN\bWHERE]+)/i';
        preg_match_all($joinPattern, $sql, $joins, PREG_SET_ORDER);
        foreach ($joins as $j) {
            $ast['joins'][] = [
                'type'  => strtoupper(preg_replace('/\s+/', ' ', trim($j[1]))),
                'table' => $j[2],
                'on'    => trim($j[3]),
            ];
        }
        if (!empty($ast['joins'])) {
            $sql = preg_replace($joinPattern, '', $sql);
        }

        // FROM table
        if (preg_match('/\bFROM\s+`?(\w+)`?(?:\s+(?:AS\s+)?`?(\w+)`?)?/i', $sql, $m)) {
            $ast['table'] = $m[1];
            $sql = preg_replace('/\bFROM\s+`?\w+`?(?:\s+(?:AS\s+)?`?\w+`?)?/i', '', $sql, 1);
        }

        // SELECT columns / aggregates
        $colStr = trim(preg_replace('/^\s*SELECT\s+/i', '', $sql));

        // COUNT(*)
        if (preg_match('/^\s*COUNT\s*\(\s*\*\s*\)\s*$/i', $colStr)) {
            $ast['aggregate'] = ['func' => 'COUNT', 'col' => '*'];
            $ast['columns']   = ['COUNT(*)'];
        } elseif (preg_match('/^(COUNT|SUM|AVG|MAX|MIN)\s*\((.+?)\)\s*(?:AS\s*\w+)?$/i', $colStr, $m)) {
            $ast['aggregate'] = ['func' => strtoupper($m[1]), 'col' => self::bareCol($m[2])];
            $ast['columns']   = [$colStr];
        } elseif ($colStr === '*' || $colStr === '') {
            $ast['columns'] = ['*'];
        } else {
            foreach (explode(',', $colStr) as $c) {
                $ast['columns'][] = trim(self::bareCol(trim($c)));
            }
        }

        return $ast;
    }

    /* ------------------------------------------------------------------ */

    private static function parseInsert(string $sql): array
    {
        $ast = ['type' => 'INSERT', 'table' => '', 'columns' => [], 'values' => []];

        if (preg_match('/INSERT\s+(?:IGNORE\s+)?INTO\s+`?(\w+)`?\s*\(([^)]+)\)\s*VALUES\s*\(([^)]*)\)/is', $sql, $m)) {
            $ast['table']   = $m[1];
            $ast['columns'] = array_map(fn($c) => trim(trim($c), '`'), explode(',', $m[2]));
            // Values are placeholders; actual values come via execute()
        }

        return $ast;
    }

    /* ------------------------------------------------------------------ */

    private static function parseUpdate(string $sql): array
    {
        $ast = ['type' => 'UPDATE', 'table' => '', 'set' => [], 'where' => []];

        // Table name
        if (preg_match('/UPDATE\s+`?(\w+)`?\s+SET\s+/i', $sql, $m)) {
            $ast['table'] = $m[1];
        }

        // SET clause
        $setStr = '';
        if (preg_match('/SET\s+(.+?)(?:\bWHERE|$)/is', $sql, $m)) {
            $setStr = trim($m[1]);
        }
        foreach (explode(',', $setStr) as $pair) {
            if (preg_match('/`?(\w+)`?\s*=\s*(.+)/', trim($pair), $m)) {
                $ast['set'][] = ['col' => $m[1], 'expr' => trim($m[2])];
            }
        }

        // WHERE
        if (preg_match('/\bWHERE\s+(.+?)$/is', $sql, $m)) {
            $ast['where'] = self::parseWhere(trim($m[1]));
        }

        return $ast;
    }

    /* ------------------------------------------------------------------ */

    private static function parseDelete(string $sql): array
    {
        $ast = ['type' => 'DELETE', 'table' => '', 'where' => []];

        if (preg_match('/DELETE\s+FROM\s+`?(\w+)`?/i', $sql, $m)) {
            $ast['table'] = $m[1];
        }
        if (preg_match('/\bWHERE\s+(.+?)$/is', $sql, $m)) {
            $ast['where'] = self::parseWhere(trim($m[1]));
        }

        return $ast;
    }

    /* ------------------------------------------------------------------ */

    /**
     * Parse WHERE clause into condition array.
     * Returns array of ['col', 'op', 'value'] grouped by AND/OR.
     */
    public static function parseWhere(string $where): array
    {
        $conditions = [];
        // Split on AND (not inside parentheses)
        $parts = preg_split('/\bAND\b/i', $where);

        foreach ($parts as $part) {
            $part = trim($part, " \t\n\r\0\x0B()");
            if ($part === '') continue;

            // IS NULL / IS NOT NULL
            if (preg_match('/`?(\w+)`?\s+IS\s+(NOT\s+)?NULL/i', $part, $m)) {
                $conditions[] = [
                    'col' => $m[1],
                    'op'  => empty($m[2]) ? 'IS NULL' : 'IS NOT NULL',
                    'val' => null,
                ];
                continue;
            }

            // IN (?,?,?)
            if (preg_match('/`?(\w+)`?\s+(?:NOT\s+)?IN\s*\(/i', $part, $m)) {
                $not = stripos($part, 'NOT IN') !== false;
                $conditions[] = ['col' => $m[1], 'op' => $not ? 'NOT IN' : 'IN', 'val' => '__IN__'];
                continue;
            }

            // LIKE
            if (preg_match('/`?(\w+)`?\s+(?:NOT\s+)?LIKE\s+(.+)/i', $part, $m)) {
                $not = stripos($part, 'NOT LIKE') !== false;
                $conditions[] = ['col' => $m[1], 'op' => $not ? 'NOT LIKE' : 'LIKE', 'val' => trim($m[2])];
                continue;
            }

            // Standard operators: =, !=, <>, >=, <=, >, <
            if (preg_match('/`?(\w+(?:\.\w+)?)`?\s*(!=|<>|>=|<=|>|<|=)\s*(.+)/i', $part, $m)) {
                $op = $m[2] === '<>' ? '!=' : $m[2];
                $conditions[] = [
                    'col' => self::bareCol(trim($m[1])),
                    'op'  => $op,
                    'val' => trim($m[3]),
                ];
                continue;
            }

            // BETWEEN
            if (preg_match('/`?(\w+)`?\s+BETWEEN\s+(.+?)\s+AND\s+(.+)/i', $part, $m)) {
                $conditions[] = ['col' => $m[1], 'op' => 'BETWEEN', 'val' => [$m[2], $m[3]]];
                continue;
            }
        }

        return $conditions;
    }

    /* ------------------------------------------------------------------ */

    private static function bareCol(string $expr): string
    {
        // Strip table prefix (t.col → col) and backticks
        $expr = trim($expr, '` ');
        if (str_contains($expr, '.')) {
            $expr = substr($expr, strrpos($expr, '.') + 1);
        }
        return trim($expr, '` ');
    }
}
