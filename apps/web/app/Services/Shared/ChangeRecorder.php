<?php

namespace App\Services\Shared;

use App\Events\Shared\ResourcesChanged;
use Illuminate\Database\DatabaseManager;
use Throwable;

/**
 * Collects the tables written during one request (or command) and broadcasts them once as
 * ResourcesChanged when it ends. Clients reload whatever shows one of those tables.
 *
 * Writes inside a transaction count only after the commit, so a rolled-back write is never
 * announced. Bound as a scoped singleton and flushed from AppServiceProvider.
 */
class ChangeRecorder
{
    public const MAX_IDS = 50;

    /** @var array<string, array<int|string, true>|null> table => ids as keys, null = many/unknown */
    private array $pending = [];

    public function __construct(private readonly DatabaseManager $db) {}

    /**
     * $id null means the rows are unknown (bulk query writes), so clients reload the whole table.
     */
    public function record(string $table, int|string|null $id = null): void
    {
        $this->db->afterCommit(fn () => $this->add($table, $id));
    }

    public function flush(): void
    {
        if ($this->pending === []) {
            return;
        }

        $resources = array_map(fn (?array $ids) => $ids === null ? null : array_keys($ids), $this->pending);
        $this->pending = [];

        try {
            event(new ResourcesChanged($resources));
        } catch (Throwable $e) {
            report($e);
        }
    }

    private function add(string $table, int|string|null $id): void
    {
        if ($id === null || (array_key_exists($table, $this->pending) && $this->pending[$table] === null)) {
            $this->pending[$table] = null;

            return;
        }

        $this->pending[$table][$id] = true;

        if (count($this->pending[$table]) > self::MAX_IDS) {
            $this->pending[$table] = null;
        }
    }
}