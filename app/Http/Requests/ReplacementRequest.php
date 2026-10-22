<?php

namespace App\Http\Requests;

use App\Models\ScheduleAssignment;
use Illuminate\Foundation\Http\FormRequest;

class ReplacementRequest extends FormRequest
{
    public function authorize(): bool
    {
        $assignment = $this->route('assignment');

        return $assignment instanceof ScheduleAssignment
            && $this->user()->can('update', $assignment);
    }

    public function rules(): array
    {
        return [
            'replacement_user_id' => ['required', 'integer', 'exists:users,id'],
            'replacement_reason' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
