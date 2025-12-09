<?php

return [
    // Target types
    'target_type_product' => 'Producto',
    'target_type_category' => 'Categoría',
    'target_type_cart' => 'Carrito',
    'target_type_shipment' => 'Envío',
    'target_type_unknown' => 'Desconocido',

    // Target names
    'all_products' => 'Todos los Productos',
    'all_categories' => 'Todas las Categorías',
    'all_shipments' => 'Todos los Envíos',
    'shopping_cart' => 'Carrito de Compras',
    'product_id' => 'Producto #:id',
    'category_id' => 'Categoría #:id',
    'shipment_id' => 'Envío #:id',

    // Condition types
    'min_cart_value' => 'Valor mínimo del carrito',
    'min_quantity' => 'Cantidad mínima',
    'customer_segment_condition' => 'Segmento de cliente :operator [:segments]',
    'has_product_condition' => 'Tiene producto :operator [:products]',
    'is_first_order' => 'Es primera orden',
    'is_not_first_order' => 'No es primera orden',
    'date_range' => 'Rango de fechas',
    'valid_from_to' => 'Válido desde :start hasta :end',
    'valid_from' => 'Válido desde :date',
    'valid_until' => 'Válido hasta :date',

    // Operators
    'operator_at_least' => 'al menos',
    'operator_greater_than' => 'mayor que',
    'operator_at_most' => 'como máximo',
    'operator_less_than' => 'menor que',
    'operator_equals' => 'igual a',
    'operator_not_equals' => 'no igual a',
    'operator_in' => 'en',
    'operator_not_in' => 'no en',
];
