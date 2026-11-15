<?php
declare(strict_types=1);

namespace IMS\Firebase;

use Google\Cloud\Firestore\CollectionReference;
use Google\Cloud\Firestore\DocumentReference;
use Google\Cloud\Firestore\Query;

/**
 * Executes a parsed SQL AST against Firestore.
 * Returns results as arrays of associative rows — same shape as PDO::FETCH_ASSOC.
 */
class QueryExecutor
{
    private \Kreait\Firebase\Contract\Firestore $firestore;

    public function __construct(\Kreait\Firebase\Contract\Firestore $firestore)
    {
        $this->firestore = $firestore;
    }

    /* ============================================================
       PUBLIC ENTRY POINTS
       ============================================================ */

    public function execute(array $ast, array $params = []): array
    {
        return match ($ast['type']) {
            'SELECT' => $this->execSelect($ast, $params),
            'INSERT' => $this->execInsert($ast, $params),
            'UPDATE' => $this->execUpdate($ast, $params),
            'DELETE' => $this->execDelete($ast, $params),
            default  => throw new \RuntimeException("Unsupported SQL type: {$ast['type']}"),
        };
    }

    /* ============================================================
       SELECT
       ============================================================ */

    private function execSelect(array $ast, array $params): array
    {
        $db   = $this->firestore->database();
        $col  = $db->collection($ast['table']);
        $query = $col;

        // Apply WHERE conditions
        $pIdx = 0;
        foreach ($ast['where'] as $cond) {
            [$query, $pIdx] = $this->applyWhere($query, $cond, $params, $pIdx);
        }

        // ORDER BY
        foreach ($ast['orderBy'] as $ord) {
            $query = $query->orderBy($ord['col'], $ord['dir']);
        }

        // LIMIT / OFFSET
        if ($ast['limit'] !== null) {
            $query = $query->limit((int)$ast['limit']);
        }
        if ($ast['offset'] !== null) {
            $query = $query->offset((int)$ast['offset']);
        }

        $documents = $query->documents();
        $rows = [];

        foreach ($documents as $doc) {
            if (!$doc->exists()) continue;
            $row = array_merge(['id' => $doc->id()], $doc->data());
            $rows[] = $this->filterColumns($row, $ast['columns']);
        }

        // Handle JOINs (fetch related docs and merge)
        if (!empty($ast['joins'])) {
            $rows = $this->applyJoins($rows, $ast['joins'], $db);
        }

        // Handle aggregates
        if ($ast['aggregate'] !== null) {
            return [$this->aggregate($ast['aggregate'], $rows)];
        }

        return $rows;
    }

    /* ============================================================
       INSERT
       ============================================================ */

    private function execInsert(array $ast, array $params): array
    {
        $db   = $this->firestore->database();
        $col  = $db->collection($ast['table']);

        $data = [];
        foreach ($ast['columns'] as $i => $col_name) {
            $data[$col_name] = $this->castParam($params[$i] ?? null);
        }
        $data['created_at'] = new \Google\Cloud\Core\Timestamp(new \DateTime());

        $ref = $col->add($data);

        // Store the last insert ID
        $this->lastInsertId = $ref->id();

        return [];
    }

    /* ============================================================
       UPDATE
       ============================================================ */

    private function execUpdate(array $ast, array $params): array
    {
        $db  = $this->firestore->database();
        $col = $db->collection($ast['table']);

        // Resolve SET placeholders
        $setData = [];
        $pIdx = 0;
        foreach ($ast['set'] as $pair) {
            $val = $pair['expr'];
            if ($val === '?' || $val === ':' . $pair['col']) {
                $val = $this->castParam($params[$pIdx++] ?? null);
            } elseif (str_contains($val, '?')) {
                $val = $this->castParam($params[$pIdx++] ?? null);
            }
            $setData[$pair['col']] = $val;
        }

        // Find matching documents
        $query = $col;
        foreach ($ast['where'] as $cond) {
            [$query, $pIdx] = $this->applyWhere($query, $cond, $params, $pIdx);
        }

        $docs = $query->documents();
        $this->affectedRows = 0;

        foreach ($docs as $doc) {
            if (!$doc->exists()) continue;
            $doc->reference()->update(
                array_map(fn($k, $v) => ['path' => $k, 'value' => $v],
                    array_keys($setData), array_values($setData))
            );
            $this->affectedRows++;
        }

        return [];
    }

    /* ============================================================
       DELETE
       ============================================================ */

