<?php

namespace Ingenius\Discounts\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Ingenius\Discounts\Services\DiscountApplicatorFactory;

class PatchDiscountCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Get available discount types dynamically from the applicator factory
        $applicatorFactory = app(DiscountApplicatorFactory::class);
        $availableTypes = array_keys($applicatorFactory->getAllApplicators());

        return [
            // Campaign basic info - all optional for PATCH
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'discount_type' => ['sometimes', 'string', Rule::in($availableTypes)],
            'discount_value' => ['sometimes', 'numeric', 'min:0'],
            'start_date' => ['sometimes', 'date'],
            'end_date' => ['sometimes', 'date', 'after:start_date'],
            'is_active' => ['sometimes', 'boolean'],
            'priority' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'max_uses_total' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'max_uses_per_customer' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'is_stackable' => ['sometimes', 'boolean'],
            'metadata' => ['sometimes', 'nullable', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'discount_type.in' => 'The selected discount type is not supported.',
            'end_date.after' => 'The end date must be after the start date.',
        ];
    }
}
