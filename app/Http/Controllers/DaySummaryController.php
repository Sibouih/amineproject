<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Sale;
use App\Models\UserWarehouse;
use App\Models\Warehouse;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * "Today's summary" drawer of the header: sales, purchases, profit, payments by method and stock for one day.
 * Administrators only: users with the "Sales_profit" permission (Owner and Admin roles by default).
 * The shop does not use VAT, so no tax rows.
 */
class DaySummaryController extends BaseController
{
    public function show(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'Sales_profit', Sale::class);

        $user = Auth::user();
        $can_profit = $user->can('Sales_profit', Sale::class);

        try {
            $date = Carbon::parse($request->get('date') ?: Carbon::today())->toDateString();
        } catch (\Exception $e) {
            $date = Carbon::today()->toDateString();
        }

        // warehouses the user can see, optionally only one of them
        $warehouses = Warehouse::where('deleted_at', '=', null);
        if (!$user->is_all_warehouses) {
            $warehouses->whereIn('id', UserWarehouse::where('user_id', $user->id)->pluck('warehouse_id')->toArray());
        }
        $warehouses = $warehouses->orderBy('name')->get(['id', 'name']);
        $ids = $warehouses->pluck('id')->toArray();
        $one_warehouse = $request->filled('warehouse_id') && in_array((int) $request->warehouse_id, $ids);
        if ($one_warehouse) {
            $ids = [(int) $request->warehouse_id];
        }
        // payments not attached to a sale/purchase (customer or supplier balance) have no warehouse
        $with_unlinked = !$one_warehouse && $user->is_all_warehouses;

        // ---------------- sales ----------------
        $sales = DB::table('sales')
            ->whereNull('deleted_at')->where('date', $date)->whereIn('warehouse_id', $ids)
            ->selectRaw('COUNT(*) AS count, COALESCE(SUM(GrandTotal),0) AS net, COALESCE(SUM(discount),0) AS discount, COALESCE(SUM(shipping),0) AS shipping')
            ->first();
        $sale_lines = DB::table('sale_details as sd')
            ->join('sales as s', 's.id', '=', 'sd.sale_id')
            ->whereNull('s.deleted_at')->where('s.date', $date)->whereIn('s.warehouse_id', $ids)
            ->selectRaw('COALESCE(SUM(sd.total),0) AS gross, COALESCE(SUM(sd.quantity),0) AS items')
            ->first();
        $sale_returns = (float) DB::table('sale_returns')
            ->whereNull('deleted_at')->where('date', $date)->whereIn('warehouse_id', $ids)->sum('GrandTotal');

        // ---------------- purchases ----------------
        $purchases = DB::table('purchases')
            ->whereNull('deleted_at')->where('date', $date)->whereIn('warehouse_id', $ids)
            ->selectRaw('COUNT(*) AS count, COALESCE(SUM(GrandTotal),0) AS net, COALESCE(SUM(discount),0) AS discount, COALESCE(SUM(shipping),0) AS shipping')
            ->first();
        $purchase_lines = DB::table('purchase_details as pd')
            ->join('purchases as p', 'p.id', '=', 'pd.purchase_id')
            ->whereNull('p.deleted_at')->where('p.date', $date)->whereIn('p.warehouse_id', $ids)
            ->selectRaw('COALESCE(SUM(pd.total),0) AS gross, COALESCE(SUM(pd.quantity),0) AS items')
            ->first();
        $purchase_returns = (float) DB::table('purchase_returns')
            ->whereNull('deleted_at')->where('date', $date)->whereIn('warehouse_id', $ids)->sum('GrandTotal');

        $expenses = (float) DB::table('expenses')
            ->whereNull('deleted_at')->where('date', $date)->whereIn('warehouse_id', $ids)->sum('amount');

        // ---------------- payments by method ----------------
        $received = DB::table('payment_sales as ps')
            ->leftJoin('sales as s', 's.id', '=', 'ps.sale_id')
            ->whereNull('ps.deleted_at')->where('ps.date', $date)
            ->where(function ($q) use ($ids, $with_unlinked) {
                $q->whereIn('s.warehouse_id', $ids);
                if ($with_unlinked) {
                    $q->orWhereNull('ps.sale_id');
                }
            })
            ->groupBy('ps.Reglement')
            ->selectRaw('ps.Reglement AS method, SUM(ps.montant) AS amount')
            ->pluck('amount', 'method');
        $given = DB::table('payment_purchases as pp')
            ->leftJoin('purchases as p', 'p.id', '=', 'pp.purchase_id')
            ->whereNull('pp.deleted_at')->where('pp.date', $date)
            ->where(function ($q) use ($ids, $with_unlinked) {
                $q->whereIn('p.warehouse_id', $ids);
                if ($with_unlinked) {
                    $q->orWhereNull('pp.purchase_id');
                }
            })
            ->groupBy('pp.Reglement')
            ->selectRaw('pp.Reglement AS method, SUM(pp.montant) AS amount')
            ->pluck('amount', 'method');

        $methods = array_values(array_unique(array_merge(array_keys($received->toArray()), array_keys($given->toArray()))));
        sort($methods);
        $payments = [
            'methods' => $methods,
            'received' => array_map('floatval', $received->toArray()),
            'given' => array_map('floatval', $given->toArray()),
            'received_total' => (float) $received->sum(),
            'given_total' => (float) $given->sum(),
        ];

