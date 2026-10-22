@extends('layouts.app')

@section('title', 'Reports & exports')
@section('eyebrow', 'Operational intelligence')
@section('subtitle', 'Coverage, hours, breaks, leave, conflicts, and genuine Excel exports.')

@section('actions')
    <form method="POST" action="{{ route('reports.conflicts.run') }}">
        @csrf
        <input type="hidden" name="mode" value="{{ request('mode', 'month') }}">
        <input type="hidden" name="week" value="{{ request('week', now()->toDateString()) }}">
        <input type="hidden" name="month" value="{{ request('month', now()->format('Y-m')) }}">
        <input type="hidden" name="date" value="{{ request('date', now()->toDateString()) }}">
        <input type="hidden" name="from" value="{{ request('from') }}">
        <input type="hidden" name="to" value="{{ request('to') }}">
        <input type="hidden" name="department_id" value="{{ $departmentId }}">
        <button type="submit" class="ax-btn-secondary ax-btn-sm">
            <x-icon name="refresh" class="h-4 w-4" />
            <span class="hidden sm:inline">Run conflict scan</span>
        </button>
    </form>
    <button type="button" class="ax-btn-primary ax-btn-sm" data-export-open="reports-export">
        <x-icon name="download" class="h-4 w-4" />
        <span class="hidden sm:inline">Export report</span>
    </button>
@endsection

