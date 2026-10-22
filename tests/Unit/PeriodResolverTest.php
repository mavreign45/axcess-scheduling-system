<?php

namespace Tests\Unit;

use App\Services\PeriodResolver;
use InvalidArgumentException;
use Tests\TestCase;

class PeriodResolverTest extends TestCase
{
    public function test_date_week_month_and_custom_modes_resolve_correctly(): void
    {
        [$start, $end] = PeriodResolver::resolve(['mode' => 'date', 'date' => '2026-10-21']);
        $this->assertSame('2026-10-21 00:00:00', $start->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-21 23:59:59', $end->format('Y-m-d H:i:s'));

        [$start, $end] = PeriodResolver::resolve(['mode' => 'week', 'week' => '2026-10-21']);
        $this->assertSame('2026-10-19', $start->toDateString());
        $this->assertSame('2026-10-25', $end->toDateString());
        $this->assertSame('Monday', $start->format('l'));
        $this->assertSame('Sunday', $end->format('l'));

        [$start, $end] = PeriodResolver::resolve(['mode' => 'month', 'month' => '2026-02']);
        $this->assertSame('2026-02-01', $start->toDateString());
        $this->assertSame('2026-02-28', $end->toDateString());

        [$start, $end] = PeriodResolver::resolve(['mode' => 'custom', 'from' => '2026-10-19', 'to' => '2026-10-25']);
        $this->assertSame('2026-10-19 00:00:00', $start->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-25 23:59:59', $end->format('Y-m-d H:i:s'));
        $this->assertSame('Oct 19, 2026 – Oct 25, 2026', PeriodResolver::label($start, $end));
    }

    public function test_reversed_periods_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The end date must be on or after the start date.');

        PeriodResolver::resolve(['mode' => 'custom', 'from' => '2026-10-25', 'to' => '2026-10-19']);
    }

    public function test_periods_longer_than_the_configured_maximum_are_rejected(): void
    {
        config(['axcess.exports.max_range_days' => 7]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The selected period cannot exceed 7 days.');

        PeriodResolver::resolve(['mode' => 'custom', 'from' => '2026-10-01', 'to' => '2026-10-31']);
    }
}
