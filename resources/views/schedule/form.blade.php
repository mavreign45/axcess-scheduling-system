@extends('layouts.app')

@php
    $standbyMode = $standbyMode ?? false;
    $isStandby = (bool) old('is_standby', $assignment->is_standby);
    $breakRows = old('breaks');

    if ($breakRows === null) {
        $breakRows = $assignment->breaks->map(fn ($break) => [
            'label' => $break->label,
            'start_at' => $break->start_at?->format('Y-m-d\TH:i'),
            'end_at' => $break->end_at?->format('Y-m-d\TH:i'),
        ])->all();
    }

    if ($breakRows === []) {
        $breakRows = [['label' => 'Meal break', 'start_at' => '', 'end_at' => '']];
    }
@endphp

@section('title', $isEdit ? 'Edit '.($standbyMode ? 'standby duty' : 'shift') : ($standbyMode ? 'Schedule standby' : 'Add shift'))
@section('eyebrow', $isEdit ? 'Schedule maintenance' : 'New assignment')
@section('subtitle', $isEdit ? 'Update timing, breaks, status, or replacement cover.' : 'Create a shift for an employee or leave it open for cover.')

@section('actions')
    <a href="{{ $standbyMode ? route('standby.index') : route('schedule.index') }}" class="ax-btn-secondary ax-btn-sm">
        <x-icon name="chevron-left" class="h-4 w-4" />
        Back
    </a>
@endsection

