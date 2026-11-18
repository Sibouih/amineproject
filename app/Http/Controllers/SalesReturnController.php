<?php

namespace App\Http\Controllers;

use App\Mail\ReturnMail;
use App\Mail\CustomEmail;
use App\Models\EmailMessage;
use App\Models\SMSMessage;
use Illuminate\Support\Str;
use Twilio\Rest\Client as Client_Twilio;
use GuzzleHttp\Client as Client_guzzle;
use Infobip\Api\SendSmsApi;
use Infobip\Configuration;
use Infobip\Model\SmsAdvancedTextualRequest;
use Infobip\Model\SmsDestination;
use Infobip\Model\SmsTextualMessage;
use App\Models\Client;
use App\Models\Unit;
use App\Models\PaymentSaleReturns;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\product_warehouse;
use App\Models\Role;
use App\Models\Sale;
use App\Models\SaleDetail;
use App\Models\SaleReturn;
use App\Models\SaleReturnDetails;
use App\Models\Setting;
use App\Models\Warehouse;
use App\Models\User;
use App\Models\Account;
use App\Models\UserWarehouse;
use App\utils\helpers;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use \Nwidart\Modules\Facades\Module;
use App\Models\sms_gateway;
use DB;
use PDF;
use ArPHP\I18N\Arabic;

class SalesReturnController extends BaseController
{

    //------------ GET ALL Sale Return--------------\\

    public function index(request $request)
    {
        $this->authorizeForUser($request->user('api'), 'view', SaleReturn::class);
        $role = Auth::user()->roles()->first();
        $view_records = Role::findOrFail($role->id)->inRole('record_view');
        // How many items do you want to display.
        $perPage = $request->limit;
        $pageStart = \Request::get('page', 1);
        // Start displaying items from this number;
        $offSet = ($pageStart * $perPage) - $perPage;
        $order = $request->SortField;
        $dir = $request->SortType;
        $helpers = new helpers();
        // Filter fields With Params to retrieve
        $param = array(
            0 => 'like',
            1 => 'like',
            2 => '=',
            3 => 'like',
            4 => '=',
            5 => '=',
            6 => '=',
        );
        $columns = array(
            0 => 'Ref',
            1 => 'statut',
            2 => 'client_id',
            3 => 'payment_statut',
            4 => 'warehouse_id',
            5 => 'date',
            6 => 'sale_id',
        );
        $data = array();

        // Check If User Has Permission View  All Records
        $SaleReturn = SaleReturn::with('sale','facture', 'client', 'warehouse')
            ->where('deleted_at', '=', null)
            ->where(function ($query) use ($view_records) {
                if (!$view_records) {
                    return $query->where('user_id', '=', Auth::user()->id);
                }
            });

        //Multiple Filter
        $Filtred = $helpers->filter($SaleReturn, $columns, $param, $request)
        // Search With Multiple Param
            ->where(function ($query) use ($request) {
                return $query->when($request->filled('search'), function ($query) use ($request) {
                    return $query->where('Ref', 'LIKE', "%{$request->search}%")
                        ->orWhere('statut', 'LIKE', "%{$request->search}%")
                        ->orWhere('GrandTotal', $request->search)
                        ->orWhere('payment_statut', 'like', "$request->search")
                        ->orWhere(function ($query) use ($request) {
                            return $query->whereHas('client', function ($q) use ($request) {
                                $q->where('name', 'LIKE', "%{$request->search}%");
                            });
                        })
                        ->orWhere(function ($query) use ($request) {
                            return $query->whereHas('sale', function ($q) use ($request) {
                                $q->where('Ref', 'LIKE', "%{$request->search}%");
                            });
                        })
                        ->orWhere(function ($query) use ($request) {
                            return $query->whereHas('warehouse', function ($q) use ($request) {
                                $q->where('name', 'LIKE', "%{$request->search}%");
                            });
                        });
                });
            });

        $totalRows = $Filtred->count();
        if($perPage == "-1"){
            $perPage = $totalRows;
        }
        $SaleReturn = $Filtred->offset($offSet)
            ->limit($perPage)
            ->orderBy($order, $dir)
            ->get();

        foreach ($SaleReturn as $Sale_Return) {

            $item['id'] = $Sale_Return->id;
            $item['date'] = $Sale_Return->date;
            $item['Ref'] = $Sale_Return->Ref;
            $item['discount'] = $Sale_Return->discount;
            $item['shipping'] = $Sale_Return->shipping;
            $item['statut'] = $Sale_Return->statut;
            $item['qte_retturn'] = $Sale_Return->qte_retour;
            $item['warehouse_name'] = $Sale_Return['warehouse']->name;
            $item['sale_ref'] = $Sale_Return['sale']?$Sale_Return['sale']->Ref:'---';
            $item['sale_id'] = $Sale_Return['sale']?$Sale_Return['sale']->id:NULL;
            $item['client_id'] = $Sale_Return['client']->id;
            $item['client_name'] = $Sale_Return['client']->name;
            $item['client_email'] = $Sale_Return['client']->email;
            $item['client_tele'] = $Sale_Return['client']->phone;
            $item['client_code'] = $Sale_Return['client']->code;
            $item['client_adr'] = $Sale_Return['client']->adresse;
            $item['GrandTotal'] = number_format($Sale_Return['GrandTotal'], 2, '.', '');
            $item['paid_amount'] = number_format($Sale_Return['paid_amount'], 2, '.', '');
            $item['due'] = number_format($item['GrandTotal'] - $item['paid_amount'], 2, '.', '');
            $item['payment_status'] = $Sale_Return['payment_statut'];

            $data[] = $item;
        }

        $customers = client::where('deleted_at', '=', null)->get(['id', 'name']);
        $sales = Sale::where('deleted_at', '=', null)->get(['id', 'Ref']);
        $accounts = Account::where('deleted_at', '=', null)->orderBy('id', 'desc')->get(['id','account_name']);

        //get warehouses assigned to user
        $user_auth = auth()->user();
        if($user_auth->is_all_warehouses){
            $warehouses = Warehouse::where('deleted_at', '=', null)->get(['id', 'name']);
        }else{
            $warehouses_id = UserWarehouse::where('user_id', $user_auth->id)->pluck('warehouse_id')->toArray();
            $warehouses = Warehouse::where('deleted_at', '=', null)->whereIn('id', $warehouses_id)->get(['id', 'name']);
        }

        return response()->json([
            'totalRows' => $totalRows,
            'sale_Return' => $data,
            'customers' => $customers,
            'warehouses' => $warehouses,
            'sales' => $sales,
            'accounts' => $accounts,
        ]);

    }

