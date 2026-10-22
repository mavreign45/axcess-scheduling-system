@extends('layouts.app')

@section('title', 'Dashboard')
@section('eyebrow', now()->format('l, F j'))
@section('subtitle', 'A live view of your team, cover, and scheduling health.')

@section('actions')
    @if($managerView)
        <a href="{{ route('schedule.create') }}" class="ax-btn-primary ax-btn-sm">
            <x-icon name="plus" class="h-4 w-4" />
            <span class="hidden sm:inline">Add shift</span>
        </a>
    @else
        <a href="{{ route('leave.create') }}" class="ax-btn-secondary ax-btn-sm">
            <x-icon name="plus" class="h-4 w-4" />
            <span class="hidden sm:inline">Request leave</span>
        </a>
    @endif
@endsection

@section('content')
    @if($managerView)
        <section class="relative overflow-hidden rounded-3xl bg-slate-950 px-6 py-7 text-white shadow-xl sm:px-8">
            <div class="pointer-events-none absolute -right-16 -top-24 h-64 w-64 rounded-full bg-brand-500/20 blur-3xl"></div>
            <div class="pointer-events-none absolute -bottom-24 left-1/3 h-52 w-52 rounded-full bg-sky-500/10 blur-3xl"></div>
            <div class="relative flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-brand-300">Operations overview</p>
                    <h2 class="mt-2 text-2xl font-black tracking-tight sm:text-3xl">
                        {{ now()->hour < 12 ? 'Good morning' : (now()->hour < 18 ? 'Good afternoon' : 'Good evening') }}, {{ auth()->user()->first_name }}.
                    </h2>
                    <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-400">
                        You are viewing {{ auth()->user()->isAdmin() ? 'the full organisation' : (auth()->user()->department?->name ?? 'your department') }}
                        for the week of {{ $weekStart->format('M j') }} – {{ $weekEnd->format('M j, Y') }}.
                        {{ $metrics['shift_count'] }} shifts are planned with {{ number_format($metrics['net_hours'], 1) }} net working hours.
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('schedule.index') }}" class="ax-btn bg-white/10 text-white ring-1 ring-inset ring-white/15 hover:bg-white/15">
                        <x-icon name="calendar" class="h-4 w-4" />
                        Week schedule
                    </a>
                    <a href="{{ route('reports.index') }}" class="ax-btn bg-brand-600 text-white hover:bg-brand-500">
                        <x-icon name="chart" class="h-4 w-4" />
                        Reports &amp; exports
                    </a>
                </div>
            </div>
        </section>

        <div class="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-stat-card label="Shifts this week" :value="$metrics['shift_count']" :meta="$metrics['staff_scheduled'].' staff scheduled'" icon="calendar" />
            <x-stat-card label="Net working hours" :value="number_format($metrics['net_hours'], 1)" :meta="number_format($metrics['break_hours'], 1).' break hours'" icon="clock" tone="sky" />
            <x-stat-card label="Open conflicts" :value="$metrics['open_conflicts']" :meta="$metrics['coverage_gaps'].' coverage gap(s) this week'" icon="warning" :tone="$metrics['open_conflicts'] > 0 ? 'rose' : 'brand'" />
            <x-stat-card label="Pending leave" :value="$metrics['pending_leave_requests']" :meta="$metrics['standby_count'].' standby assignment(s)'" icon="inbox" tone="amber" />
        </div>

        <div class="mt-6 grid gap-6 xl:grid-cols-[1.5fr_1fr]">
            <section class="ax-card overflow-hidden">
                <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-5 py-4">
                    <div>
                        <h2 class="text-sm font-black text-slate-900">Today’s roster</h2>
                        <p class="mt-0.5 text-xs text-slate-500">{{ $today->format('l, F j, Y') }} · {{ $todayAssignments->count() }} assignment(s)</p>
                    </div>
                    <a href="{{ route('schedule.index', ['week' => $weekStart->toDateString()]) }}" class="text-xs font-bold text-brand-700 hover:text-brand-800">View week</a>
                </div>

                @if($todayAssignments->isEmpty())
                    <div class="p-5">
                        <x-empty-state title="No shifts scheduled today" message="There are no active assignments for this date. Add a shift or check the week view." icon="calendar" />
                    </div>
                @else
                    <div class="divide-y divide-slate-100">
                        @foreach($todayAssignments as $assignment)
                            <div class="flex flex-wrap items-center gap-3 px-5 py-3.5 transition hover:bg-slate-50/70">
                                <div class="w-[74px] shrink-0">
                                    <p class="text-xs font-black text-slate-800">{{ $assignment->start_at->format('H:i') }}</p>
                                    <p class="text-[10px] text-slate-400">{{ $assignment->end_at->format('H:i') }}{{ $assignment->is_overnight ? ' +1' : '' }}</p>
                                </div>
                                <span class="h-9 w-1 shrink-0 rounded-full" style="background: {{ $assignment->shiftType?->color ?? '#0f766e' }}"></span>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-bold text-slate-800">{{ $assignment->employee_name }}</p>
                                    <p class="truncate text-[11px] text-slate-500">
                                        {{ $assignment->shiftType?->name }} · {{ $assignment->department?->name }}
                                        @if($assignment->replacementUser)
                                            · <span class="font-semibold text-amber-600">replacing {{ $assignment->user?->full_name }}</span>
                                        @endif
                                    </p>
                                </div>
                                <div class="flex items-center gap-2">
                                    @if($assignment->is_standby)
                                        <span class="ax-chip bg-amber-50 text-amber-700 ring-1 ring-inset ring-amber-200">Standby</span>
                                    @endif
                                    <x-status-badge :status="$assignment->status" :label="$assignment->status_label" />
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>

            <div class="space-y-6">
                <section class="ax-card overflow-hidden">
                    <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                        <div>
                            <h2 class="text-sm font-black text-slate-900">Leave awaiting approval</h2>
                            <p class="mt-0.5 text-xs text-slate-500">Requests within your scope</p>
                        </div>
                        <span class="flex h-7 min-w-7 items-center justify-center rounded-lg bg-amber-50 px-2 text-xs font-black text-amber-700">{{ $pendingLeave->count() }}</span>
                    </div>
                    @if($pendingLeave->isEmpty())
                        <p class="px-5 py-8 text-center text-xs text-slate-400">No pending leave requests. You are all caught up.</p>
                    @else
                        <div class="divide-y divide-slate-100">
                            @foreach($pendingLeave as $leave)
                                <a href="{{ route('leave.show', $leave) }}" class="flex items-center gap-3 px-5 py-3 transition hover:bg-slate-50">
                                    <span class="ax-avatar bg-slate-200 text-slate-700">{{ $leave->user?->initials }}</span>
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-xs font-bold text-slate-800">{{ $leave->user?->full_name }}</p>
                                        <p class="truncate text-[11px] text-slate-500">{{ $leave->leave_type_label }} · {{ $leave->date_range_label }}</p>
                                    </div>
                                    <x-icon name="chevron-right" class="h-4 w-4 text-slate-300" />
                                </a>
                            @endforeach
                        </div>
                    @endif
                </section>

                <section class="ax-card overflow-hidden">
                    <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                        <div>
                            <h2 class="text-sm font-black text-slate-900">Coverage watch</h2>
                            <p class="mt-0.5 text-xs text-slate-500">Department-days below minimum</p>
                        </div>
                        <a href="{{ route('reports.index') }}" class="text-xs font-bold text-brand-700 hover:text-brand-800">Reports</a>
                    </div>
                    @if($coverageGaps->isEmpty())
                        <div class="px-5 py-7 text-center">
                            <span class="mx-auto flex h-9 w-9 items-center justify-center rounded-full bg-emerald-50 text-emerald-600">
                                <x-icon name="check" class="h-4 w-4" />
                            </span>
                            <p class="mt-2 text-xs font-semibold text-slate-600">Every department-day is covered this week.</p>
                        </div>
                    @else
                        <div class="divide-y divide-slate-100">
                            @foreach($coverageGaps as $gap)
                                <div class="flex items-center justify-between gap-3 px-5 py-3">
                                    <div>
                                        <p class="text-xs font-bold text-slate-800">{{ $gap['department'] }}</p>
                                        <p class="text-[11px] text-slate-500">{{ $gap['date']->format('D, M j') }} · {{ $gap['scheduled'] }}/{{ $gap['required'] }} staff</p>
                                    </div>
                                    <span class="ax-chip bg-rose-50 text-rose-700 ring-1 ring-inset ring-rose-200">Gap {{ $gap['gap'] }}</span>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </section>
            </div>
        </div>

        <div class="mt-6 grid gap-6 xl:grid-cols-2">
            <section class="ax-card overflow-hidden">
                <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                    <div>
                        <h2 class="text-sm font-black text-slate-900">Open conflicts</h2>
                        <p class="mt-0.5 text-xs text-slate-500">Prioritised by severity</p>
                    </div>
                    <a href="{{ route('reports.index') }}" class="text-xs font-bold text-brand-700 hover:text-brand-800">Review all</a>
                </div>
                @if($openConflicts->isEmpty())
                    <p class="px-5 py-8 text-center text-xs text-slate-400">No open conflicts detected. Your schedule is looking healthy.</p>
                @else
                    <div class="divide-y divide-slate-100">
                        @foreach($openConflicts as $conflict)
                            <div class="flex items-start gap-3 px-5 py-3.5">
                                <span class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-lg {{ $conflict->severity === 'error' ? 'bg-rose-50 text-rose-600' : ($conflict->severity === 'warning' ? 'bg-amber-50 text-amber-600' : 'bg-sky-50 text-sky-600') }}">
                                    <x-icon name="warning" class="h-3.5 w-3.5" />
                                </span>
                                <div class="min-w-0 flex-1">
                                    <p class="text-xs font-bold text-slate-800">{{ $conflict->title }}</p>
                                    <p class="mt-0.5 line-clamp-2 text-[11px] leading-5 text-slate-500">{{ $conflict->description }}</p>
                                </div>
                                <x-status-badge :status="$conflict->severity" :label="$conflict->severity_label" />
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>

            <section class="ax-card overflow-hidden">
                <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                    <div>
                        <h2 class="text-sm font-black text-slate-900">Upcoming standby</h2>
                        <p class="mt-0.5 text-xs text-slate-500">Team members on call</p>
                    </div>
                    @can('manage-operations')
                        <a href="{{ route('standby.index') }}" class="text-xs font-bold text-brand-700 hover:text-brand-800">Manage</a>
                    @endcan
                </div>
                @if($upcomingStandby->isEmpty())
                    <p class="px-5 py-8 text-center text-xs text-slate-400">No standby duties scheduled.</p>
                @else
                    <div class="divide-y divide-slate-100">
                        @foreach($upcomingStandby as $standby)
                            <div class="flex items-center gap-3 px-5 py-3.5">
                                <span class="ax-avatar bg-amber-100 text-amber-700">{{ $standby->effective_employee?->initials ?? '—' }}</span>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-xs font-bold text-slate-800">{{ $standby->effective_employee?->full_name ?? 'Unassigned standby' }}</p>
                                    <p class="truncate text-[11px] text-slate-500">{{ $standby->schedule_date->format('D, M j') }} · {{ $standby->start_at->format('H:i') }}–{{ $standby->end_at->format('H:i') }} · {{ $standby->department?->name }}</p>
                                </div>
                                <x-status-badge :status="$standby->standby_status" :label="$standby->standby_status_label" />
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>
        </div>
    @else
        @php
            $firstName = auth()->user()->first_name;
            $nextShift = $weekAssignments->first(fn ($assignment) => $assignment->start_at->isFuture());
        @endphp

        <section class="relative overflow-hidden rounded-3xl bg-slate-950 px-6 py-7 text-white shadow-xl sm:px-8">
            <div class="pointer-events-none absolute -right-12 -top-20 h-60 w-60 rounded-full bg-brand-500/20 blur-3xl"></div>
            <div class="relative flex flex-col gap-6 md:flex-row md:items-end md:justify-between">
                <div>
                    <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-brand-300">Your week at a glance</p>
                    <h2 class="mt-2 text-2xl font-black tracking-tight sm:text-3xl">Hello, {{ $firstName }}.</h2>
                    <p class="mt-2 max-w-xl text-sm leading-6 text-slate-400">
                        You have {{ $stats['shifts_this_week'] }} scheduled shift(s) and {{ $stats['standby_this_week'] }} standby duty(ies) this week.
                        @if($nextShift)
                            Your next shift starts {{ $nextShift->start_at->format('D, M j \a\t H:i') }}.
                        @else
                            No further shifts are scheduled this week.
                        @endif
                    </p>
                </div>
                <a href="{{ route('schedule.index') }}" class="ax-btn bg-brand-600 text-white hover:bg-brand-500">
                    <x-icon name="calendar" class="h-4 w-4" />
                    View my schedule
                </a>
            </div>
        </section>

        <div class="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-stat-card label="Shifts this week" :value="$stats['shifts_this_week']" :meta="$weekStart->format('M j').' – '.$weekEnd->format('M j')" icon="calendar" />
            <x-stat-card label="Net working hours" :value="number_format($stats['hours_this_week'], 1)" meta="Scheduled hours minus breaks" icon="clock" tone="sky" />
            <x-stat-card label="Standby duties" :value="$stats['standby_this_week']" meta="This week" icon="phone" tone="amber" />
            <x-stat-card label="Approved leave" :value="number_format($stats['leave_days_this_year'], 1)" meta="Days this year" icon="inbox" tone="violet" />
        </div>

        <div class="mt-6 grid gap-6 xl:grid-cols-[1.4fr_1fr]">
            <section class="ax-card overflow-hidden">
                <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                    <div>
                        <h2 class="text-sm font-black text-slate-900">My week</h2>
                        <p class="mt-0.5 text-xs text-slate-500">{{ $weekStart->format('M j') }} – {{ $weekEnd->format('M j, Y') }}</p>
                    </div>
                    <a href="{{ route('schedule.index', ['week' => $weekStart->toDateString()]) }}" class="text-xs font-bold text-brand-700 hover:text-brand-800">Open schedule</a>
                </div>

                @if($weekAssignments->isEmpty())
                    <div class="p-5">
                        <x-empty-state title="No assignments this week" message="You have no shifts or standby duties scheduled for this week." icon="calendar" />
                    </div>
                @else
                    <div class="divide-y divide-slate-100">
                        @foreach($weekAssignments as $assignment)
                            <div class="flex items-center gap-3 px-5 py-3.5">
                                <div class="flex h-12 w-12 shrink-0 flex-col items-center justify-center rounded-xl bg-slate-50 ring-1 ring-slate-200">
                                    <span class="text-[9px] font-bold uppercase tracking-wide text-slate-400">{{ $assignment->schedule_date->format('D') }}</span>
                                    <span class="text-sm font-black text-slate-800">{{ $assignment->schedule_date->format('j') }}</span>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-bold text-slate-800">{{ $assignment->shiftType?->name }}{{ $assignment->is_standby ? ' · Standby' : '' }}</p>
                                    <p class="text-[11px] text-slate-500">
                                        {{ $assignment->start_at->format('H:i') }}–{{ $assignment->end_at->format('H:i') }}{{ $assignment->is_overnight ? ' (+1 day)' : '' }}
                                        · {{ $assignment->department?->name }}
                                        @if($assignment->break_count > 0) · {{ $assignment->break_count }} break(s) @endif
                                    </p>
                                </div>
                                <x-status-badge :status="$assignment->status" :label="$assignment->status_label" />
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>

            <div class="space-y-6">
                <section class="ax-card overflow-hidden">
                    <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                        <div>
                            <h2 class="text-sm font-black text-slate-900">Today</h2>
                            <p class="mt-0.5 text-xs text-slate-500">{{ $today->format('l, M j') }}</p>
                        </div>
                    </div>
                    @if($todayAssignments->isEmpty())
                        <p class="px-5 py-8 text-center text-xs text-slate-400">No shift today. Enjoy the break.</p>
                    @else
                        <div class="space-y-3 p-5">
                            @foreach($todayAssignments as $assignment)
                                <div class="rounded-xl border border-slate-200 bg-slate-50/60 p-4">
                                    <div class="flex items-center justify-between gap-2">
                                        <p class="text-sm font-black text-slate-800">{{ $assignment->start_at->format('H:i') }} – {{ $assignment->end_at->format('H:i') }}{{ $assignment->is_overnight ? ' +1' : '' }}</p>
                                        <x-status-badge :status="$assignment->status" :label="$assignment->status_label" />
                                    </div>
                                    <p class="mt-1 text-xs text-slate-500">{{ $assignment->shiftType?->name }} · {{ $assignment->department?->name }}</p>
                                    @if($assignment->break_count > 0)
                                        <p class="mt-2 text-[11px] text-slate-400">{{ $assignment->break_count }} break period(s), {{ number_format($assignment->break_hours, 1) }} hours</p>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif
                </section>

                <section class="ax-card overflow-hidden">
                    <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                        <h2 class="text-sm font-black text-slate-900">Recent leave</h2>
                        <a href="{{ route('leave.create') }}" class="text-xs font-bold text-brand-700 hover:text-brand-800">Request leave</a>
                    </div>
                    @if($leaves->isEmpty())
                        <p class="px-5 py-8 text-center text-xs text-slate-400">No leave requests yet.</p>
                    @else
                        <div class="divide-y divide-slate-100">
                            @foreach($leaves as $leave)
                                <a href="{{ route('leave.show', $leave) }}" class="flex items-center justify-between gap-3 px-5 py-3 transition hover:bg-slate-50">
                                    <div>
                                        <p class="text-xs font-bold text-slate-800">{{ $leave->leave_type_label }}</p>
                                        <p class="text-[11px] text-slate-500">{{ $leave->date_range_label }}</p>
                                    </div>
                                    <x-status-badge :status="$leave->status" :label="$leave->status_label" />
                                </a>
                            @endforeach
                        </div>
                    @endif
                </section>
            </div>
        </div>
    @endif
@endsection
