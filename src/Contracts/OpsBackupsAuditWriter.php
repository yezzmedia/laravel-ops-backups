<?php

declare(strict_types=1);

namespace YezzMedia\OpsBackups\Contracts;

interface OpsBackupsAuditWriter
{
    public function record(object $event): void;
}
