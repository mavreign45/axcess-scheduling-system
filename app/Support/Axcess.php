<?php

namespace App\Support;

final class Axcess
{
    public const NAME = 'Axcess';

    public const TAGLINE = 'Staff Scheduling & Workforce Management';

    public const FULL_TITLE = 'Axcess — Staff Scheduling & Workforce Management';

    public const ROLES = [
        'admin' => 'Administrator',
        'manager' => 'Manager',
        'employee' => 'Employee',
    ];

    public const ASSIGNMENT_STATUSES = [
        'draft' => 'Draft',
        'published' => 'Published',
        'confirmed' => 'Confirmed',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
    ];

    public const STANDBY_STATUSES = [
        'not_applicable' => 'Not applicable',
        'available' => 'Available',
        'called_in' => 'Called in',
        'released' => 'Released',
        'completed' => 'Completed',
    ];

    public const LEAVE_TYPES = [
        'annual' => 'Annual leave',
        'sick' => 'Sick leave',
        'personal' => 'Personal leave',
        'unpaid' => 'Unpaid leave',
        'maternity' => 'Maternity leave',
        'paternity' => 'Paternity leave',
        'bereavement' => 'Bereavement leave',
        'other' => 'Other',
    ];

    public const LEAVE_STATUSES = [
        'pending' => 'Pending',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
        'cancelled' => 'Cancelled',
    ];

    public const EMPLOYMENT_STATUSES = [
        'active' => 'Active',
        'on_leave' => 'On leave',
        'inactive' => 'Inactive',
    ];

    public const EMPLOYMENT_TYPES = [
        'full_time' => 'Full time',
        'part_time' => 'Part time',
        'contract' => 'Contract',
        'temporary' => 'Temporary',
    ];

    public const ABSENCE_TYPES = [
        'sick' => 'Sick absence',
        'no_show' => 'No show',
        'unauthorized' => 'Unauthorized absence',
        'other' => 'Other',
    ];

    public const OFF_DAY_TYPES = [
        'regular' => 'Regular off day',
        'requested' => 'Requested off day',
        'public_holiday' => 'Public holiday',
    ];

    public const CONFLICT_TYPES = [
        'leave_conflict' => 'Approved leave conflict',
        'double_booking' => 'Double booking',
        'unassigned_shift' => 'Unassigned shift',
        'coverage_gap' => 'Coverage gap',
        'missing_break' => 'Break coverage',
        'weekly_overtime' => 'Weekly overtime',
        'long_shift' => 'Long shift',
        'standby_ineligible' => 'Standby eligibility',
    ];

    public const CONFLICT_SEVERITIES = [
        'info' => 'Information',
        'warning' => 'Warning',
        'error' => 'Error',
    ];

    public const CONFLICT_STATUSES = [
        'open' => 'Open',
        'resolved' => 'Resolved',
        'ignored' => 'Ignored',
    ];

    public const SCHEDULE_WORKSHEETS = [
        'schedule' => 'Schedule',
        'staff_summary' => 'Staff Summary',
        'breaks' => 'Breaks',
        'leave_absences' => 'Leave & Absences',
        'standby_replacements' => 'Standby & Replacements',
        'conflicts' => 'Conflicts',
    ];

    public const REPORT_TYPES = [
        'full' => 'Full management report',
        'staff_summary' => 'Staff hours & summary',
        'coverage' => 'Coverage & gaps',
        'breaks' => 'Break coverage',
        'leave' => 'Leave & absence totals',
        'conflicts' => 'Scheduling conflicts',
        'replacements' => 'Standby & replacements',
    ];

    public static function label(string $group, ?string $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        $labels = match ($group) {
            'role' => self::ROLES,
            'assignment_status' => self::ASSIGNMENT_STATUSES,
            'standby_status' => self::STANDBY_STATUSES,
            'leave_type' => self::LEAVE_TYPES,
            'leave_status' => self::LEAVE_STATUSES,
            'employment_status' => self::EMPLOYMENT_STATUSES,
            'employment_type' => self::EMPLOYMENT_TYPES,
            'absence_type' => self::ABSENCE_TYPES,
            'off_day_type' => self::OFF_DAY_TYPES,
            'conflict_type' => self::CONFLICT_TYPES,
            'conflict_severity' => self::CONFLICT_SEVERITIES,
            'conflict_status' => self::CONFLICT_STATUSES,
            default => [],
        };

        return $labels[$value] ?? ucfirst(str_replace('_', ' ', $value));
    }
}
