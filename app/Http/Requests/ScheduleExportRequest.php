<?php

namespace App\Http\Requests;

use App\Support\Axcess;
use Illuminate\Validation\Rule;

class ScheduleExportRequest extends ExcelExportRequest
{
    protected function prepareForValidation(): void
    {
        $this->defaultPeriodFields('week');
        $this->booleanFields(['standby_only', 'include_sensitive']);

        if (! $this->filled('worksheets')) {
            $this->merge(['worksheets' => ['schedule']]);
        }
    }

    public function rules(): array
    {
        return [
            'format' => ['required', Rule::in(['xlsx', 'csv'])],
            'mode' => ['required', Rule::in(['date', 'week', 'month', 'custom'])],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'week' => ['nullable', 'date_format:Y-m-d'],
            'month' => ['nullable', 'date_format:Y-m'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'shift_type_id' => ['nullable', 'integer', 'exists:shift_types,id'],
            'status' => ['nullable', Rule::in(array_keys(Axcess::ASSIGNMENT_STATUSES))],
            'standby_only' => ['boolean'],
            'worksheets' => ['required', 'array', 'min:1'],
            'worksheets.*' => ['string', Rule::in(array_keys(Axcess::SCHEDULE_WORKSHEETS))],
            'include_sensitive' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'worksheets.*.in' => 'One of the selected worksheets is not available.',
        ];
    }
}