    private function execDelete(array $ast, array $params): array
    {
        $db  = $this->firestore->database();
        $col = $db->collection($ast['table']);

        $query = $col;
        $pIdx  = 0;
        foreach ($ast['where'] as $cond) {
            [$query, $pIdx] = $this->applyWhere($query, $cond, $params, $pIdx);
        }

        $docs = $query->documents();
        $this->affectedRows = 0;

        foreach ($docs as $doc) {
            if (!$doc->exists()) continue;
            $doc->reference()->delete();
            $this->affectedRows++;
        }

        return [];
    }

    /* ============================================================
       HELPERS
       ============================================================ */

    private function applyWhere(mixed $query, array $cond, array $params, int $pIdx): array
    {
        $val = $cond['val'];

        // Resolve placeholders (? or :name)
        if ($val === '?' || (is_string($val) && str_starts_with($val, ':'))) {
            $val = $params[$pIdx++] ?? null;
        } elseif (is_string($val) && str_contains($val, '?')) {
            $val = $params[$pIdx++] ?? null;
        }

        $col = $cond['col'];
        $op  = $cond['op'];

        switch ($op) {
            case '=':
                $query = $query->where($col, '=', $this->castParam($val));
                break;
            case '!=':
                $query = $query->where($col, '!=', $this->castParam($val));
                break;
            case '>':
                $query = $query->where($col, '>', $this->castParam($val));
                break;
            case '>=':
                $query = $query->where($col, '>=', $this->castParam($val));
                break;
            case '<':
                $query = $query->where($col, '<', $this->castParam($val));
                break;
            case '<=':
                $query = $query->where($col, '<=', $this->castParam($val));
                break;
            case 'IN':
                $query = $query->where($col, 'in', (array)$val);
                break;
            case 'NOT IN':
                $query = $query->where($col, 'not-in', (array)$val);
                break;
            case 'IS NULL':
                $query = $query->where($col, '=', null);
                break;
            case 'IS NOT NULL':
                $query = $query->where($col, '!=', null);
                break;
            // LIKE and BETWEEN are done post-fetch (Firestore limitation)
        }

        return [$query, $pIdx];
    }

    private function filterColumns(array $row, array $columns): array
    {
        if (empty($columns) || $columns === ['*']) {
            return $row;
        }
        $out = [];
        foreach ($columns as $c) {
            if ($c === '*') return $row;
            if (array_key_exists($c, $row)) {
                $out[$c] = $row[$c];
            }
        }
        return $out ?: $row;
    }

    private function aggregate(array $agg, array $rows): array
    {
        $func = $agg['func'];
        $col  = $agg['col'];

        return match ($func) {
            'COUNT' => ['COUNT(*)' => count($rows), 'count' => count($rows)],
            'SUM'   => ['SUM(' . $col . ')' => array_sum(array_column($rows, $col))],
            'AVG'   => ['AVG(' . $col . ')' => count($rows) ? array_sum(array_column($rows, $col)) / count($rows) : 0],
            'MAX'   => ['MAX(' . $col . ')' => max(array_column($rows, $col))],
            'MIN'   => ['MIN(' . $col . ')' => min(array_column($rows, $col))],
            default => ['result' => 0],
        };
    }

    private function applyJoins(array $rows, array $joins, mixed $db): array
    {
        // Basic join: fetch related collection and merge by common key
        foreach ($joins as $join) {
            $relCol  = $db->collection($join['table']);
            $relDocs = $relCol->documents();
            $relMap  = [];

            foreach ($relDocs as $doc) {
                if (!$doc->exists()) continue;
                $relMap[$doc->id()] = array_merge(['id' => $doc->id()], $doc->data());
            }

            // Parse ON condition to find matching keys
            preg_match('/(\w+)\.(\w+)\s*=\s*(\w+)\.(\w+)/i', $join['on'], $m);
            $leftKey  = $m[2]  ?? 'id';
            $rightKey = $m[4]  ?? 'id';

            foreach ($rows as &$row) {
                $fkVal = $row[$leftKey] ?? $row[$rightKey] ?? null;
                $related = null;

                if ($fkVal !== null) {
                    foreach ($relMap as $relRow) {
                        if (($relRow[$rightKey] ?? $relRow['id'] ?? null) == $fkVal) {
                            $related = $relRow;
                            break;
                        }
                    }
                }

                if ($related !== null) {
                    $row = array_merge($related, $row); // row takes priority
                } elseif (stripos($join['type'], 'LEFT') !== false) {
                    // LEFT JOIN: keep row even without match
                }
            }
            unset($row);
        }

        return $rows;
    }

    private function castParam(mixed $val): mixed
    {
        if ($val === null) return null;
        if ($val === 'NULL') return null;
        if (is_numeric($val)) {
            return str_contains((string)$val, '.') ? (float)$val : (int)$val;
        }
        return $val;
    }

    public string $lastInsertId = '';
    public int    $affectedRows = 0;
}
