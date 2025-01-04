<?php

namespace App\Http\Controllers;

use App\Models\Exchange;
use App\Models\Purchase;
use App\Models\ExchangeDetail;
use App\Models\product_warehouse; 
use App\Models\Unit;
use App\Models\Client;
use App\Models\Category;
use App\Models\Brand;
use App\Models\Provider;
use App\Models\Warehouse;
use App\Models\UserWarehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use DB;

class ExchangeController extends BaseController
{
    public function index(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'view', Exchange::class);
        
        $perPage = $request->limit;
        $pageStart = \Request::get('page', 1);
        $offSet = ($pageStart * $perPage) - $perPage;
        
        $exchanges = Exchange::with('customer', 'supplier', 'warehouse')
            ->where('deleted_at', '=', null);

        $totalRows = $exchanges->count();
        
        $exchanges = $exchanges->offset($offSet)
            ->limit($perPage)
            ->orderBy('id', 'desc')
            ->get();

        $data = [];
        foreach ($exchanges as $exchange) {
            $item['id'] = $exchange->id;
            $item['date'] = $exchange->date;
            $item['Ref'] = $exchange->Ref;
            $item['customer_name'] = $exchange->customer->name;
            $item['supplier_name'] = $exchange->supplier->name;
            $item['warehouse_name'] = $exchange->warehouse->name;
            $item['GrandTotal'] = $exchange->GrandTotal;
            $item['paid_amount'] = $exchange->paid_amount;
            $item['payment_status'] = $exchange->payment_status;
            $data[] = $item;
        }

        return response()->json([
            'exchanges' => $data,
            'totalRows' => $totalRows,
        ]);
    }

    public function create(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'create', Purchase::class);

        $user_auth = auth()->user();
        if($user_auth->is_all_warehouses){
            $warehouses = Warehouse::where('deleted_at', '=', null)->get(['id', 'name']);
        }else{
            $warehouses_id = UserWarehouse::where('user_id', $user_auth->id)->pluck('warehouse_id')->toArray();
            $warehouses = Warehouse::where('deleted_at', '=', null)->whereIn('id', $warehouses_id)->get(['id', 'name']);
        }

        $customers = Client::where('deleted_at', '=', null)->get(['id', 'name']);
        $suppliers = Provider::where('deleted_at', '=', null)->get(['id', 'name']);
        $categories = Category::where('deleted_at', null)->get(['id', 'name']);
        $brands = Brand::where('deleted_at', null)->get(['id', 'name']);

        return response()->json([
            'customers' => $customers,
            'suppliers' => $suppliers,
            'categories' => $categories,
            'brands' => $brands,
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'create', Exchange::class);

        request()->validate([
            'customer_id' => 'required',
            'supplier_id' => 'required',
            'warehouse_id' => 'required',
        ]);

        \DB::transaction(function () use ($request) {
            $order = new Exchange;
            $order->date = $request->date;
            $order->Ref = $this->getNumberOrder();
            $order->customer_id = $request->customer_id;
            $order->supplier_id = $request->supplier_id;
            $order->warehouse_id = $request->warehouse_id;
            $order->tax_rate = $request->tax_rate;
            $order->TaxNet = $request->TaxNet; 
            $order->discount = $request->discount;
            $order->shipping = $request->shipping;
            $order->GrandTotal = $request->GrandTotal;
            $order->payment_status = 'unpaid';
            $order->status = $request->status;
            $order->notes = $request->notes;
            $order->user_id = Auth::user()->id;
            $order->save();

            $exchange_details = [];
            
            // Products being exchanged in (purchases)
            foreach ($request->exchange_in as $product) {
                $detail = $this->handleExchangeDetail($product, $order->id, 'in');
                $exchange_details[] = $detail;
                
                if($order->status == 'completed') {
                    $this->updateProductQuantity($product, $order->warehouse_id, true);
                }
            }

            // Products being exchanged out (sales) 
            foreach ($request->exchange_out as $product) {
                $detail = $this->handleExchangeDetail($product, $order->id, 'out');
                $exchange_details[] = $detail;
                
                if($order->status == 'completed') {
                    $this->updateProductQuantity($product, $order->warehouse_id, false);
                }
            }

            ExchangeDetail::insert($exchange_details);
        });

        return response()->json(['success' => true]);
    }

    private function handleExchangeDetail($product, $exchange_id, $direction)
    {
        return [
            'exchange_id' => $exchange_id,
            'product_id' => $product['product_id'],
            'product_variant_id' => $product['product_variant_id'],
            'exchange_unit_id' => $product['exchange_unit_id'],
            'quantity' => $product['quantity'],
            'price' => $product['price'],
            'TaxNet' => $product['tax_percent'],
            'tax_method' => $product['tax_method'],
            'discount' => $product['discount'],
            'discount_method' => $product['discount_method'],
            'total' => $product['subtotal'],
            'direction' => $direction,
            'imei_number' => $product['imei_number']
        ];
    }

    private function updateProductQuantity($product, $warehouse_id, $isIncrease)
    {
        $unit = Unit::find($product['exchange_unit_id']);
        $product_warehouse = product_warehouse::where('deleted_at', '=', null)
            ->where('warehouse_id', $warehouse_id)
            ->where('product_id', $product['product_id'])
            ->where('product_variant_id', $product['product_variant_id'])
            ->first();

        if ($unit && $product_warehouse) {
            $qty_change = $product['quantity'];
            if ($unit->operator == '/') {
                $qty_change = $qty_change / $unit->operator_value;
            } else {
                $qty_change = $qty_change * $unit->operator_value;
            }

            if($isIncrease) {
                $product_warehouse->qte += $qty_change;
            } else {
                $product_warehouse->qte -= $qty_change;
            }
            
            $product_warehouse->save();
        }
    }

    private function getNumberOrder()
    {
        $last = DB::table('exchanges')->latest('id')->first();
        if ($last) {
            $item = $last->Ref;
            $nwMsg = explode("_", $item);
            $inMsg = $nwMsg[1] + 1;
            $code = $nwMsg[0] . '_' + $inMsg;
        } else {
            $code = 'EX_1111';
        }
        return $code;
    }
}