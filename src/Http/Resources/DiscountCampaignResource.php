<?php

namespace Ingenius\Discounts\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Ingenius\Coins\Services\CurrencyServices;
use Ingenius\Discounts\Enums\ConditionType;

class DiscountCampaignResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param Request $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'discount_type' => $this->discount_type,
            'discount_value' => $this->formatDiscountValue($this->discount_value, $this->discount_type),
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'is_active' => $this->is_active,
            'priority' => $this->priority,
            'is_stackable' => $this->is_stackable,
            'max_uses_total' => $this->max_uses_total,
            'max_uses_per_customer' => $this->max_uses_per_customer,
            'current_uses' => $this->current_uses,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            // Formatted targets with names
            'targets' => $this->when($this->relationLoaded('targets'), function () {
                return $this->targets->map(function ($target) {
                    return [
                        'id' => $target->id,
                        'target_action' => $target->target_action,
                        'target_type' => $this->getReadableTargetType($target->targetable_type),
                        'name' => $this->buildTargetName($target),
                        'metadata' => $target->metadata,
                    ];
                });
            }),

            // Formatted conditions with readable names
            'conditions' => $this->when($this->relationLoaded('conditions'), function () {
                return $this->conditions->map(function ($condition) {
                    return [
                        'id' => $condition->id,
                        'name' => $this->buildConditionName($condition),
                        'logic_operator' => $condition->logic_operator,
                        'priority' => $condition->priority,
                    ];
                });
            }),
        ];
    }

    /**
     * Build a readable target name based on targetable type and id
     */
    protected function buildTargetName($target): string
    {
        $targetableType = $target->targetable_type;
        $targetableId = $target->targetable_id;

        $productClass = config('discounts.product_model');
        $categoryClass = config('discounts.category_model');
        $shopCartClass = config('discounts.shop_cart_model');
        $shipmentClass = config('discounts.shipment_model');

        // Load the targetable relationship if not loaded and not a shop cart (which is a service, not a model)
        if (!$target->relationLoaded('targetable') && $targetableId && $targetableType !== $shopCartClass) {
            try {
                $target->load('targetable');
            } catch (\Exception $e) {
                // If loading fails, we'll just use the ID fallback
            }
        }

        // Product targets
        if ($targetableType === $productClass) {
            if ($targetableId === null) {
                return __('discounts::messages.all_products');
            }

            if ($target->targetable) {
                return $target->targetable->name ?? __('discounts::messages.product_id', ['id' => $targetableId]);
            }

            return __('discounts::messages.product_id', ['id' => $targetableId]);
        }

        // Category targets
        if ($targetableType === $categoryClass) {
            if ($targetableId === null) {
                return __('discounts::messages.all_categories');
            }

            if ($target->targetable) {
                return $target->targetable->name ?? __('discounts::messages.category_id', ['id' => $targetableId]);
            }

            return __('discounts::messages.category_id', ['id' => $targetableId]);
        }

        // Shop cart targets
        if ($targetableType === $shopCartClass) {
            return __('discounts::messages.shopping_cart');
        }

        // Shipment targets
        if ($targetableType === $shipmentClass) {
            if ($targetableId === null) {
                return __('discounts::messages.all_shipments');
            }

            if ($target->targetable) {
                return $target->targetable->name ?? __('discounts::messages.shipment_id', ['id' => $targetableId]);
            }

            return __('discounts::messages.shipment_id', ['id' => $targetableId]);
        }

        // Fallback for unknown types
        return $targetableId ? "{$targetableType} #{$targetableId}" : $targetableType;
    }

    /**
     * Get readable target type from targetable type class
     */
    protected function getReadableTargetType(string $targetableType): string
    {
        $productClass = config('discounts.product_model');
        $categoryClass = config('discounts.category_model');
        $shopCartClass = config('discounts.shop_cart_model');
        $shipmentClass = config('discounts.shipment_model');

        return match ($targetableType) {
            $productClass => __('discounts::messages.target_type_product'),
            $categoryClass => __('discounts::messages.target_type_category'),
            $shopCartClass => __('discounts::messages.target_type_cart'),
            $shipmentClass => __('discounts::messages.target_type_shipment'),
            default => __('discounts::messages.target_type_unknown'),
        };
    }

    /**
     * Build a readable condition name based on condition type, operator, and value
     */
    protected function buildConditionName($condition): string
    {
        $conditionType = $condition->condition_type;
        $operator = $condition->operator;
        $value = $condition->value;

        switch ($conditionType) {
            case ConditionType::MIN_CART_VALUE->value:
                $amount = $value['amount'] ?? $value;
                // Format currency from cents in base currency
                if (is_numeric($amount)) {
                    try {
                        $baseCurrencyCode = CurrencyServices::getBaseCurrencyShortName();
                        $amount = CurrencyServices::formatCurrency((int)$amount, $baseCurrencyCode);
                    } catch (\Exception $e) {
                        $amount = number_format($amount / 100, 2);
                    }
                }
                return $this->formatCondition(__('discounts::messages.min_cart_value'), $operator, $amount);

            case ConditionType::MIN_QUANTITY->value:
                return $this->formatCondition(__('discounts::messages.min_quantity'), $operator, $value['quantity'] ?? $value);

            case ConditionType::CUSTOMER_SEGMENT->value:
                if (is_array($value)) {
                    $segments = $value['segments'] ?? $value;
                    if (is_array($segments)) {
                        $segmentList = implode(', ', $segments);
                        return __('discounts::messages.customer_segment_condition', [
                            'operator' => $this->translateOperator($operator),
                            'segments' => $segmentList
                        ]);
                    }
                }
                return __('discounts::messages.customer_segment_condition', [
                    'operator' => $this->translateOperator($operator),
                    'segments' => is_array($value) ? json_encode($value) : $value
                ]);

            case ConditionType::HAS_PRODUCT->value:
                if (is_array($value)) {
                    $productIds = $value['product_ids'] ?? $value;
                    if (is_array($productIds)) {
                        $productList = $this->getProductNames($productIds);
                        return __('discounts::messages.has_product_condition', [
                            'operator' => $this->translateOperator($operator),
                            'products' => $productList
                        ]);
                    }
                }
                return __('discounts::messages.has_product_condition', [
                    'operator' => $this->translateOperator($operator),
                    'products' => is_array($value) ? json_encode($value) : $value
                ]);

            case ConditionType::FIRST_ORDER->value:
                $boolValue = $value['is_first_order'] ?? $value;
                return $boolValue ? __('discounts::messages.is_first_order') : __('discounts::messages.is_not_first_order');

            case ConditionType::DATE_RANGE->value:
                $startDate = $value['start_date'] ?? null;
                $endDate = $value['end_date'] ?? null;

                if ($startDate && $endDate) {
                    return __('discounts::messages.valid_from_to', ['start' => $startDate, 'end' => $endDate]);
                } elseif ($startDate) {
                    return __('discounts::messages.valid_from', ['date' => $startDate]);
                } elseif ($endDate) {
                    return __('discounts::messages.valid_until', ['date' => $endDate]);
                }
                return __('discounts::messages.date_range');

            default:
                return ucfirst(str_replace('_', ' ', $conditionType)) . " {$operator} " . (is_array($value) ? json_encode($value) : $value);
        }
    }

    /**
     * Format a condition with operator and value
     */
    protected function formatCondition(string $label, string $operator, $value): string
    {
        $operatorText = $this->translateOperator($operator);

        if (is_array($value)) {
            $value = json_encode($value);
        }

        return "{$label} {$operatorText} {$value}";
    }

    /**
     * Translate operator to readable text
     */
    protected function translateOperator(string $operator): string
    {
        $operatorMap = [
            '>=' => __('discounts::messages.operator_at_least'),
            '>' => __('discounts::messages.operator_greater_than'),
            '<=' => __('discounts::messages.operator_at_most'),
            '<' => __('discounts::messages.operator_less_than'),
            '==' => __('discounts::messages.operator_equals'),
            '!=' => __('discounts::messages.operator_not_equals'),
            'in' => __('discounts::messages.operator_in'),
            'not_in' => __('discounts::messages.operator_not_in'),
        ];

        return $operatorMap[$operator] ?? $operator;
    }

    /**
     * Get product names from IDs
     */
    protected function getProductNames(array $productIds): string
    {
        try {
            $productClass = config('discounts.product_model');

            if (!class_exists($productClass)) {
                return implode(', ', $productIds);
            }

            $products = $productClass::whereIn('id', $productIds)->pluck('name', 'id');

            if ($products->isEmpty()) {
                return implode(', ', $productIds);
            }

            return $products->map(function ($name, $id) {
                return $name ?? __('discounts::messages.product_id', ['id' => $id]);
            })->implode(', ');
        } catch (\Exception $e) {
            return implode(', ', $productIds);
        }
    }

    /**
     * Format discount value based on discount type
     */
    protected function formatDiscountValue($value, string $discountType): string|int
    {
        // If it's a percentage discount, return as-is
        if ($discountType === 'percentage') {
            return $value;
        }

        // If it's a fixed amount, convert from cents and format with currency
        if ($discountType === 'fixed_amount') {
            try {
                $currencyCode = CurrencyServices::getBaseCurrencyShortName();
                return CurrencyServices::formatCurrency($value, $currencyCode);
            } catch (\Exception $e) {
                // Fallback to simple formatting
                return number_format($value / 100, 2);
            }
        }

        return $value;
    }
}
