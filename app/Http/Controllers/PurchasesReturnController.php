<?php

namespace App\Http\Controllers;
use App\Models\Account;

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
use App\Models\Unit;
use App\Models\PaymentPurchaseReturns;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\product_warehouse;
use App\Models\Provider;
use App\Models\Purchase;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnDetails;
use App\Models\PurchaseDetail;
use App\Models\Role;
use App\Models\Setting;
use App\Models\Warehouse;
use App\Models\User;
use App\Models\UserWarehouse;
use App\Models\sms_gateway;
use App\utils\helpers;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use DB;
use PDF;
use ArPHP\I18N\Arabic;

class PurchasesReturnController extends BaseController
{

    //------------ GET ALL Purchases Return  --------------\\

    public function index(request $request)
    {
        $this->authorizeForUser($request->user('api'), 'view', PurchaseReturn::class);
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
            2 => 'provider_id',
            3 => 'payment_statut',
            4 => 'warehouse_id',
            5 => 'date',
            6 => 'purchase_id',
        );
        $data = array();

        // Check If User Has Permission View  All Records
        $PurchaseReturn = PurchaseReturn::with('purchase','facture', 'provider', 'warehouse')
            ->where('deleted_at', '=', null)
            ->where(function ($query) use ($view_records) {
                if (!$view_records) {
                    return $query->where('user_id', '=', Auth::user()->id);
                }
            });