    //------------ Store new Sale Return --------------\\

    public function store(request $request)
    {        
        $this->authorizeForUser($request->user('api'), 'create', SaleReturn::class);

        request()->validate([
            'client_id' => 'required',
            'warehouse_id' => 'required',
            'statut' => 'required',
        ]);

        \DB::transaction(function () use ($request) {
            $order = new SaleReturn;

            $order->date = $request->date;
            $order->Ref = $this->getNumberOrder();
            $order->client_id = $request->client_id;
            $order->sale_id = $request->sale_id ?? null;
            $order->warehouse_id = $request->warehouse_id;
            $order->tax_rate = $request->tax_rate;
            $order->TaxNet = $request->TaxNet;
            $order->discount = $request->discount;
            $order->shipping = $request->shipping;
            $order->GrandTotal = $request->GrandTotal;
            $order->statut = $request->statut;
            $order->payment_statut = 'unpaid';
            $order->notes = $request->notes;
            $order->user_id = Auth::user()->id;

            $order->save();

            $data = $request['details'];
            foreach ($data as $key => $value) {
                $unit = Unit::where('id', $value['sale_unit_id'])->first();

                $orderDetails[] = [
                    'sale_return_id' => $order->id,
                    'quantity' => $value['quantity'],
                    'price' => $value['Unit_price'],
                    'sale_unit_id' =>  $value['sale_unit_id'],
                    'TaxNet' => $value['tax_percent'],
                    'tax_method' => $value['tax_method'],
                    'discount' => $value['discount'],
                    'discount_method' => $value['discount_Method'],
                    'product_id' => $value['product_id'],
                    'product_variant_id' => $value['product_variant_id'],
                    'total' => $value['subtotal'],
                    'imei_number' => $value['imei_number'],
                ];

                if ($order->statut == "received") {
                    if ($value['product_variant_id'] !== null) {
                        $product_warehouse = product_warehouse::where('deleted_at', '=', null)
                            ->where('warehouse_id', $order->warehouse_id)
                            ->where('product_id', $value['product_id'])
                            ->where('product_variant_id', $value['product_variant_id'])
                            ->first();

                        if ($unit && $product_warehouse) {
                            if ($unit->operator == '/') {
                                $product_warehouse->qte += $value['quantity'] / $unit->operator_value;
                            } else {
                                $product_warehouse->qte += $value['quantity'] * $unit->operator_value;
                            }

                            $product_warehouse->save();
                        }

                    } else {
                        $product_warehouse = product_warehouse::where('deleted_at', '=', null)
                            ->where('warehouse_id', $order->warehouse_id)
                            ->where('product_id', $value['product_id'])
                            ->first();

                        if ($unit && $product_warehouse) {
                            if ($unit->operator == '/') {
                                $product_warehouse->qte += $value['quantity'] / $unit->operator_value;
                            } else {
                                $product_warehouse->qte += $value['quantity'] * $unit->operator_value;
                            }

                            $product_warehouse->save();
                        }
                    }
                }

            }
            SaleReturnDetails::insert($orderDetails);
        }, 10);

        return response()->json(['success' => true]);
    }

    //------------ Update Return Sale--------------\\

