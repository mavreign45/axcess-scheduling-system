<?php

namespace App\Services;

use Carbon\Carbon;
use InvalidArgumentException;

class PeriodResolver
{
    /**
     * Resolve the selected period from validated export filters.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function resolve(array $data): array
    {
        $mode = $data['mode'] ?? 'week';

        try {
            [$start, $end] = match ($mode) {
                'date' => self::datePeriod($data['date'] ?? null),
                'week' => self::weekPeriod($data['week'] ?? null),
                'month' => self::monthPeriod($data['month'] ?? null),
                'custom' => self::customPeriod($data['from'] ?? null, $data['to'] ?? null),
                default => throw new InvalidArgumentException('Invalid reporting period mode.'),
            };
        } catch (InvalidArgumentException $exception) {
            throw $exception;
        } catch (\Throwable) {
            throw new InvalidArgumentException('The selected reporting period is invalid.');
        }

        if ($end->lt($start)) {
            throw new InvalidArgumentException('The end date must be on or after the start date.');
        }

        $maxDays = (int) config('axcess.exports.max_range_days', 366);

        if ($start->diffInDays($end) + 1 > $maxDays) {
            throw new InvalidArgumentException("The selected period cannot exceed {$maxDays} days. Please narrow the date range.");
        }

        return [$start->copy()->startOfDay(), $end->copy()->endOfDay()];
    }

    public static function label(Carbon $start, Carbon $end): string
    {
        if ($start->isSameDay($end)) {
            return $start->format('l, F j, Y');
        }

        if ($start->year === $end->year) {
            return $start->format('M j, Y').' – '.$end->format('M j, Y');
        }

        return $start->format('M j, Y').' – '.$end->format('M j, Y');
    }

    private static function datePeriod(?string $date): array
    {
        $start = Carbon::createFromFormat('Y-m-d', (string) $date)->startOfDay();

        return [$start, $start->copy()];
    }

    private static function weekPeriod(?string $week): array
    {
        $start = Carbon::parse((string) $week)->startOfWeek(Carbon::MONDAY);

        return [$start, $start->copy()->endOfWeek(Carbon::SUNDAY)];
    }

    private static function monthPeriod(?string $month): array
    {
        $start = Carbon::createFromFormat('Y-m', (string) $month)->startOfMonth();

        return [$start, $start->copy()->endOfMonth()];
    }

    private static function customPeriod(?string $from, ?string $to): array
    {
        return [
            Carbon::createFromFormat('Y-m-d', (string) $from)->startOfDay(),
            Carbon::createFromFormat('Y-m-d', (string) $to)->startOfDay(),
        ];
    }
}
