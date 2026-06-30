<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Capacity Cost Guard Ratio
    |--------------------------------------------------------------------------
    |
    | Maximum allowed ratio of a single capacity posting amount to the
    | production order planned value (quantity * planned unit cost).
    |
    | Example: 100 means capacity cost cannot exceed 100x planned value.
    |
    */
    'capacity_guard_ratio' => 100,

    /*
    |--------------------------------------------------------------------------
    | Capacity Cost Center Priority
    |--------------------------------------------------------------------------
    |
    | Determines which center rates are used when both machine center and
    | work center exist on a routing line.
    |
    | Supported values:
    | - machine_center_first (default)
    | - work_center_first
    |
    */
    'capacity_cost_center_priority' => 'work_center_first',

    /*
    |--------------------------------------------------------------------------
    | Negative Component Stock
    |--------------------------------------------------------------------------
    |
    | Production consumption should not push component inventory below zero.
    | Keep this disabled unless a controlled legacy or backflush process has
    | explicitly accepted negative component stock risk.
    |
    */
    'allow_negative_component_stock' => env('MANUFACTURING_ALLOW_NEGATIVE_COMPONENT_STOCK', false),
];
