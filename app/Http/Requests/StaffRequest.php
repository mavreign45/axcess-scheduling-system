<?php

namespace App\Http\Requests;

use App\Models\JobPosition;
use App\Models\User;
use App\Support\Axcess;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        $staff = $this->route('staff');

        if ($staff instanceof User) {
            return $this->user()->can('update', $staff);
        }

        return $this->user()->can('create', User::class);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'employee_id' => $this->filled('employee_id') ? strtoupper(trim((string) $this->input('employee_id'))) : null,
            'standby_eligible' => $this->boolean('standby_eligible'),
            'is_active' => $this->boolean('is_active'),
        ]);

        if (! $this->filled('password')) {
            $this->merge(['password' => null]);
        }

        if (! $this->filled('employment_type')) {
            $this->merge(['employment_type' => 'full_time']);
        }

        if (! $this->filled('employment_status')) {
            $this->merge(['employment_status' => 'active']);
        }

        if (! $this->filled('max_weekly_hours')) {
            $this->merge(['max_weekly_hours' => 40]);
        }

        if ($this->user()?->isManager()) {
            $this->merge(['department_id' => $this->user()->department_id]);
        }
    }

    public function rules(): array
    {
        $staff = $this->route('staff');
        $staffId = $staff instanceof User ? $staff->id : null;
        $allowedRoles = $this->user()?->isAdmin()
            ? array_keys(Axcess::ROLES)
            : ['employee'];

        return [
            'employee_id' => ['required', 'string', 'max:40', Rule::unique('users', 'employee_id')->ignore($staffId)],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($staffId)],
            'phone' => ['nullable', 'string', 'max:40'],
            'password' => [$staffId ? 'nullable' : 'required', 'string', 'min:8', 'max:255'],
            'department_id' => ['required', 'integer', 'exists:departments,id'],
            'job_position_id' => ['nullable', 'integer', 'exists:job_positions,id'],
            'role' => ['required', Rule::in($allowedRoles)],
            'employment_status' => ['required', Rule::in(array_keys(Axcess::EMPLOYMENT_STATUSES))],
            'employment_type' => ['required', Rule::in(array_keys(Axcess::EMPLOYMENT_TYPES))],
            'hire_date' => ['nullable', 'date'],
            'max_weekly_hours' => ['required', 'numeric', 'min:1', 'max:80'],
            'standby_eligible' => ['boolean'],
            'is_active' => ['boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            if ($this->user()?->isManager() && (int) $this->input('department_id') !== (int) $this->user()->department_id) {
                $validator->errors()->add('department_id', 'You can only manage staff in your own department.');
            }

            if ($this->user()?->isManager() && $this->input('role') !== 'employee') {
                $validator->errors()->add('role', 'Managers can only create employee accounts.');
            }

            if ($this->filled('job_position_id')) {
                $position = JobPosition::find($this->input('job_position_id'));

                if ($position && (int) $position->department_id !== (int) $this->input('department_id')) {
                    $validator->errors()->add('job_position_id', 'The selected job position does not belong to this department.');
                }
            }
        });
    }
}