        //Multiple Filter
        $Filtred = $helpers->filter($PurchaseReturn, $columns, $param, $request)
        // Search With Multiple Param
            ->where(function ($query) use ($request) {
                return $query->when($request->filled('search'), function ($query) use ($request) {
                    return $query->where('Ref', 'LIKE', "%{$request->search}%")
                        ->orWhere('statut', 'LIKE', "%{$request->search}%")
                        ->orWhere('GrandTotal', $request->search)
                        ->orWhere('payment_statut', 'like', "$request->search")
                        ->orWhere(function ($query) use ($request) {
                            return $query->whereHas('provider', function ($q) use ($request) {
                                $q->where('name', 'LIKE', "%{$request->search}%");
                            });
                        })
                        ->orWhere(function ($query) use ($request) {
                            return $query->whereHas('purchase', function ($q) use ($request) {
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
        $PurchaseReturn = $Filtred->offset($offSet)
            ->limit($perPage)
            ->orderBy($order, $dir)
            ->get();

        foreach ($PurchaseReturn as $Purchase_Return) {

            $item['id'] = $Purchase_Return->id;
            $item['date'] = $Purchase_Return->date;
            $item['Ref'] = $Purchase_Return->Ref;
            $item['discount'] = $Purchase_Return->discount;
            $item['shipping'] = $Purchase_Return->shipping;
            $item['statut'] = $Purchase_Return->statut;
            $item['purchase_ref'] = $Purchase_Return['purchase']?$Purchase_Return['purchase']->Ref:'---';
            $item['purchase_id'] = $Purchase_Return['purchase']?$Purchase_Return['purchase']->id:NULL;
            $item['warehouse_name'] = $Purchase_Return['warehouse']->name;
            $item['provider_id'] = $Purchase_Return['provider']->id;
            $item['provider_name'] = $Purchase_Return['provider']->name;
            $item['provider_email'] = $Purchase_Return['provider']->email;
            $item['provider_tele'] = $Purchase_Return['provider']->phone;
            $item['provider_code'] = $Purchase_Return['provider']->code;
            $item['provider_adr'] = $Purchase_Return['provider']->adresse;
            $item['GrandTotal'] = number_format($Purchase_Return['GrandTotal'], 2, '.', '');
            $item['paid_amount'] = number_format($Purchase_Return['paid_amount'], 2, '.', '');
            $item['due'] = number_format($item['GrandTotal'] - $item['paid_amount'], 2, '.', '');
            $item['payment_status'] = $Purchase_Return['payment_statut'];

            $data[] = $item;
        }

        $suppliers = Provider::where('deleted_at', '=', null)->get(['id', 'name']);
        $purchases = Purchase::where('deleted_at', '=', null)->get(['id', 'Ref']);
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
            'purchase_returns' => $data,
            'suppliers' => $suppliers,
            'warehouses' => $warehouses,
            'purchases' => $purchases,
            'accounts' => $accounts,
        ]);

    }

    //------------ Store New Purchase Return  --------------\\

    public function store(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'create', PurchaseReturn::class);

        \DB::transaction(function () use ($request) {
            $order = new PurchaseReturn;

            $order->date = $request->date;
            $order->Ref = $this->getNumberOrder();
            $order->purchase_id = $request->purchase_id;
            $order->provider_id = $request->supplier_id;
            $order->warehouse_id = $request->warehouse_id;
            $order->tax_rate = $request->tax_rate;
            $order->TaxNet = $request->TaxNet;
            $order->discount = $request->discount;
            $order->shipping = $request->shipping;
            $order->statut = $request->statut;
            $order->GrandTotal = $request->GrandTotal;
            $order->payment_statut = 'unpaid';
            $order->notes = $request->notes;
            $order->user_id = Auth::user()->id;

            $order->save();

            $data = $request['details'];
            foreach ($data as $key => $value) {
                $unit = Unit::where('id', $value['purchase_unit_id'])->first();
                $orderDetails[] = [
                    'purchase_return_id' => $order->id,
                    'quantity' => $value['quantity'],
                    'cost' => $value['Unit_cost'],
                    'purchase_unit_id' =>  $value['purchase_unit_id'],
                    'TaxNet' => $value['tax_percent'],
                    'tax_method' => $value['tax_method'],
                    'discount' => $value['discount'],
                    'discount_method' => $value['discount_Method'],
                    'product_id' => $value['product_id'],
                    'product_variant_id' => $value['product_variant_id'],
                    'total' => $value['subtotal'],
                    'imei_number' => $value['imei_number'],
                ];

            
                if ($order->statut == "completed") {
                    if ($value['product_variant_id'] !== null) {

                        $product_warehouse = product_warehouse::where('deleted_at', '=', null)
                            ->where('warehouse_id', $order->warehouse_id)
                            ->where('product_id', $value['product_id'])
                            ->where('product_variant_id', $value['product_variant_id'])
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
                        $product_warehouse = product_warehouse::where('deleted_at', '=', null)
                            ->where('warehouse_id', $order->warehouse_id)
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
            PurchaseReturnDetails::insert($orderDetails);
        }, 10);

        return response()->json(['success' => true]);
    }

    //------------ Update Purchase Return --------------\\

    public function update(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'update', PurchaseReturn::class);

        request()->validate([
            'date' => 'required',
            'statut' => 'required',
            'details' => 'required|array|min:1',
        ]);

        \DB::transaction(function () use ($request, $id) {
            $role = Auth::user()->roles()->first();
            $view_records = Role::findOrFail($role->id)->inRole('record_view');
            $current_PurchaseReturn = PurchaseReturn::where('deleted_at', '=', null)->findOrFail($id);

            // Check If User Has Permission view All Records
            if (!$view_records) {
                // Check If User->id === PurchaseReturn->id
                $this->authorizeForUser($request->user('api'), 'check_record', $current_PurchaseReturn);
            }

            // A return linked to a purchase keeps the purchase's supplier and warehouse.
            // A standalone return (no purchase) can change both.
            if ($current_PurchaseReturn->purchase_id) {
                $provider_id = $current_PurchaseReturn->provider_id;
                $warehouse_id = $current_PurchaseReturn->warehouse_id;
            } else {
                $provider_id = $request['supplier_id'] ?: $current_PurchaseReturn->provider_id;
                $warehouse_id = $request['warehouse_id'] ?: $current_PurchaseReturn->warehouse_id;
            }
            // Stock of the new lines is taken from the (possibly new) warehouse
            $request->merge(['warehouse_id' => $warehouse_id]);

            $old_Return_Details = PurchaseReturnDetails::where('purchase_return_id', $id)->get();
            $New_Return_Details = $request['details'];
            $length = sizeof($New_Return_Details);

            // Get Ids details
            $new_products_id = [];
            foreach ($New_Return_Details as $new_detail) {
                // lines added in the edit form have no id yet
                $new_products_id[] = $new_detail['id'] ?? 0;
            }

            // Init Data with old Parametre
            $old_products_id = [];
            foreach ($old_Return_Details as $key => $value) {
                $old_products_id[] = $value->id;

                 //check if detail has purchase_unit_id Or Null
                 if($value['purchase_unit_id'] !== null){
                    $unit = Unit::where('id', $value['purchase_unit_id'])->first();
                }else{
                    $product_unit_purchase_id = Product::with('unitPurchase')
                    ->where('id', $value['product_id'])
                    ->first();
                    $unit = Unit::where('id', $product_unit_purchase_id['unitPurchase']->id)->first();
                }

                if($value['purchase_unit_id'] !== null){
                    if ($current_PurchaseReturn->statut == "completed") {
                        if ($value['product_variant_id'] !== null) {

                            $product_warehouse = product_warehouse::where('deleted_at', '=', null)
                                ->where('warehouse_id', $current_PurchaseReturn->warehouse_id)
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
                                ->where('warehouse_id', $current_PurchaseReturn->warehouse_id)
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

                    // Delete Detail
                    if (!in_array($old_products_id[$key], $new_products_id)) {
                        $PurchaseReturnDetails = PurchaseReturnDetails::findOrFail($value->id);
                        $PurchaseReturnDetails->delete();
                    }
                }

            }

            // Update Data with New request
            foreach ($New_Return_Details as $key => $product_detail) {
                $product_detail['id'] = $product_detail['id'] ?? 0;
                $product_detail['imei_number'] = $product_detail['imei_number'] ?? null;
                $product_detail['product_variant_id'] = $product_detail['product_variant_id'] ?? null;

                if(($product_detail['no_unit'] ?? 1) !== 0){
                    $unit_prod = Unit::where('id', $product_detail['purchase_unit_id'])->first();

                    if ($request->statut == "completed") {
                        if ($product_detail['product_variant_id'] !== null) {
                            $product_warehouse = product_warehouse::where('deleted_at', '=', null)
                                ->where('warehouse_id', $request->warehouse_id)
                                ->where('product_id', $product_detail['product_id'])
                                ->where('product_variant_id', $product_detail['product_variant_id'])
                                ->first();

                            if ($unit_prod && $product_warehouse) {
                                if ($unit_prod->operator == '/') {
                                    $product_warehouse->qte -= $product_detail['quantity'] / $unit_prod->operator_value;
                                } else {
                                    $product_warehouse->qte -= $product_detail['quantity'] * $unit_prod->operator_value;
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
                                    $product_warehouse->qte -= $product_detail['quantity'] / $unit_prod->operator_value;
                                } else {
                                    $product_warehouse->qte -= $product_detail['quantity'] * $unit_prod->operator_value;
                                }
                                $product_warehouse->save();
                            }
                        }
                    }

                    $orderDetails['purchase_return_id'] = $id;
                    $orderDetails['cost'] = $product_detail['Unit_cost'];
                    $orderDetails['purchase_unit_id'] = $product_detail['purchase_unit_id'];
                    $orderDetails['TaxNet'] = $product_detail['tax_percent'];
                    $orderDetails['tax_method'] = $product_detail['tax_method'];
                    $orderDetails['discount'] = $product_detail['discount'];
                    $orderDetails['discount_method'] = $product_detail['discount_Method'];
                    $orderDetails['quantity'] = $product_detail['quantity'];
                    $orderDetails['product_id'] = $product_detail['product_id'];
                    $orderDetails['product_variant_id'] = $product_detail['product_variant_id'];
                    $orderDetails['total'] = $product_detail['subtotal'];
                    $orderDetails['imei_number'] = $product_detail['imei_number'];

                    if (!in_array($product_detail['id'], $old_products_id)) {
                        PurchaseReturnDetails::Create($orderDetails);
                    } else {
                        PurchaseReturnDetails::where('id', $product_detail['id'])->update($orderDetails);
                    }
                }

            }

            // If the new GrandTotal is less than what was already paid, cap paid_amount
            $paid_amount = $current_PurchaseReturn->paid_amount;
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

            $current_PurchaseReturn->update([
                'date' => $request['date'],
                'provider_id' => $provider_id,
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

    //------------ Delete Purchase Return  --------------\\

    public function destroy(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'delete', PurchaseReturn::class);

        \DB::transaction(function () use ($id, $request) {
            $role = Auth::user()->roles()->first();
            $view_records = Role::findOrFail($role->id)->inRole('record_view');
            $current_PurchaseReturn = PurchaseReturn::findOrFail($id);
            $old_Return_Details = PurchaseReturnDetails::where('purchase_return_id', $id)->get();

            // Check If User Has Permission view All Records
            if (!$view_records) {
                // Check If User->id === PurchaseReturn->id
                $this->authorizeForUser($request->user('api'), 'check_record', $current_PurchaseReturn);
            }

            foreach ($old_Return_Details as $key => $value) {

                 //check if detail has purchase_unit_id Or Null
                 if($value['purchase_unit_id'] !== null){
                    $unit = Unit::where('id', $value['purchase_unit_id'])->first();
                }else{
                    $product_unit_purchase_id = Product::with('unitPurchase')
                    ->where('id', $value['product_id'])
                    ->first();
                    $unit = Unit::where('id', $product_unit_purchase_id['unitPurchase']->id)->first();
                }

                if ($current_PurchaseReturn->statut == "completed") {
                    if ($value['product_variant_id'] !== null) {

                        $product_warehouse = product_warehouse::where('deleted_at', '=', null)
                            ->where('warehouse_id', $current_PurchaseReturn->warehouse_id)
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
                            ->where('warehouse_id', $current_PurchaseReturn->warehouse_id)
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
            $current_PurchaseReturn->details()->delete();
            $current_PurchaseReturn->update([
                'deleted_at' => Carbon::now(),
            ]);

            // get all payments
            $payments = PaymentPurchaseReturns::where('purchase_return_id', $id)->get();

            foreach ($payments as $payment) {

                $account = Account::find($payment->account_id);

                if ($account) {
                    $account->update([
                        'balance' => $account->balance - $payment->montant,
                    ]);
                }

            }

            PaymentPurchaseReturns::where('purchase_return_id', $id)->update([
                'deleted_at' => Carbon::now(),
            ]);

        }, 10);

        return response()->json(['success' => true]);
    }

    //-------------- Delete by selection  ---------------\\

    public function delete_by_selection(Request $request)
    {

        $this->authorizeForUser($request->user('api'), 'delete', PurchaseReturn::class);

        \DB::transaction(function () use ($request) {
            $role = Auth::user()->roles()->first();
            $view_records = Role::findOrFail($role->id)->inRole('record_view');
            $selectedIds = $request->selectedIds;

            foreach ($selectedIds as $PurchaseReturn_id) {
                $current_PurchaseReturn = PurchaseReturn::findOrFail($PurchaseReturn_id);
                $old_Return_Details = PurchaseReturnDetails::where('purchase_return_id', $PurchaseReturn_id)->get();


                // Check If User Has Permission view All Records
                if (!$view_records) {
                    // Check If User->id === current_PurchaseReturn->id
                    $this->authorizeForUser($request->user('api'), 'check_record', $current_PurchaseReturn);
                }
                foreach ($old_Return_Details as $key => $value) {

                    //check if detail has purchase_unit_id Or Null
                    if($value['purchase_unit_id'] !== null){
                       $unit = Unit::where('id', $value['purchase_unit_id'])->first();
                   }else{
                       $product_unit_purchase_id = Product::with('unitPurchase')
                       ->where('id', $value['product_id'])
                       ->first();
                       $unit = Unit::where('id', $product_unit_purchase_id['unitPurchase']->id)->first();
                   }
   
                   if ($current_PurchaseReturn->statut == "completed") {
                       if ($value['product_variant_id'] !== null) {
   
                           $product_warehouse = product_warehouse::where('deleted_at', '=', null)
                               ->where('warehouse_id', $current_PurchaseReturn->warehouse_id)
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
                               ->where('warehouse_id', $current_PurchaseReturn->warehouse_id)
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
               $current_PurchaseReturn->details()->delete();
               $current_PurchaseReturn->update([
                   'deleted_at' => Carbon::now(),
               ]);

               $Payment_purchase_return_data = PaymentPurchaseReturns::where('purchase_return_id', $PurchaseReturn_id)->get();
               foreach($Payment_purchase_return_data as $Payment_return){
                   $account = Account::find($Payment_return->account_id);

                   if ($account) {
                       $account->update([
                           'balance' => $account->balance - $Payment_return->montant,
                       ]);
                   }

                   $Payment_return->delete();
               }

            }

        }, 10);

        return response()->json(['success' => true]);

    }

    //---------------- Show Form Create Purchase Return ---------------\\

    public function create(Request $request)
    {

       //

    }

    
    //---------------- edit ---------------\\

    public function edit(Request $request , $id)
    {

       //

    }

     //------------------- create_purchase_return -----------------\\

     public function create_purchase_return(Request $request , $id)
     {
 
         $this->authorizeForUser($request->user('api'), 'create', PurchaseReturn::class);
         $role = Auth::user()->roles()->first();
         $view_records = Role::findOrFail($role->id)->inRole('record_view');
         $Purchase_data = Purchase::with('details.product.unitPurchase')
             ->where('deleted_at', '=', null)
             ->findOrFail($id);
 
         $details = array();
         
         // Check If User Has Permission view All Records
         if (!$view_records) {
             // Check If User->id === Purchase->id
             $this->authorizeForUser($request->user('api'), 'check_record', $Purchase_data);
         }
 
         $Return_detail['supplier_id'] = $Purchase_data->provider_id;
         $Return_detail['warehouse_id'] = $Purchase_data->warehouse_id;
         $Return_detail['purchase_id'] = $Purchase_data->id;
         $Return_detail['purchase_ref'] = $Purchase_data->Ref;
         $Return_detail['tax_rate'] = 0;
         $Return_detail['TaxNet'] = 0;
         $Return_detail['discount'] = 0;
         $Return_detail['shipping'] = 0;
         $Return_detail['statut'] = "completed";
         $Return_detail['notes'] = "";
 
         $detail_id = 0;
         foreach ($Purchase_data['details'] as $detail) {
 
             //-------check if detail has purchase_unit_id Or Null
             if($detail->purchase_unit_id !== null){
                 $unit = Unit::where('id', $detail->purchase_unit_id)->first();
                 $data['no_unit'] = 1;
             }else{
                 $product_unit_purchase_id = Product::with('unitPurchase')
                 ->where('id', $detail->product_id)
                 ->first();
                 $unit = Unit::where('id', $product_unit_purchase_id['unitPurchase']->id)->first();
                 $data['no_unit'] = 0;
             }
 
             if ($detail->product_variant_id) {
                 $item_product = product_warehouse::where('product_id', $detail->product_id)
                     ->where('deleted_at', '=', null)
                     ->where('product_variant_id', $detail->product_variant_id)
                     ->where('warehouse_id', $Purchase_data->warehouse_id)
                     ->first();
 
                 $productsVariants = ProductVariant::where('product_id', $detail->product_id)
                     ->where('id', $detail->product_variant_id)->first();
 
                 $item_product ? $data['del'] = 0 : $data['del'] = 1;
                 $data['code'] = $productsVariants->code;
                 $data['name'] = '['.$productsVariants->name . ']' . $detail['product']['name'];
                 $data['product_variant_id'] = $detail->product_variant_id;                
 
                 if ($unit && $unit->operator == '/') {
                     $data['stock'] = $item_product ? $item_product->qte * $unit->operator_value : 0;
                 } else if ($unit && $unit->operator == '*') {
                     $data['stock'] = $item_product ? $item_product->qte / $unit->operator_value : 0;
                 } else {
                     $data['stock'] = 0;
                 }
 
             } else {
                 $item_product = product_warehouse::where('product_id', $detail->product_id)
                     ->where('deleted_at', '=', null)->where('product_variant_id', '=', null)
                     ->where('warehouse_id', $Purchase_data->warehouse_id)->first();
 
                 $item_product ? $data['del'] = 0 : $data['del'] = 1;
                 $data['product_variant_id'] = null;
                 $data['code'] = $detail['product']['code'];
                 $data['name'] = $detail['product']['name'];
              
 
                 if ($unit && $unit->operator == '/') {
                     $data['stock'] = $item_product ? $item_product->qte * $unit->operator_value : 0;
                 } else if ($unit && $unit->operator == '*') {
                     $data['stock'] = $item_product ? $item_product->qte / $unit->operator_value : 0;
                 } else {
                     $data['stock'] = 0;
                 }
 
             }
 
             $data['id'] = $detail->id;
             $data['detail_id'] = $detail_id += 1;
             $data['quantity'] = 0;
             $data['purchase_quantity'] = $detail->quantity;
             $data['product_id'] = $detail->product_id;
             $data['unitPurchase'] = $unit->ShortName;
             $data['purchase_unit_id'] = $unit->id;
             
             $data['is_imei'] = $detail['product']['is_imei'];
             $data['imei_number'] = $detail->imei_number;
 
             if ($detail->discount_method == '2') {
                 $data['DiscountNet'] = $detail->discount;
             } else {
                 $data['DiscountNet'] = $detail->cost * $detail->discount / 100;
             }
 
             $tax_cost = $detail->TaxNet * (($detail->cost - $data['DiscountNet']) / 100);
             $data['Unit_cost'] = $detail->cost;
             $data['tax_percent'] = $detail->TaxNet;
             $data['tax_method'] = $detail->tax_method;
             $data['discount'] = $detail->discount;
             $data['discount_Method'] = $detail->discount_method;
 
             if ($detail->tax_method == '1') {
                 $data['Net_cost'] = $detail->cost - $data['DiscountNet'];
                 $data['taxe'] = $tax_cost;
                 $data['subtotal'] = ($data['Net_cost'] * $data['quantity']) + ($tax_cost * $data['quantity']);
             } else {
                 $data['Net_cost'] = ($detail->cost - $data['DiscountNet']) / (($detail->TaxNet / 100) + 1);
                 $data['taxe'] = $detail->cost - $data['Net_cost'] - $data['DiscountNet'];
                 $data['subtotal'] = ($data['Net_cost'] * $data['quantity']) + ($tax_cost * $data['quantity']);
             }
 
             $details[] = $data;
         }
 
         return response()->json([
             'details' => $details,
             'purchase_return' => $Return_detail,
         ]);
 
     }

     //------------- Show Form Edit Purchase Return-----------\\

    public function edit_purchase_return(Request $request, $id, $purchase_id)
    {

        $this->authorizeForUser($request->user('api'), 'update', PurchaseReturn::class);

        $role = Auth::user()->roles()->first();
        $view_records = Role::findOrFail($role->id)->inRole('record_view');
        $Purchase_Return = PurchaseReturn::with('details.product.unitPurchase')
            ->where('deleted_at', '=', null)
            ->findOrFail($id);
        $details = array();
        // Check If User Has Permission view All Records
        if (!$view_records) {
            // Check If User->id === PurchaseReturn->id
            $this->authorizeForUser($request->user('api'), 'check_record', $Purchase_Return);
        }

        $Return_detail['id'] = $Purchase_Return->id;
        $Return_detail['Ref'] = $Purchase_Return->Ref;
        $Return_detail['supplier_id'] = $Purchase_Return->provider_id;
        $Return_detail['supplier_name'] = optional($Purchase_Return->provider)->name;
        $Return_detail['warehouse_id'] = $Purchase_Return->warehouse_id;
        $Return_detail['warehouse_name'] = optional($Purchase_Return->warehouse)->name;
        $Return_detail['purchase_id'] = $Purchase_Return['purchase']?$Purchase_Return['purchase']->id:NULL;
        $Return_detail['purchase_ref'] = $Purchase_Return['purchase']?$Purchase_Return['purchase']->Ref:'---';

        $Return_detail['date'] = $Purchase_Return->date;
        $Return_detail['tax_rate'] = $Purchase_Return->tax_rate;
        $Return_detail['TaxNet'] = $Purchase_Return->TaxNet;
        $Return_detail['discount'] = $Purchase_Return->discount;
        $Return_detail['shipping'] = $Purchase_Return->shipping;
        $Return_detail['notes'] = $Purchase_Return->notes;
        $Return_detail['statut'] = $Purchase_Return->statut;

        $detail_id = 0;
        foreach ($Purchase_Return['details'] as $detail) {

            //-------check if detail has purchase_unit_id Or Null
            if($detail->purchase_unit_id !== null){
                $unit = Unit::where('id', $detail->purchase_unit_id)->first();
                $data['no_unit'] = 1;
            }else{
                $product_unit_purchase_id = Product::with('unitPurchase')
                ->where('id', $detail->product_id)
                ->first();
                $unit = Unit::where('id', $product_unit_purchase_id['unitPurchase']->id)->first();
                $data['no_unit'] = 0;
            }

            if ($detail->product_variant_id) {
                $item_product = product_warehouse::where('product_id', $detail->product_id)
                    ->where('deleted_at', '=', null)
                    ->where('product_variant_id', $detail->product_variant_id)
                    ->where('warehouse_id', $Purchase_Return->warehouse_id)
                    ->first();

                $productsVariants = ProductVariant::where('product_id', $detail->product_id)
                    ->where('id', $detail->product_variant_id)->first();

                $item_product ? $data['del'] = 0 : $data['del'] = 1;
                $data['name'] = '['.$productsVariants->name . ']' . $detail['product']['name'];
                $data['code'] = $productsVariants->code;

                $data['product_variant_id'] = $detail->product_variant_id;

                if ($unit && $unit->operator == '/') {
                    $data['current_stock'] = $item_product ? $item_product->qte * $unit->operator_value : 0;
                } else if ($unit && $unit->operator == '*') {
                    $data['current_stock'] = $item_product ? $item_product->qte / $unit->operator_value : 0;
                } else {
                    $data['current_stock'] = 0;
                }

            } else {
                $item_product = product_warehouse::where('product_id', $detail->product_id)
                    ->where('warehouse_id', $Purchase_Return->warehouse_id)
                    ->where('deleted_at', '=', null)->where('product_variant_id', '=', null)
                    ->first();

                    $item_product ? $data['del'] = 0 : $data['del'] = 1;
                    $data['code'] = $detail['product']['code'];
                    $data['name'] = $detail['product']['name'];
                    $data['product_variant_id'] = null;

                if ($unit && $unit->operator == '/') {
                    $data['current_stock'] = $item_product ? $item_product->qte * $unit->operator_value : 0;
                } else if ($unit && $unit->operator == '*') {
                    $data['current_stock'] = $item_product ? $item_product->qte / $unit->operator_value : 0;
                } else {
                    $data['current_stock'] = 0;
                }

            }

            if($purchase_id && $purchase_id !== 'null'){
                $purchase_detail = PurchaseDetail::where('purchase_id', $purchase_id)
                ->where('product_id', $detail->product_id)
                ->where('product_variant_id', $detail->product_variant_id)
                ->first();
                $data['purchase_quantity'] = $purchase_detail ? $purchase_detail->quantity : $detail->quantity;
            }else{
                // Standalone return (no associated purchase) - use the return's own quantity as the max
                $data['purchase_quantity'] = $detail->quantity;
            }
            $data['stock'] =  $data['current_stock'] + $detail->quantity;
            // Stock in base unit (what is in the warehouse + what this return already took out)
            $returned_base = $unit ? ($unit->operator == '/' ? $detail->quantity / $unit->operator_value : $detail->quantity * $unit->operator_value) : $detail->quantity;
            $data['fix_stock'] = ($item_product ? $item_product->qte : 0) + $returned_base;
            $data['product_type'] = $detail['product']['type'];
            $data['quantity'] = $detail->quantity;
            $data['quantity_copy'] = $detail->quantity;

            $data['id'] = $detail->id;
            $data['detail_id'] = $detail_id += 1;
            $data['product_id'] = $detail->product_id;
            $data['unitPurchase'] = $unit->ShortName;
            $data['purchase_unit_id'] = $unit->id;

            $data['is_imei'] = $detail['product']['is_imei'];
            $data['imei_number'] = $detail->imei_number;
            
            if ($detail->discount_method == '2') {
                $data['DiscountNet'] = $detail->discount;
            } else {
                $data['DiscountNet'] = $detail->cost * $detail->discount / 100;
            }

            $tax_cost = $detail->TaxNet * (($detail->cost - $data['DiscountNet']) / 100);
            $data['Unit_cost'] = $detail->cost;
            $data['tax_percent'] = $detail->TaxNet;
            $data['tax_method'] = $detail->tax_method;
            $data['discount'] = $detail->discount;
            $data['discount_Method'] = $detail->discount_method;

            if ($detail->tax_method == '1') {
                $data['Net_cost'] = $detail->cost - $data['DiscountNet'];
                $data['taxe'] = $tax_cost;
                $data['subtotal'] = ($data['Net_cost'] * $data['quantity']) + ($tax_cost * $data['quantity']);
            } else {
                $data['Net_cost'] = ($detail->cost - $data['DiscountNet']) / (($detail->TaxNet / 100) + 1);
                $data['taxe'] = $detail->cost - $data['Net_cost'] - $data['DiscountNet'];
                $data['subtotal'] = ($data['Net_cost'] * $data['quantity']) + ($tax_cost * $data['quantity']);
            }

            $details[] = $data;
        }

        // Standalone return: supplier and warehouse are editable, send the lists
        $suppliers = [];
        $warehouses = [];
        if (!$Return_detail['purchase_id']) {
            $suppliers = Provider::where('deleted_at', '=', null)->get(['id', 'name']);

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
            'purchase_return' => $Return_detail,
            'suppliers' => $suppliers,
            'warehouses' => $warehouses,
        ]);

    } 


 
    //------------- GET Payments Purchase Return BY ID-----------\\

    public function Payment_Returns(Request $request, $id)
    {

        $this->authorizeForUser($request->user('api'), 'view', PaymentPurchaseReturns::class);

        $role = Auth::user()->roles()->first();
        $view_records = Role::findOrFail($role->id)->inRole('record_view');
        $PurchaseReturn = PurchaseReturn::findOrFail($id);

        // Check If User Has Permission view All Records
        if (!$view_records) {
            // Check If User->id === PurchaseReturn->id
            $this->authorizeForUser($request->user('api'), 'check_record', $PurchaseReturn);
        }

        $payments = PaymentPurchaseReturns::with('PurchaseReturn')
            ->where('purchase_return_id', $id)
            ->where(function ($query) use ($view_records) {
                if (!$view_records) {
                    return $query->where('user_id', '=', Auth::user()->id);
                }
            })->orderBy('id', 'DESC')->get();

        $due = $PurchaseReturn->GrandTotal - $PurchaseReturn->paid_amount;

        return response()->json(['payments' => $payments, 'due' => $due]);
    }

    //------------ Reference Number Purchase Return --------------\\

    public function getNumberOrder()
    {
        $last = DB::table('purchase_returns')->latest('id')->first();

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

    //---------------- Get Details Purchase Return  -----------------\\

    public function show(Request $request, $id)
    {

        $this->authorizeForUser($request->user('api'), 'view', PurchaseReturn::class);
        $role = Auth::user()->roles()->first();
        $view_records = Role::findOrFail($role->id)->inRole('record_view');
        $Purchase_Return = PurchaseReturn::with('purchase','details.product.unitPurchase')
            ->where('deleted_at', '=', null)
            ->findOrFail($id);

        $details = array();

        // Check If User Has Permission view All Records
        if (!$view_records) {
            // Check If User->id === PurchaseReturn->id
            $this->authorizeForUser($request->user('api'), 'check_record', $Purchase_Return);
        }

        $return_details['purchase_ref'] = $Purchase_Return['purchase']?$Purchase_Return['purchase']->Ref:'---';
        $return_details['purchase_id'] = $Purchase_Return['purchase']?$Purchase_Return['purchase']->id:NULL;
        $return_details['Ref'] = $Purchase_Return->Ref;
        $return_details['date'] = $Purchase_Return->date;
        $return_details['statut'] = $Purchase_Return->statut;
        $return_details['note'] = $Purchase_Return->notes;
        $return_details['discount'] = $Purchase_Return->discount;
        $return_details['shipping'] = $Purchase_Return->shipping;
        $return_details['tax_rate'] = $Purchase_Return->tax_rate;
        $return_details['TaxNet'] = $Purchase_Return->TaxNet;
        $return_details['supplier_name'] = $Purchase_Return['provider']->name;
        $return_details['supplier_email'] = $Purchase_Return['provider']->email;
        $return_details['supplier_phone'] = $Purchase_Return['provider']->phone;
        $return_details['supplier_adr'] = $Purchase_Return['provider']->adresse;
        $return_details['supplier_tax'] = $Purchase_Return['provider']->tax_number;
        $return_details['warehouse'] = $Purchase_Return['warehouse']->name;
        $return_details['GrandTotal'] = number_format($Purchase_Return->GrandTotal, 2, '.', '');
        $return_details['paid_amount'] = number_format($Purchase_Return->paid_amount, 2, '.', '');
        $return_details['due'] = number_format($return_details['GrandTotal'] - $return_details['paid_amount'] , 2, '.', '');
        $return_details['payment_status'] = $Purchase_Return->payment_statut;

        foreach ($Purchase_Return['details'] as $detail) {

             //-------check if detail has purchase_unit_id Or Null
             if($detail->purchase_unit_id !== null){
                $unit = Unit::where('id', $detail->purchase_unit_id)->first();
            }else{
                $product_unit_purchase_id = Product::with('unitPurchase')
                ->where('id', $detail->product_id)
                ->first();
                $unit = Unit::where('id', $product_unit_purchase_id['unitPurchase']->id)->first();
            }

            if ($detail->product_variant_id) {

                $productsVariants = ProductVariant::where('product_id', $detail->product_id)
                    ->where('id', $detail->product_variant_id)->first();

                $data['name'] = '['.$productsVariants->name . ']' . $detail['product']['name'];
                $data['code'] = $productsVariants->code;

            } else {
                $data['code'] = $detail['product']['code'];
                $data['name'] = $detail['product']['name'];
            }
            
            $data['quantity'] = $detail->quantity;
            $data['total'] = $detail->total;
            $data['cost'] = $detail->cost;
            $data['unit_purchase'] = $unit->ShortName;

            if ($detail->discount_method == '2') {
                $data['DiscountNet'] = $detail->discount;
            } else {
                $data['DiscountNet'] = $detail->cost * $detail->discount / 100;
            }
            $tax_cost = $detail->TaxNet * (($detail->cost - $data['DiscountNet']) / 100);
            $data['Unit_cost'] = $detail->cost;
            $data['discount'] = $detail->discount;
            if ($detail->tax_method == '1') {
                $data['Net_cost'] = $detail->cost - $data['DiscountNet'];
                $data['taxe'] = $tax_cost;
            } else {
                $data['Net_cost'] = ($detail->cost - $data['DiscountNet']) / (($detail->TaxNet / 100) + 1);
                $data['taxe'] = $detail->cost - $data['Net_cost'] - $data['DiscountNet'];
            }

            $data['is_imei'] = $detail['product']['is_imei'];
            $data['imei_number'] = $detail->imei_number;

            $details[] = $data;
        }

        $company = Setting::where('deleted_at', '=', null)->first();

        return response()->json([
            'details' => $details,
            'purchase_return' => $return_details,
            'company' => $company,
        ]);

    }

    //------------- Purchase Return PDF-----------\\

    public function Return_pdf(Request $request, $id)
    {

        $details = array();
        $helpers = new helpers();
        $PurchaseReturn = PurchaseReturn::with('purchase','details.product.unitPurchase')
            ->where('deleted_at', '=', null)
            ->findOrFail($id);

        $return_details['purchase_ref'] = $PurchaseReturn['purchase']?$PurchaseReturn['purchase']->Ref:'---';
        $return_details['supplier_name'] = $PurchaseReturn['provider']->name;
        $return_details['supplier_phone'] = $PurchaseReturn['provider']->phone;
        $return_details['supplier_adr'] = $PurchaseReturn['provider']->adresse;
        $return_details['supplier_email'] = $PurchaseReturn['provider']->email;
        $return_details['supplier_tax'] = $PurchaseReturn['provider']->tax_number;
        $return_details['TaxNet'] = number_format($PurchaseReturn->TaxNet, 2, '.', '');
        $return_details['discount'] = number_format($PurchaseReturn->discount, 2, '.', '');
        $return_details['shipping'] = number_format($PurchaseReturn->shipping, 2, '.', '');
        $return_details['statut'] = $PurchaseReturn->statut;
        $return_details['Ref'] = $PurchaseReturn->Ref;
        $return_details['date'] = $PurchaseReturn->date;
        $return_details['GrandTotal'] = number_format($PurchaseReturn->GrandTotal, 2, '.', '');
        $return_details['paid_amount'] = number_format($PurchaseReturn->paid_amount, 2, '.', '');
        $return_details['due'] = number_format($return_details['GrandTotal'] - $return_details['paid_amount'], 2, '.', '');
        $return_details['payment_status'] = $PurchaseReturn->payment_statut;

        $detail_id = 0;
        foreach ($PurchaseReturn['details'] as $detail) {

            //-------check if detail has purchase_unit_id Or Null
            if($detail->purchase_unit_id !== null){
                $unit = Unit::where('id', $detail->purchase_unit_id)->first();
            }else{
                $product_unit_purchase_id = Product::with('unitPurchase')
                ->where('id', $detail->product_id)
                ->first();
                $unit = Unit::where('id', $product_unit_purchase_id['unitPurchase']->id)->first();
            }

            if ($detail->product_variant_id) {

                $productsVariants = ProductVariant::where('product_id', $detail->product_id)
                    ->where('id', $detail->product_variant_id)->first();

                $data['name'] = '['.$productsVariants->name . ']' . $detail['product']['name'];
                $data['code'] = $productsVariants->code;

            } else {
                $data['code'] = $detail['product']['code'];
                $data['name'] = $detail['product']['name'];
            }

                $data['detail_id'] = $detail_id += 1;
                $data['quantity'] = number_format($detail->quantity, 2, '.', '');
                $data['total'] = number_format($detail->total, 2, '.', '');
                $data['cost'] = number_format($detail->cost, 2, '.', '');
                $data['unit_purchase'] = $unit->ShortName;

            if ($detail->discount_method == '2') {
                $data['DiscountNet'] = number_format($detail->discount, 2, '.', '');
            } else {
                $data['DiscountNet'] = number_format($detail->cost * $detail->discount / 100, 2, '.', '');
            }

            $tax_cost = $detail->TaxNet * (($detail->cost - $data['DiscountNet']) / 100);
            $data['Unit_cost'] = number_format($detail->cost, 2, '.', '');
            $data['discount'] = number_format($detail->discount, 2, '.', '');

            if ($detail->tax_method == '1') {

                $data['Net_cost'] = $detail->cost - $data['DiscountNet'];
                $data['taxe'] = number_format($tax_cost, 2, '.', '');
            } else {
                $data['Net_cost'] = ($detail->cost - $data['DiscountNet']) / (($detail->TaxNet / 100) + 1);
                $data['taxe'] = number_format($detail->cost - $data['Net_cost'] - $data['DiscountNet'], 2, '.', '');
            }

            $data['is_imei'] = $detail['product']['is_imei'];
            $data['imei_number'] = $detail->imei_number;

            $details[] = $data;
        }

        $settings = Setting::where('deleted_at', '=', null)->first();
        $symbol = $helpers->Get_Currency_Code();

        $Html = view('pdf.Purchase_Return_pdf', [
            'symbol' => $symbol,
            'setting' => $settings,
            'return_purchase' => $return_details,
            'details' => $details,
        ])->render();

        $arabic = new Arabic();
        $p = $arabic->arIdentify($Html);

        for ($i = count($p)-1; $i >= 0; $i-=2) {
            $utf8ar = $arabic->utf8Glyphs(substr($Html, $p[$i-1], $p[$i] - $p[$i-1]));
            $Html = substr_replace($Html, $utf8ar, $p[$i-1], $p[$i] - $p[$i-1]);
        }

        $pdf = PDF::loadHTML($Html);
        return $pdf->download('Purchase_Return.pdf');
    }

    //------------- Message tags for a purchase return (email / SMS) -----------\\

    private function Return_Message_Tags($PurchaseReturn)
    {
        $helpers = new helpers();
        $currency = $helpers->Get_Currency();
        $settings = Setting::where('deleted_at', '=', null)->first();

        return [
            '{contact_name}' => $PurchaseReturn['provider']->name,
            '{business_name}' => $settings ? $settings->CompanyName : '',
            '{invoice_url}' => url('/api/return_purchase_pdf/' . $PurchaseReturn->id . '?' . Str::random(10)),
            '{invoice_number}' => $PurchaseReturn->Ref,
            '{total_amount}' => $currency . ' ' . number_format($PurchaseReturn->GrandTotal, 2, '.', ','),
            '{paid_amount}' => $currency . ' ' . number_format($PurchaseReturn->paid_amount, 2, '.', ','),
            '{due_amount}' => $currency . ' ' . number_format($PurchaseReturn->GrandTotal - $PurchaseReturn->paid_amount, 2, '.', ','),
        ];
    }

    //------------- Send purchase return on Email -----------\\

    public function Send_Email(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'view', PurchaseReturn::class);

        $PurchaseReturn = PurchaseReturn::with('provider')->where('deleted_at', '=', null)->findOrFail($request->id);

        $receiver_email = $PurchaseReturn['provider'] ? $PurchaseReturn['provider']->email : null;
        if (!$receiver_email || !filter_var($receiver_email, FILTER_VALIDATE_EMAIL)) {
            return response()->json(['success' => false, 'message' => 'supplier_email_missing'], 422);
        }

        // Custom message "purchase_return" if it exists, otherwise a default text
        $emailMessage = EmailMessage::where('name', 'purchase_return')->first();
        $message_subject = $emailMessage ? $emailMessage->subject : 'مرتجع الشراء {invoice_number}';
        $message_body = $emailMessage ? $emailMessage->body :
            '<p>مرحبا {contact_name}،</p>'
            . '<p>تجدون رفقته مرتجع الشراء رقم <strong>{invoice_number}</strong> بمبلغ <strong>{total_amount}</strong>'
            . ' (المدفوع: {paid_amount} - المتبقي: {due_amount}).</p>'
            . '<p><a href="{invoice_url}">تحميل المرتجع PDF</a></p>'
            . '<p>{business_name}</p>';

        $tags = $this->Return_Message_Tags($PurchaseReturn);

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

    //------------- Send purchase return by SMS -----------\\

    public function Send_SMS(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'view', PurchaseReturn::class);

        $PurchaseReturn = PurchaseReturn::with('provider')->where('deleted_at', '=', null)->findOrFail($request->id);

        $receiverNumber = $PurchaseReturn['provider'] ? $PurchaseReturn['provider']->phone : null;
        if (!$receiverNumber) {
            return response()->json(['success' => false, 'message' => 'supplier_phone_missing'], 422);
        }

        $settings = Setting::where('deleted_at', '=', null)->first();
        $default_sms_gateway = $settings ? sms_gateway::where('id', $settings->sms_gateway)
            ->where('deleted_at', '=', null)->first() : null;
        if (!$default_sms_gateway) {
            return response()->json(['success' => false, 'message' => 'sms_gateway_missing'], 422);
        }

        // Custom message "purchase_return" if it exists, otherwise a default text
        $smsMessage = SMSMessage::where('name', 'purchase_return')->first();
        $message_text = $smsMessage ? $smsMessage->text :
            'مرحبا {contact_name}، مرتجع الشراء {invoice_number} بمبلغ {total_amount}. {invoice_url} - {business_name}';
        $message_text = strtr($message_text, $this->Return_Message_Tags($PurchaseReturn));

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
