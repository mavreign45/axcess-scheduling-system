@extends('layouts.app')

@section('title', 'Monthly schedule')
@section('eyebrow', 'Workforce planning')
@section('subtitle', 'A month-at-a-glance view of shifts, cover, and unassigned work.')

@section('actions')
    @if($canManage)
        <a href="{{ route('schedule.create', ['date' => now()->toDateString()]) }}" class="ax-btn-primary ax-btn-sm">
            <x-icon name="plus" class="h-4 w-4" />
            <span class="hidden sm:inline">Add shift</span>
        </a>
    @endif
    @can('export-data')
        <button type="button" class="ax-btn-secondary ax-btn-sm" data-export-open="month-schedule-export">
            <x-icon name="download" class="h-4 w-4" />
            <span class="hidden sm:inline">Export</span>
        </button>
    @endcan
@endsection

@section('content')
    <div class="mb-5 flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('schedule.month', array_merge(request()->except(['month', 'week', 'page']), ['month' => $monthStart->copy()->subMonth()->format('Y-m')])) }}" class="ax-btn-secondary ax-btn-sm" aria-label="Previous month">
                <x-icon name="chevron-left" class="h-4 w-4" />
            </a>
            <div class="rounded-xl border border-slate-200 bg-white px-4 py-2 shadow-sm">
                <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Month</p>
                <p class="text-sm font-black text-slate-800">{{ $monthStart->format('F Y') }}</p>
            </div>
            <a href="{{ route('schedule.month', array_merge(request()->except(['month', 'week', 'page']), ['month' => $monthStart->copy()->addMonth()->format('Y-m')])) }}" class="ax-btn-secondary ax-btn-sm" aria-label="Next month">
                <x-icon name="chevron-right" class="h-4 w-4" />
            </a>
            <a href="{{ route('schedule.month') }}" class="text-xs font-semibold text-slate-500 hover:text-brand-700">This month</a>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <div class="inline-flex rounded-xl border border-slate-200 bg-white p-1 shadow-sm">
                <a href="{{ route('schedule.index', ['week' => $monthStart->toDateString()]) }}" class="rounded-lg px-3 py-1.5 text-xs font-bold text-slate-500 transition hover:text-brand-700">Week</a>
                <span class="rounded-lg bg-slate-900 px-3 py-1.5 text-xs font-bold text-white">Month</span>
            </div>
            <form method="GET" action="{{ route('schedule.month') }}" class="flex items-center gap-2">
                <input type="month" name="month" value="{{ $monthStart->format('Y-m') }}" class="rounded-lg border border-slate-300 bg-white px-2.5 py-2 text-xs font-semibold text-slate-700" aria-label="Jump to month">
                <button class="ax-btn-secondary ax-btn-sm" type="submit">Go</button>
            </form>
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        <x-stat-card label="Assignments" :value="$stats['assignments']" meta="Active this month" icon="calendar" />
        <x-stat-card label="Net hours" :value="number_format($stats['net_hours'], 1)" meta="After scheduled breaks" icon="clock" tone="sky" />
        <x-stat-card label="Staff scheduled" :value="$stats['staff']" meta="Distinct employees" icon="users" tone="violet" />
        <x-stat-card label="Standby" :value="$stats['standby']" meta="On-call duties" icon="phone" tone="amber" />
        <x-stat-card label="Unassigned" :value="$stats['unassigned']" meta="Shifts needing cover" icon="warning" :tone="$stats['unassigned'] > 0 ? 'rose' : 'brand'" />
    </div>

    <form method="GET" action="{{ route('schedule.month') }}" class="ax-card mt-5 p-4">
        <input type="hidden" name="month" value="{{ $monthStart->format('Y-m') }}">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-5">
            @unless(auth()->user()->isEmployee())
                <div>
                    <label class="ax-label" for="month-filter-department">Department</label>
                    <select id="month-filter-department" name="department_id" class="ax-input" data-auto-submit @disabled($departments->count() <= 1)>
                        <option value="">All available departments</option>
                        @foreach($departments as $department)
                            <option value="{{ $department->id }}" @selected((int) request('department_id') === $department->id)>{{ $department->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="ax-label" for="month-filter-employee">Employee</label>
                    <select id="month-filter-employee" name="user_id" class="ax-input" data-auto-submit>
                        <option value="">All employees</option>
                        @foreach($employees as $employee)
                            <option value="{{ $employee->id }}" @selected((int) request('user_id') === $employee->id)>{{ $employee->full_name }} · {{ $employee->employee_id }}</option>
                        @endforeach
                    </select>
                </div>
            @endunless
            <div>
                <label class="ax-label" for="month-filter-shift">Shift type</label>
                <select id="month-filter-shift" name="shift_type_id" class="ax-input" data-auto-submit>
                    <option value="">All shift types</option>
                    @foreach($shiftTypes as $shiftType)
                        <option value="{{ $shiftType->id }}" @selected((int) request('shift_type_id') === $shiftType->id)>{{ $shiftType->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="ax-label" for="month-filter-status">Status</label>
                <select id="month-filter-status" name="status" class="ax-input" data-auto-submit>
                    <option value="">All statuses</option>
                    @foreach(\App\Support\Axcess::ASSIGNMENT_STATUSES as $value => $label)
                        <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-end gap-2">
                <button type="submit" class="ax-btn-dark ax-btn-sm w-full">
                    <x-icon name="filter" class="h-4 w-4" />
                    Apply
                </button>
                @if(request()->hasAny(['department_id', 'user_id', 'shift_type_id', 'status']))
                    <a href="{{ route('schedule.month', ['month' => $monthStart->format('Y-m')]) }}" class="ax-btn-secondary ax-btn-sm">Clear</a>
                @endif
            </div>
        </div>
    </form>

    <div class="ax-card mt-5 overflow-hidden">
        <div class="ax-scrollbar overflow-x-auto">
            <div class="min-w-[960px]">
                <div class="grid grid-cols-7 border-b border-slate-200 bg-slate-50">
                    @foreach(['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'] as $dayName)
                        <div class="border-r border-slate-200 px-3 py-2.5 text-[10px] font-bold uppercase tracking-wider text-slate-500 last:border-r-0">{{ $dayName }}</div>
                    @endforeach
                </div>

                @foreach($weeks as $week)
                    <div class="grid grid-cols-7 border-b border-slate-200 last:border-b-0">
                        @foreach($week as $day)
                            <div class="group min-h-[138px] border-r border-slate-200 p-2.5 last:border-r-0 {{ $day['in_month'] ? 'bg-white' : 'bg-slate-50/60' }} {{ $day['date']->isToday() ? 'ring-2 ring-inset ring-brand-500' : '' }}">
                                <div class="mb-2 flex items-center justify-between">
                                    <span class="flex h-6 min-w-6 items-center justify-center rounded-lg px-1.5 text-[11px] font-black {{ $day['date']->isToday() ? 'bg-brand-700 text-white' : ($day['in_month'] ? 'text-slate-700' : 'text-slate-400') }}">
                                        {{ $day['date']->format('j') }}
                                    </span>
                                    <span class="text-[9px] font-semibold text-slate-400">{{ $day['staff_count'] }} staff</span>
                                </div>

                                <div class="space-y-1">
                                    @foreach($day['assignments']->take(3) as $assignment)
                                        <a href="{{ $canManage ? route('schedule.edit', $assignment) : '#' }}" class="block rounded-lg border-l-2 bg-slate-50 px-2 py-1.5 transition hover:bg-slate-100" style="border-color: {{ $assignment->shiftType?->color ?? '#0f766e' }}" title="{{ $assignment->employee_name }} · {{ $assignment->shiftType?->name }} · {{ $assignment->start_at->format('H:i') }}–{{ $assignment->end_at->format('H:i') }}">
                                            <p class="truncate text-[10px] font-bold text-slate-800">{{ $assignment->employee_name }}</p>
                                            <p class="truncate text-[9px] text-slate-500">{{ $assignment->start_at->format('H:i') }}–{{ $assignment->end_at->format('H:i') }} · {{ $assignment->shiftType?->name }}</p>
                                        </a>
                                    @endforeach

                                    @if($day['assignments']->count() > 3)
                                        <p class="px-2 text-[9px] font-semibold text-slate-400">+{{ $day['assignments']->count() - 3 }} more</p>
                                    @endif

                                    @if($day['assignments']->isEmpty() && $day['in_month'])
                                        <p class="px-1 pt-3 text-[9px] text-slate-300">No shifts</p>
                                    @endif
                                </div>

                                @if($canManage && $day['in_month'])
                                    <a href="{{ route('schedule.create', ['date' => $day['date']->toDateString()]) }}" class="mt-2 hidden items-center gap-1 rounded-md px-1 py-0.5 text-[9px] font-semibold text-brand-600 hover:bg-brand-50 group-hover:inline-flex" aria-label="Add shift on {{ $day['date']->format('M j') }}">
                                        <x-icon name="plus" class="h-3 w-3" />
                                        Add
                                    </a>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="mt-4 flex flex-wrap items-center gap-4 text-[11px] text-slate-500">
        <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-brand-600"></span> Today</span>
        <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-slate-300"></span> Outside selected month</span>
        <span>Shift colours follow the configured shift type.</span>
    </div>

    @can('export-data')
        <x-export-dialog id="month-schedule-export" title="Export monthly schedule" description="The export uses the selected reporting period, not just the calendar month on screen. Include any relevant worksheets for a complete workbook." :url="route('exports.schedule')" action="Export schedule">
            <x-export-period-fields id="month" mode="month" />

            <div class="grid gap-4 sm:grid-cols-2">
                @unless(auth()->user()->isEmployee())
                    <div>
                        <label class="ax-label" for="export-month-department">Department</label>
                        <select id="export-month-department" name="department_id" class="ax-input">
                            <option value="">All available departments</option>
                            @foreach($departments as $department)
                                <option value="{{ $department->id }}">{{ $department->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="ax-label" for="export-month-employee">Employee</label>
                        <select id="export-month-employee" name="user_id" class="ax-input">
                            <option value="">All employees</option>
                            @foreach($employees as $employee)
                                <option value="{{ $employee->id }}">{{ $employee->full_name }} · {{ $employee->employee_id }}</option>
                            @endforeach
                        </select>
                    </div>
                @endunless
                <div>
                    <label class="ax-label" for="export-month-shift">Shift type</label>
                    <select id="export-month-shift" name="shift_type_id" class="ax-input">
                        <option value="">All shift types</option>
                        @foreach($shiftTypes as $shiftType)
                            <option value="{{ $shiftType->id }}">{{ $shiftType->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="ax-label" for="export-month-status">Assignment status</label>
                    <select id="export-month-status" name="status" class="ax-input">
                        <option value="">All statuses</option>
                        @foreach(\App\Support\Axcess::ASSIGNMENT_STATUSES as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div>
                <p class="ax-label">Workbook worksheets</p>
                <div class="grid gap-2 sm:grid-cols-2">
                    @foreach(\App\Support\Axcess::SCHEDULE_WORKSHEETS as $value => $label)
                        <label class="flex items-center gap-2.5 rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-xs font-semibold text-slate-700">
                            <input type="checkbox" name="worksheets[]" value="{{ $value }}" @checked($value === 'schedule') class="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                            {{ $label }}
                        </label>
                    @endforeach
                </div>
            </div>

            <div class="space-y-2 rounded-xl border border-slate-200 bg-slate-50 p-3.5">
                <label class="flex items-start gap-2.5 text-xs font-semibold text-slate-700">
                    <input type="checkbox" name="standby_only" value="1" class="mt-0.5 h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                    Include standby duties only
                </label>
                <label class="flex items-start gap-2.5 text-xs font-semibold text-slate-700">
                    <input type="checkbox" name="include_sensitive" value="1" class="mt-0.5 h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                    Include confidential notes and replacement reasons
                </label>
            </div>
        </x-export-dialog>
    @endcan
@endsection
