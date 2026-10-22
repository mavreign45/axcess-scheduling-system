<?php

namespace App\Http\Requests;

use App\Models\LeaveRequest;
use App\Models\User;
use App\Support\Axcess;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreLeaveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', LeaveRequest::class);
    }

    protected function prepareForValidation(): void
    {
        if ($this->user()->isEmployee()) {
            $this->merge(['user_id' => $this->user()->id]);
        }

        if (! $this->filled('user_id')) {
            $this->merge(['user_id' => $this->user()->id]);
        }
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'leave_type' => ['required', Rule::in(array_keys(Axcess::LEAVE_TYPES))],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['nullable', 'string', 'max:2000'],
            'supporting_information' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $target = User::find($this->input('user_id'));

            if (! $target) {
                return;
            }

            if ($this->user()->isManager() && (int) $target->department_id !== (int) $this->user()->department_id) {
                $validator->errors()->add('user_id', 'You can only record leave for your own department.');
            }

            $overlap = LeaveRequest::query()
                ->where('user_id', $target->id)
                ->whereIn('status', ['pending', 'approved'])
                ->whereDate('start_date', '<=', $this->input('end_date'))
                ->whereDate('end_date', '>=', $this->input('start_date'))
                ->exists();

            if ($overlap) {
                $validator->errors()->add('start_date', 'This employee already has a pending or approved leave request overlapping these dates.');
            }
        });
    }
}
