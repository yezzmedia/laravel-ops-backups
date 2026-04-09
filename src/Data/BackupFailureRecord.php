<?php

declare(strict_types=1);

namespace YezzMedia\OpsBackups\Data;

use Carbon\CarbonImmutable;

final readonly class BackupFailureRecord
{
    /**
     * @param  array<string, mixed>  $details
     */
    public function __construct(
        public string $targetKey,
        public ?string $runKey,
        public ?CarbonImmutable $occurredAt,
        public string $summary,
        public array $details,
    ) {}
}
