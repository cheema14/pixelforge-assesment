<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGenerationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'prompt' => ['required', 'string', 'max:4000'],
            'size' => ['required', 'string', Rule::in(['1024x1024', '1536x1024', '1024x1536'])],
            'quality' => ['required', 'string', Rule::in(['low', 'medium', 'high'])],
            'reference_images' => ['nullable', 'array', 'max:4'],
            'reference_images.*' => ['string', 'max:2048', 'url:http,https'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('prompt'))) {
            $this->merge(['prompt' => trim($this->input('prompt'))]);
        }

        $references = $this->input('reference_images');

        if (is_array($references)) {
            $this->merge([
                'reference_images' => array_values(array_filter(
                    array_map(fn (mixed $url): mixed => is_string($url) ? trim($url) : $url, $references),
                    fn (mixed $url): bool => is_string($url) && $url !== '',
                )),
            ]);
        }
    }
}
