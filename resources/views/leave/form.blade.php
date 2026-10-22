@extends('layouts.app')

@section('title', 'Request leave')
@section('eyebrow', 'Time away')
@section('subtitle', 'Submit a leave request for approval. Overlapping requests are prevented automatically.')

@section('actions')
    <a href="{{ route('leave.index') }}" class="ax-btn-secondary ax-btn-sm">
        <x-icon name="chevron-left" class="h-4 w-4" />
        Back
    </a>
@endsection

@section('content')
    <div class="mx-auto max-w-3xl">
        <form method="POST" action="{{ route('leave.store') }}" class="space-y-5">
            @csrf

            <section class="ax-card p-5 sm:p-6">
                <div class="flex items-start gap-3 border-b border-slate-100 pb-4">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-700">
                        <x-icon name="clock" class="h-5 w-5" />
                    </span>
                    <div>
                        <h2 class="text-base font-black text-slate-900">Leave details</h2>
                        <p class="mt-0.5 text-xs text-slate-500">Requests are submitted as pending and require a manager decision.</p>
                    </div>
                </div>

                <div class="mt-5 grid gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label for="user_id" class="ax-label">Employee</label>
                        @if(auth()->user()->isEmployee())
                            <input type="hidden" name="user_id" value="{{ auth()->id() }}">
                            <div class="ax-input flex items-center justify-between bg-slate-50 text-slate-600">
                                <span>{{ auth()->user()->full_name }} · {{ auth()->user()->employee_id }}</span>
                                <x-icon name="user" class="h-4 w-4 text-slate-400" />
                            </div>
                        @else
                            <select id="user_id" name="user_id" class="ax-input" required>
                                <option value="">Select an employee</option>
                                @foreach($employees as $employee)
                                    <option value="{{ $employee->id }}" @selected((int) old('user_id', $leave->user_id) === $employee->id)>{{ $employee->full_name }} · {{ $employee->employee_id }}@if($employee->department) · {{ $employee->department->name }}@endif</option>
                                @endforeach
                            </select>
                        @endif
                    </div>

                    <div>
                        <label for="leave_type" class="ax-label">Leave type</label>
                        <select id="leave_type" name="leave_type" class="ax-input" required>
                            @foreach(\App\Support\Axcess::LEAVE_TYPES as $value => $label)
                                <option value="{{ $value }}" @selected(old('leave_type', $leave->leave_type) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label for="start_date" class="ax-label">Start date</label>
                            <input id="start_date" type="date" name="start_date" class="ax-input" required value="{{ old('start_date', $leave->start_date?->format('Y-m-d')) }}">
                        </div>
                        <div>
                            <label for="end_date" class="ax-label">End date</label>
                            <input id="end_date" type="date" name="end_date" class="ax-input" required value="{{ old('end_date', $leave->end_date?->format('Y-m-d')) }}">
                        </div>
                    </div>

                    <div class="sm:col-span-2">
                        <label for="reason" class="ax-label">Reason</label>
                        <textarea id="reason" name="reason" rows="4" class="ax-input" maxlength="2000" placeholder="Provide context for your manager.">{{ old('reason') }}</textarea>
                        <p class="mt-1 text-[11px] text-slate-500">This is treated as confidential and is only shown to authorised managers.</p>
                    </div>

                    <div class="sm:col-span-2">
                        <label for="supporting_information" class="ax-label">Supporting information</label>
                        <textarea id="supporting_information" name="supporting_information" rows="3" class="ax-input" maxlength="2000" placeholder="Optional reference details, certificate number, or handover notes.">{{ old('supporting_information') }}</textarea>
                    </div>
                </div>
            </section>

            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-between">
                <a href="{{ route('leave.index') }}" class="ax-btn-secondary">Cancel</a>
                <button type="submit" class="ax-btn-primary">
                    <x-icon name="check" class="h-4 w-4" />
                    Submit leave request
                </button>
            </div>
        </form>
    </div>
@endsection
