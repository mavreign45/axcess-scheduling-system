@extends('layouts.app')

@section('title', $isEdit ? 'Edit staff profile' : 'Add staff member')
@section('eyebrow', $isEdit ? $staffMember->full_name : 'People & access')
@section('subtitle', $isEdit ? 'Update profile, employment, and access details.' : 'Create a profile and sign-in account for a team member.')

@section('actions')
    <a href="{{ $isEdit ? route('staff.show', $staffMember) : route('staff.index') }}" class="ax-btn-secondary ax-btn-sm">
        <x-icon name="chevron-left" class="h-4 w-4" />
        Back
    </a>
@endsection

@section('content')
    <div class="mx-auto max-w-4xl">
        <form method="POST" action="{{ $isEdit ? route('staff.update', $staffMember) : route('staff.store') }}" class="space-y-5">
            @csrf
            @if($isEdit)
                @method('PUT')
            @endif

            <section class="ax-card p-5 sm:p-6">
                <div class="flex items-start gap-3 border-b border-slate-100 pb-4">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-700">
                        <x-icon name="user" class="h-5 w-5" />
                    </span>
                    <div>
                        <h2 class="text-base font-black text-slate-900">Personal details</h2>
                        <p class="mt-0.5 text-xs text-slate-500">The employee ID is used throughout schedules and exports.</p>
                    </div>
                </div>

                <div class="mt-5 grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="employee_id" class="ax-label">Employee ID</label>
                        <input id="employee_id" name="employee_id" type="text" value="{{ old('employee_id', $staffMember->employee_id) }}" class="ax-input font-mono uppercase" required maxlength="40" placeholder="EMP-1015">
                    </div>
                    <div>
                        <label for="email" class="ax-label">Work email</label>
                        <input id="email" name="email" type="email" value="{{ old('email', $staffMember->email) }}" class="ax-input" required maxlength="255" placeholder="name@company.com">
                    </div>
                    <div>
                        <label for="first_name" class="ax-label">First name</label>
                        <input id="first_name" name="first_name" type="text" value="{{ old('first_name', $staffMember->first_name) }}" class="ax-input" required maxlength="100">
                    </div>
                    <div>
                        <label for="last_name" class="ax-label">Last name</label>
                        <input id="last_name" name="last_name" type="text" value="{{ old('last_name', $staffMember->last_name) }}" class="ax-input" required maxlength="100">
                    </div>
                    <div>
                        <label for="phone" class="ax-label">Phone</label>
                        <input id="phone" name="phone" type="text" value="{{ old('phone', $staffMember->phone) }}" class="ax-input" maxlength="40" placeholder="+1 555 010 2030">
                    </div>
                    <div>
                        <label for="password" class="ax-label">{{ $isEdit ? 'New password' : 'Initial password' }}</label>
                        <input id="password" name="password" type="password" class="ax-input" @required(! $isEdit) minlength="8" autocomplete="new-password" placeholder="{{ $isEdit ? 'Leave blank to keep the current password' : 'At least 8 characters' }}">
                        <p class="mt-1 text-[11px] text-slate-500">{{ $isEdit ? 'Only enter a value if you want to reset this account’s password.' : 'Share this securely with the staff member.' }}</p>
                    </div>
                </div>
            </section>

            <section class="ax-card p-5 sm:p-6">
                <div class="flex items-start gap-3 border-b border-slate-100 pb-4">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-sky-50 text-sky-700">
                        <x-icon name="briefcase" class="h-5 w-5" />
                    </span>
                    <div>
                        <h2 class="text-base font-black text-slate-900">Employment &amp; scheduling</h2>
                        <p class="mt-0.5 text-xs text-slate-500">These settings control scheduling eligibility and reporting.</p>
                    </div>
                </div>

                <div class="mt-5 grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="department_id" class="ax-label">Department</label>
                        <select id="department_id" name="department_id" class="ax-input" required @disabled(auth()->user()->isManager())>
                            <option value="">Select a department</option>
                            @foreach($departments as $department)
                                <option value="{{ $department->id }}" @selected((int) old('department_id', $staffMember->department_id) === $department->id)>{{ $department->name }}</option>
                            @endforeach
                        </select>
                        @if(auth()->user()->isManager())
                            <input type="hidden" name="department_id" value="{{ auth()->user()->department_id }}">
                        @endif
                    </div>
                    <div>
                        <label for="job_position_id" class="ax-label">Job position</label>
                        <select id="job_position_id" name="job_position_id" class="ax-input">
                            <option value="">No position assigned</option>
                            @foreach($positions as $position)
                                <option value="{{ $position->id }}" @selected((int) old('job_position_id', $staffMember->job_position_id) === $position->id)>
                                    {{ $position->title }}{{ $position->department ? ' · '.$position->department->name : '' }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="employment_status" class="ax-label">Employment status</label>
                        <select id="employment_status" name="employment_status" class="ax-input" required>
                            @foreach(\App\Support\Axcess::EMPLOYMENT_STATUSES as $value => $label)
                                <option value="{{ $value }}" @selected(old('employment_status', $staffMember->employment_status) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="employment_type" class="ax-label">Employment type</label>
                        <select id="employment_type" name="employment_type" class="ax-input" required>
                            @foreach(\App\Support\Axcess::EMPLOYMENT_TYPES as $value => $label)
                                <option value="{{ $value }}" @selected(old('employment_type', $staffMember->employment_type) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="hire_date" class="ax-label">Hire date</label>
                        <input id="hire_date" name="hire_date" type="date" value="{{ old('hire_date', $staffMember->hire_date?->format('Y-m-d')) }}" class="ax-input">
                    </div>
                    <div>
                        <label for="max_weekly_hours" class="ax-label">Maximum weekly hours</label>
                        <input id="max_weekly_hours" name="max_weekly_hours" type="number" step="0.25" min="1" max="80" value="{{ old('max_weekly_hours', $staffMember->max_weekly_hours ?? 40) }}" class="ax-input" required>
                    </div>
                    @if($canSetRole)
                        <div>
                            <label for="role" class="ax-label">Access role</label>
                            <select id="role" name="role" class="ax-input" required>
                                @foreach(\App\Support\Axcess::ROLES as $value => $label)
                                    <option value="{{ $value }}" @selected(old('role', $staffMember->role ?? 'employee') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-[11px] text-slate-500">Administrators see everything. Managers are scoped to their department. Employees see their own records.</p>
                        </div>
                    @else
                        <input type="hidden" name="role" value="employee">
                    @endif
                </div>

                <div class="mt-5 grid gap-3 sm:grid-cols-2">
                    <label class="flex items-start gap-3 rounded-xl border border-slate-200 bg-slate-50 p-4">
                        <input type="checkbox" name="standby_eligible" value="1" class="mt-0.5 h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500" @checked(old('standby_eligible', $staffMember->standby_eligible ?? true))>
                        <span>
                            <span class="block text-sm font-bold text-slate-800">Standby eligible</span>
                            <span class="mt-0.5 block text-[11px] text-slate-500">Can be assigned to on-call standby duties.</span>
                        </span>
                    </label>
                    <label class="flex items-start gap-3 rounded-xl border border-slate-200 bg-slate-50 p-4">
                        <input type="checkbox" name="is_active" value="1" class="mt-0.5 h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500" @checked(old('is_active', $staffMember->is_active ?? true))>
                        <span>
                            <span class="block text-sm font-bold text-slate-800">Active account</span>
                            <span class="mt-0.5 block text-[11px] text-slate-500">Inactive accounts cannot sign in and are excluded from active counts.</span>
                        </span>
                    </label>
                </div>
            </section>

            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-between">
                <a href="{{ $isEdit ? route('staff.show', $staffMember) : route('staff.index') }}" class="ax-btn-secondary">Cancel</a>
                <button type="submit" class="ax-btn-primary">
                    <x-icon name="check" class="h-4 w-4" />
                    {{ $isEdit ? 'Save changes' : 'Create staff account' }}
                </button>
            </div>
        </form>
    </div>
@endsection
