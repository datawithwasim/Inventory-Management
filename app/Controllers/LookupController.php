<?php
declare(strict_types=1);

namespace App\Controllers;

use Core\Auth;
use Core\Controller;
use Core\DB;

final class LookupController extends Controller
{
    /** Item picker for purchase forms: name / SKU / barcode, with default cost and tax. */
    public function items(): void
    {
        $q = trim((string)($_GET['q'] ?? ''));
        $rows = [];
        if ($q !== '') {
            $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%';
            $rows = DB::all(
                'SELECT v.id, v.sku, v.name, v.cost_price, i.name AS item_name, i.track_batch, u.short_name AS unit, u.allow_decimal,
                        COALESCE(x.rate, 0) AS tax_rate
                 FROM item_variants v JOIN items i ON i.id = v.item_id JOIN units u ON u.id = i.unit_id LEFT JOIN taxes x ON x.id = i.tax_id
                 WHERE v.tenant_id = ? AND i.is_bundle = 0 AND i.is_active = 1 AND v.is_active = 1
                   AND (i.name LIKE ? OR v.name LIKE ? OR v.sku LIKE ? OR v.barcode = ?) ORDER BY i.name, v.name LIMIT 15',
                [Auth::tenantId(), $like, $like, $like, $q]);
        }
        header('Content-Type: application/json');
        echo json_encode($rows);
        exit;
    }
}