    public function update(Request $request, $id)
    {

        $this->authorizeForUser($request->user('api'), 'update', SaleReturn::class);

        request()->validate([
            'date' => 'required',
            'statut' => 'required',
            'details' => 'required|array|min:1',
        ]);

        \DB::transaction(function () use ($request, $id) {
            $role = Auth::user()->roles()->first();
            $view_records = Role::findOrFail($role->id)->inRole('record_view');
            $current_SaleReturn = SaleReturn::where('deleted_at', '=', null)->findOrFail($id);

            // Check If User Has Permission view All Records
            if (!$view_records) {
                // Check If User->id === SaleReturn->id
                $this->authorizeForUser($request->user('api'), 'check_record', $current_SaleReturn);
            }

            // A return linked to a sale keeps the sale's client and warehouse.
            // A standalone return (no sale) can change both.
            if ($current_SaleReturn->sale_id) {
                $client_id = $current_SaleReturn->client_id;
                $warehouse_id = $current_SaleReturn->warehouse_id;
            } else {
                $client_id = $request['client_id'] ?: $current_SaleReturn->client_id;
                $warehouse_id = $request['warehouse_id'] ?: $current_SaleReturn->warehouse_id;
            }
            // Stock of the new lines goes to the (possibly new) warehouse
            $request->merge(['warehouse_id' => $warehouse_id]);

            $old_return_details = SaleReturnDetails::where('sale_return_id', $id)->get();
            $new_return_details = $request['details'];
            $length = sizeof($new_return_details);

            // Get Ids details (lines added in the edit form have no id yet)
            $new_products_id = [];
            foreach ($new_return_details as $new_detail) {
                $new_products_id[] = $new_detail['id'] ?? 0;
            }

            // Init Data with old Parametre
            $old_products_id = [];
            foreach ($old_return_details as $key => $value) {
                $old_products_id[] = $value->id;

                 //check if detail has sale_unit_id Or Null
                 if($value['sale_unit_id'] !== null){
                    $unit = Unit::where('id', $value['sale_unit_id'])->first();
                }else{
                    $product_unit_sale_id = Product::with('unitSale')
                    ->where('id', $value['product_id'])
                    ->first();

                    if($product_unit_sale_id['unitSale']){
                        $unit = Unit::where('id', $product_unit_sale_id['unitSale']->id)->first();
                    }else{
                        $unit = NULL;
                    }
                }

                if($value['sale_unit_id'] !== null){
                    if ($current_SaleReturn->statut == "received") {
                        if ($value['product_variant_id'] !== null) {
                            $product_warehouse = product_warehouse::where('deleted_at', '=', null)->where('warehouse_id', $current_SaleReturn->warehouse_id)
                                ->where('product_id', $value['product_id'])->where('product_variant_id', $value['product_variant_id'])
                                ->first();

                            if ($unit && $product_warehouse) {
                                if ($unit->operator == '/') {
                                    $product_warehouse->qte -= $value['quantity'] / $unit->operator_value;
                                } else {
                                    $product_warehouse->qte -= $value['quantity'] * $unit->operator_value;
                                }
                                $product_warehouse->save();
                            }

                        } else {
                            $product_warehouse = product_warehouse::where('deleted_at', '=', null)->where('warehouse_id', $current_SaleReturn->warehouse_id)
                                ->where('product_id', $value['product_id'])
                                ->first();

                            if ($unit && $product_warehouse) {
                                if ($unit->operator == '/') {
                                    $product_warehouse->qte -= $value['quantity'] / $unit->operator_value;
                                } else {
                                    $product_warehouse->qte -= $value['quantity'] * $unit->operator_value;
                                }
                                $product_warehouse->save();
                            }
                        }
                    }

                    // Delete Detail
                    if (!in_array($old_products_id[$key], $new_products_id)) {
                        $SaleReturnDetails = SaleReturnDetails::findOrFail($value->id);
                        $SaleReturnDetails->delete();
                    }
                }

            }

            // Update Data with New request
            foreach ($new_return_details as $key => $product_detail) {
               
                $get_type_product = Product::where('id', $product_detail['product_id'])->first()->type;
                $product_detail['id'] = $product_detail['id'] ?? 0;
                $product_detail['imei_number'] = $product_detail['imei_number'] ?? null;
                $product_detail['product_variant_id'] = $product_detail['product_variant_id'] ?? null;
                $no_unit = $product_detail['no_unit'] ?? 1;

                if($no_unit !== 0 || $get_type_product == 'is_service'){

                    $unit_prod = Unit::where('id', $product_detail['sale_unit_id'])->first();

                    if ($request['statut'] == "received") {

                        if ($product_detail['product_variant_id'] !== null) {
                            $product_warehouse = product_warehouse::where('deleted_at', '=', null)
                                ->where('warehouse_id', $request->warehouse_id)
                                ->where('product_id', $product_detail['product_id'])
                                ->where('product_variant_id', $product_detail['product_variant_id'])
                                ->first();

                            if ($unit_prod && $product_warehouse) {
                                if ($unit_prod->operator == '/') {
                                    $product_warehouse->qte += $product_detail['quantity'] / $unit_prod->operator_value;
                                } else {
                                    $product_warehouse->qte += $product_detail['quantity'] * $unit_prod->operator_value;
                                }
                                $product_warehouse->save();
                            }

                        } else {
                            $product_warehouse = product_warehouse::where('deleted_at', '=', null)
                                ->where('warehouse_id', $request->warehouse_id)
                                ->where('product_id', $product_detail['product_id'])
                                ->first();

                            if ($unit_prod && $product_warehouse) {
                                if ($unit_prod->operator == '/') {
                                    $product_warehouse->qte += $product_detail['quantity'] / $unit_prod->operator_value;
                                } else {
                                    $product_warehouse->qte += $product_detail['quantity'] * $unit_prod->operator_value;
                                }
                                $product_warehouse->save();
                            }
                        }
                    }

                    $orderDetails['sale_return_id'] = $id;
                    $orderDetails['sale_unit_id'] = $product_detail['sale_unit_id'];
                    $orderDetails['quantity'] = $product_detail['quantity'];
                    $orderDetails['price'] = $product_detail['Unit_price'];
                    $orderDetails['TaxNet'] = $product_detail['tax_percent'];
                    $orderDetails['tax_method'] = $product_detail['tax_method'];
                    $orderDetails['discount'] = $product_detail['discount'];
                    $orderDetails['discount_method'] = $product_detail['discount_Method'];
                    $orderDetails['product_id'] = $product_detail['product_id'];
                    $orderDetails['product_variant_id'] = $product_detail['product_variant_id'];
                    $orderDetails['total'] = $product_detail['subtotal'];
                    $orderDetails['imei_number'] = $product_detail['imei_number'];

                    if (!in_array($product_detail['id'], $old_products_id)) {
                        SaleReturnDetails::Create($orderDetails);
                    } else {
                        SaleReturnDetails::where('id', $product_detail['id'])->update($orderDetails);
                    }
                }

            }

            // If the new GrandTotal is less than what was already paid, cap paid_amount
            $paid_amount = $current_SaleReturn->paid_amount;
            if ($paid_amount > $request['GrandTotal']) {
                $paid_amount = $request['GrandTotal'];
            }

            $due = $request['GrandTotal'] - $paid_amount;
            if (round($due, 2) <= 0) {
                $payment_statut = 'paid';
            } else if ($due != $request['GrandTotal']) {
                $payment_statut = 'partial';
            } else if ($due == $request['GrandTotal']) {
                $payment_statut = 'unpaid';
            }

            $current_SaleReturn->update([
                'date' => $request['date'],
                'client_id' => $client_id,
                'warehouse_id' => $warehouse_id,
                'notes' => $request['notes'],
                'statut' => $request['statut'],
                'tax_rate' => $request['tax_rate'],
                'TaxNet' => $request['TaxNet'],
                'discount' => $request['discount'],
                'shipping' => $request['shipping'],
                'GrandTotal' => $request['GrandTotal'],
                'paid_amount' => $paid_amount,
                'payment_statut' => $payment_statut,
            ]);

        }, 10);

        return response()->json(['success' => true]);
    }

    //------------ Delete Sale Return--------------\\

    public function destroy(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'delete', SaleReturn::class);

        \DB::transaction(function () use ($id, $request) {
            $role = Auth::user()->roles()->first();
            $view_records = Role::findOrFail($role->id)->inRole('record_view');
            $current_SaleReturn = SaleReturn::findOrFail($id);
            $old_return_details = SaleReturnDetails::where('sale_return_id', $id)->get();

            // Check If User Has Permission view All Records
            if (!$view_records) {
                // Check If User->id === current_SaleReturn->id
                $this->authorizeForUser($request->user('api'), 'check_record', $current_SaleReturn);
            }

            foreach ($old_return_details as $key => $value) {

                 //check if detail has sale_unit_id Or Null
                 if($value['sale_unit_id'] !== null){
                    $unit = Unit::where('id', $value['sale_unit_id'])->first();
                }else{
                    $product_unit_sale_id = Product::with('unitSale')
                    ->where('id', $value['product_id'])
                    ->first();

                    if($product_unit_sale_id['unitSale']){
                        $unit = Unit::where('id', $product_unit_sale_id['unitSale']->id)->first();
                    }{
                        $unit = NULL;
                    }
                }

                if ($current_SaleReturn->statut == "received") {
                    if ($value['product_variant_id'] !== null) {
                        $product_warehouse = product_warehouse::where('deleted_at', '=', null)->where('warehouse_id', $current_SaleReturn->warehouse_id)
                            ->where('product_id', $value['product_id'])->where('product_variant_id', $value['product_variant_id'])
                            ->first();

                        if ($unit && $product_warehouse) {
                            if ($unit->operator == '/') {
                                $product_warehouse->qte -= $value['quantity'] / $unit->operator_value;
                            } else {
                                $product_warehouse->qte -= $value['quantity'] * $unit->operator_value;
                            }
                            $product_warehouse->save();
                        }

                    } else {
                        $product_warehouse = product_warehouse::where('deleted_at', '=', null)->where('warehouse_id', $current_SaleReturn->warehouse_id)
                            ->where('product_id', $value['product_id'])
                            ->first();

                        if ($unit && $product_warehouse) {
                            if ($unit->operator == '/') {
                                $product_warehouse->qte -= $value['quantity'] / $unit->operator_value;
                            } else {
                                $product_warehouse->qte -= $value['quantity'] * $unit->operator_value;
                            }
                            $product_warehouse->save();
                        }
                    }
                }

            }

            $current_SaleReturn->details()->delete();
            $current_SaleReturn->update([
                'deleted_at' => Carbon::now(),
            ]);

             // get all payments
             $payments = PaymentSaleReturns::where('sale_return_id', $id)->get();

             foreach ($payments as $payment) {
 
                 $account = Account::find($payment->account_id);
 
                 if ($account) {
                     $account->update([
                         'balance' => $account->balance + $payment->montant,
                     ]);
                 }
 
             }

            PaymentSaleReturns::where('sale_return_id', $id)->update([
                'deleted_at' => Carbon::now(),
            ]);

        }, 10);

