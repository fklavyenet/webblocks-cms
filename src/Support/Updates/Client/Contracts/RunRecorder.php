<?php

// Generated from the shared Publisher Client runtime. Do not edit directly.

declare(strict_types=1);

namespace WebBlocks\Cms\Support\Updates\Client\Contracts;

/**
 * Records update-run history. The default writes a bounded JSON file so the
 * engine works with no migration; a product rebinds to a DB-backed recorder
 * (a consumer may use an Eloquent-backed update-run model).
 */
interface RunRecorder
{
    /**
     * Begin a run. Returns an opaque reference passed back to finish().
     */
    public function start(string $fromVersion, string $toVersion, ?int $userId): mixed;

    public function finish(
        mixed $ref,
        string $status,
        string $summary,
        string $output,
        int $warningCount,
        int $durationMs,
    ): void;

    /**
     * Trim stored runs to the configured retention.
     */
    public function prune(): void;

    /**
     * All retained runs, newest first.
     *
     * @return list<array<string,mixed>>
     */
    public function all(): array;
}
