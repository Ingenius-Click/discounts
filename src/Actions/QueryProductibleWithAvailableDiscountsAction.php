<?php

namespace Ingenius\Discounts\Actions;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class QueryProductibleWithAvailableDiscountsAction {

    public function handle(Builder $incommingQuery = null): Builder {

        $productClass = config('discounts.product_model');

        // Start with all products as base query
        $query = $incommingQuery ? $incommingQuery : $productClass::query();

        // Check if calculate-discounts feature is enabled
        $tenant = tenant();
        if (!$tenant || !$tenant->hasFeature('calculate-discounts')) {
            // Return empty query if feature is not enabled
            $query->whereRaw('1 = 0');
            return $query;
        }

        $now = now();

        // Check if there are any active campaigns WITHOUT targets (applies to ALL products)
        $hasGlobalDiscounts = \DB::table('discount_campaigns')
            ->where('is_active', true)
            ->where('start_date', '<=', $now)
            ->where('end_date', '>=', $now)
            ->whereNotExists(function ($subQuery) {
                $subQuery->select(\DB::raw(1))
                    ->from('discount_targets')
                    ->whereColumn('discount_targets.campaign_id', 'discount_campaigns.id')
                    ->where('discount_targets.target_action', 'apply_to');
            })
            ->exists();

        // If there are global discounts, all products have discounts
        if ($hasGlobalDiscounts) {
            return $query;
        }

        $categoryClass = config('discounts.category_model');

        // Otherwise, filter products that have specific discount targets
        $query->where(function($q) use ($productClass, $categoryClass, $now) {
            // 1. Products with direct discount targets (targetable_type = Product, targetable_id = product.id)
            $q->whereExists(function ($subQuery) use ($productClass, $now) {
                $subQuery->select(\DB::raw(1))
                    ->from('discount_targets')
                    ->join('discount_campaigns', 'discount_targets.campaign_id', '=', 'discount_campaigns.id')
                    ->whereColumn('discount_targets.targetable_id', $productClass::make()->getTable() . '.id')
                    ->where('discount_targets.targetable_type', $productClass)
                    ->where('discount_targets.target_action', 'apply_to')
                    ->where('discount_campaigns.is_active', true)
                    ->where('discount_campaigns.start_date', '<=', $now)
                    ->where('discount_campaigns.end_date', '>=', $now);
            })
            // 2. Products matching all-product discounts (targetable_type = Product, targetable_id is NULL)
            ->orWhereExists(function ($subQuery) use ($productClass, $now) {
                $subQuery->select(\DB::raw(1))
                    ->from('discount_targets')
                    ->join('discount_campaigns', 'discount_targets.campaign_id', '=', 'discount_campaigns.id')
                    ->where('discount_targets.targetable_type', $productClass)
                    ->whereNull('discount_targets.targetable_id')
                    ->where('discount_targets.target_action', 'apply_to')
                    ->where('discount_campaigns.is_active', true)
                    ->where('discount_campaigns.start_date', '<=', $now)
                    ->where('discount_campaigns.end_date', '>=', $now);
            })
            // 3. Products in categories with discounts (targetable_type = Category, targetable_id = category.id)
            ->orWhereExists(function ($subQuery) use ($productClass, $categoryClass, $now) {
                $subQuery->select(\DB::raw(1))
                    ->from('discount_targets')
                    ->join('discount_campaigns', 'discount_targets.campaign_id', '=', 'discount_campaigns.id')
                    ->join('products_categories', 'discount_targets.targetable_id', '=', 'products_categories.category_id')
                    ->whereColumn('products_categories.product_id', $productClass::make()->getTable() . '.id')
                    ->where('discount_targets.targetable_type', $categoryClass)
                    ->whereNotNull('discount_targets.targetable_id')
                    ->where('discount_targets.target_action', 'apply_to')
                    ->where('discount_campaigns.is_active', true)
                    ->where('discount_campaigns.start_date', '<=', $now)
                    ->where('discount_campaigns.end_date', '>=', $now);
            })
            // 4. Products matching all-category discounts (targetable_type = Category, targetable_id is NULL)
            ->orWhereExists(function ($subQuery) use ($categoryClass, $now) {
                $subQuery->select(\DB::raw(1))
                    ->from('discount_targets')
                    ->join('discount_campaigns', 'discount_targets.campaign_id', '=', 'discount_campaigns.id')
                    ->where('discount_targets.targetable_type', $categoryClass)
                    ->whereNull('discount_targets.targetable_id')
                    ->where('discount_targets.target_action', 'apply_to')
                    ->where('discount_campaigns.is_active', true)
                    ->where('discount_campaigns.start_date', '<=', $now)
                    ->where('discount_campaigns.end_date', '>=', $now);
            });
        });

        return $query;
    }

}