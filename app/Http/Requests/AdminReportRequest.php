<?php

namespace App\Http\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class AdminReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('view-admin-reports') ?? false;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['from', 'to'])) {
                    return;
                }

                [$from, $to] = $this->range();

                if ($from->gt($to)) {
                    $validator->errors()->add('from', 'The start date must be on or before the end date.');

                    return;
                }

                if ($from->diffInDays($to) > 91) {
                    $validator->errors()->add('to', 'Pixelforge usage reports cover at most 92 days.');
                }
            },
        ];
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function range(): array
    {
        $to = $this->filled('to')
            ? CarbonImmutable::parse((string) $this->input('to'), 'UTC')
            : CarbonImmutable::now('UTC')->startOfDay();

        $from = $this->filled('from')
            ? CarbonImmutable::parse((string) $this->input('from'), 'UTC')
            : $to->subDays(29);

        return [$from, $to];
    }
}
