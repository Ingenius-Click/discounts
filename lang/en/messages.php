<?php

return [
    // Target types
    'target_type_product' => 'Product',
    'target_type_category' => 'Category',
    'target_type_cart' => 'Cart',
    'target_type_shipment' => 'Shipment',
    'target_type_unknown' => 'Unknown',

    // Target names
    'all_products' => 'All Products',
    'all_categories' => 'All Categories',
    'all_shipments' => 'All Shipments',
    'shopping_cart' => 'Shopping Cart',
    'product_id' => 'Product #:id',
    'category_id' => 'Category #:id',
    'shipment_id' => 'Shipment #:id',

    // Condition types
    'min_cart_value' => 'Minimum cart value',
    'min_quantity' => 'Minimum quantity',
    'customer_segment_condition' => 'Customer segment :operator [:segments]',
    'has_product_condition' => 'Has product :operator [:products]',
    'is_first_order' => 'Is first order',
    'is_not_first_order' => 'Is not first order',
    'date_range' => 'Date range',
    'valid_from_to' => 'Valid from :start to :end',
    'valid_from' => 'Valid from :date',
    'valid_until' => 'Valid until :date',

    // Operators
    'operator_at_least' => 'at least',
    'operator_greater_than' => 'greater than',
    'operator_at_most' => 'at most',
    'operator_less_than' => 'less than',
    'operator_equals' => 'equals',
    'operator_not_equals' => 'not equals',
    'operator_in' => 'in',
    'operator_not_in' => 'not in',
];
