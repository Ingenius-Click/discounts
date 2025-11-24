<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Configuration
    |--------------------------------------------------------------------------
    |
    | Here you can specify configuration options for the discounts package.
    |
    */

    'product_model' => env('PRODUCT_MODEL', 'Ingenius\Products\Models\Product'),
    'category_model' => env('CATEGORY_MODEL',  'Ingenius\Products\Models\Category'),
    'order_model' => env('ORDER_MODEL', 'Ingenius\Orders\Models\Order'),
    'shop_cart_model' => env('SHOP_CART_MODEL', 'Ingenius\ShopCart\Services\ShopCart'),
    'shipment_model' => env('SHIPMENT_MODEL', 'Ingenius\Shipment\Models\Shipment')
];