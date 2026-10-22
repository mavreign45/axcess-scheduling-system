@extends('layouts.app')

@section('title', 'Schedule')
@section('eyebrow', 'Workforce planning')
@section('subtitle', 'Plan shifts, breaks, standby cover, and replacements across the week.')

@section('actions')
    @if($canManage)
        <a href="{{ route('schedule.create', ['date' => now()->toDateString()]) }}" class="ax-btn-primary ax-btn-sm">
            <x-icon name="plus" class="h-4 w-4" />
            <span class="hidden sm:inline">Add shift</span>
        </a>
    @endif
    @can('export-data')
        <button type="button" class="ax-btn-secondary ax-btn-sm" data-export-open="schedule-export">
            <x-icon name="download" class="h-4 w-4" />
            <span class="hidden sm:inline">Export</span>
        </button>
    @endcan
@endsection

@section('content')
    <div class="mb-5 flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('schedule.index', array_merge(request()->except(['week', 'page']), ['week' => $weekStart->copy()->subWeek()->toDateString()])) }}" class="ax-btn-secondary ax-btn-sm" aria-label="Previous week">
                <x-icon name="chevron-left" class="h-4 w-4" />
            </a>
            <div class="rounded-xl border border-slate-200 bg-white px-4 py-2 shadow-sm">
                <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Week of</p>
                <p class="text-sm font-black text-slate-800">{{ $weekStart->format('M j') }} – {{ $weekEnd->format('M j, Y') }}</p>
            </div>
            <a href="{{ route('schedule.index', array_merge(request()->except(['week', 'page']), ['week' => $weekStart->copy()->addWeek()->toDateString()])) }}" class="ax-btn-secondary ax-btn-sm" aria-label="Next week">
                <x-icon name="chevron-right" class="h-4 w-4" />
            </a>
            <a href="{{ route('schedule.index') }}" class="text-xs font-semibold text-slate-500 hover:text-brand-700">This week</a>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <div class="inline-flex rounded-xl border border-slate-200 bg-white p-1 shadow-sm">
                <span class="rounded-lg bg-slate-900 px-3 py-1.5 text-xs font-bold text-white">Week</span>
                <a href="{{ route('schedule.month', array_merge(request()->except(['week', 'page', 'month']), ['month' => $weekStart->format('Y-m')])) }}" class="rounded-lg px-3 py-1.5 text-xs font-bold text-slate-500 transition hover:text-brand-700">Month</a>
            </div>
            <form method="GET" action="{{ route('schedule.index') }}" class="flex items-center gap-2">
                <input type="date" name="week" value="{{ $weekStart->toDateString() }}" class="rounded-lg border border-slate-300 bg-white px-2.5 py-2 text-xs font-semibold text-slate-700" aria-label="Jump to week">
                <button class="ax-btn-secondary ax-btn-sm" type="submit">Go</button>
            </form>
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        <x-stat-card label="Assignments" :value="$stats['assignments']" meta="Active this week" icon="calendar" />
        <x-stat-card label="Shifts" :value="$stats['shifts']" meta="Non-standby work" icon="briefcase" tone="sky" />
        <x-stat-card label="Net hours" :value="number_format($stats['net_hours'], 1)" meta="After scheduled breaks" icon="clock" tone="violet" />
        <x-stat-card label="Standby" :value="$stats['standby']" meta="On-call duties" icon="phone" tone="amber" />
        <x-stat-card label="Unassigned" :value="$stats['unassigned']" meta="Shifts needing cover" icon="warning" :tone="$stats['unassigned'] > 0 ? 'rose' : 'brand'" />
    </div>

    <form method="GET" action="{{ route('schedule.index') }}" class="ax-card mt-5 p-4">
        <input type="hidden" name="week" value="{{ $weekStart->toDateString() }}">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-5">
            @unless(auth()->user()->isEmployee())
                <div>
                    <label class="ax-label" for="filter-department">Department</label>
                    <select id="filter-department" name="department_id" class="ax-input" data-auto-submit @disabled($departments->count() <= 1)>
                        <option value="">All available departments</option>
                        @foreach($departments as $department)
                            <option value="{{ $department->id }}" @selected((int) request('department_id') === $department->id)>{{ $department->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="ax-label" for="filter-employee">Employee</label>
                    <select id="filter-employee" name="user_id" class="ax-input" data-auto-submit>
                        <option value="">All employees</option>
                        @foreach($employees as $employee)
                            <option value="{{ $employee->id }}" @selected((int) request('user_id') === $employee->id)>{{ $employee->full_name }} · {{ $employee->employee_id }}</option>
                        @endforeach
                    </select>
                </div>
            @endunless
            <div>
                <label class="ax-label" for="filter-shift">Shift type</label>
                <select id="filter-shift" name="shift_type_id" class="ax-input" data-auto-submit>
                    <option value="">All shift types</option>
                    @foreach($shiftTypes as $shiftType)
                        <option value="{{ $shiftType->id }}" @selected((int) request('shift_type_id') === $shiftType->id)>{{ $shiftType->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="ax-label" for="filter-status">Status</label>
                <select id="filter-status" name="status" class="ax-input" data-auto-submit>
                    <option value="">All statuses</option>
                    @foreach(\App\Support\Axcess::ASSIGNMENT_STATUSES as $value => $label)
                        <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-end gap-2">
                <button type="submit" class="ax-btn-dark ax-btn-sm w-full">
                    <x-icon name="filter" class="h-4 w-4" />
                    Apply filters
                </button>
                @if(request()->hasAny(['department_id', 'user_id', 'shift_type_id', 'status']))
                    <a href="{{ route('schedule.index', ['week' => $weekStart->toDateString()]) }}" class="ax-btn-secondary ax-btn-sm">Clear</a>
                @endif
            </div>
        </div>
    </form>

    @if($assignments->isEmpty())
        <div class="mt-6">
            <x-empty-state title="No assignments in this week" message="Try another week, clear the filters, or create the first shift for this period." icon="calendar">
                @if($canManage)
                    <a href="{{ route('schedule.create', ['date' => $weekStart->toDateString()]) }}" class="ax-btn-primary ax-btn-sm">
                        <x-icon name="plus" class="h-4 w-4" />
                        Add the first shift
                    </a>
                @endif
            </x-empty-state>
        </div>
    @else
        <div class="mt-6 grid gap-4 lg:grid-cols-2 2xl:grid-cols-3">
            @foreach($days as $day)
                <section class="ax-card overflow-hidden {{ $day['date']->isToday() ? 'ring-2 ring-brand-500 ring-offset-2' : '' }}">
                    <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-4 py-3.5 {{ $day['date']->isToday() ? 'bg-brand-50/70' : 'bg-white' }}">
                        <div class="flex items-center gap-3">
                            <div class="flex h-11 w-11 flex-col items-center justify-center rounded-xl {{ $day['date']->isToday() ? 'bg-brand-700 text-white' : 'bg-slate-100 text-slate-700' }}">
                                <span class="text-[9px] font-bold uppercase tracking-wide {{ $day['date']->isToday() ? 'text-brand-100' : 'text-slate-400' }}">{{ $day['date']->format('D') }}</span>
                                <span class="text-base font-black leading-4">{{ $day['date']->format('j') }}</span>
                            </div>
                            <div>
                                <h2 class="text-sm font-black text-slate-900">{{ $day['date']->format('l') }}</h2>
                                <p class="text-[11px] text-slate-500">{{ $day['date']->format('F j, Y') }} · {{ $day['staff_count'] }} staff · {{ number_format($day['hours'], 1) }}h</p>
                            </div>
                        </div>
                        @if($canManage)
                            <a href="{{ route('schedule.create', ['date' => $day['date']->toDateString()]) }}" class="rounded-lg p-2 text-slate-400 transition hover:bg-slate-100 hover:text-brand-700" title="Add shift on {{ $day['date']->format('M j') }}" aria-label="Add shift on {{ $day['date']->format('M j') }}">
                                <x-icon name="plus" class="h-4 w-4" />
                            </a>
                        @endif
                    </div>

                    @if($day['assignments']->isEmpty())
                        <p class="px-4 py-6 text-center text-[11px] text-slate-400">No shifts scheduled.</p>
                    @else
                        <div class="divide-y divide-slate-100">
                            @foreach($day['assignments'] as $assignment)
                                <div class="px-4 py-3 transition hover:bg-slate-50/70">
                                    <div class="flex items-start gap-3">
                                        <div class="w-[62px] shrink-0 pt-0.5">
                                            <p class="text-xs font-black text-slate-800">{{ $assignment->start_at->format('H:i') }}</p>
                                            <p class="text-[10px] text-slate-400">{{ $assignment->end_at->format('H:i') }}{{ $assignment->is_overnight ? ' +1' : '' }}</p>
                                        </div>
                                        <span class="mt-1 h-8 w-1 shrink-0 rounded-full" style="background: {{ $assignment->shiftType?->color ?? '#0f766e' }}"></span>
                                        <div class="min-w-0 flex-1">
                                            <div class="flex flex-wrap items-center gap-1.5">
                                                <p class="truncate text-xs font-bold text-slate-800">{{ $assignment->employee_name }}</p>
                                                @if($assignment->is_standby)
                                                    <span class="ax-chip bg-amber-50 px-1.5 py-0.5 text-[9px] text-amber-700 ring-1 ring-inset ring-amber-200">Standby</span>
                                                @endif
                                                @if($assignment->replacementUser)
                                                    <span class="ax-chip bg-sky-50 px-1.5 py-0.5 text-[9px] text-sky-700 ring-1 ring-inset ring-sky-200">Replacement</span>
                                                @endif
                                            </div>
                                            <p class="mt-0.5 text-[11px] text-slate-500">
                                                {{ $assignment->shiftType?->name }}
                                                @if(auth()->user()->isAdmin() || auth()->user()->isManager()) · {{ $assignment->department?->name }} @endif
                                            </p>
                                            <div class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-[10px] text-slate-400">
                                                <span>{{ number_format($assignment->net_hours, 1) }}h net</span>
                                                @if($assignment->break_count > 0)
                                                    <span>· {{ $assignment->break_count }} break(s)</span>
                                                @else
                                                    <span class="text-rose-500">· No breaks</span>
                                                @endif
                                                @if($assignment->is_overnight)
                                                    <span>· Overnight</span>
                                                @endif
                                            </div>
                                            @if($canManage && $assignment->notes)
                                                <p class="mt-1 line-clamp-1 text-[10px] italic text-slate-400" title="{{ $assignment->notes }}">{{ $assignment->notes }}</p>
                                            @endif
                                        </div>
                                        <div class="flex shrink-0 items-center gap-1">
                                            <x-status-badge :status="$assignment->status" :label="$assignment->status_label" />
                                            @if($canManage)
                                                <a href="{{ route('schedule.edit', $assignment) }}" class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-brand-700" title="Edit shift" aria-label="Edit shift">
                                                    <x-icon name="edit" class="h-3.5 w-3.5" />
                                                </a>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </section>
            @endforeach
        </div>
    @endif

    @can('export-data')
        <x-export-dialog id="schedule-export" title="Export schedule" description="Choose a period and the worksheets to include. Excel workbooks contain formatted, filterable sheets with overnight shifts and every recorded break." :url="route('exports.schedule')" action="Export schedule">
            <x-export-period-fields id="schedule" mode="week" />

            <div class="grid gap-4 sm:grid-cols-2">
                @unless(auth()->user()->isEmployee())
                    <div>
                        <label class="ax-label" for="export-schedule-department">Department</label>
                        <select id="export-schedule-department" name="department_id" class="ax-input">
                            <option value="">All available departments</option>
                            @foreach($departments as $department)
                                <option value="{{ $department->id }}">{{ $department->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="ax-label" for="export-schedule-employee">Employee</label>
                        <select id="export-schedule-employee" name="user_id" class="ax-input">
                            <option value="">All employees</option>
                            @foreach($employees as $employee)
                                <option value="{{ $employee->id }}">{{ $employee->full_name }} · {{ $employee->employee_id }}</option>
                            @endforeach
                        </select>
                    </div>
                @endunless
                <div>
                    <label class="ax-label" for="export-schedule-shift">Shift type</label>
                    <select id="export-schedule-shift" name="shift_type_id" class="ax-input">
                        <option value="">All shift types</option>
                        @foreach($shiftTypes as $shiftType)
                            <option value="{{ $shiftType->id }}">{{ $shiftType->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="ax-label" for="export-schedule-status">Assignment status</label>
                    <select id="export-schedule-status" name="status" class="ax-input">
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
                @if(auth()->user()->isAdmin() || auth()->user()->isManager())
                    <label class="flex items-start gap-2.5 text-xs font-semibold text-slate-700">
                        <input type="checkbox" name="include_sensitive" value="1" class="mt-0.5 h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                        Include confidential notes and replacement reasons
                    </label>
                @endif
            </div>
        </x-export-dialog>
    @endcan
@endsection
