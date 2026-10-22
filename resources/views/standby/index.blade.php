@extends('layouts.app')

@section('title', 'Standby & cover')
@section('eyebrow', 'On-call operations')
@section('subtitle', 'Schedule standby cover, track call-ins, and keep replacement history.')

@section('actions')
    <a href="{{ route('standby.create', ['date' => now()->toDateString()]) }}" class="ax-btn-primary ax-btn-sm">
        <x-icon name="plus" class="h-4 w-4" />
        <span class="hidden sm:inline">Add standby</span>
    </a>
    @can('export-data')
        <button type="button" class="ax-btn-secondary ax-btn-sm" data-export-open="standby-export">
            <x-icon name="download" class="h-4 w-4" />
            <span class="hidden sm:inline">Export</span>
        </button>
    @endcan
@endsection

@section('content')
    <div class="mb-5 flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('standby.index', array_merge(request()->except(['week', 'page']), ['week' => $weekStart->copy()->subWeek()->toDateString()])) }}" class="ax-btn-secondary ax-btn-sm" aria-label="Previous week">
                <x-icon name="chevron-left" class="h-4 w-4" />
            </a>
            <div class="rounded-xl border border-slate-200 bg-white px-4 py-2 shadow-sm">
                <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Standby week</p>
                <p class="text-sm font-black text-slate-800">{{ $weekStart->format('M j') }} – {{ $weekEnd->format('M j, Y') }}</p>
            </div>
            <a href="{{ route('standby.index', array_merge(request()->except(['week', 'page']), ['week' => $weekStart->copy()->addWeek()->toDateString()])) }}" class="ax-btn-secondary ax-btn-sm" aria-label="Next week">
                <x-icon name="chevron-right" class="h-4 w-4" />
            </a>
            <a href="{{ route('standby.index') }}" class="text-xs font-semibold text-slate-500 hover:text-brand-700">This week</a>
        </div>
        <form method="GET" action="{{ route('standby.index') }}" class="flex items-center gap-2">
            <input type="date" name="week" value="{{ $weekStart->toDateString() }}" class="rounded-lg border border-slate-300 bg-white px-2.5 py-2 text-xs font-semibold text-slate-700" aria-label="Jump to week">
            <button class="ax-btn-secondary ax-btn-sm" type="submit">Go</button>
        </form>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat-card label="Standby duties" :value="$stats['total']" meta="This week" icon="phone" />
        <x-stat-card label="Available" :value="$stats['available']" meta="Ready to be called" icon="check-circle" tone="sky" />
        <x-stat-card label="Called in" :value="$stats['called_in']" meta="Actively covering" icon="bolt" tone="amber" />
        <x-stat-card label="Standby hours" :value="number_format($stats['hours'], 1)" meta="Planned on-call hours" icon="clock" tone="violet" />
    </div>

    <form method="GET" action="{{ route('standby.index') }}" class="ax-card mt-5 p-4">
        <input type="hidden" name="week" value="{{ $weekStart->toDateString() }}">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            @unless(auth()->user()->isManager())
                <div>
                    <label class="ax-label" for="standby-department">Department</label>
                    <select id="standby-department" name="department_id" class="ax-input" data-auto-submit>
                        <option value="">All departments</option>
                        @foreach($departments as $department)
                            <option value="{{ $department->id }}" @selected((int) request('department_id') === $department->id)>{{ $department->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endunless
            <div>
                <label class="ax-label" for="standby-employee">Employee</label>
                <select id="standby-employee" name="user_id" class="ax-input" data-auto-submit>
                    <option value="">All employees</option>
                    @foreach($employees as $employee)
                        <option value="{{ $employee->id }}" @selected((int) request('user_id') === $employee->id)>{{ $employee->full_name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="ax-label" for="standby-status-filter">Standby status</label>
                <select id="standby-status-filter" name="standby_status" class="ax-input" data-auto-submit>
                    <option value="">All statuses</option>
                    @foreach(\App\Support\Axcess::STANDBY_STATUSES as $value => $label)
                        @if($value !== 'not_applicable')
                            <option value="{{ $value }}" @selected(request('standby_status') === $value)>{{ $label }}</option>
                        @endif
                    @endforeach
                </select>
            </div>
            <div class="flex items-end gap-2">
                <button type="submit" class="ax-btn-dark ax-btn-sm w-full">Filter</button>
                @if(request()->hasAny(['department_id', 'user_id', 'standby_status']))
                    <a href="{{ route('standby.index', ['week' => $weekStart->toDateString()]) }}" class="ax-btn-secondary ax-btn-sm">Clear</a>
                @endif
            </div>
        </div>
    </form>

    @if($assignments->isEmpty())
        <div class="mt-6">
            <x-empty-state title="No standby duties this week" message="Standby duties make on-call responsibilities visible and reportable. Add the first one for this week." icon="phone">
                <a href="{{ route('standby.create', ['date' => $weekStart->toDateString()]) }}" class="ax-btn-primary ax-btn-sm">
                    <x-icon name="plus" class="h-4 w-4" />
                    Add standby duty
                </a>
            </x-empty-state>
        </div>
    @else
        <div class="mt-6 space-y-4">
            @foreach(range(0, 6) as $dayOffset)
                @php
                    $date = $weekStart->copy()->addDays($dayOffset);
                    $dayAssignments = $byDay->get($date->toDateString(), collect());
                @endphp
                @if($dayAssignments->isNotEmpty())
                    <section class="ax-card overflow-hidden">
                        <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-5 py-3.5 {{ $date->isToday() ? 'bg-brand-50/60' : 'bg-slate-50/50' }}">
                            <div class="flex items-center gap-3">
                                <span class="flex h-9 w-9 items-center justify-center rounded-xl {{ $date->isToday() ? 'bg-brand-700 text-white' : 'bg-white text-slate-700 ring-1 ring-slate-200' }}">
                                    <span class="text-[10px] font-bold">{{ $date->format('D') }}</span>
                                </span>
                                <div>
                                    <h2 class="text-sm font-black text-slate-900">{{ $date->format('l, F j') }}</h2>
                                    <p class="text-[11px] text-slate-500">{{ $dayAssignments->count() }} standby assignment(s)</p>
                                </div>
                            </div>
                            <a href="{{ route('standby.create', ['date' => $date->toDateString()]) }}" class="text-xs font-bold text-brand-700 hover:text-brand-800">Add</a>
                        </div>

                        <div class="ax-scrollbar overflow-x-auto">
                            <table class="ax-table">
                                <thead>
                                    <tr>
                                        <th>Employee</th>
                                        <th>Shift</th>
                                        <th>Department</th>
                                        <th>Cover</th>
                                        <th>Status</th>
                                        <th class="text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($dayAssignments as $assignment)
                                        <tr>
                                            <td>
                                                <div class="flex items-center gap-3">
                                                    <span class="ax-avatar bg-amber-100 text-amber-700">{{ $assignment->effective_employee?->initials ?? '—' }}</span>
                                                    <div>
                                                        <p class="text-xs font-bold text-slate-800">{{ $assignment->effective_employee?->full_name ?? 'Unassigned standby' }}</p>
                                                        <p class="text-[11px] text-slate-500">{{ $assignment->effective_employee?->employee_id }}</p>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <p class="text-xs font-semibold text-slate-700">{{ $assignment->start_at->format('H:i') }}–{{ $assignment->end_at->format('H:i') }}{{ $assignment->is_overnight ? ' +1' : '' }}</p>
                                                <p class="text-[11px] text-slate-500">{{ $assignment->shiftType?->name }}</p>
                                            </td>
                                            <td><span class="text-xs text-slate-500">{{ $assignment->department?->name }}</span></td>
                                            <td>
                                                @if($assignment->replacementUser)
                                                    <p class="text-xs font-semibold text-sky-700">{{ $assignment->replacementUser->full_name }}</p>
                                                    <p class="text-[10px] text-slate-400">replacing {{ $assignment->user?->full_name }}</p>
                                                @else
                                                    <span class="text-[11px] text-slate-400">Original assignee</span>
                                                @endif
                                            </td>
                                            <td><x-status-badge :status="$assignment->standby_status" :label="$assignment->standby_status_label" /></td>
                                            <td>
                                                <div class="flex items-center justify-end gap-1">
                                                    <form method="POST" action="{{ route('standby.status', $assignment) }}" class="flex items-center gap-1.5">
                                                        @csrf
                                                        <select name="standby_status" class="rounded-lg border border-slate-300 bg-white px-2 py-1.5 text-[11px] font-semibold text-slate-700" aria-label="Update standby status">
                                                            @foreach(\App\Support\Axcess::STANDBY_STATUSES as $value => $label)
                                                                @if($value !== 'not_applicable')
                                                                    <option value="{{ $value }}" @selected($assignment->standby_status === $value)>{{ $label }}</option>
                                                                @endif
                                                            @endforeach
                                                        </select>
                                                        <button type="submit" class="rounded-lg border border-slate-200 p-1.5 text-slate-500 transition hover:bg-slate-100 hover:text-brand-700" title="Update status" aria-label="Update status">
                                                            <x-icon name="refresh" class="h-3.5 w-3.5" />
                                                        </button>
                                                    </form>
                                                    <a href="{{ route('schedule.edit', $assignment) }}" class="rounded-lg p-2 text-slate-400 transition hover:bg-slate-100 hover:text-brand-700" title="Edit standby" aria-label="Edit standby">
                                                        <x-icon name="edit" class="h-4 w-4" />
                                                    </a>
                                                    <form method="POST" action="{{ route('standby.destroy', $assignment) }}" data-confirm="Remove this standby assignment?">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="rounded-lg p-2 text-slate-400 transition hover:bg-rose-50 hover:text-rose-600" title="Delete standby" aria-label="Delete standby">
                                                            <x-icon name="trash" class="h-4 w-4" />
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </section>
                @endif
            @endforeach
        </div>
    @endif

    @can('export-data')
        <x-export-dialog id="standby-export" title="Export standby & cover" description="Creates a schedule workbook filtered to standby duties. Add the Standby &amp; Replacements worksheet for complete cover history." :url="route('exports.schedule')" action="Export standby">
            <x-export-period-fields id="standby" mode="week" />

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="ax-label" for="export-standby-employee">Employee</label>
                    <select id="export-standby-employee" name="user_id" class="ax-input">
                        <option value="">All employees</option>
                        @foreach($employees as $employee)
                            <option value="{{ $employee->id }}">{{ $employee->full_name }} · {{ $employee->employee_id }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="ax-label" for="export-standby-department">Department</label>
                    <select id="export-standby-department" name="department_id" class="ax-input">
                        <option value="">All available departments</option>
                        @foreach($departments as $department)
                            <option value="{{ $department->id }}">{{ $department->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <input type="hidden" name="standby_only" value="1">

            <div>
                <p class="ax-label">Workbook worksheets</p>
                <div class="grid gap-2 sm:grid-cols-2">
                    @foreach(['schedule' => 'Schedule', 'standby_replacements' => 'Standby & Replacements', 'conflicts' => 'Conflicts'] as $value => $label)
                        <label class="flex items-center gap-2.5 rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-xs font-semibold text-slate-700">
                            <input type="checkbox" name="worksheets[]" value="{{ $value }}" @checked(in_array($value, ['schedule', 'standby_replacements'], true)) class="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                            {{ $label }}
                        </label>
                    @endforeach
                </div>
            </div>

            @if(auth()->user()->isAdmin() || auth()->user()->isManager())
                <label class="flex items-start gap-2.5 rounded-xl border border-slate-200 bg-slate-50 p-3.5 text-xs font-semibold text-slate-700">
                    <input type="checkbox" name="include_sensitive" value="1" class="mt-0.5 h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                    Include confidential manager notes and replacement reasons
                </label>
            @endif

            <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-[11px] leading-5 text-amber-800">
                The export is filtered to standby duties only. Scheduled hours represent planned standby time, not hours actually worked.
            </div>
        </x-export-dialog>
    @endcan
@endsection
