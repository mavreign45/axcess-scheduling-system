<?php

return [
    'brand' => [
        'name' => 'Axcess',
        'tagline' => 'Staff Scheduling & Workforce Management',
        'full_title' => 'Axcess — Staff Scheduling & Workforce Management',
        'support_email' => env('AXCESS_SUPPORT_EMAIL', 'support@axcess.app'),
    ],

    'exports' => [
        // A single export request cannot span more than this many days.
        'max_range_days' => (int) env('AXCESS_EXPORT_MAX_RANGE_DAYS', 366),
        // Above this estimate the UI recommends narrowing the filters.
        'large_export_threshold' => (int) env('AXCESS_EXPORT_LARGE_THRESHOLD', 5000),
        'timezone_label' => env('AXCESS_EXPORT_TIMEZONE_LABEL', 'UTC'),
    ],

    'scheduling' => [
        'week_starts_on' => 1, // Monday
        'max_shift_hours' => 24,
        'long_shift_hours' => 12,
        'minimum_break_after_hours' => 6,
    ],
];
