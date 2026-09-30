<?php

namespace App\Discovery;

/** Pre-aggregated public progress; adapters must not query participations. */
final readonly class UnlockProgress
{
    public function __construct(
        public int $target,
        public int $progress,
    ) {}

    public function remaining(): int
    {
        return max(0, $this->target - $this->progress);
    }
}
