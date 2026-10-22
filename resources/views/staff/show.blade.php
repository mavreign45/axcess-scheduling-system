@extends('layouts.app')

@section('title', $staffMember->full_name)
@section('eyebrow', 'Staff profile')
@section('subtitle', $staffMember->employee_id.' · '.($staffMember->jobPosition?->title ?? $staffMember->roleLabel()))

@section('actions')
    @can('update', $staffMember)
        <a href="{{ route('staff.edit', $staffMember) }}" class="ax-btn-primary ax-btn-sm">
            <x-icon name="edit" class="h-4 w-4" />
            <span class="hidden sm:inline">Edit profile</span>
        </a>
    @endcan
    <a href="{{ route('staff.index') }}" class="ax-btn-secondary ax-btn-sm">
        <x-icon name="chevron-left" class="h-4 w-4" />
        <span class="hidden sm:inline">Directory</span>
    </a>
@endsection

@section('content')
    @php
        $approvedLeaveDays = $leaves->where('status', 'approved')->sum('duration_days');
    @endphp

    <div class="grid gap-5 xl:grid-cols-[1.5fr_1fr]">
        <section class="ax-card overflow-hidden">
            <div class="h-24 bg-slate-950">
                <div class="relative h-full overflow-hidden">
                    <div class="absolute -right-6 -top-12 h-40 w-40 rounded-full bg-brand-500/25 blur-2xl"></div>
                    <div class="absolute left-10 top-8 h-20 w-20 rounded-full bg-sky-500/10 blur-xl"></div>
                </div>
            </div>
            <div class="px-5 pb-5 sm:px-6">
                <div class="-mt-9 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div class="flex items-end gap-4">
                        <span class="flex h-20 w-20 items-center justify-center rounded-2xl border-4 border-white bg-slate-900 text-xl font-black text-white shadow-lg">{{ $staffMember->initials }}</span>
                        <div class="pb-1">
                            <h2 class="text-xl font-black tracking-tight text-slate-900">{{ $staffMember->full_name }}</h2>
                            <p class="text-xs text-slate-500">{{ $staffMember->jobPosition?->title ?? 'No position assigned' }} · {{ $staffMember->department?->name ?? 'No department' }}</p>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-2 pb-1">
                        <x-status-badge :status="$staffMember->employment_status" :label="\App\Support\Axcess::label('employment_status', $staffMember->employment_status)" />
                        <span class="ax-chip bg-slate-100 text-slate-600">{{ $staffMember->roleLabel() }}</span>
                        @unless($staffMember->is_active)
                            <span class="ax-chip bg-rose-50 text-rose-700 ring-1 ring-inset ring-rose-200">Sign-in disabled</span>
                        @endunless
                    </div>
                </div>

                <div class="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <div class="rounded-xl border border-slate-200 bg-slate-50/60 p-3.5">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Employee ID</p>
                        <p class="mt-1 font-mono text-sm font-bold text-slate-800">{{ $staffMember->employee_id }}</p>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-slate-50/60 p-3.5">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Email</p>
                        <a href="mailto:{{ $staffMember->email }}" class="mt-1 block truncate text-sm font-semibold text-brand-700 hover:text-brand-800">{{ $staffMember->email }}</a>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-slate-50/60 p-3.5">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Phone</p>
                        <p class="mt-1 text-sm font-semibold text-slate-800">{{ $staffMember->phone ?: 'Not provided' }}</p>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-slate-50/60 p-3.5">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Employment type</p>
                        <p class="mt-1 text-sm font-semibold text-slate-800">{{ \App\Support\Axcess::label('employment_type', $staffMember->employment_type) }}</p>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-slate-50/60 p-3.5">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Hire date</p>
                        <p class="mt-1 text-sm font-semibold text-slate-800">{{ $staffMember->hire_date?->format('M j, Y') ?? 'Not recorded' }}</p>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-slate-50/60 p-3.5">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Weekly maximum</p>
                        <p class="mt-1 text-sm font-semibold text-slate-800">{{ rtrim(rtrim(number_format((float) $staffMember->max_weekly_hours, 1), '0'), '.') }} hours</p>
                    </div>
                </div>
            </div>
        </section>

        <div class="space-y-5">
            <section class="ax-card p-5">
                <h2 class="text-sm font-black text-slate-900">Availability</h2>
                <div class="mt-4 space-y-3">
                    <div class="flex items-center justify-between gap-3 rounded-xl border border-slate-200 px-3.5 py-3">
                        <span class="text-xs font-semibold text-slate-600">Standby eligibility</span>
                        @if($staffMember->standby_eligible)
                            <span class="inline-flex items-center gap-1 text-xs font-bold text-emerald-600"><x-icon name="check-circle" class="h-4 w-4" /> Eligible</span>
                        @else
                            <span class="text-xs font-semibold text-slate-400">Not eligible</span>
                        @endif
                    </div>
                    <div class="flex items-center justify-between gap-3 rounded-xl border border-slate-200 px-3.5 py-3">
                        <span class="text-xs font-semibold text-slate-600">Approved leave days</span>
                        <span class="text-sm font-black text-slate-800">{{ rtrim(rtrim(number_format((float) $approvedLeaveDays, 1), '0'), '.') }}</span>
                    </div>
                    <div class="flex items-center justify-between gap-3 rounded-xl border border-slate-200 px-3.5 py-3">
                        <span class="text-xs font-semibold text-slate-600">Upcoming assignments</span>
                        <span class="text-sm font-black text-slate-800">{{ $upcoming->count() }}</span>
                    </div>
                </div>
            </section>

            <section class="rounded-2xl border border-rose-200 bg-white p-5">
                <h2 class="text-sm font-black text-slate-900">Account access</h2>
                <p class="mt-1 text-xs leading-5 text-slate-500">Deactivating an account disables sign-in but preserves historical schedules, leave, and exports.</p>
                @can('delete', $staffMember)
                    <form method="POST" action="{{ route('staff.destroy', $staffMember) }}" class="mt-4" data-confirm="Deactivate {{ $staffMember->full_name }}? Their historical records will be preserved.">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="ax-btn-danger ax-btn-sm w-full" @disabled(! $staffMember->is_active)>
                            <x-icon name="shield" class="h-4 w-4" />
                            {{ $staffMember->is_active ? 'Deactivate staff account' : 'Account already inactive' }}
                        </button>
                    </form>
                @else
                    <p class="mt-4 rounded-xl bg-slate-50 px-3.5 py-3 text-[11px] text-slate-500">You do not have permission to deactivate this account.</p>
                @endcan
            </section>
        </div>
    </div>

    <section class="ax-card mt-6 overflow-hidden">
        <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-5 py-4">
            <div>
                <h2 class="text-sm font-black text-slate-900">Upcoming shifts</h2>
                <p class="mt-0.5 text-xs text-slate-500">Published assignments including standby and replacements</p>
            </div>
            @if(auth()->user()->canManageOperations())
                <a href="{{ route('schedule.create') }}" class="text-xs font-bold text-brand-700 hover:text-brand-800">Add shift</a>
            @endif
        </div>

        @if($upcoming->isEmpty())
            <div class="p-5">
                <x-empty-state title="No upcoming assignments" message="This staff member has no published shifts or standby duties scheduled." icon="calendar" />
            </div>
        @else
            <div class="ax-scrollbar overflow-x-auto">
                <table class="ax-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Shift</th>
                            <th>Department</th>
                            <th>Breaks</th>
                            <th>Hours</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($upcoming as $assignment)
                            <tr>
                                <td>
                                    <p class="text-xs font-bold text-slate-800">{{ $assignment->schedule_date->format('D, M j, Y') }}</p>
                                    <p class="text-[11px] text-slate-400">{{ $assignment->start_at->format('H:i') }}–{{ $assignment->end_at->format('H:i') }}{{ $assignment->is_overnight ? ' +1' : '' }}</p>
                                </td>
                                <td>
                                    <span class="inline-flex items-center gap-2 text-xs font-semibold text-slate-700">
                                        <span class="h-2 w-2 rounded-full" style="background: {{ $assignment->shiftType?->color ?? '#0f766e' }}"></span>
                                        {{ $assignment->shiftType?->name }}
                                    </span>
                                    @if($assignment->is_standby)<span class="ml-1 text-[9px] font-bold uppercase text-amber-600">standby</span>@endif
                                </td>
                                <td><span class="text-xs text-slate-500">{{ $assignment->department?->name }}</span></td>
                                <td><span class="text-xs text-slate-500">{{ $assignment->break_count }} · {{ number_format($assignment->break_hours, 1) }}h</span></td>
                                <td><span class="text-xs font-semibold text-slate-700">{{ number_format($assignment->net_hours, 1) }}h net</span></td>
                                <td><x-status-badge :status="$assignment->status" :label="$assignment->status_label" /></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <div class="mt-6 grid gap-5 xl:grid-cols-2">
        <section class="ax-card overflow-hidden">
            <div class="border-b border-slate-100 px-5 py-4">
                <h2 class="text-sm font-black text-slate-900">Leave history</h2>
                <p class="mt-0.5 text-xs text-slate-500">Most recent leave requests</p>
            </div>
            @if($leaves->isEmpty())
                <p class="px-5 py-8 text-center text-xs text-slate-400">No leave requests recorded.</p>
            @else
                <div class="divide-y divide-slate-100">
                    @foreach($leaves as $leave)
                        <a href="{{ route('leave.show', $leave) }}" class="flex items-center justify-between gap-3 px-5 py-3.5 transition hover:bg-slate-50">
                            <div>
                                <p class="text-xs font-bold text-slate-800">{{ $leave->leave_type_label }}</p>
                                <p class="text-[11px] text-slate-500">{{ $leave->date_range_label }} · {{ $leave->duration_label }}</p>
                            </div>
                            <x-status-badge :status="$leave->status" :label="$leave->status_label" />
                        </a>
                    @endforeach
                </div>
            @endif
        </section>

        <section class="ax-card overflow-hidden">
            <div class="border-b border-slate-100 px-5 py-4">
                <h2 class="text-sm font-black text-slate-900">Absences &amp; off days</h2>
                <p class="mt-0.5 text-xs text-slate-500">Recorded exceptions and upcoming rest days</p>
            </div>
            <div class="divide-y divide-slate-100">
                @forelse($absences as $absence)
                    <div class="flex items-center justify-between gap-3 px-5 py-3">
                        <div>
                            <p class="text-xs font-bold text-slate-800">{{ $absence->type_label }}</p>
                            <p class="text-[11px] text-slate-500">{{ $absence->absence_date->format('D, M j, Y') }}@if($absence->notes) · {{ \Illuminate\Support\Str::limit($absence->notes, 60) }}@endif</p>
                        </div>
                        <span class="ax-chip bg-rose-50 text-rose-700 ring-1 ring-inset ring-rose-200">Absence</span>
                    </div>
                @empty
                    @if($offDays->isEmpty())
                        <p class="px-5 py-8 text-center text-xs text-slate-400">No absences or off days recorded this month.</p>
                    @endif
                @endforelse
                @foreach($offDays as $offDay)
                    <div class="flex items-center justify-between gap-3 px-5 py-3">
                        <div>
                            <p class="text-xs font-bold text-slate-800">{{ $offDay->type_label }}</p>
                            <p class="text-[11px] text-slate-500">{{ $offDay->date->format('D, M j, Y') }}</p>
                        </div>
                        <span class="ax-chip bg-slate-100 text-slate-600">Off day</span>
                    </div>
                @endforeach
            </div>
        </section>
    </div>
@endsection
