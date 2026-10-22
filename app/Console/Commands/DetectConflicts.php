<?php

namespace App\Console\Commands;

use App\Services\ConflictDetector;
use Illuminate\Console\Command;

class DetectConflicts extends Command
{
    protected $signature = 'axcess:detect-conflicts
        {--days=60 : Number of days ahead to scan}
        {--department= : Limit the scan to a department ID}';

    protected $description = 'Scan Axcess schedules for coverage gaps, leave conflicts, double bookings and other issues';

    public function handle(ConflictDetector $detector): int
    {
        $days = max(1, (int) $this->option('days'));
        $departmentId = $this->option('department') ? (int) $this->option('department') : null;

        $count = $detector->detect(
            now()->startOfDay(),
            now()->addDays($days)->endOfDay(),
            $departmentId,
        );

        $this->info("Axcess conflict scan completed: {$count} new conflict(s) recorded.");

        return self::SUCCESS;
    }
}
