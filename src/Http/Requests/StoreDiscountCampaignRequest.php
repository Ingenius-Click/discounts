<?php

namespace Ingenius\Discounts\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Ingenius\Discounts\Services\DiscountApplicatorFactory;
use Ingenius\Discounts\Enums\TargetType;

class StoreDiscountCampaignRequest extends FormRequest
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

        // Get available condition types from the ConditionType enum
        $conditionTypes = array_column(\Ingenius\Discounts\Enums\ConditionType::cases(), 'value');

        // Get available target actions from the TargetAction enum
        $targetActions = array_column(\Ingenius\Discounts\Enums\TargetAction::cases(), 'value');

        // Get available target types from the TargetType enum
        $targetTypes = array_column(TargetType::cases(), 'value');

        return [
            // Campaign basic info
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'discount_type' => ['required', 'string', Rule::in($availableTypes)],
            'discount_value' => ['required', 'numeric', 'min:0'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'is_active' => ['boolean'],
            'priority' => ['nullable', 'integer', 'min:0'],
            'max_uses' => ['nullable', 'integer', 'min:0'],
            'max_uses_per_customer' => ['nullable', 'integer', 'min:0'],
            'is_stackable' => ['nullable', 'boolean'],

            // Conditions validation
            'conditions' => ['nullable', 'array'],
            'conditions.*.condition_type' => ['required', 'string', Rule::in($conditionTypes)],
            'conditions.*.operator' => ['nullable', 'string', Rule::in(['>=', '>', '<=', '<', '==', '!=', 'in', 'not_in'])],
            'conditions.*.value' => ['nullable', 'array'],
            'conditions.*.logic_operator' => ['nullable', 'string', Rule::in(['AND', 'OR'])],
            'conditions.*.priority' => ['nullable', 'integer', 'min:0'],

            // Targets validation
            'targets' => ['nullable', 'array'],
            'targets.*.targetable_id' => ['nullable', 'integer'],
            'targets.*.targetable_type' => ['required', 'string', Rule::in($targetTypes)],
            'targets.*.target_action' => ['required', 'string', Rule::in($targetActions)],
            'targets.*.metadata' => ['nullable', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'discount_type.in' => 'The selected discount type is not supported. Available types are dynamically determined by registered applicators.',
            'conditions.*.condition_type.in' => 'The selected condition type is not valid. Must be one of the ConditionType enum values.',
            'conditions.*.operator.in' => 'The operator must be one of: >=, >, <=, <, ==, !=, in, not_in.',
            'conditions.*.value.array' => 'The condition value must be an array.',
            'conditions.*.logic_operator.in' => 'The logic operator must be either AND or OR.',
            'targets.*.targetable_type.in' => 'The target type must be one of: products, categories, shipment, shopcart.',
            'targets.*.target_action.in' => 'The target action must be one of the TargetAction enum values.',
        ];
    }
}