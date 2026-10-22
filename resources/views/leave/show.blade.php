@extends('layouts.app')

@section('title', 'Leave request')
@section('eyebrow', $leave->status === 'pending' ? 'Decision required' : 'Leave record')
@section('subtitle', $leave->user?->full_name.' · '.$leave->date_range_label)

@section('actions')
    <a href="{{ route('leave.index') }}" class="ax-btn-secondary ax-btn-sm">
        <x-icon name="chevron-left" class="h-4 w-4" />
        Back
    </a>
@endsection

@section('content')
    <div class="grid gap-5 xl:grid-cols-[1.45fr_1fr]">
        <section class="ax-card overflow-hidden">
            <div class="flex flex-col gap-4 border-b border-slate-100 p-5 sm:flex-row sm:items-start sm:justify-between sm:p-6">
                <div class="flex items-start gap-4">
                    <span class="ax-avatar h-12 w-12 bg-slate-900 text-sm">{{ $leave->user?->initials }}</span>
                    <div>
                        <h2 class="text-lg font-black tracking-tight text-slate-900">{{ $leave->user?->full_name }}</h2>
                        <p class="mt-0.5 text-xs text-slate-500">{{ $leave->user?->employee_id }} · {{ $leave->user?->department?->name }} · {{ $leave->user?->jobPosition?->title }}</p>
                        <div class="mt-2 flex flex-wrap items-center gap-2">
                            <x-status-badge :status="$leave->status" :label="$leave->status_label" />
                            <span class="ax-chip bg-slate-100 text-slate-600">{{ $leave->leave_type_label }}</span>
                        </div>
                    </div>
                </div>
                @can('cancel', $leave)
                    <form method="POST" action="{{ route('leave.cancel', $leave) }}" data-confirm="Cancel this leave request?">
                        @csrf
                        <button type="submit" class="ax-btn-danger ax-btn-sm">Cancel request</button>
                    </form>
                @endcan
            </div>

            <div class="grid gap-3 p-5 sm:grid-cols-2 sm:p-6">
                <div class="rounded-xl border border-slate-200 bg-slate-50/60 p-4">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Start date</p>
                    <p class="mt-1 text-sm font-bold text-slate-800">{{ $leave->start_date->format('l, M j, Y') }}</p>
                </div>
                <div class="rounded-xl border border-slate-200 bg-slate-50/60 p-4">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">End date</p>
                    <p class="mt-1 text-sm font-bold text-slate-800">{{ $leave->end_date->format('l, M j, Y') }}</p>
                </div>
                <div class="rounded-xl border border-slate-200 bg-slate-50/60 p-4">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Duration</p>
                    <p class="mt-1 text-sm font-bold text-slate-800">{{ $leave->duration_label }}</p>
                </div>
                <div class="rounded-xl border border-slate-200 bg-slate-50/60 p-4">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Submitted</p>
                    <p class="mt-1 text-sm font-bold text-slate-800">{{ $leave->created_at?->format('M j, Y g:i A') }}</p>
                </div>

                <div class="sm:col-span-2">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Reason</p>
                    <p class="mt-1 whitespace-pre-line rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm leading-6 text-slate-700">{{ $leave->reason ?: 'No reason provided.' }}</p>
                </div>

                @if($leave->supporting_information)
                    <div class="sm:col-span-2">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Supporting information</p>
                        <p class="mt-1 whitespace-pre-line rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm leading-6 text-slate-700">{{ $leave->supporting_information }}</p>
                    </div>
                @endif

                @if($leave->rejection_reason)
                    <div class="sm:col-span-2 rounded-xl border border-rose-200 bg-rose-50 p-4">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-rose-500">Rejection reason</p>
                        <p class="mt-1 text-sm leading-6 text-rose-800">{{ $leave->rejection_reason }}</p>
                    </div>
                @endif
            </div>

            @can('approve', $leave)
                <div class="border-t border-slate-200 bg-slate-50/70 p-5 sm:p-6">
                    <h3 class="text-sm font-black text-slate-900">Manager decision</h3>
                    <p class="mt-0.5 text-xs text-slate-500">Approving leave triggers a conflict scan and emails the employee.</p>

                    <div class="mt-4 grid gap-4 lg:grid-cols-[auto_1fr]">
                        <form method="POST" action="{{ route('leave.approve', $leave) }}">
                            @csrf
                            <button type="submit" class="ax-btn-primary w-full">
                                <x-icon name="check" class="h-4 w-4" />
                                Approve request
                            </button>
                        </form>

                        <form method="POST" action="{{ route('leave.reject', $leave) }}" class="flex flex-col gap-2 sm:flex-row">
                            @csrf
                            <input type="text" name="rejection_reason" required maxlength="1000" class="ax-input flex-1" placeholder="Reason for rejection (required)" value="{{ old('rejection_reason') }}">
                            <button type="submit" class="ax-btn-danger shrink-0">
                                <x-icon name="x" class="h-4 w-4" />
                                Reject
                            </button>
                        </form>
                    </div>
                </div>
            @endcan
        </section>

        <div class="space-y-5">
            <section class="ax-card p-5">
                <h2 class="text-sm font-black text-slate-900">Approval status</h2>
                <div class="mt-4 space-y-3 text-xs">
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-slate-500">Status</span>
                        <x-status-badge :status="$leave->status" :label="$leave->status_label" />
                    </div>
                    <div class="flex items-center justify-between gap-3 border-t border-slate-100 pt-3">
                        <span class="text-slate-500">Approver</span>
                        <span class="font-semibold text-slate-700">{{ $leave->approver?->full_name ?? 'Not assigned' }}</span>
                    </div>
                    <div class="flex items-center justify-between gap-3 border-t border-slate-100 pt-3">
                        <span class="text-slate-500">Decision date</span>
                        <span class="font-semibold text-slate-700">{{ $leave->approved_at?->format('M j, Y g:i A') ?? '—' }}</span>
                    </div>
                </div>
            </section>

            <section class="ax-card overflow-hidden">
                <div class="border-b border-slate-100 px-5 py-4">
                    <h2 class="text-sm font-black text-slate-900">Scheduled shifts in this period</h2>
                    <p class="mt-0.5 text-xs text-slate-500">Approved leave overlapping a shift is flagged as a conflict.</p>
                </div>

                @if($conflictingShifts->isEmpty())
                    <p class="px-5 py-8 text-center text-xs text-slate-400">No shifts are scheduled during this leave period.</p>
                @else
                    <div class="divide-y divide-slate-100">
                        @foreach($conflictingShifts as $assignment)
                            <div class="flex items-center justify-between gap-3 px-5 py-3">
                                <div>
                                    <p class="text-xs font-bold text-slate-800">{{ $assignment->schedule_date->format('D, M j') }} · {{ $assignment->shiftType?->name }}</p>
                                    <p class="text-[11px] text-slate-500">{{ $assignment->start_at->format('H:i') }}–{{ $assignment->end_at->format('H:i') }} · {{ $assignment->department?->name }}</p>
                                </div>
                                <x-status-badge :status="$assignment->status" :label="$assignment->status_label" />
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>
        </div>
    </div>
@endsection
