<?php

declare(strict_types=1);

namespace YezzMedia\OpsBackups\Data;

use YezzMedia\OpsBackups\Support\RestoreReadinessStatus;

final readonly class RestoreReadinessRecord
{
    /**
     * @param  array<int, string>  $issues
     */
    public function __construct(
        public string $targetKey,
        public RestoreReadinessStatus $status,
        public bool $hasRecentArtifact,
        public bool $checksumPresent,
        public ?bool $encrypted,
        public bool $restoreTested,
        public string $summary,
        public array $issues,
    ) {}
}
