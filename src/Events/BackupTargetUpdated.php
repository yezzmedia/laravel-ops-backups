<?php

declare(strict_types=1);

namespace YezzMedia\OpsBackups\Events;

final readonly class BackupTargetUpdated
{
    public function __construct(
        public string $targetKey,
        public string $scopeType,
        public string $scopeKey,
        public string $lifecycleStatus,
        public ?int $actorId,
        public string $source,
        public string $completedAt,
    ) {}
}
