<?php

namespace App\Http\Requests;

use App\Models\ScheduleAssignment;
use App\Support\Axcess;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $assignment = $this->route('assignment');

        if ($assignment instanceof ScheduleAssignment) {
            return $this->user()->can('update', $assignment);
        }

        return $this->user()->can('create', ScheduleAssignment::class);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'user_id' => $this->filled('user_id') ? $this->input('user_id') : null,
            'department_id' => $this->filled('department_id') ? $this->input('department_id') : null,
            'is_standby' => $this->boolean('is_standby'),
        ]);

        if (! $this->filled('status')) {
            $this->merge(['status' => 'published']);
        }

        if (! $this->filled('standby_status')) {
            $this->merge(['standby_status' => $this->boolean('is_standby') ? 'available' : 'not_applicable']);
        }
    }

    public function rules(): array
    {
        return [
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'shift_type_id' => ['required', 'integer', 'exists:shift_types,id'],
            'schedule_date' => ['required', 'date'],
            'start_at' => ['required', 'date'],
            'end_at' => ['required', 'date'],
            'status' => ['required', Rule::in(array_keys(Axcess::ASSIGNMENT_STATUSES))],
            'is_standby' => ['boolean'],
            'standby_status' => ['required', Rule::in(array_keys(Axcess::STANDBY_STATUSES))],
            'notes' => ['nullable', 'string', 'max:2000'],
            'breaks' => ['nullable', 'array', 'max:6'],
            'breaks.*.label' => ['nullable', 'string', 'max:80'],
            'breaks.*.start_at' => ['nullable', 'required_with:breaks.*.end_at', 'date'],
            'breaks.*.end_at' => ['nullable', 'required_with:breaks.*.start_at', 'date'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            if ($this->filled('start_at') && $this->filled('end_at')) {
                $start = Carbon::parse($this->input('start_at'));
                $end = Carbon::parse($this->input('end_at'));

                if ($end->lessThanOrEqualTo($start)) {
                    $end->addDay();
                }

                if ($start->diffInMinutes($end) > (float) config('axcess.scheduling.max_shift_hours', 24) * 60) {
                    $validator->errors()->add('end_at', 'A shift cannot be longer than 24 hours.');
                }

                $breakMinutes = 0;

                foreach ($this->input('breaks', []) as $index => $break) {
                    if (empty($break['start_at']) || empty($break['end_at'])) {
                        continue;
                    }

                    $breakStart = Carbon::parse($break['start_at']);
                    $breakEnd = Carbon::parse($break['end_at']);

                    if ($breakEnd->lessThanOrEqualTo($breakStart)) {
                        $breakEnd->addDay();
                    }

                    if ($breakStart->lt($start) || $breakEnd->gt($end)) {
                        $validator->errors()->add("breaks.{$index}.start_at", 'Break periods must fall inside the shift.');
                    }

                    if ($breakEnd->lte($breakStart)) {
                        $validator->errors()->add("breaks.{$index}.end_at", 'The break end time must be after the start time.');
                    }

                    $breakMinutes += (int) $breakStart->diffInMinutes($breakEnd);
                }

                if ($breakMinutes >= (int) $start->diffInMinutes($end)) {
                    $validator->errors()->add('breaks', 'Total break time must be shorter than the shift duration.');
                }
            }
        });
    }
}