        return response()->json(['success' => true]);
    }

    //-------------- Delete by selection  ---------------\\

    public function delete_by_selection(Request $request)
    {

        $this->authorizeForUser($request->user('api'), 'delete', SaleReturn::class);

        \DB::transaction(function () use ($request) {
            $role = Auth::user()->roles()->first();
            $view_records = Role::findOrFail($role->id)->inRole('record_view');
            $selectedIds = $request->selectedIds;
            foreach ($selectedIds as $SaleReturn_id) {

                $current_SaleReturn = SaleReturn::findOrFail($SaleReturn_id);
                $old_return_details = SaleReturnDetails::where('sale_return_id', $SaleReturn_id)->get();
                // Check If User Has Permission view All Records
                if (!$view_records) {
                    // Check If User->id === current_SaleReturn->id
                    $this->authorizeForUser($request->user('api'), 'check_record', $current_SaleReturn);
                }

                foreach ($old_return_details as $key => $value) {

                    //check if detail has sale_unit_id Or Null
                    if($value['sale_unit_id'] !== null){
                       $unit = Unit::where('id', $value['sale_unit_id'])->first();
                   }else{
                       $product_unit_sale_id = Product::with('unitSale')
                       ->where('id', $value['product_id'])
                       ->first();
                       
                       if($product_unit_sale_id['unitSale']){
                        $unit = Unit::where('id', $product_unit_sale_id['unitSale']->id)->first();
                    }{
                        $unit = NULL;
                    }
                   }
   
                   if ($current_SaleReturn->statut == "received") {
                       if ($value['product_variant_id'] !== null) {
                           $product_warehouse = product_warehouse::where('deleted_at', '=', null)->where('warehouse_id', $current_SaleReturn->warehouse_id)
                               ->where('product_id', $value['product_id'])->where('product_variant_id', $value['product_variant_id'])
                               ->first();
   
                           if ($unit && $product_warehouse) {
                               if ($unit->operator == '/') {
                                   $product_warehouse->qte -= $value['quantity'] / $unit->operator_value;
                               } else {
                                   $product_warehouse->qte -= $value['quantity'] * $unit->operator_value;
                               }
                               $product_warehouse->save();
                           }
   
                       } else {
                           $product_warehouse = product_warehouse::where('deleted_at', '=', null)->where('warehouse_id', $current_SaleReturn->warehouse_id)
                               ->where('product_id', $value['product_id'])
                               ->first();
   
                           if ($unit && $product_warehouse) {
                               if ($unit->operator == '/') {
                                   $product_warehouse->qte -= $value['quantity'] / $unit->operator_value;
                               } else {
                                   $product_warehouse->qte -= $value['quantity'] * $unit->operator_value;
                               }
                               $product_warehouse->save();
                           }
                       }
                   }
   
               }

                $current_SaleReturn->details()->delete();
                $current_SaleReturn->update([
                    'deleted_at' => Carbon::now(),
                ]);

                  // get all payments
                $payments = PaymentSaleReturns::where('sale_return_id', $SaleReturn_id)->get();

                foreach ($payments as $payment) {
    
                    $account = Account::find($payment->account_id);
    
                    if ($account) {
                        $account->update([
                            'balance' => $account->balance + $payment->montant,
                        ]);
                    }
    
                }
                PaymentSaleReturns::where('sale_return_id', $SaleReturn_id)->update([
                    'deleted_at' => Carbon::now(),
                ]);
            }

        }, 10);

        return response()->json(['success' => true]);
    }



    //------------- GET Payments Sale Return-----------\\

    public function Payment_Returns(Request $request, $id)
    {

        $this->authorizeForUser($request->user('api'), 'view', PaymentSaleReturns::class);

        $role = Auth::user()->roles()->first();
        $view_records = Role::findOrFail($role->id)->inRole('record_view');
        $SaleReturn = SaleReturn::findOrFail($id);

        // Check If User Has Permission view All Records
        if (!$view_records) {
            // Check If User->id === SaleReturn->id
            $this->authorizeForUser($request->user('api'), 'check_record', $SaleReturn);
        }

        $payments = PaymentSaleReturns::with('SaleReturn')
            ->where('sale_return_id', $id)
            ->where(function ($query) use ($view_records) {
                if (!$view_records) {
                    return $query->where('user_id', '=', Auth::user()->id);
                }
            })->orderBy('id', 'DESC')->get();

        $due = $SaleReturn->GrandTotal - $SaleReturn->paid_amount;

        return response()->json(['payments' => $payments, 'due' => $due]);
    }

  

    //------------ Reference Order Of Sale Return --------------\\

    public function getNumberOrder()
    {
        $last = DB::table('sale_returns')->latest('id')->first();

        if ($last) {
            $item = $last->Ref;
            $nwMsg = explode("_", $item);
            $inMsg = $nwMsg[1] + 1;
            $code = $nwMsg[0] . '_' . $inMsg;
        } else {
            $code = 'RT_1111';
        }
        return $code;
    }

    //---------------- Get Details Sale Return  -----------------\\

    public function show(Request $request, $id)
    {

        $this->authorizeForUser($request->user('api'), 'view', SaleReturn::class);
        $role = Auth::user()->roles()->first();
        $view_records = Role::findOrFail($role->id)->inRole('record_view');
        $Sale_Return = SaleReturn::with('sale','details.product.unitSale')
            ->where('deleted_at', '=', null)
            ->findOrFail($id);

        $details = array();

        // Check If User Has Permission view All Records
        if (!$view_records) {
            // Check If User->id === SaleReturn->id
            $this->authorizeForUser($request->user('api'), 'check_record', $Sale_Return);
        }

        $return_details['Ref'] = $Sale_Return->Ref;
        $return_details['sale_id'] = $Sale_Return->sale_id?$Sale_Return['sale']->id:NULL;
        $return_details['sale_ref'] = $Sale_Return['sale']?$Sale_Return['sale']->Ref:'---';
        $return_details['date'] = $Sale_Return->date;
        $return_details['note'] = $Sale_Return->notes;
        $return_details['statut'] = $Sale_Return->statut;
        $return_details['discount'] = $Sale_Return->discount;
        $return_details['shipping'] = $Sale_Return->shipping;
        $return_details['tax_rate'] = $Sale_Return->tax_rate;
        $return_details['TaxNet'] = $Sale_Return->TaxNet;
        $return_details['client_name'] = $Sale_Return['client']->name;
        $return_details['client_phone'] = $Sale_Return['client']->phone;
        $return_details['client_adr'] = $Sale_Return['client']->adresse;
        $return_details['client_email'] = $Sale_Return['client']->email;
        $return_details['client_tax'] = $Sale_Return['client']->tax_number;
        $return_details['warehouse'] = $Sale_Return['warehouse']->name;
        $return_details['GrandTotal'] = number_format($Sale_Return->GrandTotal, 2, '.', '');
        $return_details['paid_amount'] = number_format($Sale_Return->paid_amount, 2, '.', '');
        $return_details['due'] = number_format($return_details['GrandTotal'] - $return_details['paid_amount'], 2, '.', '');
        $return_details['payment_status'] = $Sale_Return->payment_statut;

        foreach ($Sale_Return['details'] as $detail) {

             //check if detail has sale_unit_id Or Null
             if($detail->sale_unit_id !== null){
                $unit = Unit::where('id', $detail->sale_unit_id)->first();
            }else{
                $product_unit_sale_id = Product::with('unitSale')
                ->where('id', $detail->product_id)
                ->first();

                if($product_unit_sale_id['unitSale']){
                    $unit = Unit::where('id', $product_unit_sale_id['unitSale']->id)->first();
                }else{
                    $unit = NULL;
                }

            }
            
            if ($detail->product_variant_id) {

                $productsVariants = ProductVariant::where('product_id', $detail->product_id)
                    ->where('id', $detail->product_variant_id)->first();

                $data['code'] = $productsVariants->code;
                $data['name'] = '['.$productsVariants->name . ']' . $detail['product']['name'];

               
            } else {
                $data['code'] = $detail['product']['code'];
                $data['name'] = $detail['product']['name'];
            }

            $data['quantity'] = $detail->quantity;
            $data['total'] = $detail->total;
            $data['price'] = $detail->price;
            $data['unit_sale'] = $unit?$unit->ShortName:'';

            if ($detail->discount_method == '2') {
                $data['DiscountNet'] = $detail->discount;
            } else {
                $data['DiscountNet'] = $detail->price * $detail->discount / 100;
            }

            $tax_price = $detail->TaxNet * (($detail->price - $data['DiscountNet']) / 100);
            $data['Unit_price'] = $detail->price;
            $data['discount'] = $detail->discount;

            if ($detail->tax_method == '1') {
                $data['Net_price'] = $detail->price - $data['DiscountNet'];
                $data['taxe'] = $tax_price;
            } else {
                $data['Net_price'] = ($detail->price - $data['DiscountNet']) / (($detail->TaxNet / 100) + 1);
                $data['taxe'] = $detail->price - $data['Net_price'] - $data['DiscountNet'];
            }

            $data['is_imei'] = $detail['product']['is_imei'];
            $data['imei_number'] = $detail->imei_number;

            $details[] = $data;

        }

        $company = Setting::where('deleted_at', '=', null)->first();

        return response()->json([
            'details' => $details,
            'sale_Return' => $return_details,
            'company' => $company,
        ]);
    }

    //---------------- Show Elements Sale Return ---------------\\

    public function create(Request $request)
    {

        //

    }

    //---------------- edit ---------------\\

    public function edit(Request $request , $id)
    {

        //

    }

    public function create_sell_return(Request $request , $id)
    {

        $this->authorizeForUser($request->user('api'), 'create', SaleReturn::class);
        $role = Auth::user()->roles()->first();
        $view_records = Role::findOrFail($role->id)->inRole('record_view');
        $SaleReturn = Sale::with('details.product.unitSale')
            ->where('deleted_at', '=', null)
            ->findOrFail($id);

        $details = array();

        // Check If User Has Permission view All Records
        if (!$view_records) {
            // Check If User->id === SaleReturn->id
            $this->authorizeForUser($request->user('api'), 'check_record', $SaleReturn);
        }

        $Return_detail['client_id'] = $SaleReturn->client_id;
        $Return_detail['warehouse_id'] = $SaleReturn->warehouse_id;
        $Return_detail['sale_id'] = $SaleReturn->id;
        $Return_detail['sale_ref'] = $SaleReturn->Ref;
        $Return_detail['tax_rate'] = 0;
        $Return_detail['TaxNet'] = 0;
        $Return_detail['discount'] = 0;
        $Return_detail['shipping'] = 0;
        $Return_detail['statut'] = "received";
        $Return_detail['notes'] = "";

        $detail_id = 0;
        foreach ($SaleReturn['details'] as $detail) {

            //check if detail has sale_unit_id Or Null
            if($detail->sale_unit_id !== null){
                $unit = Unit::where('id', $detail->sale_unit_id)->first();
                $data['no_unit'] = 1;
            }else{
                $product_unit_sale_id = Product::with('unitSale')
                ->where('id', $detail->product_id)
                ->first();

                if($product_unit_sale_id['unitSale']){
                    $unit = Unit::where('id', $product_unit_sale_id['unitSale']->id)->first();
                }else{
                    $unit = NULL;
                }

                $data['no_unit'] = 0;
            }

            if ($detail->product_variant_id) {
                $item_product = product_warehouse::where('product_id', $detail->product_id)
                    ->where('product_variant_id', $detail->product_variant_id)
                    ->where('deleted_at', '=', null)
                    ->where('warehouse_id', $SaleReturn->warehouse_id)
                    ->first();

                $productsVariants = ProductVariant::where('product_id', $detail->product_id)
                    ->where('id', $detail->product_variant_id)->first();

                $item_product ? $data['del'] = 0 : $data['del'] = 1;
                $data['product_variant_id'] = $detail->product_variant_id;
                $data['code'] = $productsVariants->code;
                $data['name'] = '['.$productsVariants->name . ']' . $detail['product']['name'];

            } else {
                $item_product = product_warehouse::where('product_id', $detail->product_id)
                    ->where('warehouse_id', $SaleReturn->warehouse_id)
                    ->where('deleted_at', '=', null)->where('product_variant_id', '=', null)
                    ->first();

                $item_product ? $data['del'] = 0 : $data['del'] = 1;
                $data['product_variant_id'] = null;
                $data['code'] = $detail['product']['code'];
                $data['name'] = $detail['product']['name'];

            }

            $data['id'] = $detail->id;
            $data['detail_id'] = $detail_id += 1;
            $data['product_type'] = $detail['product']['type'];
            $data['quantity'] = 0;
            $data['sale_quantity'] = $detail->quantity;
            $data['product_id'] = $detail->product_id;
            $data['unitSale'] = $unit?$unit->ShortName:'';
            $data['sale_unit_id'] = $unit?$unit->id:'';
            $data['is_imei'] = $detail['product']['is_imei'];
            $data['imei_number'] = $detail->imei_number;

            if ($detail->discount_method == '2') {
                $data['DiscountNet'] = $detail->discount;
            } else {
                $data['DiscountNet'] = $detail->price * $detail->discount / 100;
            }

            $tax_price = $detail->TaxNet * (($detail->price - $data['DiscountNet']) / 100);
            $data['Unit_price'] = $detail->price;
            $data['tax_percent'] = $detail->TaxNet;
            $data['tax_method'] = $detail->tax_method;
            $data['discount'] = $detail->discount;
            $data['discount_Method'] = $detail->discount_method;

            if ($detail->tax_method == '1') {

                $data['Net_price'] = $detail->price - $data['DiscountNet'];
                $data['taxe'] = $tax_price;
                $data['subtotal'] = ($data['Net_price'] * $data['quantity']) + ($tax_price * $data['quantity']);
            } else {
                $data['Net_price'] = ($detail->price - $data['DiscountNet']) / (($detail->TaxNet / 100) + 1);
                $data['taxe'] = $detail->price - $data['Net_price'] - $data['DiscountNet'];
                $data['subtotal'] = ($data['Net_price'] * $data['quantity']) + ($tax_price * $data['quantity']);
            }

            $details[] = $data;
        }


        return response()->json([
            'details' => $details,
            'sale_return' => $Return_detail,
        ]);

    }

    //------------- Sale Return PDF-----------\\

    public function Return_pdf(Request $request, $id)
    {

        $details = array();
        $helpers = new helpers();
        $Sale_Return = SaleReturn::with('sale','details.product.unitSale')
            ->where('deleted_at', '=', null)
            ->findOrFail($id);

        $return_details['client_name'] = $Sale_Return['client']->name;
        $return_details['client_phone'] = $Sale_Return['client']->phone;
        $return_details['client_adr'] = $Sale_Return['client']->adresse;
        $return_details['client_email'] = $Sale_Return['client']->email;
        $return_details['client_tax'] = $Sale_Return['client']->tax_number;
        $return_details['TaxNet'] = number_format($Sale_Return->TaxNet, 2, '.', '');
        $return_details['discount'] = number_format($Sale_Return->discount, 2, '.', '');
        $return_details['shipping'] = number_format($Sale_Return->shipping, 2, '.', '');
        $return_details['statut'] = $Sale_Return->statut;
        $return_details['sale_ref'] = $Sale_Return['sale']?$Sale_Return['sale']->Ref:'---';
        $return_details['Ref'] = $Sale_Return->Ref;
        $return_details['date'] = $Sale_Return->date;
        $return_details['GrandTotal'] = number_format($Sale_Return->GrandTotal, 2, '.', '');
        $return_details['paid_amount'] = number_format($Sale_Return->paid_amount, 2, '.', '');
        $return_details['due'] = number_format($return_details['GrandTotal'] - $return_details['paid_amount'], 2, '.', '');
        $return_details['payment_status'] = $Sale_Return->payment_statut;

        $detail_id = 0;
        foreach ($Sale_Return['details'] as $detail) {
             //check if detail has sale_unit_id Or Null
             if($detail->sale_unit_id !== null){
                $unit = Unit::where('id', $detail->sale_unit_id)->first();
            }else{
                $product_unit_sale_id = Product::with('unitSale')
                ->where('id', $detail->product_id)
                ->first();

                if($product_unit_sale_id['unitSale']){
                    $unit = Unit::where('id', $product_unit_sale_id['unitSale']->id)->first();
                }else{
                    $unit = NULL;
                }

            }

            if ($detail->product_variant_id) {
                $productsVariants = ProductVariant::where('product_id', $detail->product_id)
                    ->where('id', $detail->product_variant_id)
                    ->first();
                $data['code'] = $productsVariants->code;
                $data['name'] = '['.$productsVariants->name . ']' . $detail['product']['name'];
            } else {
                $data['code'] = $detail['product']['code'];
                $data['name'] = $detail['product']['name'];
            }
                $data['detail_id'] = $detail_id += 1;
                $data['quantity'] = number_format($detail->quantity, 2, '.', '');
                $data['total'] = number_format($detail->total, 2, '.', '');
                $data['unitSale'] = $unit?$unit->ShortName:'';
                $data['price'] = number_format($detail->price, 2, '.', '');

            if ($detail->discount_method == '2') {
                $data['DiscountNet'] = number_format($detail->discount, 2, '.', '');
            } else {
                $data['DiscountNet'] = number_format($detail->price * $detail->discount / 100, 2, '.', '');
            }

            $tax_price = $detail->TaxNet * (($detail->price - $data['DiscountNet']) / 100);
            $data['Unit_price'] = number_format($detail->price, 2, '.', '');
            $data['discount'] = $detail->discount;number_format($detail->discount, 2, '.', '');

            if ($detail->tax_method == '1') {
                $data['Net_price'] = $detail->price - $data['DiscountNet'];
                $data['taxe'] = number_format($tax_price, 2, '.', '');
            } else {
                $data['Net_price'] = ($detail->price - $data['DiscountNet']) / (($detail->TaxNet / 100) + 1);
                $data['taxe'] = number_format($detail->price - $data['Net_price'] - $data['DiscountNet'], 2, '.', '');
            }

            $data['is_imei'] = $detail['product']['is_imei'];
            $data['imei_number'] = $detail->imei_number;

            $details[] = $data;
        }

        $settings = Setting::where('deleted_at', '=', null)->first();
        $symbol = $helpers->Get_Currency_Code();

        $Html = view('pdf.Sales_Return_pdf', [
            'symbol' => $symbol,
            'setting' => $settings,
            'return_sale' => $return_details,
            'details' => $details,
        ])->render();

        $arabic = new Arabic();
        $p = $arabic->arIdentify($Html);

        for ($i = count($p)-1; $i >= 0; $i-=2) {
            $utf8ar = $arabic->utf8Glyphs(substr($Html, $p[$i-1], $p[$i] - $p[$i-1]));
            $Html = substr_replace($Html, $utf8ar, $p[$i-1], $p[$i] - $p[$i-1]);
        }

        $pdf = PDF::loadHTML($Html);

        return $pdf->download('Sales_Return.pdf');
    }

    //------------- Show Form Edit Sale Return-----------\\

    public function edit_sell_return(Request $request, $id, $sale_id)
    {

        $this->authorizeForUser($request->user('api'), 'update', SaleReturn::class);
        $role = Auth::user()->roles()->first();
        $view_records = Role::findOrFail($role->id)->inRole('record_view');
        $SaleReturn = SaleReturn::with('sale','details.product.unitSale')
            ->where('deleted_at', '=', null)
            ->findOrFail($id);
        $details = array();
        // Check If User Has Permission view All Records
        if (!$view_records) {
            // Check If User->id === SaleReturn->id
            $this->authorizeForUser($request->user('api'), 'check_record', $SaleReturn);
        }
      
        $Return_detail['id'] = $SaleReturn->id;
        $Return_detail['Ref'] = $SaleReturn->Ref;
        $Return_detail['client_id'] = $SaleReturn->client_id;
        $Return_detail['client_name'] = optional($SaleReturn->client)->name;
        $Return_detail['warehouse_id'] = $SaleReturn->warehouse_id;
        $Return_detail['warehouse_name'] = optional($SaleReturn->warehouse)->name;
        $Return_detail['paid_amount'] = $SaleReturn->paid_amount;
        $Return_detail['sale_id'] = $SaleReturn->sale_id && $SaleReturn['sale'] ? $SaleReturn['sale']->id : NULL;
        $Return_detail['sale_ref'] = $SaleReturn['sale']?$SaleReturn['sale']->Ref:'---';
        $Return_detail['date'] = $SaleReturn->date;
        $Return_detail['tax_rate'] = $SaleReturn->tax_rate;
        $Return_detail['TaxNet'] = $SaleReturn->TaxNet;
        $Return_detail['discount'] = $SaleReturn->discount;
        $Return_detail['shipping'] = $SaleReturn->shipping;
        $Return_detail['notes'] = $SaleReturn->notes;
        $Return_detail['statut'] = $SaleReturn->statut;

        $detail_id = 0;
        foreach ($SaleReturn['details'] as $detail) {

            //check if detail has sale_unit_id Or Null
            if($detail->sale_unit_id !== null){
                $unit = Unit::where('id', $detail->sale_unit_id)->first();
                $data['no_unit'] = 1;
            }else{
                $product_unit_sale_id = Product::with('unitSale')
                ->where('id', $detail->product_id)
                ->first();

                if($product_unit_sale_id['unitSale']){
                    $unit = Unit::where('id', $product_unit_sale_id['unitSale']->id)->first();
                }else{
                    $unit = NULL;
                }

                $data['no_unit'] = 0;
            }

            if ($detail->product_variant_id) {
                $item_product = product_warehouse::where('product_id', $detail->product_id)
                    ->where('product_variant_id', $detail->product_variant_id)
                    ->where('deleted_at', '=', null)
                    ->where('warehouse_id', $SaleReturn->warehouse_id)
                    ->first();

                $productsVariants = ProductVariant::where('product_id', $detail->product_id)
                    ->where('id', $detail->product_variant_id)->first();

                $item_product ? $data['del'] = 0 : $data['del'] = 1;
                $data['product_variant_id'] = $detail->product_variant_id;

                $data['code'] = $productsVariants->code;
                $data['name'] = '['.$productsVariants->name . ']' . $detail['product']['name'];

            } else {
                $item_product = product_warehouse::where('product_id', $detail->product_id)
                    ->where('warehouse_id', $SaleReturn->warehouse_id)
                    ->where('deleted_at', '=', null)->where('product_variant_id', '=', null)
                    ->first();

                $item_product ? $data['del'] = 0 : $data['del'] = 1;
                $data['product_variant_id'] = null;
                $data['code'] = $detail['product']['code'];
                $data['name'] = $detail['product']['name'];

            }

            $data['id'] = $detail->id;
            $data['detail_id'] = $detail_id += 1;

            if($sale_id && $sale_id !== 'null'){
                $sell_detail = SaleDetail::where('sale_id', $sale_id)
                ->where('product_id', $detail->product_id)
                ->where('product_variant_id', $detail->product_variant_id)
                ->first();
                $data['sale_quantity'] = $sell_detail ? $sell_detail->quantity : $detail->quantity;
            }else{
                // Standalone return (no associated sale) - use the return's own quantity as the max
                $data['sale_quantity'] = $detail->quantity;
            }
            // Current stock in the return's warehouse (base unit + sale unit)
            $data['fix_stock'] = $item_product ? $item_product->qte : 0;
            if ($item_product && $unit) {
                $data['stock'] = $unit->operator == '/'
                    ? $item_product->qte * $unit->operator_value
                    : $item_product->qte / $unit->operator_value;
            } else {
                $data['stock'] = $item_product ? $item_product->qte : 0;
            }
            $data['product_type'] = $detail['product']['type'];
            $data['quantity'] = $detail->quantity;
            $data['product_id'] = $detail->product_id;
            $data['unitSale'] = $unit?$unit->ShortName:'';
            $data['sale_unit_id'] = $unit?$unit->id:'';
            $data['is_imei'] = $detail['product']['is_imei'];
            $data['imei_number'] = $detail->imei_number;

            if ($detail->discount_method == '2') {
                $data['DiscountNet'] = $detail->discount;
            } else {
                $data['DiscountNet'] = $detail->price * $detail->discount / 100;
            }

            $tax_price = $detail->TaxNet * (($detail->price - $data['DiscountNet']) / 100);
            $data['Unit_price'] = $detail->price;
            $data['tax_percent'] = $detail->TaxNet;
            $data['tax_method'] = $detail->tax_method;
            $data['discount'] = $detail->discount;
            $data['discount_Method'] = $detail->discount_method;

            if ($detail->tax_method == '1') {

                $data['Net_price'] = $detail->price - $data['DiscountNet'];
                $data['taxe'] = $tax_price;
                $data['subtotal'] = ($data['Net_price'] * $data['quantity']) + ($tax_price * $data['quantity']);
            } else {
                $data['Net_price'] = ($detail->price - $data['DiscountNet']) / (($detail->TaxNet / 100) + 1);
                $data['taxe'] = $detail->price - $data['Net_price'] - $data['DiscountNet'];
                $data['subtotal'] = ($data['Net_price'] * $data['quantity']) + ($tax_price * $data['quantity']);
            }

            $details[] = $data;
        }

        // Standalone return: client and warehouse are editable, send the lists
        $clients = [];
        $warehouses = [];
        if (!$Return_detail['sale_id']) {
            $clients = Client::where('deleted_at', '=', null)->get(['id', 'name']);

            $user_auth = auth()->user();
            if ($user_auth->is_all_warehouses) {
                $warehouses = Warehouse::where('deleted_at', '=', null)->get(['id', 'name']);
            } else {
                $warehouses_id = UserWarehouse::where('user_id', $user_auth->id)->pluck('warehouse_id')->toArray();
                $warehouses = Warehouse::where('deleted_at', '=', null)->whereIn('id', $warehouses_id)->get(['id', 'name']);
            }
        }

        return response()->json([
            'details' => $details,
            'sale_return' => $Return_detail,
            'clients' => $clients,
            'warehouses' => $warehouses,
        ]);
    }

    //------------- Message tags for a sale return (email / SMS) -----------\\

    private function Return_Message_Tags($SaleReturn)
    {
        $helpers = new helpers();
        $currency = $helpers->Get_Currency();
        $settings = Setting::where('deleted_at', '=', null)->first();

        return [
            '{contact_name}' => $SaleReturn['client']->name,
            '{business_name}' => $settings ? $settings->CompanyName : '',
            '{invoice_url}' => url('/api/return_sale_pdf/' . $SaleReturn->id . '?' . Str::random(10)),
            '{invoice_number}' => $SaleReturn->Ref,
            '{total_amount}' => $currency . ' ' . number_format($SaleReturn->GrandTotal, 2, '.', ','),
            '{paid_amount}' => $currency . ' ' . number_format($SaleReturn->paid_amount, 2, '.', ','),
            '{due_amount}' => $currency . ' ' . number_format($SaleReturn->GrandTotal - $SaleReturn->paid_amount, 2, '.', ','),
        ];
    }

    //------------- Send sale return on Email -----------\\

    public function Send_Email(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'view', SaleReturn::class);

        $SaleReturn = SaleReturn::with('client')->where('deleted_at', '=', null)->findOrFail($request->id);

        $receiver_email = $SaleReturn['client'] ? $SaleReturn['client']->email : null;
        if (!$receiver_email || !filter_var($receiver_email, FILTER_VALIDATE_EMAIL)) {
            return response()->json(['success' => false, 'message' => 'client_email_missing'], 422);
        }

        // Custom message "sale_return" if it exists, otherwise a default text
        $emailMessage = EmailMessage::where('name', 'sale_return')->first();
        $message_subject = $emailMessage ? $emailMessage->subject : 'مرتجع البيع {invoice_number}';
        $message_body = $emailMessage ? $emailMessage->body :
            '<p>مرحبا {contact_name}،</p>'
            . '<p>تجدون رفقته مرتجع البيع رقم <strong>{invoice_number}</strong> بمبلغ <strong>{total_amount}</strong>'
            . ' (المدفوع: {paid_amount} - المتبقي: {due_amount}).</p>'
            . '<p><a href="{invoice_url}">تحميل المرتجع PDF</a></p>'
            . '<p>{business_name}</p>';

        $tags = $this->Return_Message_Tags($SaleReturn);

        $email['subject'] = strtr($message_subject, $tags);
        $email['body'] = strtr($message_body, $tags);
        $email['company_name'] = $tags['{business_name}'];

        $this->Set_config_mail();

        try {
            Mail::to($receiver_email)->send(new CustomEmail($email));
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }

        return response()->json(['success' => true]);
    }

    //------------- Send sale return by SMS -----------\\

    public function Send_SMS(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'view', SaleReturn::class);

        $SaleReturn = SaleReturn::with('client')->where('deleted_at', '=', null)->findOrFail($request->id);

        $receiverNumber = $SaleReturn['client'] ? $SaleReturn['client']->phone : null;
        if (!$receiverNumber) {
            return response()->json(['success' => false, 'message' => 'client_phone_missing'], 422);
        }

        $settings = Setting::where('deleted_at', '=', null)->first();
        $default_sms_gateway = $settings ? sms_gateway::where('id', $settings->sms_gateway)
            ->where('deleted_at', '=', null)->first() : null;
        if (!$default_sms_gateway) {
            return response()->json(['success' => false, 'message' => 'sms_gateway_missing'], 422);
        }

        // Custom message "sale_return" if it exists, otherwise a default text
        $smsMessage = SMSMessage::where('name', 'sale_return')->first();
        $message_text = $smsMessage ? $smsMessage->text :
            'مرحبا {contact_name}، مرتجع البيع {invoice_number} بمبلغ {total_amount}. {invoice_url} - {business_name}';
        $message_text = strtr($message_text, $this->Return_Message_Tags($SaleReturn));

        try {
            if ($default_sms_gateway->title == "twilio") {
                $client = new Client_Twilio(env("TWILIO_SID"), env("TWILIO_TOKEN"));
                $client->messages->create($receiverNumber, [
                    'from' => env("TWILIO_FROM"),
                    'body' => $message_text,
                ]);
            } elseif ($default_sms_gateway->title == "nexmo") {
                $basic = new \Nexmo\Client\Credentials\Basic(env("NEXMO_KEY"), env("NEXMO_SECRET"));
                $client = new \Nexmo\Client($basic);
                $client->message()->send([
                    'to' => $receiverNumber,
                    'from' => env("NEXMO_FROM"),
                    'text' => $message_text,
                ]);
            } elseif ($default_sms_gateway->title == "infobip") {
                $configuration = (new Configuration())
                    ->setHost(env("base_url"))
                    ->setApiKeyPrefix('Authorization', 'App')
                    ->setApiKey('Authorization', env("api_key"));

                $sendSmsApi = new SendSMSApi(new Client_guzzle(), $configuration);
                $destination = (new SmsDestination())->setTo($receiverNumber);
                $message = (new SmsTextualMessage())
                    ->setFrom(env("sender_from"))
                    ->setText($message_text)
                    ->setDestinations([$destination]);

                $sendSmsApi->sendSmsMessage((new SmsAdvancedTextualRequest())->setMessages([$message]));
            }
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }

        return response()->json(['success' => true]);
    }

}
