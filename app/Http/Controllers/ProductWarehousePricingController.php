<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\product_warehouse;
use App\Models\ProductWarehousePriceHistory;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class ProductWarehousePricingController extends Controller
{
    /**
     * Get products with their warehouse-specific pricing
     */
    public function index(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'view', Product::class);

        // Initialize all variables at the top
        $perPage = $request->limit ?: 10;
        $order = $request->SortField ?: 'id';
        $dir = $request->SortType ?: 'desc';
        $page = $request->page ?: 1;
        
        $query = product_warehouse::with(['product', 'warehouse', 'productVariant'])
            ->where('deleted_at', null);

        // Filter by warehouse
        if ($request->filled('warehouse_id')) {
            $query->where('warehouse_id', $request->warehouse_id);
        }

        // Filter by product
        if ($request->filled('product_id')) {
            $query->where('product_id', $request->product_id);
        }

        // Improved search functionality
        if ($request->filled('search')) {
            $searchTerm = $request->search;
            $query->where(function ($q) use ($searchTerm) {
                // Search in product name and code
                $q->whereHas('product', function ($productQuery) use ($searchTerm) {
                    $productQuery->where('name', 'like', '%' . $searchTerm . '%')
                                ->orWhere('code', 'like', '%' . $searchTerm . '%');
                })
                // Search in variant name and code if variant exists
                ->orWhereHas('productVariant', function ($variantQuery) use ($searchTerm) {
                    $variantQuery->where('name', 'like', '%' . $searchTerm . '%')
                                ->orWhere('code', 'like', '%' . $searchTerm . '%');
                })
                // Search in warehouse name
                ->orWhereHas('warehouse', function ($warehouseQuery) use ($searchTerm) {
                    $warehouseQuery->where('name', 'like', '%' . $searchTerm . '%');
                });
            });
        }

        // Apply ordering
        if ($order === 'product_name') {
            $query->join('products', 'product_warehouse.product_id', '=', 'products.id')
                  ->orderBy('products.name', $dir)
                  ->select('product_warehouse.*');
        } elseif ($order === 'warehouse_name') {
            $query->join('warehouses', 'product_warehouse.warehouse_id', '=', 'warehouses.id')
                  ->orderBy('warehouses.name', $dir)
                  ->select('product_warehouse.*');
        } else {
            $query->orderBy($order, $dir);
        }

        // Get total count before pagination
        $totalCount = $query->count();
        
        // Calculate max pages
        $maxPages = ceil($totalCount / $perPage);
        
        // If requested page is beyond available data, reset to page 1
        if ($page > $maxPages && $maxPages > 0) {
            $page = 1;
        }

        // Apply pagination manually to have more control
        $productWarehouses = $query->skip(($page - 1) * $perPage)
                                  ->take($perPage)
                                  ->get();

        $data = [];
        foreach ($productWarehouses as $pw) {
            // Build product display name
            $productName = $pw->product->name;
            if ($pw->productVariant) {
                $productName = '[' . $pw->productVariant->name . '] ' . $productName;
            }

            // Build product code
            $productCode = $pw->productVariant ? $pw->productVariant->code : $pw->product->code;

            // Calculate pricing comparison
            $globalPrice = $pw->productVariant ? $pw->productVariant->price : $pw->product->price;
            $globalCost = $pw->productVariant ? $pw->productVariant->cost : $pw->product->cost;
            
            $priceComparison = '';
            if ($pw->price != $globalPrice) {
                $priceComparison .= 'Price: ' . number_format($pw->price, 2) . ' (Global: ' . number_format($globalPrice, 2) . ') ';
            }
            if ($pw->cost != $globalCost) {
                $priceComparison .= 'Cost: ' . number_format($pw->cost, 2) . ' (Global: ' . number_format($globalCost, 2) . ')';
            }
            if (empty($priceComparison)) {
                $priceComparison = 'Same as global pricing';
            }

            $item = [
                'id' => $pw->id,
                'product_id' => $pw->product_id,
                'warehouse_id' => $pw->warehouse_id,
                'product_variant_id' => $pw->product_variant_id,
                'product_name' => $productName,
                'product_code' => $productCode,
                'warehouse_name' => $pw->warehouse->name,
                'variant_name' => $pw->productVariant ? $pw->productVariant->name : null,
                'quantity' => number_format($pw->qte, 2),
                'price' => number_format($pw->price ?: 0, 2),
                'cost' => number_format($pw->cost ?: 0, 2),
                'profit_margin' => $pw->price > 0 ? number_format((($pw->price - $pw->cost) / $pw->price) * 100, 2) : '0.00',
                'global_price' => number_format($globalPrice, 2),
                'global_cost' => number_format($globalCost, 2),
                'pricing_comparison' => $priceComparison,
            ];
            $data[] = $item;
        }

        // Get warehouses for filter dropdown
        $warehouses = Warehouse::where('deleted_at', null)->get(['id', 'name']);

        // Get products for filter dropdown
        $products = Product::where('deleted_at', null)
            ->select('id', 'name', 'code')
            ->orderBy('name')
            ->get()
            ->map(function ($product) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'code' => $product->code,
                    'display_name' => $product->name . ' (' . $product->code . ')'
                ];
            });

        return response()->json([
            'products' => $data,
            'totalRows' => $totalCount,
            'currentPage' => $page,
            'maxPages' => $maxPages,
            'perPage' => $perPage,
            'warehouses' => $warehouses,
            'all_products' => $products,
            'debug' => [
                'filters' => [
                    'warehouse_id' => $request->warehouse_id,
                    'product_id' => $request->product_id,
                    'search' => $request->search,
                ],
                'requested_page' => $request->page,
                'actual_page' => $page,
                'total_records' => $totalCount,
                'records_on_page' => count($data),
            ]
        ]);
    }

    /**
     * Update price and/or cost for a product in a specific warehouse
     */
    public function updatePricing(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'update', Product::class);

        $request->validate([
            'product_warehouse_id' => 'required|exists:product_warehouse,id',
            'price' => 'nullable|numeric|min:0',
            'cost' => 'nullable|numeric|min:0',
            'reason' => 'nullable|string|max:500',
        ]);

        try {
            DB::transaction(function () use ($request) {
                $productWarehouse = product_warehouse::findOrFail($request->product_warehouse_id);
                
                $oldPrice = $productWarehouse->price;
                $oldCost = $productWarehouse->cost;
                $changeType = [];

                // Update price if provided
                if ($request->filled('price') && $request->price != $oldPrice) {
                    $productWarehouse->price = $request->price;
                    $changeType[] = 'price';
                }

                // Update cost if provided
                if ($request->filled('cost') && $request->cost != $oldCost) {
                    $productWarehouse->cost = $request->cost;
                    $changeType[] = 'cost';
                }

                if (!empty($changeType)) {
                    $productWarehouse->save();

                    // Log the change in history
                    ProductWarehousePriceHistory::create([
                        'product_id' => $productWarehouse->product_id,
                        'warehouse_id' => $productWarehouse->warehouse_id,
                        'product_variant_id' => $productWarehouse->product_variant_id,
                        'old_price' => in_array('price', $changeType) ? $oldPrice : null,
                        'new_price' => in_array('price', $changeType) ? $productWarehouse->price : null,
                        'old_cost' => in_array('cost', $changeType) ? $oldCost : null,
                        'new_cost' => in_array('cost', $changeType) ? $productWarehouse->cost : null,
                        'change_type' => implode(',', $changeType),
                        'reason' => $request->reason,
                        'changed_by' => Auth::id(),
                    ]);
                }
            });

            return response()->json(['success' => true, 'message' => 'Pricing updated successfully']);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error updating pricing: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Bulk update pricing for multiple products in a warehouse
     */
    public function bulkUpdatePricing(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'update', Product::class);

        $request->validate([
            'warehouse_id' => 'required|exists:warehouses,id',
            'updates' => 'required|array',
            'updates.*.product_id' => 'required|exists:products,id',
            'updates.*.product_variant_id' => 'nullable|exists:product_variants,id',
            'updates.*.price' => 'nullable|numeric|min:0',
            'updates.*.cost' => 'nullable|numeric|min:0',
            'reason' => 'nullable|string|max:500',
        ]);

        try {
            DB::transaction(function () use ($request) {
                foreach ($request->updates as $update) {
                    $query = product_warehouse::where('warehouse_id', $request->warehouse_id)
                        ->where('product_id', $update['product_id'])
                        ->where('deleted_at', null);

                    if (isset($update['product_variant_id'])) {
                        $query->where('product_variant_id', $update['product_variant_id']);
                    } else {
                        $query->whereNull('product_variant_id');
                    }

                    $productWarehouse = $query->first();

                    if ($productWarehouse) {
                        $oldPrice = $productWarehouse->price;
                        $oldCost = $productWarehouse->cost;
                        $changeType = [];

                        if (isset($update['price']) && $update['price'] != $oldPrice) {
                            $productWarehouse->price = $update['price'];
                            $changeType[] = 'price';
                        }

                        if (isset($update['cost']) && $update['cost'] != $oldCost) {
                            $productWarehouse->cost = $update['cost'];
                            $changeType[] = 'cost';
                        }

                        if (!empty($changeType)) {
                            $productWarehouse->save();

                            ProductWarehousePriceHistory::create([
                                'product_id' => $productWarehouse->product_id,
                                'warehouse_id' => $productWarehouse->warehouse_id,
                                'product_variant_id' => $productWarehouse->product_variant_id,
                                'old_price' => in_array('price', $changeType) ? $oldPrice : null,
                                'new_price' => in_array('price', $changeType) ? $productWarehouse->price : null,
                                'old_cost' => in_array('cost', $changeType) ? $oldCost : null,
                                'new_cost' => in_array('cost', $changeType) ? $productWarehouse->cost : null,
                                'change_type' => implode(',', $changeType),
                                'reason' => $request->reason,
                                'changed_by' => Auth::id(),
                            ]);
                        }
                    }
                }
            });

            return response()->json(['success' => true, 'message' => 'Bulk pricing updated successfully']);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error updating pricing: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Get pricing history for a specific product and warehouse
     */
    public function getPricingHistory(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'view', Product::class);

        $request->validate([
            'product_id' => 'required|exists:products,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'product_variant_id' => 'nullable|exists:product_variants,id',
        ]);

        $query = ProductWarehousePriceHistory::with(['product', 'warehouse', 'productVariant', 'changedBy'])
            ->where('product_id', $request->product_id)
            ->where('warehouse_id', $request->warehouse_id);

        if ($request->filled('product_variant_id')) {
            $query->where('product_variant_id', $request->product_variant_id);
        } else {
            $query->whereNull('product_variant_id');
        }

        $history = $query->orderBy('created_at', 'desc')->get();

        $data = [];
        foreach ($history as $record) {
            $item = [
                'id' => $record->id,
                'product_name' => $record->product->name,
                'warehouse_name' => $record->warehouse->name,
                'variant_name' => $record->productVariant ? $record->productVariant->name : null,
                'change_type' => $record->change_type,
                'old_price' => $record->old_price ? number_format($record->old_price, 2) : null,
                'new_price' => $record->new_price ? number_format($record->new_price, 2) : null,
                'old_cost' => $record->old_cost ? number_format($record->old_cost, 2) : null,
                'new_cost' => $record->new_cost ? number_format($record->new_cost, 2) : null,
                'reason' => $record->reason,
                'changed_by' => $record->changedBy ? $record->changedBy->username : 'Unknown',
                'changed_at' => $record->created_at->format('Y-m-d H:i:s'),
            ];
            $data[] = $item;
        }

        return response()->json(['history' => $data]);
    }

    /**
     * Get pricing history for all products in a warehouse
     */
    public function getWarehousePricingHistory(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'view', Product::class);

        $request->validate([
            'warehouse_id' => 'required|exists:warehouses,id',
        ]);

        $perPage = $request->limit ?: 20;
        
        $query = ProductWarehousePriceHistory::with(['product', 'warehouse', 'productVariant', 'changedBy'])
            ->where('warehouse_id', $request->warehouse_id);

        // Filter by date range
        if ($request->filled('start_date')) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }

        if ($request->filled('end_date')) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        // Filter by change type
        if ($request->filled('change_type')) {
            $query->where('change_type', 'like', '%' . $request->change_type . '%');
        }

        $history = $query->orderBy('created_at', 'desc')->paginate($perPage);

        $data = [];
        foreach ($history as $record) {
            $item = [
                'id' => $record->id,
                'product_name' => $record->product->name,
                'product_code' => $record->product->code,
                'variant_name' => $record->productVariant ? $record->productVariant->name : null,
                'change_type' => $record->change_type,
                'old_price' => $record->old_price ? number_format($record->old_price, 2) : null,
                'new_price' => $record->new_price ? number_format($record->new_price, 2) : null,
                'old_cost' => $record->old_cost ? number_format($record->old_cost, 2) : null,
                'new_cost' => $record->new_cost ? number_format($record->new_cost, 2) : null,
                'price_change' => $record->old_price && $record->new_price ? 
                    number_format($record->new_price - $record->old_price, 2) : null,
                'cost_change' => $record->old_cost && $record->new_cost ? 
                    number_format($record->new_cost - $record->old_cost, 2) : null,
                'reason' => $record->reason,
                'changed_by' => $record->changedBy ? $record->changedBy->username : 'Unknown',
                'changed_at' => $record->created_at->format('Y-m-d H:i:s'),
            ];
            $data[] = $item;
        }

        return response()->json([
            'history' => $data,
            'totalRows' => $history->total(),
            'warehouse' => Warehouse::find($request->warehouse_id, ['id', 'name']),
        ]);
    }
}