        // ---------------- profit (administrators) ----------------
        $profit = null;
        if ($can_profit) {
            // cost per base unit: warehouse cost, then variant cost, then product cost (same as the sale profit view),
            // converted to the unit the line was sold in
            $base_cost = 'COALESCE(NULLIF((SELECT pw.cost FROM product_warehouse pw WHERE pw.deleted_at IS NULL'
                . ' AND pw.warehouse_id = s.warehouse_id AND pw.product_id = sd.product_id'
                . ' AND pw.product_variant_id <=> sd.product_variant_id LIMIT 1), 0), NULLIF(pv.cost, 0), p.cost, 0)';
            $cogs = (float) DB::table('sale_details as sd')
                ->join('sales as s', 's.id', '=', 'sd.sale_id')
                ->join('products as p', 'p.id', '=', 'sd.product_id')
                ->leftJoin('product_variants as pv', 'pv.id', '=', 'sd.product_variant_id')
                ->leftJoin('units as u', 'u.id', '=', DB::raw('COALESCE(sd.sale_unit_id, p.unit_sale_id)'))
                ->whereNull('s.deleted_at')->where('s.date', $date)->whereIn('s.warehouse_id', $ids)
                ->selectRaw("COALESCE(SUM(sd.quantity * (CASE
                    WHEN u.id IS NULL OR u.operator_value IS NULL OR u.operator_value = 0 THEN $base_cost
                    WHEN u.operator = '/' THEN $base_cost / u.operator_value
                    ELSE $base_cost * u.operator_value END)), 0) AS cogs")
                ->value('cogs');

            $gross_profit = (float) $sale_lines->gross - $cogs;
            $profit = [
                'cogs' => round($cogs, 2),
                'gross_profit' => round($gross_profit, 2),
                // the order discount is given away from the profit; expenses of the day too
                'discount' => (float) $sales->discount,
                'expenses' => $expenses,
                'net_profit' => round($gross_profit - (float) $sales->discount - $expenses, 2),
            ];
        }

        // ---------------- stock now ----------------
        $stock_query = DB::table('product_warehouse as pw')
            ->join('products as p', 'p.id', '=', 'pw.product_id')
            ->leftJoin('product_variants as pv', 'pv.id', '=', 'pw.product_variant_id')
            ->whereNull('pw.deleted_at')->whereNull('p.deleted_at')
            ->where('p.type', '!=', 'is_service')
            ->whereIn('pw.warehouse_id', $ids);
        $stock = $stock_query->selectRaw('
            COALESCE(SUM(GREATEST(pw.qte, 0)), 0) AS units,
            COALESCE(SUM(GREATEST(pw.qte, 0) * COALESCE(NULLIF(pw.cost, 0), NULLIF(pv.cost, 0), p.cost, 0)), 0) AS at_cost,
            COALESCE(SUM(GREATEST(pw.qte, 0) * COALESCE(NULLIF(pw.price, 0), NULLIF(pv.price, 0), p.price, 0)), 0) AS at_retail,
            COALESCE(SUM(pw.qte <= 0 AND pw.manage_stock = 1), 0) AS out_of_stock,
            COALESCE(SUM(pw.qte > 0 AND pw.qte <= p.stock_alert), 0) AS low_stock
        ')->first();

        $new_customers = Client::where('deleted_at', '=', null)->whereDate('created_at', $date)->count();
        $on_shift = DB::table('attendances')
            ->whereNull('deleted_at')->where('date', $date)
            ->where(function ($q) {
                $q->whereNull('clock_out')->orWhere('clock_out', '');
            })
            ->distinct()->count('employee_id');

        $sales_count = (int) $sales->count;
        $purchases_count = (int) $purchases->count;

        return response()->json([
            'date' => $date,
            'is_today' => $date === Carbon::today()->toDateString(),
            'warehouses' => $warehouses,
            'can_profit' => $can_profit,
            'sales' => [
                'count' => $sales_count,
                'items' => (float) $sale_lines->items,
                'gross' => (float) $sale_lines->gross,
                'discount' => (float) $sales->discount,
                'shipping' => (float) $sales->shipping,
                'net' => (float) $sales->net,
                'returns' => $sale_returns,
                'average' => $sales_count ? round($sales->net / $sales_count, 2) : 0,
            ],
            'purchases' => [
                'count' => $purchases_count,
                'items' => (float) $purchase_lines->items,
                'gross' => (float) $purchase_lines->gross,
                'discount' => (float) $purchases->discount,
                'shipping' => (float) $purchases->shipping,
                'net' => (float) $purchases->net,
                'returns' => $purchase_returns,
                'average' => $purchases_count ? round($purchases->net / $purchases_count, 2) : 0,
            ],
            'expenses' => $expenses,
            'profit' => $profit,
            'payments' => $payments,
            'stock' => [
                'units' => (float) $stock->units,
                'at_cost' => $can_profit ? round((float) $stock->at_cost, 2) : null,
                'at_retail' => round((float) $stock->at_retail, 2),
                'potential_margin' => $can_profit ? round((float) $stock->at_retail - (float) $stock->at_cost, 2) : null,
                'out_of_stock' => (int) $stock->out_of_stock,
                'low_stock' => (int) $stock->low_stock,
            ],
            'new_customers' => $new_customers,
            'on_shift' => $on_shift,
        ]);
    }
}
