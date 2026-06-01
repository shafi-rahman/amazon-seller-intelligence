<?php

namespace App\Modules\Finance\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Finance\Models\Order;
use App\Modules\Finance\Resources\OrderResource;
use App\Modules\Workspace\Models\Workspace;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    use ApiResponse;

    public function index(Request $request, int $workspaceId): JsonResponse
    {
        $workspace = Workspace::findOrFail($workspaceId);
        abort_unless($workspace->hasMember($request->user()), 403);

        $query = Order::where('workspace_id', $workspaceId)
            ->orderByDesc('purchase_date');

        if ($from = $request->query('date_from')) {
            $query->where('purchase_date', '>=', $from.' 00:00:00');
        }
        if ($to = $request->query('date_to')) {
            $query->where('purchase_date', '<=', $to.' 23:59:59');
        }
        if ($status = $request->query('status')) {
            $query->where('order_status', $status);
        }
        if ($sku = $request->query('sku')) {
            $query->where('sku', $sku);
        }
        if ($asin = $request->query('asin')) {
            $query->where('asin', $asin);
        }
        if ($channel = $request->query('fulfillment_channel')) {
            $query->where('fulfillment_channel', $channel);
        }
        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('amazon_order_id', 'ILIKE', "%{$search}%")
                  ->orWhere('product_name', 'ILIKE', "%{$search}%")
                  ->orWhere('sku', 'ILIKE', "%{$search}%");
            });
        }

        $paginator = $query->paginate((int) $request->query('per_page', 50));

        return $this->paginated($paginator);
    }

    public function summary(Request $request, int $workspaceId): JsonResponse
    {
        $workspace = Workspace::findOrFail($workspaceId);
        abort_unless($workspace->hasMember($request->user()), 403);

        $from = $request->query('date_from', now()->startOfMonth()->toDateString());
        $to   = $request->query('date_to',   now()->toDateString());

        $base = Order::where('workspace_id', $workspaceId)
            ->whereBetween('purchase_date', [$from.' 00:00:00', $to.' 23:59:59']);

        $agg = $base->selectRaw("
            COUNT(*) as total_orders,
            COALESCE(SUM(item_price), 0) as total_revenue,
            COALESCE(SUM(item_tax), 0) as total_tax,
            COALESCE(SUM(quantity), 0) as total_units
        ")->first();

        $byStatus = $base->clone()
            ->selectRaw('order_status, COUNT(*) as cnt')
            ->groupBy('order_status')
            ->pluck('cnt', 'order_status');

        $byFulfillment = $base->clone()
            ->selectRaw('fulfillment_channel, COUNT(*) as cnt')
            ->whereNotNull('fulfillment_channel')
            ->groupBy('fulfillment_channel')
            ->pluck('cnt', 'fulfillment_channel');

        return $this->success([
            'period'         => ['start' => $from, 'end' => $to],
            'total_orders'   => (int) $agg->total_orders,
            'total_revenue'  => round((float) $agg->total_revenue, 2),
            'total_tax'      => round((float) $agg->total_tax, 2),
            'total_units'    => (int) $agg->total_units,
            'by_status'      => $byStatus,
            'by_fulfillment' => $byFulfillment,
        ]);
    }
}