@section('content')
    <div class="mb-5 flex flex-col gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm xl:flex-row xl:items-end xl:justify-between">
        <div class="flex items-start gap-3">
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-700">
                <x-icon name="chart" class="h-5 w-5" />
            </span>
            <div>
                <h2 class="text-sm font-black text-slate-900">{{ \App\Services\PeriodResolver::label($start, $end) }}</h2>
                <p class="mt-0.5 text-[11px] text-slate-500">Planned scheduling data. Hours exclude breaks unless stated and are not payroll or attendance.</p>
            </div>
        </div>

        <form method="GET" action="{{ route('reports.index') }}" class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end" data-period-scope>
            <div class="w-full sm:w-36">
                <label class="ax-label" for="report-mode">Period</label>
                <select id="report-mode" name="mode" class="ax-input" data-period-select>
                    <option value="date" @selected(request('mode') === 'date')>Single date</option>
                    <option value="week" @selected(request('mode') === 'week')>Week</option>
                    <option value="month" @selected(request('mode', 'month') === 'month')>Month</option>
                    <option value="custom" @selected(request('mode') === 'custom')>Custom range</option>
                </select>
            </div>
            <div class="w-full sm:w-40 {{ request('mode') === 'date' ? '' : 'hidden' }}" data-period-option="date">
                <label class="ax-label" for="report-date">Date</label>
                <input id="report-date" type="date" name="date" class="ax-input" value="{{ request('date', now()->toDateString()) }}">
            </div>
            <div class="w-full sm:w-40 {{ request('mode') === 'week' ? '' : 'hidden' }}" data-period-option="week">
                <label class="ax-label" for="report-week">Week beginning</label>
                <input id="report-week" type="date" name="week" class="ax-input" value="{{ request('week', now()->startOfWeek()->toDateString()) }}">
            </div>
            <div class="w-full sm:w-40 {{ in_array(request('mode', 'month'), ['month', null], true) ? '' : 'hidden' }}" data-period-option="month">
                <label class="ax-label" for="report-month">Month</label>
                <input id="report-month" type="month" name="month" class="ax-input" value="{{ request('month', now()->format('Y-m')) }}">
            </div>
            <div class="flex w-full gap-2 sm:w-auto {{ request('mode') === 'custom' ? '' : 'hidden' }}" data-period-option="custom">
                <div class="min-w-0 flex-1">
                    <label class="ax-label" for="report-from">From</label>
                    <input id="report-from" type="date" name="from" class="ax-input" value="{{ request('from', now()->startOfMonth()->toDateString()) }}">
                </div>
                <div class="min-w-0 flex-1">
                    <label class="ax-label" for="report-to">To</label>
                    <input id="report-to" type="date" name="to" class="ax-input" value="{{ request('to', now()->endOfMonth()->toDateString()) }}">
                </div>
            </div>
            @unless(auth()->user()->isManager())
                <div class="w-full sm:w-48">
                    <label class="ax-label" for="report-department">Department</label>
                    <select id="report-department" name="department_id" class="ax-input">
                        <option value="">All departments</option>
                        @foreach($departments as $department)
                            <option value="{{ $department->id }}" @selected((int) $departmentId === $department->id)>{{ $department->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endunless
            <button type="submit" class="ax-btn-dark ax-btn-sm h-[42px]">Apply</button>
        </form>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat-card label="Staff scheduled" :value="$metrics['staff_scheduled']" :meta="$metrics['total_staff'].' active staff in scope'" icon="users" />
        <x-stat-card label="Net working hours" :value="number_format($metrics['net_hours'], 1)" :meta="number_format($metrics['break_hours'], 1).' break hours'" icon="clock" tone="sky" />
        <x-stat-card label="Coverage gaps" :value="$coverageGapCount" :meta="$metrics['unassigned_shifts'].' unassigned shift(s)' icon="warning" :tone="$coverageGapCount > 0 ? 'rose' : 'brand'" />
        <x-stat-card label="Open conflicts" :value="$conflictCount" :meta="$breakIssueCount.' break issue(s)'" icon="shield" :tone="$conflictCount > 0 ? 'amber' : 'brand'" />
    </div>

    <div class="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="ax-card flex items-center justify-between gap-3 p-4">
            <div><p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Standby duties</p><p class="mt-1 text-lg font-black text-slate-900">{{ $metrics['standby_count'] }}</p></div>
            <span class="text-xs font-semibold text-amber-600">{{ number_format($metrics['standby_hours'], 1) }}h</span>
        </div>
        <div class="ax-card flex items-center justify-between gap-3 p-4">
            <div><p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Approved leave days</p><p class="mt-1 text-lg font-black text-slate-900">{{ number_format($metrics['approved_leave_days'], 1) }}</p></div>
            <span class="text-xs font-semibold text-sky-600">{{ $metrics['pending_leave_requests'] }} pending</span>
        </div>
        <div class="ax-card flex items-center justify-between gap-3 p-4">
            <div><p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Recorded absences</p><p class="mt-1 text-lg font-black text-slate-900">{{ $metrics['absences'] }}</p></div>
            <span class="text-xs font-semibold text-rose-500">Exceptions</span>
        </div>
        <div class="ax-card flex items-center justify-between gap-3 p-4">
            <div><p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Replacements</p><p class="mt-1 text-lg font-black text-slate-900">{{ $replacementCount }}</p></div>
            <span class="text-xs font-semibold text-violet-600">History</span>
        </div>
    </div>

    <div class="mt-6 grid gap-6 2xl:grid-cols-2">
        <section class="ax-card overflow-hidden">
            <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-5 py-4">
                <div>
                    <h2 class="text-sm font-black text-slate-900">Staff hours &amp; summary</h2>
                    <p class="mt-0.5 text-xs text-slate-500">Scheduled, break, standby, leave, and replacement totals</p>
                </div>
                <span class="ax-chip bg-slate-100 text-slate-600">{{ $staffSummary->count() }} people</span>
            </div>
            @if($staffSummary->isEmpty())
                <p class="px-5 py-10 text-center text-xs text-slate-400">No staff data in this period.</p>
            @else
                <div class="ax-scrollbar max-h-[420px] overflow-auto">
                    <table class="ax-table">
                        <thead class="sticky top-0 z-10">
                            <tr>
                                <th>Employee</th>
                                <th>Department</th>
                                <th>Shifts</th>
                                <th>Net hrs</th>
                                <th>Leave</th>
                                <th>Standby</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($staffSummary as $row)
                                <tr>
                                    <td>
                                        <p class="text-xs font-bold text-slate-800">{{ $row['user']->full_name }}</p>
                                        <p class="text-[10px] text-slate-400">{{ $row['user']->employee_id }}</p>
                                    </td>
                                    <td><span class="text-[11px] text-slate-500">{{ $row['user']->department?->name }}</span></td>
                                    <td><span class="text-xs font-semibold text-slate-700">{{ $row['shift_count'] }}</span></td>
                                    <td><span class="text-xs font-semibold text-slate-700">{{ number_format($row['net_hours'], 1) }}</span></td>
                                    <td><span class="text-xs text-slate-500">{{ number_format($row['leave_days'], 1) }}d</span></td>
                                    <td><span class="text-xs text-slate-500">{{ $row['standby_count'] }}</span></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        <section class="ax-card overflow-hidden">
            <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-5 py-4">
                <div>
                    <h2 class="text-sm font-black text-slate-900">Coverage gaps</h2>
                    <p class="mt-0.5 text-xs text-slate-500">Department-days below the configured minimum</p>
                </div>
                <span class="ax-chip {{ $coverageGapCount > 0 ? 'bg-rose-50 text-rose-700 ring-1 ring-inset ring-rose-200' : 'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-200' }}">{{ $coverageGapCount }}</span>
            </div>
            @if($coverageGaps->isEmpty())
                <p class="px-5 py-10 text-center text-xs text-slate-400">No coverage gaps in this period.</p>
            @else
                <div class="ax-scrollbar max-h-[420px] divide-y divide-slate-100 overflow-auto">
                    @foreach($coverageGaps as $gap)
                        <div class="flex items-center justify-between gap-3 px-5 py-3">
                            <div>
                                <p class="text-xs font-bold text-slate-800">{{ $gap['department'] }}</p>
                                <p class="text-[11px] text-slate-500">{{ $gap['date']->format('D, M j, Y') }} · {{ $gap['scheduled'] }} scheduled vs {{ $gap['required'] }} required</p>
                            </div>
                            <span class="ax-chip bg-rose-50 text-rose-700 ring-1 ring-inset ring-rose-200">Gap {{ $gap['gap'] }}</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>

        <section class="ax-card overflow-hidden">
            <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-5 py-4">
                <div>
                    <h2 class="text-sm font-black text-slate-900">Break coverage</h2>
                    <p class="mt-0.5 text-xs text-slate-500">Shifts with missing or insufficient break time</p>
                </div>
                <span class="ax-chip {{ $breakIssueCount > 0 ? 'bg-amber-50 text-amber-700 ring-1 ring-inset ring-amber-200' : 'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-200' }}">{{ $breakIssueCount }}</span>
            </div>
            @if($breakIssues->isEmpty())
                <p class="px-5 py-10 text-center text-xs text-slate-400">Every qualifying shift has adequate break coverage.</p>
            @else
                <div class="ax-scrollbar max-h-[420px] divide-y divide-slate-100 overflow-auto">
                    @foreach($breakIssues as $issue)
                        <div class="flex items-start justify-between gap-3 px-5 py-3">
                            <div class="min-w-0">
                                <p class="text-xs font-bold text-slate-800">{{ $issue['employee_name'] }} · {{ $issue['shift_type'] }}</p>
                                <p class="text-[11px] text-slate-500">{{ $issue['date']->format('D, M j') }} · {{ number_format($issue['shift_hours'], 1) }}h shift · {{ $issue['break_count'] }} break(s)</p>
                                <p class="mt-0.5 text-[10px] text-slate-400">{{ $issue['detail'] }}</p>
                            </div>
                            <x-status-badge :status="$issue['status']" :label="$issue['status']" />
                        </div>
                    @endforeach
                </div>
            @endif
        </section>

        <section class="ax-card overflow-hidden">
            <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-5 py-4">
                <div>
                    <h2 class="text-sm font-black text-slate-900">Open conflicts</h2>
                    <p class="mt-0.5 text-xs text-slate-500">Detected scheduling issues requiring review</p>
                </div>
                <span class="ax-chip {{ $conflictCount > 0 ? 'bg-rose-50 text-rose-700 ring-1 ring-inset ring-rose-200' : 'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-200' }}">{{ $conflictCount }}</span>
            </div>
            @if($conflicts->isEmpty())
                <p class="px-5 py-10 text-center text-xs text-slate-400">No open conflicts. Run a conflict scan after schedule changes.</p>
            @else
                <div class="ax-scrollbar max-h-[420px] divide-y divide-slate-100 overflow-auto">
                    @foreach($conflicts as $conflict)
                        <div class="flex items-start gap-3 px-5 py-3.5">
                            <span class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-lg {{ $conflict->severity === 'error' ? 'bg-rose-50 text-rose-600' : ($conflict->severity === 'warning' ? 'bg-amber-50 text-amber-600' : 'bg-sky-50 text-sky-600') }}">
                                <x-icon name="warning" class="h-3.5 w-3.5" />
                            </span>
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-1.5">
                                    <p class="text-xs font-bold text-slate-800">{{ $conflict->title }}</p>
                                    <x-status-badge :status="$conflict->severity" :label="$conflict->severity_label" />
                                </div>
                                <p class="mt-0.5 text-[11px] leading-5 text-slate-500">{{ $conflict->description }}</p>
                                <p class="mt-0.5 text-[10px] text-slate-400">{{ $conflict->conflict_date->format('D, M j, Y') }}{{ $conflict->department ? ' · '.$conflict->department->name : '' }}</p>
                            </div>
                            @can('resolve', $conflict)
                                <form method="POST" action="{{ route('reports.conflicts.resolve', $conflict) }}">
                                    @csrf
                                    <button type="submit" class="rounded-lg border border-slate-200 px-2.5 py-1.5 text-[10px] font-bold text-slate-600 transition hover:border-emerald-300 hover:bg-emerald-50 hover:text-emerald-700">Resolve</button>
                                </form>
                            @endcan
                        </div>
                    @endforeach
                </div>
            @endif
        </section>
    </div>

    <div class="mt-6 grid gap-6 2xl:grid-cols-2">
        <section class="ax-card overflow-hidden">
            <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-5 py-4">
                <div>
                    <h2 class="text-sm font-black text-slate-900">Unassigned shifts</h2>
                    <p class="mt-0.5 text-xs text-slate-500">Open work still needing an employee</p>
                </div>
                <span class="ax-chip bg-slate-100 text-slate-600">{{ $unassignedCount }}</span>
            </div>
            @if($unassigned->isEmpty())
                <p class="px-5 py-10 text-center text-xs text-slate-400">Every shift has an assigned employee or replacement.</p>
            @else
                <div class="divide-y divide-slate-100">
                    @foreach($unassigned as $assignment)
                        <div class="flex items-center justify-between gap-3 px-5 py-3">
                            <div>
                                <p class="text-xs font-bold text-slate-800">{{ $assignment->schedule_date->format('D, M j') }} · {{ $assignment->shiftType?->name }}</p>
                                <p class="text-[11px] text-slate-500">{{ $assignment->start_at->format('H:i') }}–{{ $assignment->end_at->format('H:i') }} · {{ $assignment->department?->name }}</p>
                            </div>
                            <a href="{{ route('schedule.edit', $assignment) }}" class="rounded-lg p-2 text-slate-400 transition hover:bg-slate-100 hover:text-brand-700" title="Assign employee" aria-label="Assign employee">
                                <x-icon name="arrow-right" class="h-4 w-4" />
                            </a>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>

        <section class="ax-card overflow-hidden">
            <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-5 py-4">
                <div>
                    <h2 class="text-sm font-black text-slate-900">Recent replacements</h2>
                    <p class="mt-0.5 text-xs text-slate-500">Original assignee, cover, and recorded reason</p>
                </div>
                <span class="ax-chip bg-slate-100 text-slate-600">{{ $replacementCount }}</span>
            </div>
            @if($replacements->isEmpty())
                <p class="px-5 py-10 text-center text-xs text-slate-400">No replacement history in this period.</p>
            @else
                <div class="divide-y divide-slate-100">
                    @foreach($replacements as $replacement)
                        <div class="px-5 py-3">
                            <div class="flex items-center justify-between gap-3">
                                <p class="text-xs font-bold text-slate-800">{{ $replacement->scheduleAssignment?->schedule_date->format('D, M j') }} · {{ $replacement->scheduleAssignment?->shiftType?->name }}</p>
                                <span class="text-[10px] text-slate-400">{{ $replacement->replaced_at?->format('M j, H:i') }}</span>
                            </div>
                            <p class="mt-0.5 text-[11px] text-slate-500">
                                {{ $replacement->originalUser?->full_name ?? 'Unassigned' }}
                                <span class="mx-1 text-slate-300">→</span>
                                <span class="font-semibold text-sky-700">{{ $replacement->replacementUser?->full_name }}</span>
                            </p>
                            @if($replacement->reason)
                                <p class="mt-0.5 text-[10px] italic text-slate-400">{{ $replacement->reason }}</p>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </section>
    </div>

    <x-export-dialog id="reports-export" title="Export management report" description="Generate a formatted multi-sheet Excel workbook. Choose a focused report type or include every management worksheet." :url="route('exports.reports')" action="Export report">
        <x-export-period-fields id="reports" mode="month" />

        <div class="grid gap-4 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <label class="ax-label" for="export-report-type">Report type</label>
                <select id="export-report-type" name="report_type" class="ax-input">
                    @foreach(\App\Support\Axcess::REPORT_TYPES as $value => $label)
                        <option value="{{ $value }}" @selected($value === 'full')>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            @unless(auth()->user()->isManager())
                <div>
                    <label class="ax-label" for="export-report-department">Department</label>
                    <select id="export-report-department" name="department_id" class="ax-input">
                        <option value="">All departments</option>
                        @foreach($departments as $department)
                            <option value="{{ $department->id }}">{{ $department->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endunless
            <div>
                <label class="ax-label" for="export-report-employee">Employee</label>
                <select id="export-report-employee" name="user_id" class="ax-input">
                    <option value="">All employees</option>
                    @foreach($employees as $employee)
                        <option value="{{ $employee->id }}">{{ $employee->full_name }} · {{ $employee->employee_id }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="rounded-xl border border-slate-200 bg-slate-50 p-3.5">
            <label class="flex items-start gap-2.5 text-xs font-semibold text-slate-700">
                <input type="checkbox" name="include_sensitive" value="1" class="mt-0.5 h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                Include confidential manager notes, leave reasons, and replacement reasons
            </label>
            <p class="mt-2 pl-6 text-[10px] leading-4 text-slate-400">Sensitive fields are omitted unless explicitly selected. Managers only ever export their own department.</p>
        </div>

        <div class="rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-[11px] leading-5 text-sky-800">
            Excel workbooks include title rows, frozen headers, filters, date and number formats, sensible column widths, and totals where useful. CSV contains only the primary sheet.
        </div>
    </x-export-dialog>
@endsection
