<?php

declare(strict_types=1);

namespace YezzMedia\OpsBackups\Support;

use YezzMedia\OpsBackups\Contracts\OpsBackupsAuditWriter;

final class NullOpsBackupsAuditWriter implements OpsBackupsAuditWriter
{
    public function record(object $event): void {}
}
