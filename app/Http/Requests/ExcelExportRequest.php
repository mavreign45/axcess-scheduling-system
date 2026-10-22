<?php

namespace App\Http\Requests;

use App\Services\PeriodResolver;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use InvalidArgumentException;

abstract class ExcelExportRequest extends FormRequest
{
    protected ?Carbon $resolvedStart = null;

    protected ?Carbon $resolvedEnd = null;

    public function authorize(): bool
    {
        return $this->user()?->can('export-data') ?? false;
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                try {
                    [$this->resolvedStart, $this->resolvedEnd] = PeriodResolver::resolve($this->validationData());
                } catch (InvalidArgumentException $exception) {
                    $validator->errors()->add('from', $exception->getMessage());
                }
            },
        ];
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    public function resolvedPeriod(): array
    {
        if ($this->resolvedStart && $this->resolvedEnd) {
            return [$this->resolvedStart, $this->resolvedEnd];
        }

        return [now()->startOfDay(), now()->endOfDay()];
    }

    protected function defaultPeriodFields(string $mode = 'week'): void
    {
        if (! $this->filled('mode')) {
            $this->merge(['mode' => $mode]);
        }

        $mode = $this->input('mode');

        if ($mode === 'week' && ! $this->filled('week')) {
            $this->merge(['week' => now()->startOfWeek()->toDateString()]);
        }

        if ($mode === 'month' && ! $this->filled('month')) {
            $this->merge(['month' => now()->format('Y-m')]);
        }

        if ($mode === 'date' && ! $this->filled('date')) {
            $this->merge(['date' => now()->toDateString()]);
        }

        if (! $this->filled('format')) {
            $this->merge(['format' => 'xlsx']);
        }
    }

    protected function booleanFields(array $fields): void
    {
        foreach ($fields as $field) {
            if ($this->has($field)) {
                $this->merge([$field => $this->boolean($field)]);
            }
        }
    }
}
