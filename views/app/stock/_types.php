<?php
if (!function_exists('stock_type')) {
    function stock_type(string $t): string
    {
        return App\Controllers\StockController::TYPES[$t] ?? $t;
    }
}
