<?php

namespace App\Http\Requests;

use App\Support\Axcess;
use Illuminate\Validation\Rule;

class LeaveExportRequest extends ExcelExportRequest
{
    protected function prepareForValidation(): void
    {
        $this->defaultPeriodFields('month');
        $this->booleanFields(['include_absences', 'include_sensitive']);
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
            'leave_type' => ['nullable', Rule::in(array_keys(Axcess::LEAVE_TYPES))],
            'leave_status' => ['nullable', Rule::in(array_keys(Axcess::LEAVE_STATUSES))],
            'include_absences' => ['boolean'],
            'include_sensitive' => ['boolean'],
        ];
    }
}
