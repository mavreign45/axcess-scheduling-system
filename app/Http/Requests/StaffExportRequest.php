<?php

namespace App\Http\Requests;

use App\Support\Axcess;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StaffExportRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->booleanFields(['standby_only', 'include_contacts', 'include_sensitive']);

        if (! $this->filled('format')) {
            $this->merge(['format' => 'xlsx']);
        }
    }

    public function authorize(): bool
    {
        return $this->user()?->can('export-data') ?? false;
    }

    public function rules(): array
    {
        return [
            'format' => ['required', Rule::in(['xlsx', 'csv'])],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'employment_status' => ['nullable', Rule::in(array_keys(Axcess::EMPLOYMENT_STATUSES))],
            'standby_only' => ['boolean'],
            'include_contacts' => ['boolean'],
            'include_sensitive' => ['boolean'],
        ];
    }

    private function booleanFields(array $fields): void
    {
        foreach ($fields as $field) {
            if ($this->has($field)) {
                $this->merge([$field => $this->boolean($field)]);
            }
        }
    }
}