@section('content')
    <div class="mx-auto max-w-4xl">
        <form method="POST" action="{{ $isEdit ? route('schedule.update', $assignment) : ($standbyMode ? route('standby.store') : route('schedule.store')) }}" class="space-y-5" data-shift-form data-is-edit="{{ $isEdit ? 'true' : 'false' }}">
            @csrf
            @if($isEdit)
                @method('PUT')
            @endif

            <section class="ax-card p-5 sm:p-6">
                <div class="flex items-start gap-3 border-b border-slate-100 pb-4">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-700">
                        <x-icon name="calendar" class="h-5 w-5" />
                    </span>
                    <div>
                        <h2 class="text-base font-black text-slate-900">Shift details</h2>
                        <p class="mt-0.5 text-xs text-slate-500">Times are local to the organisation. Overnight shifts roll into the next date automatically.</p>
                    </div>
                </div>

                <div class="mt-5 grid gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label for="user_id" class="ax-label">Employee</label>
                        <select id="user_id" name="user_id" class="ax-input">
                            <option value="">Unassigned — open shift</option>
                            @foreach($employees as $employee)
                                <option value="{{ $employee->id }}" @selected((int) old('user_id', $assignment->user_id) === $employee->id)>
                                    {{ $employee->full_name }} · {{ $employee->employee_id }}{{ $employee->department ? ' · '.$employee->department->name : '' }}
                                </option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-[11px] text-slate-500">Leave unassigned to create an open shift that needs cover.</p>
                    </div>

                    <div>
                        <label for="department_id" class="ax-label">Department</label>
                        @if($canChooseDepartment)
                            <select id="department_id" name="department_id" class="ax-input">
                                <option value="">Select a department</option>
                                @foreach($departments as $department)
                                    <option value="{{ $department->id }}" @selected((int) old('department_id', $assignment->department_id) === $department->id)>{{ $department->name }}</option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-[11px] text-slate-500">An assigned employee’s department takes precedence.</p>
                        @else
                            <input type="hidden" name="department_id" value="{{ old('department_id', $assignment->department_id ?? auth()->user()->department_id) }}">
                            <div class="ax-input flex items-center justify-between bg-slate-50 text-slate-500">
                                <span>{{ $departments->firstWhere('id', (int) old('department_id', $assignment->department_id ?? auth()->user()->department_id))?->name ?? 'Your department' }}</span>
                                <x-icon name="shield" class="h-4 w-4 text-slate-400" />
                            </div>
                            <p class="mt-1 text-[11px] text-slate-500">Department scope is enforced automatically.</p>
                        @endif
                    </div>

                    <div>
                        <label for="shift_type_id" class="ax-label">Shift type</label>
                        <select id="shift_type_id" name="shift_type_id" class="ax-input" required data-shift-type>
                            <option value="">Select a shift type</option>
                            @foreach($shiftTypes as $shiftType)
                                <option value="{{ $shiftType->id }}"
                                    data-start="{{ $shiftType->start_time }}"
                                    data-end="{{ $shiftType->end_time }}"
                                    @selected((int) old('shift_type_id', $assignment->shift_type_id) === $shiftType->id)>
                                    {{ $shiftType->name }} · {{ $shiftType->time_range }}{{ $shiftType->is_overnight ? ' (overnight)' : '' }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="schedule_date" class="ax-label">Schedule date</label>
                        <input id="schedule_date" type="date" name="schedule_date" class="ax-input" required value="{{ old('schedule_date', $assignment->schedule_date?->format('Y-m-d') ?? now()->toDateString()) }}" data-schedule-date>
                    </div>

                    <div>
                        <label for="status" class="ax-label">Assignment status</label>
                        <select id="status" name="status" class="ax-input">
                            @foreach(\App\Support\Axcess::ASSIGNMENT_STATUSES as $value => $label)
                                <option value="{{ $value }}" @selected(old('status', $assignment->status ?? 'published') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="start_at" class="ax-label">Shift start</label>
                        <input id="start_at" type="datetime-local" name="start_at" class="ax-input" required value="{{ old('start_at', $assignment->start_at?->format('Y-m-d\TH:i')) }}" data-start-at>
                    </div>

                    <div>
                        <label for="end_at" class="ax-label">Shift end</label>
                        <input id="end_at" type="datetime-local" name="end_at" class="ax-input" required value="{{ old('end_at', $assignment->end_at?->format('Y-m-d\TH:i')) }}" data-end-at>
                        <p class="mt-1 text-[11px] text-slate-500">If the end is earlier than the start, Axcess treats it as overnight (+1 day).</p>
                    </div>

                    <div class="sm:col-span-2">
                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                            <label class="flex items-start gap-3">
                                <input type="checkbox" name="is_standby" value="1" class="mt-0.5 h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500" @checked($isStandby)>
                                <span>
                                    <span class="block text-sm font-bold text-slate-800">Standby duty</span>
                                    <span class="mt-0.5 block text-[11px] leading-5 text-slate-500">Standby duties are included in the schedule and standby reporting, but not in net working hours.</span>
                                </span>
                            </label>

                            <div class="mt-4">
                                <label for="standby_status" class="ax-label">Standby status</label>
                                <select id="standby_status" name="standby_status" class="ax-input">
                                    @foreach(\App\Support\Axcess::STANDBY_STATUSES as $value => $label)
                                        @if($value !== 'not_applicable')
                                            <option value="{{ $value }}" @selected(old('standby_status', $assignment->standby_status ?? 'available') === $value)>{{ $label }}</option>
                                        @endif
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="sm:col-span-2">
                        <div class="flex items-center justify-between">
                            <label for="notes" class="ax-label">Manager notes</label>
                            @if(auth()->user()->isAdmin() || auth()->user()->isManager())
                                <span class="mb-1.5 inline-flex items-center gap-1 text-[10px] font-semibold text-slate-400">
                                    <x-icon name="shield" class="h-3 w-3" />
                                    Restricted in exports
                                </span>
                            @endif
                        </div>
                        <textarea id="notes" name="notes" rows="3" class="ax-input" placeholder="Handover instructions, coverage notes, or context for the team.">{{ old('notes', $assignment->notes) }}</textarea>
                    </div>
                </div>

                <div class="mt-4 flex flex-wrap items-center gap-2 border-t border-slate-100 pt-4">
                    <button type="button" class="ax-btn-secondary ax-btn-sm" data-apply-shift-times>
                        <x-icon name="refresh" class="h-4 w-4" />
                        Apply shift type times
                    </button>
                    <p class="text-[11px] text-slate-400">Changing shift type recalculates start and end times for new assignments.</p>
                </div>
            </section>

            <section class="ax-card p-5 sm:p-6" data-breaks>
                <div class="flex flex-col gap-3 border-b border-slate-100 pb-4 sm:flex-row sm:items-start sm:justify-between">
                    <div class="flex items-start gap-3">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-amber-50 text-amber-700">
                            <x-icon name="clock" class="h-5 w-5" />
                        </span>
                        <div>
                            <h2 class="text-base font-black text-slate-900">Breaks</h2>
                            <p class="mt-0.5 text-xs text-slate-500">Add every break period explicitly. Axcess preserves all breaks in exports and reporting.</p>
                        </div>
                    </div>
                    <button type="button" class="ax-btn-secondary ax-btn-sm" data-add-break>
                        <x-icon name="plus" class="h-4 w-4" />
                        Add break
                    </button>
                </div>

                <div class="mt-4 space-y-3">
                    @foreach($breakRows as $index => $break)
                        <div class="rounded-xl border border-slate-200 bg-slate-50/60 p-3.5" data-break-row>
                            <div class="grid gap-3 sm:grid-cols-[1fr_1fr_1fr_auto] sm:items-end">
                                <div>
                                    <label class="ax-label" for="break-label-{{ $index }}">Label</label>
                                    <input id="break-label-{{ $index }}" type="text" name="breaks[{{ $index }}][label]" value="{{ $break['label'] ?? '' }}" class="ax-input" maxlength="80" placeholder="Meal break">
                                </div>
                                <div>
                                    <label class="ax-label" for="break-start-{{ $index }}">Start</label>
                                    <input id="break-start-{{ $index }}" type="datetime-local" name="breaks[{{ $index }}][start_at]" value="{{ $break['start_at'] ?? '' }}" class="ax-input">
                                </div>
                                <div>
                                    <label class="ax-label" for="break-end-{{ $index }}">End</label>
                                    <input id="break-end-{{ $index }}" type="datetime-local" name="breaks[{{ $index }}][end_at]" value="{{ $break['end_at'] ?? '' }}" class="ax-input">
                                </div>
                                <button type="button" class="rounded-lg border border-rose-200 bg-white p-2.5 text-rose-500 transition hover:bg-rose-50" data-remove-break title="Remove break" aria-label="Remove break">
                                    <x-icon name="trash" class="h-4 w-4" />
                                </button>
                            </div>
                        </div>
                    @endforeach
                </div>

                <template id="break-template">
                    <div class="rounded-xl border border-slate-200 bg-slate-50/60 p-3.5" data-break-row>
                        <div class="grid gap-3 sm:grid-cols-[1fr_1fr_1fr_auto] sm:items-end">
                            <div>
                                <label class="ax-label">Label</label>
                                <input type="text" name="breaks[__INDEX__][label]" class="ax-input" maxlength="80" placeholder="Meal break">
                            </div>
                            <div>
                                <label class="ax-label">Start</label>
                                <input type="datetime-local" name="breaks[__INDEX__][start_at]" class="ax-input">
                            </div>
                            <div>
                                <label class="ax-label">End</label>
                                <input type="datetime-local" name="breaks[__INDEX__][end_at]" class="ax-input">
                            </div>
                            <button type="button" class="rounded-lg border border-rose-200 bg-white p-2.5 text-rose-500 transition hover:bg-rose-50" data-remove-break title="Remove break" aria-label="Remove break">
                                <x-icon name="trash" class="h-4 w-4" />
                            </button>
                        </div>
                    </div>
                </template>
            </section>

            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-between">
                <a href="{{ $standbyMode ? route('standby.index') : route('schedule.index') }}" class="ax-btn-secondary">Cancel</a>
                <button type="submit" class="ax-btn-primary">
                    <x-icon name="check" class="h-4 w-4" />
                    {{ $isEdit ? 'Save changes' : 'Create assignment' }}
                </button>
            </div>
        </form>

        @if($isEdit && $replacementCandidates->isNotEmpty())
            <section class="ax-card mt-6 p-5 sm:p-6">
                <div class="flex items-start gap-3 border-b border-slate-100 pb-4">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-sky-50 text-sky-700">
                        <x-icon name="swap" class="h-5 w-5" />
                    </span>
                    <div>
                        <h2 class="text-base font-black text-slate-900">Replacement cover</h2>
                        <p class="mt-0.5 text-xs text-slate-500">Record a replacement without losing the original assignment or its history.</p>
                    </div>
                </div>

                @if($assignment->replacementUser)
                    <div class="mt-4 rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-xs text-sky-800">
                        <span class="font-bold">Currently covered by {{ $assignment->replacementUser->full_name }}</span>
                        @if($assignment->replacement_reason)
                            <span class="block mt-1 text-sky-700">{{ $assignment->replacement_reason }}</span>
                        @endif
                    </div>
                @endif

                <form method="POST" action="{{ route('schedule.replace', $assignment) }}" class="mt-4 grid gap-3 sm:grid-cols-[1fr_1fr_auto] sm:items-end">
                    @csrf
                    <div>
                        <label class="ax-label" for="replacement_user_id">Replacement employee</label>
                        <select id="replacement_user_id" name="replacement_user_id" class="ax-input" required>
                            <option value="">Select an employee</option>
                            @foreach($replacementCandidates as $candidate)
                                <option value="{{ $candidate->id }}">{{ $candidate->full_name }} · {{ $candidate->employee_id }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="ax-label" for="replacement_reason">Reason</label>
                        <input id="replacement_reason" name="replacement_reason" type="text" class="ax-input" maxlength="1000" placeholder="Unavailability, sickness, or cover request">
                    </div>
                    <button type="submit" class="ax-btn-dark">
                        <x-icon name="swap" class="h-4 w-4" />
                        Record
                    </button>
                </form>
            </section>
        @endif

        @if($isEdit)
            <section class="mt-6 rounded-2xl border border-rose-200 bg-white p-5">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="text-sm font-black text-rose-700">Remove assignment</h2>
                        <p class="mt-1 text-xs text-slate-500">This permanently deletes the shift and its breaks. Historical leave and replacement records are not removed.</p>
                    </div>
                    <form method="POST" action="{{ route('schedule.destroy', $assignment) }}" data-confirm="Remove this assignment? This action cannot be undone.">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="ax-btn-danger ax-btn-sm">
                            <x-icon name="trash" class="h-4 w-4" />
                            Delete shift
                        </button>
                    </form>
                </div>
            </section>
        @endif
    </div>
@endsection
