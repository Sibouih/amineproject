<?php

namespace App\Http\Controllers;

use App\Exports\Sale_Export;
use App\Models\Account;
use App\Models\Adjustment;
use App\Models\AdjustmentDetail;
use App\Models\Brand;
use App\Models\Client;
use App\Models\ClientPayment;
use App\Models\Currency;
use App\Models\Deposit;
use App\Models\Expense;
use App\Models\PaymentPurchase;
use App\Models\PaymentPurchaseReturns;
use App\Models\PaymentSale;
use App\Models\PaymentSaleReturns;
use App\Models\Product;
use App\Models\Transfer;
use App\Models\TransferDetail;
use App\Models\Unit;
use App\Models\product_warehouse;
use App\Models\Provider;
use App\Models\Purchase;
use App\Models\Setting;
use App\Models\PurchaseDetail;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnDetails;
use App\Models\Quotation;
use App\Models\QuotationDetail;
use App\Models\Role;
use App\Models\Sale;
use App\Models\SaleDetail;
use App\Models\SaleReturn;
use App\Models\SaleReturnDetails;
use App\Models\User;
use App\Models\UserWarehouse;
use App\Models\Warehouse;
use App\utils\helpers;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use DB;
use App\Models\ProductVariant;

class ReportController extends BaseController
{


    //----------- Get Last 5 Sales --------------\\

    public function Get_last_Sales()
    {

        $Role = Auth::user()->roles()->first();
        $ShowRecord = Role::findOrFail($Role->id)->inRole('record_view');

        $Sales = Sale::with('details', 'client', 'facture')->where('deleted_at', '=', null)
            ->where(function ($query) use ($ShowRecord) {
                if (!$ShowRecord) {
                    return $query->where('user_id', '=', Auth::user()->id);
                }
            })
            ->orderBy('id', 'desc')
            ->take(5)
            ->get();

        foreach ($Sales as $Sale) {

            $item['Ref'] = $Sale['Ref'];
            $item['statut'] = $Sale['statut'];
            $item['client_name'] = $Sale['client']['name'];
            $item['GrandTotal'] = $Sale['GrandTotal'];
            $item['paid_amount'] = $Sale['paid_amount'];
            $item['due'] = $Sale['GrandTotal'] - $Sale['paid_amount'];
            $item['payment_status'] = $Sale['payment_statut'];

            $data[] = $item;
        }

        return response()->json($data);
    }


    //----------------- Customers Report -----------------------\\

    public function Client_Report(request $request)
    {

        $this->authorizeForUser($request->user('api'), 'Reports_customers', Client::class);

        // How many items do you want to display.
        $perPage = $request->limit;
        $pageStart = \Request::get('page', 1);
        // Start displaying items from this number;
        $offSet = ($pageStart * $perPage) - $perPage;
        $order = $request->SortField;
        $dir = $request->SortType;
        $data = array();

        $clients = Client::where('deleted_at', '=', null)
        // Search With Multiple Param
            ->where(function ($query) use ($request) {
                return $query->when($request->filled('search'), function ($query) use ($request) {
                    return $query->where('name', 'LIKE', "%{$request->search}%")
                        ->orWhere('code', 'LIKE', "%{$request->search}%")
                        ->orWhere('phone', 'LIKE', "%{$request->search}%");
                });
            });

        $totalRows = $clients->count();
        if($perPage == "-1"){
            $perPage = $totalRows;
        }
        $clients = $clients->offset($offSet)
            ->limit($perPage)
            ->orderBy($order, $dir)
            ->get();

        foreach ($clients as $client) {
            $item['total_sales'] = DB::table('sales')
                ->where('deleted_at', '=', null)
                ->where('client_id', $client->id)
                ->count();

            $item['total_amount'] = DB::table('sales')
                ->where('deleted_at', '=', null)
                ->where('statut', 'completed')
                ->where('client_id', $client->id)
                ->sum('GrandTotal');

            $item['total_paid'] = DB::table('payment_sales')
                ->join('sales', 'payment_sales.sale_id', '=', 'sales.id')
                ->where('payment_sales.deleted_at', '=', null)
                ->where('sales.client_id', $client->id)
                ->sum('payment_sales.montant');
            
            // Get total paid from global client payments (new system)
            $global_payments = DB::table('client_payments')
                ->where('deleted_at', '=', null)
                ->where('client_id', $client->id)
                ->sum('montant');

            $item['due'] = $item['total_amount'] - (($item['total_paid'] + $global_payments));           

            $item['total_amount_return'] = DB::table('sale_returns')
                ->where('deleted_at', '=', null)
                ->where('client_id', $client->id)
                ->sum('GrandTotal');

            $item['total_paid_return'] = DB::table('sale_returns')
                ->where('sale_returns.deleted_at', '=', null)
                ->where('sale_returns.client_id', $client->id)
                ->sum('paid_amount');

            $item['credit_initial'] = $client->credit_initial ?? 0;
            $item['total_credit'] = $client->credit_initial + $item['due'] - $item['total_paid_return'];


            $item['return_Due'] = $item['total_amount_return'] - $item['total_paid_return'];

            $item['name'] = $client->name;
            $item['phone'] = $client->phone;
            $item['code'] = $client->code;
            $item['id'] = $client->id;

            $data[] = $item;
        }

        return response()->json([
            'report' => $data,
            'totalRows' => $totalRows,
        ]);

    }

    //----------------- Customers Report By ID-----------------------\\

    public function Client_Report_detail(request $request, $id)
    {

        $this->authorizeForUser($request->user('api'), 'Reports_customers', Client::class);

        $client = Client::where('deleted_at', '=', null)->findOrFail($id);

        $data['client_name'] = $client->name;
        $data['client_address'] = $client->address;

        $data['total_sales'] = DB::table('sales')
            ->where('deleted_at', '=', null)
            ->where('client_id', $id)
            ->count();

        $data['total_amount'] = DB::table('sales')
            ->where('deleted_at', '=', null)
            ->where('statut', 'completed')
            ->where('client_id', $id)
            ->sum('GrandTotal');

        $data['total_paid_return'] = DB::table('sale_returns')
            ->where('deleted_at', '=', null)
            ->where('client_id', $id)
            ->sum('paid_amount');
        
        $data['total_paid'] = DB::table('payment_sales')
        ->join('sales', 'payment_sales.sale_id', '=', 'sales.id')
        ->where('payment_sales.deleted_at', '=', null)
        ->where('sales.client_id', $id)
        ->sum('payment_sales.montant');
    
        // Get total paid from global client payments (new system)
        $global_payments = DB::table('client_payments')
            ->where('deleted_at', '=', null)
            ->where('client_id', $id)
            ->sum('montant');

        $data['due'] = $data['total_amount'] - (($data['total_paid'] + $global_payments)); 

        // Get total amount from returns to properly calculate total_credit
        $data['total_amount_return'] = DB::table('sale_returns')
            ->where('deleted_at', '=', null)
            ->where('client_id', $id)
            ->sum('GrandTotal');

        // Calculate return due
        $data['return_due'] = $data['total_amount_return'] - $data['total_paid_return'];

        // Calculate total_credit using the same formula as in ClientController
        $data['total_credit'] = $client->credit_initial + $data['due'] - $data['total_paid_return'];

        return response()->json(['report' => $data]);
    }

    //----------------- Provider Report By ID-----------------------\\

    public function Provider_Report_detail(request $request, $id)
    {

        $this->authorizeForUser($request->user('api'), 'Reports_suppliers', Provider::class);

        $provider = Provider::where('deleted_at', '=', null)->findOrFail($id);

        $data['total_purchase'] = DB::table('purchases')
            ->where('deleted_at', '=', null)
            ->where('provider_id', $id)
            ->count();

        $data['total_amount'] = DB::table('purchases')
            ->where('deleted_at', '=', null)
            ->where('statut', 'received')
            ->where('provider_id', $id)
            ->sum('GrandTotal');

        $data['total_paid'] = DB::table('purchases')
            ->where('deleted_at', '=', null)
            ->where('statut', 'received')
            ->where('provider_id', $id)
            ->sum('paid_amount');

        $data['due'] = ($data['total_amount'] - $data['total_paid']) + $provider->credit_initial;

        return response()->json(['report' => $data]);

    }

    //-------------------- Get Sales By Clients -------------\\

    public function Sales_Client(request $request)
    {

        $this->authorizeForUser($request->user('api'), 'Reports_customers', Client::class);
        // How many items do you want to display.
        $perPage = $request->limit;
        $pageStart = \Request::get('page', 1);
        // Start displaying items from this number;
        $offSet = ($pageStart * $perPage) - $perPage;

        $Role = Auth::user()->roles()->first();
        $ShowRecord = Role::findOrFail($Role->id)->inRole('record_view');

        $sales = Sale::where('deleted_at', '=', null)->with('client','warehouse')
            ->where(function ($query) use ($ShowRecord) {
                if (!$ShowRecord) {
                    return $query->where('user_id', '=', Auth::user()->id);
                }
            })
            ->where('client_id', $request->id)
             // Search With Multiple Param
             ->where(function ($query) use ($request) {
                return $query->when($request->filled('search'), function ($query) use ($request) {
                    return $query->where('Ref', 'LIKE', "%{$request->search}%")
                        ->orWhere('statut', 'LIKE', "%{$request->search}%")
                        ->orWhere('payment_statut', 'like', "%{$request->search}%")
                        ->orWhere(function ($query) use ($request) {
                            return $query->whereHas('warehouse', function ($q) use ($request) {
                                $q->where('name', 'LIKE', "%{$request->search}%");
                            });
                        })
                        ->orWhere(function ($query) use ($request) {
                            return $query->whereHas('client', function ($q) use ($request) {
                                $q->where('name', 'LIKE', "%{$request->search}%");
                            });
                        });
                });
            });

        $totalRows = $sales->count();
        if($perPage == "-1"){
            $perPage = $totalRows;
        }
        $sales = $sales->offset($offSet)
            ->limit($perPage)
            ->orderBy('id', 'desc')
            ->get();

        $data = [];
        foreach ($sales as $sale) {
            $item['id'] = $sale->id;
            $item['date'] = $sale->date;
            $item['Ref'] = $sale->Ref;
            $item['warehouse_name'] = $sale['warehouse']->name;
            $item['client_name'] = $sale['client']->name;
            $item['statut'] = $sale->statut;
            $item['GrandTotal'] = $sale->GrandTotal;
            $item['paid_amount'] = $sale->paid_amount;
            $item['due'] = $sale->GrandTotal - $sale->paid_amount;
            $item['payment_status'] = $sale->payment_statut;
            $item['shipping_status'] = $sale->shipping_status;
            
            $data[] = $item;
        }
        return response()->json([
            'totalRows' => $totalRows,
            'sales' => $data,
        ]);

    }

    //-------------------- Get Payments By Clients -------------\\

    public function Payments_Client(request $request)
    {

        $this->authorizeForUser($request->user('api'), 'Reports_customers', Client::class);
        // How many items do you want to display.
        $perPage = $request->limit;
        $pageStart = \Request::get('page', 1);
        // Start displaying items from this number;
        $offSet = ($pageStart * $perPage) - $perPage;

        $Role = Auth::user()->roles()->first();
        $ShowRecord = Role::findOrFail($Role->id)->inRole('record_view');

        // New payments from client_payments table
        $newPaymentsQuery = DB::table('client_payments')
            ->where(function ($query) use ($ShowRecord) {
                if (!$ShowRecord) {
                    return $query->where('client_payments.user_id', '=', Auth::user()->id);
                }
            })
            ->where('client_payments.deleted_at', '=', null)
            ->where('client_payments.client_id', $request->id)
             // Search With Multiple Param
             ->where(function ($query) use ($request) {
                return $query->when($request->filled('search'), function ($query) use ($request) {
                    return $query->where('client_payments.Ref', 'LIKE', "%{$request->search}%")
                        ->orWhere('client_payments.date', 'LIKE', "%{$request->search}%")
                        ->orWhere('client_payments.Reglement', 'LIKE', "%{$request->search}%");
                });
            })
            ->join('clients', 'client_payments.client_id', '=', 'clients.id')
            ->select(
                'client_payments.id', 
                'client_payments.date', 
                'client_payments.Ref AS Ref',
                'client_payments.Reglement', 
                'client_payments.montant', 
                'client_payments.notes',
                'clients.name as client_name', 
                DB::raw("'client_payment' as payment_type")
            );
            
        // Old payments from payment_sales table that are linked to sales for this client
        $oldPaymentsQuery = DB::table('payment_sales')
            ->where(function ($query) use ($ShowRecord) {
                if (!$ShowRecord) {
                    return $query->where('payment_sales.user_id', '=', Auth::user()->id);
                }
            })
            ->where('payment_sales.deleted_at', '=', null)
            ->whereNull('payment_sales.client_payment_id') // Only get old payments without client_payment_id
            ->join('sales', 'payment_sales.sale_id', '=', 'sales.id')
            ->where('sales.client_id', $request->id)
            // Search With Multiple Param
            ->where(function ($query) use ($request) {
                return $query->when($request->filled('search'), function ($query) use ($request) {
                    return $query->where('payment_sales.Ref', 'LIKE', "%{$request->search}%")
                        ->orWhere('payment_sales.date', 'LIKE', "%{$request->search}%")
                        ->orWhere('payment_sales.Reglement', 'LIKE', "%{$request->search}%");
                });
            })
            ->join('clients', 'sales.client_id', '=', 'clients.id')
            ->select(
                'payment_sales.id', 
                'payment_sales.date', 
                'payment_sales.Ref AS Ref',
                'payment_sales.Reglement', 
                'payment_sales.montant', 
                'payment_sales.notes',
                'clients.name as client_name', 
                'sales.Ref as sale_ref',
                'payment_sales.sale_id',
                DB::raw("'payment_sale' as payment_type")
            );
            
        // Count total rows
        $newPaymentsCount = $newPaymentsQuery->count();
        $oldPaymentsCount = $oldPaymentsQuery->count();
        $totalRows = $newPaymentsCount + $oldPaymentsCount;
            
        if($perPage == "-1"){
            $perPage = $totalRows;
        }
        
        // Get results from both queries separately
        $newPayments = $newPaymentsQuery->get();
        $oldPayments = $oldPaymentsQuery->get();
        
        // Convert to array format for consistent processing
        $paymentsArray = [];
        
        foreach ($newPayments as $payment) {
            $paymentsArray[] = [
                'id' => $payment->id,
                'date' => $payment->date,
                'Ref' => $payment->Ref,
                'Reglement' => $payment->Reglement,
                'montant' => $payment->montant,
                'notes' => $payment->notes,
                'client_name' => $payment->client_name,
                'payment_type' => 'client_payment'
            ];
        }
        
        foreach ($oldPayments as $payment) {
            $paymentsArray[] = [
                'id' => $payment->id,
                'date' => $payment->date,
                'Ref' => $payment->Ref,
                'Reglement' => $payment->Reglement,
                'montant' => $payment->montant,
                'notes' => $payment->notes,
                'client_name' => $payment->client_name,
                'sale_ref' => $payment->sale_ref,
                'sale_id' => $payment->sale_id,
                'payment_type' => 'payment_sale'
            ];
        }
        
        // Sort by date descending
        usort($paymentsArray, function($a, $b) {
            return strtotime($b['date']) - strtotime($a['date']);
        });
        
        // Manual pagination
        $paymentsResults = array_slice($paymentsArray, $offSet, $perPage);

        return response()->json([
            'payments' => $paymentsResults,
            'totalRows' => $totalRows,
        ]);
    }

    //-------------------- Get Payment Details -------------\\

    public function Payment_Detail(request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'Reports_customers', Client::class);

        $payment_type = $request->payment_type ?? 'client_payment';
        
        if ($payment_type === 'client_payment') {
            // Process ClientPayment
            $payment = ClientPayment::with('client', 'user', 'account')->findOrFail($id);
            
            $payment_data = [
                'id' => $payment->id,
                'date' => $payment->date,
                'Ref' => $payment->Ref,
                'Reglement' => $payment->Reglement,
                'montant' => $payment->montant,
                'notes' => $payment->notes,
                'client_name' => $payment->client->name,
                'client_id' => $payment->client_id,
                'user_name' => $payment->user ? $payment->user->username : '',
                'account_name' => $payment->account ? $payment->account->account_name : '',
                'payment_type' => 'client_payment'
            ];
            
            // Get the allocations for this payment
            $allocations = [];
            $total_allocated = 0;
            
            $paymentSales = $payment->paymentSales()->with('sale')->get();
            
            foreach ($paymentSales as $detail) {
                $allocation = [
                    'id' => $detail->id,
                    'Ref' => $detail->Ref,
                    'date' => $detail->date,
                    'montant' => $detail->montant,
                    'type_credit' => $detail->type_credit,
                ];
                
                if ($detail->sale_id) {
                    $allocation['sale_ref'] = $detail->sale ? $detail->sale->Ref : '';
                    $allocation['allocation_type'] = 'Sale';
                } else if ($detail->type_credit == 'credit_initial') {
                    $allocation['allocation_type'] = 'Initial Credit';
                } else {
                    $allocation['allocation_type'] = 'Other';
                }
                
                $allocations[] = $allocation;
                $total_allocated += $detail->montant;
            }
            
            return response()->json([
                'payment' => $payment_data,
                'allocations' => $allocations,
                'totalAllocated' => $total_allocated,
                'unallocated' => $payment->montant - $total_allocated,
            ]);
        } else {
            // Process PaymentSale (old payments)
            $payment = PaymentSale::with('client', 'user', 'account', 'sale')->findOrFail($id);
            
            $payment_data = [
                'id' => $payment->id,
                'date' => $payment->date,
                'Ref' => $payment->Ref,
                'Reglement' => $payment->Reglement,
                'montant' => $payment->montant,
                'notes' => $payment->notes,
                'client_name' => $payment->client->name,
                'client_id' => $payment->client_id,
                'user_name' => $payment->user ? $payment->user->username : '',
                'account_name' => $payment->account ? $payment->account->account_name : '',
                'payment_type' => 'payment_sale',
                'sale_ref' => $payment->sale ? $payment->sale->Ref : null,
                'sale_id' => $payment->sale_id
            ];
            
            // For old payments, they were directly allocated to a sale
            $allocations = [];
            
            if ($payment->sale_id) {
                $allocations[] = [
                    'id' => $payment->id,
                    'Ref' => $payment->Ref,
                    'date' => $payment->date,
                    'montant' => $payment->montant,
                    'sale_ref' => $payment->sale ? $payment->sale->Ref : '',
                    'allocation_type' => 'Sale',
                    'sale_id' => $payment->sale_id
                ];
            } else if ($payment->type_credit == 'credit_initial') {
                $allocations[] = [
                    'id' => $payment->id,
                    'Ref' => $payment->Ref,
                    'date' => $payment->date,
                    'montant' => $payment->montant,
                    'allocation_type' => 'Initial Credit',
                ];
            }
            
            return response()->json([
                'payment' => $payment_data,
                'allocations' => $allocations,
                'totalAllocated' => $payment->montant,
                'unallocated' => 0, // Old payments were fully allocated
            ]);
        }
    }

    //-------------------- Get Old Payment Sales Details (Pre-ClientPayment) -------------\\

    public function Old_Payment_Detail(request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'Reports_customers', Client::class);

        $payment = PaymentSale::with('client', 'user', 'account', 'sale')->findOrFail($id);
        
        $payment_data = [
            'id' => $payment->id,
            'date' => $payment->date,
            'Ref' => $payment->Ref,
            'Reglement' => $payment->Reglement,
            'montant' => $payment->montant,
            'notes' => $payment->notes,
            'client_name' => $payment->sale ? $payment->sale->client->name : ($payment->client ? $payment->client->name : 'Unknown'),
            'client_id' => $payment->sale ? $payment->sale->client_id : ($payment->client_id ?? null),
            'user_name' => $payment->user ? $payment->user->username : '',
            'account_name' => $payment->account ? $payment->account->account_name : '',
            'payment_type' => 'payment_sale',
            'sale_ref' => $payment->sale ? $payment->sale->Ref : null,
            'sale_id' => $payment->sale_id
        ];
        
        // For old payments, they were directly allocated to a sale
        $allocations = [];
        
        if ($payment->sale_id) {
            $allocations[] = [
                'id' => $payment->id,
                'Ref' => $payment->Ref,
                'date' => $payment->date,
                'montant' => $payment->montant,
                'sale_ref' => $payment->sale ? $payment->sale->Ref : '',
                'allocation_type' => 'Sale',
                'sale_id' => $payment->sale_id
            ];
        } else if ($payment->type_credit == 'credit_initial') {
            $allocations[] = [
                'id' => $payment->id,
                'Ref' => $payment->Ref,
                'date' => $payment->date,
                'montant' => $payment->montant,
                'allocation_type' => 'Initial Credit',
            ];
        }
        
        return response()->json([
            'payment' => $payment_data,
            'allocations' => $allocations,
            'totalAllocated' => $payment->montant,
            'unallocated' => 0, // Old payments were fully allocated
        ]);
    }

    //-------------------- Get Quotations By Clients -------------\\

    public function Quotations_Client(request $request)
    {

        $this->authorizeForUser($request->user('api'), 'Reports_customers', Client::class);
        // How many items do you want to display.
        $perPage = $request->limit;
        $pageStart = \Request::get('page', 1);
        // Start displaying items from this number;
        $offSet = ($pageStart * $perPage) - $perPage;

        $Role = Auth::user()->roles()->first();
        $ShowRecord = Role::findOrFail($Role->id)->inRole('record_view');
        $data = array();
        
        $Quotations = Quotation::with('client', 'warehouse')
            ->where('deleted_at', '=', null)
            ->where('client_id', $request->id)
            ->where(function ($query) use ($ShowRecord) {
                if (!$ShowRecord) {
                    return $query->where('user_id', '=', Auth::user()->id);
                }
            })
            //Search With Multiple Param
            ->where(function ($query) use ($request) {
                return $query->when($request->filled('search'), function ($query) use ($request) {
                    return $query->where('Ref', 'LIKE', "%{$request->search}%")
                        ->orWhere('statut', 'LIKE', "%{$request->search}%")
                        ->orWhere(function ($query) use ($request) {
                            return $query->whereHas('warehouse', function ($q) use ($request) {
                                $q->where('name', 'LIKE', "%{$request->search}%");
                            });
                        })
                        ->orWhere(function ($query) use ($request) {
                            return $query->whereHas('client', function ($q) use ($request) {
                                $q->where('name', 'LIKE', "%{$request->search}%");
                            });
                        });
                });
            });

        $totalRows = $Quotations->count();
        if($perPage == "-1"){
            $perPage = $totalRows;
        }
        $Quotations = $Quotations->offset($offSet)
            ->limit($perPage)
            ->orderBy('id', 'desc')
            ->get();

        foreach ($Quotations as $Quotation) {

            $item['id'] = $Quotation->id;
            $item['date'] = $Quotation->date;
            $item['Ref'] = $Quotation->Ref;
            $item['statut'] = $Quotation->statut;
            $item['warehouse_name'] = $Quotation['warehouse']->name;
            $item['client_name'] = $Quotation['client']->name;
            $item['GrandTotal'] = $Quotation->GrandTotal;

            $data[] = $item;
        }

        return response()->json([
            'quotations' => $data,
            'totalRows' => $totalRows,
        ]);
    }

    //-------------------- Get Returns By Client -------------\\

    public function Returns_Client(request $request)
    {

        $this->authorizeForUser($request->user('api'), 'Reports_customers', Client::class);
        // How many items do you want to display.
        $perPage = $request->limit;
        $pageStart = \Request::get('page', 1);
        // Start displaying items from this number;
        $offSet = ($pageStart * $perPage) - $perPage;
        $data = array();

        //  Check If User Has Permission Show All Records
        $Role = Auth::user()->roles()->first();
        $ShowRecord = Role::findOrFail($Role->id)->inRole('record_view');

        $SaleReturn = SaleReturn::where('deleted_at', '=', null)->with('sale','client','warehouse')
            ->where('client_id', $request->id)
            ->where(function ($query) use ($ShowRecord) {
                if (!$ShowRecord) {
                    return $query->where('user_id', '=', Auth::user()->id);
                }
            })
             // Search With Multiple Param
             ->where(function ($query) use ($request) {
                return $query->when($request->filled('search'), function ($query) use ($request) {
                    return $query->where('Ref', 'LIKE', "%{$request->search}%")
                        ->orWhere('statut', 'LIKE', "%{$request->search}%")
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

        $totalRows = $SaleReturn->count();
        if($perPage == "-1"){
            $perPage = $totalRows;
        }
        $SaleReturn = $SaleReturn->offset($offSet)
            ->limit($perPage)
            ->orderBy('id', 'desc')
            ->get();

        foreach ($SaleReturn as $Sale_Return) {
            $item['id'] = $Sale_Return->id;
            $item['Ref'] = $Sale_Return->Ref;
            $item['date'] = $Sale_Return->date;
            $item['statut'] = $Sale_Return->statut;
            $item['client_name'] = $Sale_Return['client']->name;
            $item['sale_ref'] = $Sale_Return['sale']?$Sale_Return['sale']->Ref:'---';
            $item['sale_id'] = $Sale_Return['sale']?$Sale_Return['sale']->id:NULL;
            $item['warehouse_name'] = $Sale_Return['warehouse']->name;
            $item['GrandTotal'] = $Sale_Return->GrandTotal;
            $item['paid_amount'] = $Sale_Return->paid_amount;
            $item['due'] = $Sale_Return->GrandTotal - $Sale_Return->paid_amount;
            $item['payment_status'] = $Sale_Return->payment_statut;

            $data[] = $item;
        }

        return response()->json([
            'totalRows' => $totalRows,
            'returns_customer' => $data,
        ]);
    }



    //------------- Show Report Purchases ----------\\

    public function Report_Purchases(request $request)
    {
        $this->authorizeForUser($request->user('api'), 'ReportPurchases', Purchase::class);
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
        );
        $columns = array(
            0 => 'Ref',
            1 => 'statut',
            2 => 'provider_id',
            3 => 'payment_statut',
            4 => 'warehouse_id',
        );
        $data = array();
        $total = 0;

        $Purchases = Purchase::select('purchases.*')
            ->with('facture', 'provider', 'warehouse')
            ->join('providers', 'purchases.provider_id', '=', 'providers.id')
            ->where('purchases.deleted_at', '=', null)
            ->whereBetween('purchases.date', array($request->from, $request->to));
            
        //  Check If User Has Permission Show All Records
        $Purchases = $helpers->Show_Records($Purchases);
        //Multiple Filter
        $Filtred = $helpers->filter($Purchases, $columns, $param, $request)
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
        $Purchases = $Filtred->offset($offSet)
            ->limit($perPage)
            ->orderBy('purchases.' . $order, $dir)
            ->get();

        foreach ($Purchases as $Purchase) {

            $item['id'] = $Purchase->id;
            $item['date'] = $Purchase->date;
            $item['Ref'] = $Purchase->Ref;
            $item['warehouse_name'] = $Purchase['warehouse']->name;
            $item['discount'] = $Purchase->discount;
            $item['shipping'] = $Purchase->shipping;
            $item['statut'] = $Purchase->statut;
            $item['provider_name'] = $Purchase['provider']->name;
            $item['provider_email'] = $Purchase['provider']->email;
            $item['provider_tele'] = $Purchase['provider']->phone;
            $item['provider_code'] = $Purchase['provider']->code;
            $item['provider_adr'] = $Purchase['provider']->adresse;
            $item['GrandTotal'] = $Purchase['GrandTotal'];
            $item['paid_amount'] = $Purchase['paid_amount'];
            $item['due'] = $Purchase['GrandTotal'] - $Purchase['paid_amount'];
            $item['payment_status'] = $Purchase['payment_statut'];

            $data[] = $item;
        }

        $suppliers = provider::where('deleted_at', '=', null)->get(['id', 'name']);

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
            'purchases' => $data,
            'suppliers' => $suppliers,
            'warehouses' => $warehouses,
        ]);
    }
    
    //------------- Show Report SALES -----------\\

    public function Report_Sales(request $request)
    {
        $this->authorizeForUser($request->user('api'), 'Reports_sales', Sale::class);
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
        );
        $columns = array(
            0 => 'Ref',
            1 => 'statut',
            2 => 'client_id',
            3 => 'payment_statut',
            4 => 'warehouse_id',
        );
        
        $data = array();

        $Sales = Sale::select('sales.*')
            ->with('facture', 'client', 'warehouse')
            ->join('clients', 'sales.client_id', '=', 'clients.id')
            ->where('sales.deleted_at', '=', null)
            ->whereBetween('sales.date', array($request->from, $request->to));

        //  Check If User Has Permission Show All Records
        $Sales = $helpers->Show_Records($Sales);
        //Multiple Filter
        $Filtred = $helpers->filter($Sales, $columns, $param, $request)
        // Search With Multiple Param
        ->where(function ($query) use ($request) {
            return $query->when($request->filled('search'), function ($query) use ($request) {
                return $query->where('Ref', 'LIKE', "%{$request->search}%")
                    ->orWhere('statut', 'LIKE', "%{$request->search}%")
                    ->orWhere('GrandTotal', $request->search)
                    ->orWhere('payment_statut', 'like', "%{$request->search}%")
                    ->orWhere('shipping_status', 'like', "%{$request->search}%")
                    ->orWhere(function ($query) use ($request) {
                        return $query->whereHas('client', function ($q) use ($request) {
                            $q->where('name', 'LIKE', "%{$request->search}%");
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
        $Sales = $Filtred->offset($offSet)
            ->limit($perPage)
            ->orderBy('sales.' . $order, $dir)
            ->get();

        foreach ($Sales as $Sale) {

            $item['id'] = $Sale['id'];
            $item['date'] = $Sale['date'];
            $item['Ref'] = $Sale['Ref'];
            $item['statut'] = $Sale['statut'];
            $item['discount'] = $Sale['discount'];
            $item['shipping'] = $Sale['shipping'];
            $item['warehouse_name'] = $Sale['warehouse']['name'];
            $item['client_name'] = $Sale['client']['name'];
            $item['client_email'] = $Sale['client']['email'];
            $item['client_tele'] = $Sale['client']['phone'];
            $item['client_code'] = $Sale['client']['code'];
            $item['client_adr'] = $Sale['client']['adresse'];
            $item['GrandTotal'] = $Sale['GrandTotal'];
            $item['paid_amount'] = $Sale['paid_amount'];
            $item['due'] = $Sale['GrandTotal'] - $Sale['paid_amount'];
            $item['payment_status'] = $Sale['payment_statut'];

            $data[] = $item;
        }

        $customers = client::where('deleted_at', '=', null)->get(['id', 'name']);

            //get warehouses assigned to user
        $user_auth = auth()->user();
        if($user_auth->is_all_warehouses){
            $warehouses = Warehouse::where('deleted_at', '=', null)->get(['id', 'name']);
        }else{
            $warehouses_id = UserWarehouse::where('user_id', $user_auth->id)->pluck('warehouse_id')->toArray();
            $warehouses = Warehouse::where('deleted_at', '=', null)->whereIn('id', $warehouses_id)->get(['id', 'name']);
        }

        return response()->json(
            [
                'totalRows' => $totalRows,
                'sales' => $data,
                'customers' => $customers, 
                'warehouses' => $warehouses
            ]
        );
    }

    //----------------- Providers Report -----------------------\\

    public function Providers_Report(request $request)
    {

        $this->authorizeForUser($request->user('api'), 'Reports_suppliers', Provider::class);
        // How many items do you want to display.
        $perPage = $request->limit;
        $pageStart = \Request::get('page', 1);
        // Start displaying items from this number;
        $offSet = ($pageStart * $perPage) - $perPage;
        $order = $request->SortField;
        $dir = $request->SortType;
        $data = array();

        $providers = Provider::where('deleted_at', '=', null)
        // Search With Multiple Param
            ->where(function ($query) use ($request) {
                return $query->when($request->filled('search'), function ($query) use ($request) {
                    return $query->where('name', 'LIKE', "%{$request->search}%")
                        ->orWhere('code', 'LIKE', "%{$request->search}%")
                        ->orWhere('phone', 'LIKE', "%{$request->search}%");
                });
            });

        $totalRows = $providers->count();
        if($perPage == "-1"){
            $perPage = $totalRows;
        }
        $providers = $providers->offset($offSet)
            ->limit($perPage)
            ->orderBy($order, $dir)
            ->get();

        foreach ($providers as $provider) {
            $item['total_purchase'] = DB::table('purchases')
                ->where('deleted_at', '=', null)
                ->where('provider_id', $provider->id)
                ->count();

            $item['total_amount'] = DB::table('purchases')
                ->where('deleted_at', '=', null)
                ->where('statut', 'received')
                ->where('provider_id', $provider->id)
                ->sum('GrandTotal');

            $item['total_paid'] = DB::table('purchases')
                ->where('deleted_at', '=', null)
                ->where('statut', 'received')
                ->where('provider_id', $provider->id)
                ->sum('paid_amount');

            $item['due'] = $item['total_amount'] - $item['total_paid'];



            $item['total_amount_return'] = DB::table('purchase_returns')
            ->where('deleted_at', '=', null)
            ->where('provider_id', $provider->id)
            ->sum('GrandTotal');

            $item['total_paid_return'] = DB::table('purchase_returns')
                ->where('deleted_at', '=', null)
                ->where('provider_id', $provider->id)
                ->sum('paid_amount');

            $item['return_Due'] = $item['total_amount_return'] - $item['total_paid_return'];

            $item['credit_initial'] = $provider->credit_initial ?? 0;
            $item['total_credit'] = $provider->credit_initial + $item['due'];

            $item['id'] = $provider->id;
            $item['name'] = $provider->name;
            $item['phone'] = $provider->phone;
            $item['code'] = $provider->code;

            $data[] = $item;
        }

        return response()->json([
            'report' => $data,
            'totalRows' => $totalRows,
        ]);

    }

    //-------------------- Get Purchases By Provider -------------\\

    public function Purchases_Provider(request $request)
    {

        $this->authorizeForUser($request->user('api'), 'Reports_suppliers', Provider::class);
        // How many items do you want to display.
        $perPage = $request->limit;
        $pageStart = \Request::get('page', 1);
        // Start displaying items from this number;
        $offSet = ($pageStart * $perPage) - $perPage;
        $data = array();

        $Role = Auth::user()->roles()->first();
        $ShowRecord = Role::findOrFail($Role->id)->inRole('record_view');

        $purchases = Purchase::where('deleted_at', '=', null)
            ->with('provider','warehouse')
            ->where('provider_id', $request->id)
            ->where(function ($query) use ($ShowRecord) {
                if (!$ShowRecord) {
                    return $query->where('user_id', '=', Auth::user()->id);
                }
            })
             // Search With Multiple Param
             ->where(function ($query) use ($request) {
                return $query->when($request->filled('search'), function ($query) use ($request) {
                    return $query->where('Ref', 'LIKE', "%{$request->search}%")
                        ->orWhere('statut', 'LIKE', "%{$request->search}%")
                        ->orWhere('payment_statut', 'like', "$request->search")
                        ->orWhere(function ($query) use ($request) {
                            return $query->whereHas('provider', function ($q) use ($request) {
                                $q->where('name', 'LIKE', "%{$request->search}%");
                            });
                        })
                        ->orWhere(function ($query) use ($request) {
                            return $query->whereHas('warehouse', function ($q) use ($request) {
                                $q->where('name', 'LIKE', "%{$request->search}%");
                            });
                        });
                });
            });

        $totalRows = $purchases->count();
        if($perPage == "-1"){
            $perPage = $totalRows;
        }
        $purchases = $purchases->offset($offSet)
            ->limit($perPage)
            ->orderBy('id', 'desc')
            ->get();

        foreach ($purchases as $purchase) {
            $item['id'] = $purchase->id;
            $item['Ref'] = $purchase->Ref;
            $item['date'] = $purchase->date;
            $item['warehouse_name'] = $purchase['warehouse']->name;
            $item['provider_name'] = $purchase['provider']->name;
            $item['statut'] = $purchase->statut;
            $item['GrandTotal'] = $purchase->GrandTotal;
            $item['paid_amount'] = $purchase->paid_amount;
            $item['due'] = $purchase->GrandTotal - $purchase->paid_amount;
            $item['payment_status'] = $purchase->payment_statut;

            $data[] = $item;
        }

        return response()->json([
            'totalRows' => $totalRows,
            'purchases' => $data,
        ]);

    }

    //-------------------- Get Payments By Provider -------------\\

    public function Payments_Provider(request $request)
    {

        $this->authorizeForUser($request->user('api'), 'Reports_suppliers', Provider::class);

        // How many items do you want to display.
        $perPage = $request->limit;
        $pageStart = \Request::get('page', 1);
        // Start displaying items from this number;
        $offSet = ($pageStart * $perPage) - $perPage;
        $data = array();

        $Role = Auth::user()->roles()->first();
        $ShowRecord = Role::findOrFail($Role->id)->inRole('record_view');

        $payments = DB::table('payment_purchases')
            ->where(function ($query) use ($ShowRecord) {
                if (!$ShowRecord) {
                    return $query->where('user_id', '=', Auth::user()->id);
                }
            })
            ->where('payment_purchases.deleted_at', '=', null)
            ->join('purchases', 'payment_purchases.purchase_id', '=', 'purchases.id')
            ->where('purchases.provider_id', $request->id)
             // Search With Multiple Param
             ->where(function ($query) use ($request) {
                return $query->when($request->filled('search'), function ($query) use ($request) {
                    return $query->where('payment_purchases.Ref', 'LIKE', "%{$request->search}%")
                        ->orWhere('payment_purchases.date', 'LIKE', "%{$request->search}%")
                        ->orWhere('payment_purchases.Reglement', 'LIKE', "%{$request->search}%");
                });
            })
            ->select(
                'payment_purchases.date', 'payment_purchases.Ref AS Ref', 'purchases.Ref AS purchase_Ref',
                'payment_purchases.Reglement', 'payment_purchases.montant'
            );

        $totalRows = $payments->count();
        if($perPage == "-1"){
            $perPage = $totalRows;
        }
        $payments = $payments->offset($offSet)
            ->limit($perPage)
            ->orderBy('payment_purchases.id', 'desc')
            ->get();

        return response()->json([
            'payments' => $payments,
            'totalRows' => $totalRows,
        ]);
    }

    //-------------------- Get Returns By Providers -------------\\

    public function Returns_Provider(request $request)
    {

        $this->authorizeForUser($request->user('api'), 'Reports_suppliers', Provider::class);

        // How many items do you want to display.
        $perPage = $request->limit;
        $pageStart = \Request::get('page', 1);
        // Start displaying items from this number;
        $offSet = ($pageStart * $perPage) - $perPage;
        $data = array();

        $Role = Auth::user()->roles()->first();
        $ShowRecord = Role::findOrFail($Role->id)->inRole('record_view');

        $PurchaseReturn = PurchaseReturn::where('deleted_at', '=', null)
            ->with('purchase','provider','warehouse')
            ->where('provider_id', $request->id)
            ->where(function ($query) use ($ShowRecord) {
                if (!$ShowRecord) {
                    return $query->where('user_id', '=', Auth::user()->id);
                }
            })
            // Search With Multiple Param
            ->where(function ($query) use ($request) {
                return $query->when($request->filled('search'), function ($query) use ($request) {
                    return $query->where('Ref', 'LIKE', "%{$request->search}%")
                        ->orWhere('statut', 'LIKE', "%{$request->search}%")
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

        $totalRows = $PurchaseReturn->count();
        if($perPage == "-1"){
            $perPage = $totalRows;
        }
        $PurchaseReturn = $PurchaseReturn->offset($offSet)
            ->limit($perPage)
            ->orderBy('id', 'desc')
            ->get();

        foreach ($PurchaseReturn as $Purchase_Return) {
            $item['id'] = $Purchase_Return->id;
            $item['Ref'] = $Purchase_Return->Ref;
            $item['statut'] = $Purchase_Return->statut;
            $item['purchase_ref'] = $Purchase_Return['purchase']?$Purchase_Return['purchase']->Ref:'---';
            $item['purchase_id'] = $Purchase_Return['purchase']?$Purchase_Return['purchase']->id:NULL;
            $item['provider_name'] = $Purchase_Return['provider']->name;
            $item['warehouse_name'] = $Purchase_Return['warehouse']->name;
            $item['GrandTotal'] = $Purchase_Return->GrandTotal;
            $item['paid_amount'] = $Purchase_Return->paid_amount;
            $item['due'] = $Purchase_Return->GrandTotal - $Purchase_Return->paid_amount;
            $item['payment_status'] = $Purchase_Return->payment_statut;

            $data[] = $item;
        }

        return response()->json([
            'totalRows' => $totalRows,
            'returns_supplier' => $data,
        ]);

    }

    //-------------------- Top 5 Suppliers -------------\\

    public function ToProviders(Request $request)
    {

        $this->authorizeForUser($request->user('api'), 'Reports_suppliers', Provider::class);

        $results = DB::table('purchases')->where('purchases.deleted_at', '=', null)
            ->join('providers', 'purchases.provider_id', '=', 'providers.id')
            ->select(DB::raw('providers.name'), DB::raw('count(*) as count'))
            ->groupBy('providers.name')
            ->orderBy('count', 'desc')
            ->take(5)
            ->get();

        $data = [];
        $providers = [];
        foreach ($results as $result) {
            $providers[] = $result->name;
            $data[] = $result->count;
        }
        $data[] = 0;
        return response()->json(['providers' => $providers, 'data' => $data]);
    }

    //----------------- Warehouse Report By ID-----------------------\\

    public function Warehouse_Report(request $request)
    {

        $this->authorizeForUser($request->user('api'), 'WarehouseStock', Product::class);

        $data['sales'] = Sale::where('deleted_at', '=', null)
            ->where(function ($query) use ($request) {
                return $query->when($request->filled('warehouse_id'), function ($query) use ($request) {
                    return $query->where('warehouse_id', $request->warehouse_id);
                });
            })->count();

        $data['purchases'] = Purchase::where('deleted_at', '=', null)
            ->where(function ($query) use ($request) {
                return $query->when($request->filled('warehouse_id'), function ($query) use ($request) {
                    return $query->where('warehouse_id', $request->warehouse_id);
                });
            })->count();

        $data['ReturnPurchase'] = PurchaseReturn::where('deleted_at', '=', null)
            ->where(function ($query) use ($request) {
                return $query->when($request->filled('warehouse_id'), function ($query) use ($request) {
                    return $query->where('warehouse_id', $request->warehouse_id);
                });
            })->count();

        $data['ReturnSale'] = SaleReturn::where('deleted_at', '=', null)
            ->where(function ($query) use ($request) {
                return $query->when($request->filled('warehouse_id'), function ($query) use ($request) {
                    return $query->where('warehouse_id', $request->warehouse_id);
                });
            })->count();

        //get warehouses assigned to user
        $user_auth = auth()->user();
        if($user_auth->is_all_warehouses){
            $warehouses = Warehouse::where('deleted_at', '=', null)->get(['id', 'name']);
        }else{
            $warehouses_id = UserWarehouse::where('user_id', $user_auth->id)->pluck('warehouse_id')->toArray();
            $warehouses = Warehouse::where('deleted_at', '=', null)->whereIn('id', $warehouses_id)->get(['id', 'name']);
        }

        return response()->json([
            'data' => $data,
            'warehouses' => $warehouses,
        ], 200);

    }

    //-------------------- Get Sales By Warehouse -------------\\

    public function Sales_Warehouse(request $request)
    {

        $this->authorizeForUser($request->user('api'), 'WarehouseStock', Product::class);
        // How many items do you want to display.
        $perPage = $request->limit;
        $pageStart = \Request::get('page', 1);
        // Start displaying items from this number;
        $offSet = ($pageStart * $perPage) - $perPage;
        $data = [];

        $Role = Auth::user()->roles()->first();
        $ShowRecord = Role::findOrFail($Role->id)->inRole('record_view');

        $sales = Sale::where('deleted_at', '=', null)->with('client','warehouse')
            ->where(function ($query) use ($ShowRecord) {
                if (!$ShowRecord) {
                    return $query->where('user_id', '=', Auth::user()->id);
                }
            })
            ->where(function ($query) use ($request) {
                return $query->when($request->filled('warehouse_id'), function ($query) use ($request) {
                    return $query->where('warehouse_id', $request->warehouse_id);
                });
            })
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
                        });
                });
            });

        $totalRows = $sales->count();
        if($perPage == "-1"){
            $perPage = $totalRows;
        }
        $sales = $sales->offset($offSet)
            ->limit($perPage)
            ->orderBy('id', 'desc')
            ->get();

        foreach ($sales as $sale) {
            $item['id'] = $sale->id;
            $item['date'] = $sale->date;
            $item['Ref'] = $sale->Ref;
            $item['client_name'] = $sale['client']->name;
            $item['warehouse_name'] = $sale['warehouse']->name;
            $item['statut'] = $sale->statut;
            $item['GrandTotal'] = $sale->GrandTotal;
            $item['paid_amount'] = $sale->paid_amount;
            $item['due'] = $sale->GrandTotal - $sale->paid_amount;
            $item['payment_status'] = $sale->payment_statut;
            $item['shipping_status'] = $sale->shipping_status;

            $data[] = $item;
        }
        return response()->json([
            'totalRows' => $totalRows,
            'sales' => $data,
        ]);

    }

    //-------------------- Get Quotations By Warehouse -------------\\

    public function Quotations_Warehouse(request $request)
    {

        $this->authorizeForUser($request->user('api'), 'WarehouseStock', Product::class);
        // How many items do you want to display.
        $perPage = $request->limit;
        $pageStart = \Request::get('page', 1);
        // Start displaying items from this number;
        $offSet = ($pageStart * $perPage) - $perPage;
        $data = [];

        $Role = Auth::user()->roles()->first();
        $ShowRecord = Role::findOrFail($Role->id)->inRole('record_view');

        $Quotations = Quotation::where('deleted_at', '=', null)
            ->with('client','warehouse')
            ->where(function ($query) use ($ShowRecord) {
                if (!$ShowRecord) {
                    return $query->where('user_id', '=', Auth::user()->id);
                }
            })
            ->where(function ($query) use ($request) {
                return $query->when($request->filled('warehouse_id'), function ($query) use ($request) {
                    return $query->where('warehouse_id', $request->warehouse_id);
                });
            })
        //Search With Multiple Param
            ->where(function ($query) use ($request) {
                return $query->when($request->filled('search'), function ($query) use ($request) {
                    return $query->where('Ref', 'LIKE', "%{$request->search}%")
                        ->orWhere('statut', 'LIKE', "%{$request->search}%")
                        ->orWhere('GrandTotal', $request->search)
                        ->orWhere(function ($query) use ($request) {
                            return $query->whereHas('client', function ($q) use ($request) {
                                $q->where('name', 'LIKE', "%{$request->search}%");
                            });
                        });
                });
            });
        $totalRows = $Quotations->count();
        if($perPage == "-1"){
            $perPage = $totalRows;
        }
        $Quotations = $Quotations->offset($offSet)
            ->limit($perPage)
            ->orderBy('id', 'desc')
            ->get();

        foreach ($Quotations as $Quotation) {
            $item['id'] = $Quotation->id;
            $item['date'] = $Quotation->date;
            $item['Ref'] = $Quotation->Ref;
            $item['warehouse_name'] = $Quotation['warehouse']->name;
            $item['client_name'] = $Quotation['client']->name;
            $item['statut'] = $Quotation->statut;
            $item['GrandTotal'] = $Quotation->GrandTotal;

            $data[] = $item;
        }

        return response()->json([
            'quotations' => $data,
            'totalRows' => $totalRows,
        ]);
    }

    //-------------------- Get Returns Sale By Warehouse -------------\\

    public function Returns_Sale_Warehouse(request $request)
    {

        $this->authorizeForUser($request->user('api'), 'WarehouseStock', Product::class);
        // How many items do you want to display.
        $perPage = $request->limit;
        $pageStart = \Request::get('page', 1);
        // Start displaying items from this number;
        $offSet = ($pageStart * $perPage) - $perPage;
        $data = array();

        //  Check If User Has Permission Show All Records
        $Role = Auth::user()->roles()->first();
        $ShowRecord = Role::findOrFail($Role->id)->inRole('record_view');

        $SaleReturn = SaleReturn::where('deleted_at', '=', null)
            ->with('sale','client','warehouse')
            ->where(function ($query) use ($ShowRecord) {
                if (!$ShowRecord) {
                    return $query->where('user_id', '=', Auth::user()->id);
                }
            })
            ->where(function ($query) use ($request) {
                return $query->when($request->filled('warehouse_id'), function ($query) use ($request) {
                    return $query->where('warehouse_id', $request->warehouse_id);
                });
            })
        //Search With Multiple Param
            ->where(function ($query) use ($request) {
                return $query->when($request->filled('search'), function ($query) use ($request) {
                    return $query->where('Ref', 'LIKE', "%{$request->search}%")
                        ->orWhere('statut', 'LIKE', "%{$request->search}%")
                        ->orWhere('GrandTotal', $request->search)
                        ->orWhere('payment_statut', 'like', "$request->search")

                        ->orWhere(function ($query) use ($request) {
                            return $query->whereHas('sale', function ($q) use ($request) {
                                $q->where('Ref', 'LIKE', "%{$request->search}%");
                            });
                        })
                        ->orWhere(function ($query) use ($request) {
                            return $query->whereHas('client', function ($q) use ($request) {
                                $q->where('name', 'LIKE', "%{$request->search}%");
                            });
                        });
                });
            });

        $totalRows = $SaleReturn->count();
        if($perPage == "-1"){
            $perPage = $totalRows;
        }
        $SaleReturn = $SaleReturn->offset($offSet)
            ->limit($perPage)
            ->orderBy('id', 'desc')
            ->get();

        foreach ($SaleReturn as $Sale_Return) {
            $item['id'] = $Sale_Return->id;
            $item['warehouse_name'] = $Sale_Return['warehouse']->name;
            $item['Ref'] = $Sale_Return->Ref;
            $item['statut'] = $Sale_Return->statut;
            $item['client_name'] = $Sale_Return['client']->name;
            $item['sale_ref'] = $Sale_Return['sale']?$Sale_Return['sale']->Ref:'---';
            $item['sale_id'] = $Sale_Return['sale']?$Sale_Return['sale']->id:NULL;
            $item['GrandTotal'] = $Sale_Return->GrandTotal;
            $item['paid_amount'] = $Sale_Return->paid_amount;
            $item['due'] = $Sale_Return->GrandTotal - $Sale_Return->paid_amount;
            $item['payment_status'] = $Sale_Return->payment_statut;

            $data[] = $item;
        }

        return response()->json([
            'totalRows' => $totalRows,
            'returns_sale' => $data,
        ]);
    }

    //-------------------- Get Returns Purchase By Warehouse -------------\\

    public function Returns_Purchase_Warehouse(request $request)
    {

        $this->authorizeForUser($request->user('api'), 'WarehouseStock', Product::class);
        // How many items do you want to display.
        $perPage = $request->limit;
        $pageStart = \Request::get('page', 1);
        // Start displaying items from this number;
        $offSet = ($pageStart * $perPage) - $perPage;
        $data = array();

        //  Check If User Has Permission Show All Records
        $Role = Auth::user()->roles()->first();
        $ShowRecord = Role::findOrFail($Role->id)->inRole('record_view');

        $PurchaseReturn = PurchaseReturn::where('deleted_at', '=', null)
            ->with('purchase','provider','warehouse')
            ->where(function ($query) use ($ShowRecord) {
                if (!$ShowRecord) {
                    return $query->where('user_id', '=', Auth::user()->id);
                }
            })
            ->orWhere(function ($query) use ($request) {
                return $query->whereHas('purchase', function ($q) use ($request) {
                    $q->where('Ref', 'LIKE', "%{$request->search}%");
                });
            })
            ->where(function ($query) use ($request) {
                return $query->when($request->filled('warehouse_id'), function ($query) use ($request) {
                    return $query->where('warehouse_id', $request->warehouse_id);
                });
            })
        //Search With Multiple Param
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
                        });
                });
            });

        $totalRows = $PurchaseReturn->count();
        if($perPage == "-1"){
            $perPage = $totalRows;
        }
        $PurchaseReturn = $PurchaseReturn->offset($offSet)
            ->limit($perPage)
            ->orderBy('id', 'desc')
            ->get();

        foreach ($PurchaseReturn as $Purchase_Return) {
            $item['id'] = $Purchase_Return->id;
            $item['Ref'] = $Purchase_Return->Ref;
            $item['statut'] = $Purchase_Return->statut;
            $item['purchase_ref'] = $Purchase_Return['purchase']?$Purchase_Return['purchase']->Ref:'---';
            $item['purchase_id'] = $Purchase_Return['purchase']?$Purchase_Return['purchase']->id:NULL;
            $item['warehouse_name'] = $Purchase_Return['warehouse']->name;
            $item['provider_name'] = $Purchase_Return['provider']->name;
            $item['GrandTotal'] = $Purchase_Return->GrandTotal;
            $item['paid_amount'] = $Purchase_Return->paid_amount;
            $item['due'] = $Purchase_Return->GrandTotal - $Purchase_Return->paid_amount;
            $item['payment_status'] = $Purchase_Return->payment_statut;

            $data[] = $item;
        }

        return response()->json([
            'totalRows' => $totalRows,
            'returns_purchase' => $data,
        ]);
    }

    //-------------------- Get Expenses By Warehouse -------------\\

    public function Expenses_Warehouse(request $request)
    {

        $this->authorizeForUser($request->user('api'), 'WarehouseStock', Product::class);
        // How many items do you want to display.
        $perPage = $request->limit;
        $pageStart = \Request::get('page', 1);
        // Start displaying items from this number;
        $offSet = ($pageStart * $perPage) - $perPage;
        $data = array();

        //  Check If User Has Permission Show All Records
        $Role = Auth::user()->roles()->first();
        $ShowRecord = Role::findOrFail($Role->id)->inRole('record_view');

        $Expenses = Expense::where('deleted_at', '=', null)
            ->with('expense_category','warehouse')
            ->where(function ($query) use ($ShowRecord) {
                if (!$ShowRecord) {
                    return $query->where('user_id', '=', Auth::user()->id);
                }
            })
            ->where(function ($query) use ($request) {
                return $query->when($request->filled('warehouse_id'), function ($query) use ($request) {
                    return $query->where('warehouse_id', $request->warehouse_id);
                });
            })
        //Search With Multiple Param
            ->where(function ($query) use ($request) {
                return $query->when($request->filled('search'), function ($query) use ($request) {
                    return $query->where('Ref', 'LIKE', "%{$request->search}%")
                        ->orWhere('date', 'LIKE', "%{$request->search}%")
                        ->orWhere('details', 'LIKE', "%{$request->search}%")
                        ->orWhere(function ($query) use ($request) {
                            return $query->whereHas('expense_category', function ($q) use ($request) {
                                $q->where('name', 'LIKE', "%{$request->search}%");
                            });
                        });
                });
            });

        $totalRows = $Expenses->count();
        if($perPage == "-1"){
            $perPage = $totalRows;
        }
        $Expenses = $Expenses->offset($offSet)
            ->limit($perPage)
            ->orderBy('id', 'desc')
            ->get();

        foreach ($Expenses as $Expense) {

            $item['date'] = $Expense->date;
            $item['Ref'] = $Expense->Ref;
            $item['details'] = $Expense->details;
            $item['amount'] = $Expense->amount;
            $item['warehouse_name'] = $Expense['warehouse']->name;
            $item['category_name'] = $Expense['expense_category']->name;
            $data[] = $item;
        }

        return response()->json([
            'totalRows' => $totalRows,
            'expenses' => $data,
        ]);
    }

    //----------------- Warhouse Count Stock -----------------------\\

    public function Warhouse_Count_Stock(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'WarehouseStock', Product::class);

        $stock_count = product_warehouse::join('products', 'product_warehouse.product_id', '=', 'products.id')
            ->join('warehouses', 'product_warehouse.warehouse_id', '=', 'warehouses.id')
            ->where('product_warehouse.deleted_at', '=', null)
            ->select(
                DB::raw("count(DISTINCT products.id) as value"),
                DB::raw("warehouses.name as name"),
                DB::raw('(IFNULL(SUM(qte),0)) AS value1'),
            )
            ->where('qte', '>', 0)
            ->groupBy('warehouses.name')
            ->get();

        $stock_value = product_warehouse::join('products', 'product_warehouse.product_id', '=', 'products.id')
            ->join('warehouses', 'product_warehouse.warehouse_id', '=', 'warehouses.id')
            ->where('product_warehouse.deleted_at', '=', null)
            ->select(
                DB::raw("SUM(products.price * qte ) as price"),
                DB::raw("SUM(products.cost * qte) as cost"),
                DB::raw("warehouses.name as name"),
            )
            ->where('qte', '>', 0)
            ->groupBy('warehouses.name')
            ->get();

        $data = [];
        foreach ($stock_value as $key => $value) {
            $item['name'] = $value->name;
            $item['value'] = $value->price;
            $item['value1'] = $value->cost;
            $data[] = $item;
        }

        //get warehouses assigned to user
        $user_auth = auth()->user();
        if($user_auth->is_all_warehouses){
            $warehouses = Warehouse::where('deleted_at', '=', null)->get(['id', 'name']);
        }else{
            $warehouses_id = UserWarehouse::where('user_id', $user_auth->id)->pluck('warehouse_id')->toArray();
            $warehouses = Warehouse::where('deleted_at', '=', null)->whereIn('id', $warehouses_id)->get(['id', 'name']);
        }

        return response()->json([
            'stock_count' => $stock_count,
            'stock_value' => $data,
            'warehouses' => $warehouses,
        ]);

    }

    //-------------- Count  Product Quantity Alerts ---------------\\

    public function count_quantity_alert(request $request)
    {

        $products_alerts = product_warehouse::join('products', 'product_warehouse.product_id', '=', 'products.id')
            ->whereRaw('qte <= stock_alert')
            ->count();

        return response()->json($products_alerts);
    }


     //-----------------Profit And Loss ---------------------------\\

     public function ProfitAndLoss(request $request)
     {
 
         $this->authorizeForUser($request->user('api'), 'Reports_profit', Client::class);
 
         $role = Auth::user()->roles()->first();
         $view_records = Role::findOrFail($role->id)->inRole('record_view');
 
         $start_date = $request->from;
         $end_date   =  $request->to;
 
         //get warehouses assigned to user
         $user_auth = auth()->user();
         if($user_auth->is_all_warehouses){
             $warehouses = Warehouse::where('deleted_at', '=', null)->get(['id', 'name']);
             $array_warehouses_id = Warehouse::where('deleted_at', '=', null)->pluck('id')->toArray();
         }else{
             $array_warehouses_id = UserWarehouse::where('user_id', $user_auth->id)->pluck('warehouse_id')->toArray();
             $warehouses = Warehouse::where('deleted_at', '=', null)->whereIn('id', $array_warehouses_id)->get(['id', 'name']);
         }
 
         if(empty($request->warehouse_id)){
             $warehouse_id = 0;
         }else{
             $warehouse_id = $request->warehouse_id;
         }
         
         $data = [];
 
 
         //-------------Sale
         $report_total_sales = Sale::where('deleted_at', '=', null)
         ->where('statut', 'completed')
         ->whereBetween('date', array($start_date, $end_date))
 
         ->where(function ($query) use ($request, $warehouse_id, $array_warehouses_id) {
             if ($warehouse_id !== 0) {
                 return $query->where('warehouse_id', $warehouse_id);
             }else{
                 return $query->whereIn('warehouse_id', $array_warehouses_id);
 
             }
         })
 
         ->select(
             DB::raw('SUM(GrandTotal) AS sum'),
             DB::raw("count(*) as nmbr")
         )->first();
 
         $item['sales_sum'] =   number_format($report_total_sales->sum, 2, '.', ',');
 
         $item['sales_count'] =   $report_total_sales->nmbr;
 
 
         //--------Purchase
         $report_total_purchases =  Purchase::where('deleted_at', '=', null)
         ->where('statut', 'received')
         ->whereBetween('date', array($start_date, $end_date))
 
         ->where(function ($query) use ($request, $warehouse_id, $array_warehouses_id) {
             if ($warehouse_id !== 0) {
                 return $query->where('warehouse_id', $warehouse_id);
             }else{
                 return $query->whereIn('warehouse_id', $array_warehouses_id);
 
             }
         })
         ->select(
             DB::raw('SUM(GrandTotal) AS sum'),
             DB::raw("count(*) as nmbr")
         )->first();
 
         $item['purchases_sum'] =   number_format($report_total_purchases->sum, 2, '.', ',');
         $item['purchases_count'] =  $report_total_purchases->nmbr;
 
 
         //--------SaleReturn
         $report_total_returns_sales = SaleReturn::where('deleted_at', '=', null)
         ->where('statut', 'received')
         ->whereBetween('date', array($start_date, $end_date))
 
         ->where(function ($query) use ($request, $warehouse_id, $array_warehouses_id) {
             if ($warehouse_id !== 0) {
                 return $query->where('warehouse_id', $warehouse_id);
             }else{
                 return $query->whereIn('warehouse_id', $array_warehouses_id);
 
             }
         })
 
         ->select(
             DB::raw('SUM(GrandTotal) AS sum'),
             DB::raw("count(*) as nmbr")
         )->first();
 
         $item['returns_sales_sum'] =   number_format($report_total_returns_sales->sum, 2, '.', ',');
         $item['returns_sales_count'] =   $report_total_returns_sales->nmbr;
 
 
 
         //--------returns_purchases
         $report_total_returns_purchases = PurchaseReturn::where('deleted_at', '=', null)
         ->where('statut', 'completed')
         ->whereBetween('date', array($start_date, $end_date))
 
             ->where(function ($query) use ($request, $warehouse_id, $array_warehouses_id) {
                 if ($warehouse_id !== 0) {
                     return $query->where('warehouse_id', $warehouse_id);
                 }else{
                     return $query->whereIn('warehouse_id', $array_warehouses_id);
 
                 }
             })
 
             ->select(
                 DB::raw('SUM(GrandTotal) AS sum'),
                 DB::raw("count(*) as nmbr")
             )->first();
 
         $item['returns_purchases_sum'] =   number_format($report_total_returns_purchases->sum, 2, '.', ',');
         $item['returns_purchases_count'] =   $report_total_returns_purchases->nmbr;
 
 
         //--------paiement_sales
         $report_total_paiement_sales = PaymentSale::with('sale')
         ->where('deleted_at', '=', null)
         ->whereBetween('date', array($start_date, $end_date))
 
         ->where(function ($query) use ($request, $warehouse_id, $array_warehouses_id) {
             if ($warehouse_id !== 0) {
                 return $query->whereHas('sale', function ($q) use ($request, $array_warehouses_id, $warehouse_id) {
                     $q->where('warehouse_id', $warehouse_id);
                 });
             }else{
                 return $query->whereHas('sale', function ($q) use ($request, $array_warehouses_id, $warehouse_id) {
                     $q->whereIn('warehouse_id', $array_warehouses_id);
                 });
 
             }
         })
 
         ->select(
             DB::raw('SUM(montant) AS sum')
         )->first();
 
         $item['paiement_sales'] =   number_format($report_total_paiement_sales->sum, 2, '.', ',');
 
 
         //--------PaymentSaleReturns
         $report_total_PaymentSaleReturns = PaymentSaleReturns::with('SaleReturn')
         ->where('deleted_at', '=', null)
         ->whereBetween('date', array($start_date, $end_date))
 
             ->where(function ($query) use ($request, $warehouse_id, $array_warehouses_id) {
                 if ($warehouse_id !== 0) {
                     return $query->whereHas('SaleReturn', function ($q) use ($request, $array_warehouses_id, $warehouse_id) {
                         $q->where('warehouse_id', $warehouse_id);
                     });
                 }else{
                     return $query->whereHas('SaleReturn', function ($q) use ($request, $array_warehouses_id, $warehouse_id) {
                         $q->whereIn('warehouse_id', $array_warehouses_id);
                     });
 
                 }
             })
 
             ->select(
                 DB::raw('SUM(montant) AS sum')
             )->first();
 
         $item['PaymentSaleReturns'] =   number_format($report_total_PaymentSaleReturns->sum, 2, '.', ',');
 
 
        //--------PaymentPurchaseReturns
         $report_total_PaymentPurchaseReturns = PaymentPurchaseReturns::with('PurchaseReturn')
         ->where('deleted_at', '=', null)
         ->whereBetween('date', array($start_date, $end_date))
 
         ->where(function ($query) use ($request, $warehouse_id, $array_warehouses_id) {
             if ($warehouse_id !== 0) {
                 return $query->whereHas('PurchaseReturn', function ($q) use ($request, $array_warehouses_id, $warehouse_id) {
                     $q->where('warehouse_id', $warehouse_id);
                 });
             }else{
                 return $query->whereHas('PurchaseReturn', function ($q) use ($request, $array_warehouses_id, $warehouse_id) {
                     $q->whereIn('warehouse_id', $array_warehouses_id);
                 });
 
             }
         })
 
         ->select(
             DB::raw('SUM(montant) AS sum')
         )->first();
 
         $item['PaymentPurchaseReturns'] =   number_format($report_total_PaymentPurchaseReturns->sum, 2, '.', ',');
 
 
         //--------paiement_purchases
         $report_total_paiement_purchases = PaymentPurchase::with('purchase')
         ->where('deleted_at', '=', null)
         ->whereBetween('date', array($start_date, $end_date))
 
         ->where(function ($query) use ($request, $warehouse_id, $array_warehouses_id) {
             if ($warehouse_id !== 0) {
                 return $query->whereHas('purchase', function ($q) use ($request, $array_warehouses_id, $warehouse_id) {
                     $q->where('warehouse_id', $warehouse_id);
                 });
             }else{
                 return $query->whereHas('purchase', function ($q) use ($request, $array_warehouses_id, $warehouse_id) {
                     $q->whereIn('warehouse_id', $array_warehouses_id);
                 });
 
             }
         })
 
         ->select(
             DB::raw('SUM(montant) AS sum')
         )->first();
 
         $item['paiement_purchases'] =   number_format($report_total_paiement_purchases->sum, 2, '.', ',');
 
 
         //--------expenses
         $report_total_expenses = Expense::whereBetween('date', array($start_date, $end_date))
         ->where('deleted_at', '=', null)
 
         ->where(function ($query) use ($request, $warehouse_id, $array_warehouses_id) {
             if ($warehouse_id !== 0) {
                 return $query->where('warehouse_id', $warehouse_id);
             }else{
                 return $query->whereIn('warehouse_id', $array_warehouses_id);
             }
         })
 
         ->select(
             DB::raw('SUM(amount) AS sum')
         )->first();
 
         $item['expenses_sum'] =   number_format($report_total_expenses->sum, 2, '.', ',');
 
        //calcule COGS and average cost
         $cogs_average_data = $this->CalculeCogsAndAverageCost($start_date, $end_date, $warehouse_id, $array_warehouses_id);
         
         $cogs = $cogs_average_data['total_cogs_products'];
         $total_average_cost = $cogs_average_data['total_average_cost'];
 
         $item['product_cost_fifo'] = number_format($cogs, 2, '.', ',');
         $item['averagecost'] = number_format($total_average_cost, 2, '.', ',');
 
         $item['profit_fifo'] = number_format($report_total_sales->sum - $cogs, 2, '.', ',');
         $item['profit_average_cost'] = number_format($report_total_sales->sum - $total_average_cost, 2, '.', ',');
         
         // Calculate real profit based on actual purchase costs
         $real_costs = $this->CalculeRealCosts($start_date, $end_date, $warehouse_id, $array_warehouses_id);
         
         // Calculate net sales (total sales - returns)
         $net_sales = $report_total_sales->sum - $report_total_returns_sales->sum;
         
         // Calculate return sales profit (profit lost due to returns)
         $return_sales_profit = $this->CalculeReturnSalesProfit($start_date, $end_date, $warehouse_id, $array_warehouses_id);

         // Calculate real profit: For each product sold, get sale price - purchase cost
         $total_real_profit = 0;
         
         // Get all sale details in the date range
         $sale_details = SaleDetail::with(['sale', 'product'])
             ->where(function ($query) use ($warehouse_id, $array_warehouses_id) {
                 if ($warehouse_id !== 0) {
                     return $query->whereHas('sale', function ($q) use ($warehouse_id) {
                         $q->where('warehouse_id', $warehouse_id)->where('statut', 'completed');
                     });
                 } else {
                     return $query->whereHas('sale', function ($q) use ($array_warehouses_id) {
                         $q->whereIn('warehouse_id', $array_warehouses_id)->where('statut', 'completed');
                     });
                 }
             })
             ->whereBetween('date', array($start_date, $end_date))
             ->get();
             
         foreach ($sale_details as $sale_detail) {
             // Get the sale price (total for this line item)
             $sale_price = $sale_detail->total;
             
             // Get the actual purchase cost for this product
             $purchase_cost = $this->getProductPurchaseCost(
                 $sale_detail->product_id,
                 $sale_detail->product_variant_id,
                 $sale_detail->quantity,
                 $sale_detail->sale->warehouse_id,
                 $sale_detail->date
             );
             
             // Calculate profit for this line item
             $line_profit = $sale_price - $purchase_cost;
             $total_real_profit += $line_profit;
         }
         
         // Subtract profit lost from returns
         $profit_net = $total_real_profit - $return_sales_profit;
         $item['real_costs'] = number_format($real_costs, 2, '.', ',');
         $item['return_profit'] = number_format($return_sales_profit, 2, '.', ',');
         $item['real_profit'] = number_format($profit_net, 2, '.', ',');
         $item['payment_received'] = number_format($report_total_paiement_sales->sum  + $report_total_PaymentPurchaseReturns->sum, 2, '.', ',');
         $item['payment_sent'] = number_format($report_total_paiement_purchases->sum + $report_total_PaymentSaleReturns->sum + $report_total_expenses->sum, 2, '.', ',');
         $item['paiement_net'] = number_format(($report_total_paiement_sales->sum  + $report_total_PaymentPurchaseReturns->sum)-($report_total_paiement_purchases->sum + $report_total_PaymentSaleReturns->sum + $report_total_expenses->sum), 2, '.', ',');
         $item['total_revenue'] =   number_format($report_total_sales->sum -  $report_total_returns_sales->sum, 2, '.', ',');
 
 
         return response()->json([
             'data' => $item ,
             'warehouses' => $warehouses,
         ]);
         
     }
 
     // Calculating the cost of goods sold (COGS)
     public function CalculeCogsAndAverageCost($start_date, $end_date , $warehouse_id, $array_warehouses_id)
     {
        
         // Initialize variable to store total COGS averageCost and for all products
         $total_cogs_products = 0;
         $total_average_cost = 0;
 
        // Get all distinct product IDs for sales between start and end date
         $productIds = SaleDetail::with('sale')
         ->where(function ($query) use ($warehouse_id, $array_warehouses_id) {
             if ($warehouse_id !== 0) {
                 return $query->whereHas('sale', function ($q) use ($array_warehouses_id, $warehouse_id) {
                     $q->where('warehouse_id', $warehouse_id)->where('statut', 'completed');
                 });
             }else{
                 return $query->whereHas('sale', function ($q) use ($array_warehouses_id, $warehouse_id) {
                     $q->whereIn('warehouse_id', $array_warehouses_id)->where('statut', 'completed');
                 });
 
             }
         })->whereBetween('date', array($start_date, $end_date))
         ->select('product_id','product_variant_id')
         ->distinct()
         ->get();
 
         // Loop through each product
         foreach ($productIds as $productId) {
 
             // $productId = 1011;
             $totalCogs = 0;
             $average_cost = 0;
             $tax_shipping = 0;
 
             // Get the total cost and quantity for all adjustments of the product
             $adjustments = AdjustmentDetail::with('adjustment')
             ->where(function ($query) use ($warehouse_id, $array_warehouses_id ,$end_date) {
                 if ($warehouse_id !== 0) {
                     return $query->whereHas('adjustment', function ($q) use ($array_warehouses_id, $warehouse_id,$end_date) {
                         $q->where('warehouse_id', $warehouse_id)
                         ->where('date', '<=' , $end_date);
                     });
                 }else{
                     return $query->whereHas('adjustment', function ($q) use ($array_warehouses_id, $warehouse_id, $end_date ) {
                         $q->whereIn('warehouse_id', $array_warehouses_id)
                         ->where('date', '<=' , $end_date);
                     });
     
                 }
             })            
             ->where('product_id', $productId['product_id'])
             ->where('product_variant_id', $productId['product_variant_id'])
             ->get();
     
             $adjustment_quantity = 0;
             foreach ($adjustments as $adjustment) {
                 if($adjustment->type == 'add'){
                     $adjustment_quantity += $adjustment->quantity;
                 }else{
                     $adjustment_quantity -= $adjustment->quantity;
                 }
             }
 
 
             // Get total quantity sold before start date
             $totalQuantitySold = SaleDetail::with('sale')
             ->where(function ($query) use ($warehouse_id, $array_warehouses_id) {
                 if ($warehouse_id !== 0) {
                     return $query->whereHas('sale', function ($q) use ($array_warehouses_id, $warehouse_id) {
                         $q->where('warehouse_id', $warehouse_id)->where('statut', 'completed');
                     });
                 }else{
                     return $query->whereHas('sale', function ($q) use ($array_warehouses_id, $warehouse_id) {
                         $q->whereIn('warehouse_id', $array_warehouses_id)->where('statut', 'completed');
                     });
     
                 }
             })->where('product_id', $productId['product_id'])
             ->where('product_variant_id', $productId['product_variant_id'])
             ->where('date', '<', $start_date)
             ->orderBy('date', 'asc')
             ->sum('quantity');
 
 
              // Get purchase details for current product, ordered by date in ascending date
              $purchases = PurchaseDetail::where('product_id',  $productId['product_id'])
              ->where('product_variant_id', $productId['product_variant_id'])
              ->join('purchases', 'purchases.id', '=', 'purchase_details.purchase_id')
              ->where('purchases.statut' , 'received')
              ->where(function ($query) use ($warehouse_id, $array_warehouses_id) {
                 if ($warehouse_id !== 0) {
                     return  $query->where('purchases.warehouse_id', $warehouse_id)->where('purchases.statut', 'received');
                 }else{
                     return  $query->whereIn('purchases.warehouse_id', $array_warehouses_id)->where('purchases.statut', 'received');
     
                 }
             })
              ->orderBy('purchases.date', 'asc')
              ->select('purchase_details.quantity as quantity',
                    'purchase_details.cost as cost',
                    'purchase_details.total as total',
                    'purchases.GrandTotal as purchase_total' ,
                    'purchase_details.purchase_id as purchase_id')
              ->get();
 
 
             if(count($purchases) > 0){
                 $purchases_to_array = $purchases->toArray();
                 $purchases_sum_qty = array_sum(array_column($purchases_to_array,'quantity'));
             }else{
                 $purchases_sum_qty = 0;
             }
             
             // Get sale details for current product between start and end date, ordered by date in ascending order
             $sales = SaleDetail::with('sale')
             ->where(function ($query) use ($warehouse_id, $array_warehouses_id) {
                 if ($warehouse_id !== 0) {
                     return $query->whereHas('sale', function ($q) use ($array_warehouses_id, $warehouse_id) {
                         $q->where('warehouse_id', $warehouse_id)->where('statut', 'completed');
                     });
                 }else{
                     return $query->whereHas('sale', function ($q) use ($array_warehouses_id, $warehouse_id) {
                         $q->whereIn('warehouse_id', $array_warehouses_id)->where('statut', 'completed');
                     });
     
                 }
             })->where('product_id', $productId['product_id'])
             ->where('product_variant_id', $productId['product_variant_id'])
             ->whereBetween('date', array($start_date, $end_date))
             ->orderBy('date', 'asc')
             ->get();
 
 
             $sales_to_array = $sales->toArray();
             $sales_sum_qty = array_sum(array_column($sales_to_array,'quantity'));
             
             $total_sum_sales = $totalQuantitySold + $sales_sum_qty;
 
 
             //calcule average Cost
             $average_cost = $this->averageCost($productId['product_id'] ,$start_date, $end_date, $warehouse_id, $array_warehouses_id);
 
             if($total_sum_sales > $purchases_sum_qty){
                 // Handle adjustments only case
                 $totalCogs += $sales_sum_qty * $average_cost;
                 $total_average_cost += $sales_sum_qty * $average_cost;
                 
             }else{
 
                foreach ($sales as $sale) {
                    
                    $saleQuantity = $sale->quantity;
                    $total_average_cost += $average_cost * $sale->quantity;

                    while ($saleQuantity > 0) {
                        $purchase = $purchases->first();
                        if ($purchase->quantity > 0) { 
                            $totalQuantitySold += $saleQuantity;
                            if ($purchase->quantity > $totalQuantitySold || $purchase->quantity = $totalQuantitySold) {
                                $totalCogs += $saleQuantity * $purchase->cost;
                                $purchase->quantity -= $totalQuantitySold;
                                $saleQuantity = 0;
                                $totalQuantitySold = 0;
                                if($purchase->quantity == 0){
                                    $purchase->quantity = 0;
                                    $saleQuantity = 0;
                                    $totalQuantitySold = 0;
                                    $purchases->shift();
                                }
                            
                            } else {


                                $diff = round($totalQuantitySold - $saleQuantity, 4);
                                if($purchase->quantity > $diff) {

                                    $rest = $purchase->quantity - $diff;
                                    if($rest <= $saleQuantity){
                                        $saleQuantity -= $rest;
                                        $totalCogs+= $rest * $purchase->cost;
                                        $totalQuantitySold =  0;
                                        $purchase->quantity = 0;
                                        $purchases->shift();

                                    }else{
                                        $totalQuantitySold -=  $saleQuantity;
                                        $purchase->quantity = $purchase->quantity - $totalQuantitySold;
                                        $totalCogs+= $purchase->quantity * $purchase->cost;
                                        $saleQuantity -= $purchase->quantity;
                                        $purchase->quantity = 0;
                                        $purchases->shift();
                                    }
                            
                                }else{
                                    $totalQuantitySold -=  $saleQuantity;
                                    $totalQuantitySold -= $purchase->quantity;
                                    $purchase->quantity = 0;
                                    $purchases->shift();
                                }
                            }
                        } else {
                            $purchases->shift();
                        }

                        
                    }
                
                }
             }
             $total_cogs_products += $totalCogs;
 
         } 
 
         return [
             'total_cogs_products' => $total_cogs_products,
             'total_average_cost'  => $total_average_cost
         ];
 
 
     }
 
     // Calculate the average cost of a product.
     public function averageCost($product_id , $start_date, $end_date , $warehouse_id, $array_warehouses_id)
     {
         // Get the cost of the product from the products table
         $product = Product::find($product_id);
         $product_cost = $product->cost;
 
          $purchases = PurchaseDetail::where('product_id', $product_id)
          ->join('purchases', 'purchases.id', '=', 'purchase_details.purchase_id')
          ->where('purchases.statut' , 'received')
          ->where(function ($query) use ($warehouse_id, $array_warehouses_id) {
             if ($warehouse_id !== 0) {
                 return  $query->where('purchases.warehouse_id', $warehouse_id)->where('purchases.statut', 'received');
             }else{
                 return  $query->whereIn('purchases.warehouse_id', $array_warehouses_id)->where('purchases.statut', 'received');
 
             }
         })
          ->where('purchases.date', '<=' , $end_date)
          ->select('purchase_details.quantity as quantity','purchase_details.total as total',
                   'purchase_details.cost as cost',
                   'purchases.GrandTotal as purchase_total')
          ->get();
 
         $purchase_cost = 0;
         $purchase_quantity = 0;
         foreach ($purchases as $purchase) {
             $purchase_cost += $purchase->quantity * $purchase->cost;
             $purchase_quantity += $purchase->quantity;
         }
 
         // Get the total cost and quantity for all adjustments of the product
         $adjustments = AdjustmentDetail::with('adjustment')
         ->where(function ($query) use ($warehouse_id, $array_warehouses_id, $start_date, $end_date) {
             if ($warehouse_id !== 0) {
                 return $query->whereHas('adjustment', function ($q) use ($array_warehouses_id, $warehouse_id, $start_date, $end_date) {
                     $q->where('warehouse_id', $warehouse_id)
                     ->where('date', '<=' , $end_date);
                 });
             }else{
                 return $query->whereHas('adjustment', function ($q) use ($array_warehouses_id, $warehouse_id , $start_date, $end_date) {
                     $q->whereIn('warehouse_id', $array_warehouses_id)
                     ->where('date', '<=' , $end_date);
                 });
 
             }
         })
         ->where('product_id', $product_id)->get();
 
         $adjustment_cost = 0;
         $adjustment_quantity = 0;
         foreach ($adjustments as $adjustment) {
             if($adjustment->type == 'add'){
                 $adjustment_cost += $adjustment->quantity * $product_cost;
                 $adjustment_quantity += $adjustment->quantity;
             }else{
                 $adjustment_cost -= $adjustment->quantity * $product_cost;
                 $adjustment_quantity -= $adjustment->quantity;
             }
         }
 
         // Calculate the average cost
         $total_cost = $purchase_cost + $adjustment_cost;
         $total_quantity = $purchase_quantity + $adjustment_quantity;
         if($total_quantity === 0 || $total_quantity == 0 || $total_quantity == '0'){
             $average_cost = $product_cost;
         }else{
             $average_cost = $total_cost / $total_quantity;
         }
 
         return $average_cost;
     }

     // Calculate the real cost of goods sold without using averages
     public function CalculeRealCosts($start_date, $end_date, $warehouse_id, $array_warehouses_id)
     {
         // Initialize variable to store total real costs for all products
         $total_real_costs = 0;
         
         // Get all products sold in the period
         $productIds = SaleDetail::with(['sale'])
             ->where(function ($query) use ($warehouse_id, $array_warehouses_id) {
                 if ($warehouse_id !== 0) {
                     return $query->whereHas('sale', function ($q) use ($warehouse_id) {
                         $q->where('warehouse_id', $warehouse_id)->where('statut', 'completed');
                     });
                 } else {
                     return $query->whereHas('sale', function ($q) use ($array_warehouses_id) {
                         $q->whereIn('warehouse_id', $array_warehouses_id)->where('statut', 'completed');
                     });
                 }
             })
             ->whereBetween('date', array($start_date, $end_date))
             ->select('product_id')
             ->distinct()
             ->get();
             
         // Loop through each product
         foreach ($productIds as $productId) {
             // For each product, get all purchases in chronological order (FIFO)
             $purchases = PurchaseDetail::join('purchases', 'purchases.id', '=', 'purchase_details.purchase_id')
                 ->where('product_id', $productId->product_id)
                 ->where('purchases.statut', 'received')
                 ->whereBetween('date', array($start_date, $end_date))
                 ->where(function ($query) use ($warehouse_id, $array_warehouses_id) {
                     if ($warehouse_id !== 0) {
                         return $query->where('purchases.warehouse_id', $warehouse_id);
                     } else {
                         return $query->whereIn('purchases.warehouse_id', $array_warehouses_id);
                     }
                 })
                 ->orderBy('purchases.date', 'asc')
                 ->select(
                     'purchase_details.quantity',
                     'purchase_details.cost',
                     'purchase_details.TaxNet',
                     'purchase_details.tax_method',
                     'purchase_details.discount',
                     'purchase_details.discount_method',
                     'purchases.date'
                 )
                 ->get()
                 ->toArray();
                 
             // Keep track of available purchase quantities
             $purchaseStock = [];
             foreach ($purchases as $purchase) {
                 if (!isset($purchaseStock[$purchase['cost']])) {
                     $purchaseStock[$purchase['cost']] = [
                         'quantity' => 0,
                         'tax_rate' => $purchase['TaxNet'],
                         'tax_method' => $purchase['tax_method'],
                         'discount' => $purchase['discount'],
                         'discount_method' => $purchase['discount_method']
                     ];
                 }
                 $purchaseStock[$purchase['cost']]['quantity'] += $purchase['quantity'];
            }

             
             // Get all sales for this product in chronological order
             $sales = SaleDetail::with(['sale'])
                 ->where(function ($query) use ($warehouse_id, $array_warehouses_id) {
                     if ($warehouse_id !== 0) {
                         return $query->whereHas('sale', function ($q) use ($warehouse_id) {
                             $q->where('warehouse_id', $warehouse_id)->where('statut', 'completed');
                         });
                     } else {
                         return $query->whereHas('sale', function ($q) use ($array_warehouses_id) {
                             $q->whereIn('warehouse_id', $array_warehouses_id)->where('statut', 'completed');
                         });
                     }
                 })
                 ->where('product_id', $productId->product_id)
                 ->whereBetween('date', array($start_date, $end_date))
                 ->orderBy('date', 'asc')
                 ->get();
                 
             // Match each sale with a purchase using FIFO method
             foreach ($sales as $sale) {
                 $quantityToMatch = $sale->quantity;
                 $saleProductCost = 0;
                 
                 // Try to match with available purchases
                 foreach ($purchaseStock as $cost => &$stockInfo) {
                     if ($stockInfo['quantity'] > 0) {
                         $quantityToTake = min($quantityToMatch, $stockInfo['quantity']);
                         
                         if ($quantityToTake > 0) {
                             // Calculate the actual cost with tax and discount
                             $actualCost = $cost;
                             
                             // Apply tax if tax method is exclusive (1)
                             if ($stockInfo['tax_method'] == 1) {
                                 $actualCost += ($actualCost * $stockInfo['tax_rate'] / 100);
                             }
                             
                             // Apply discount
                             if ($stockInfo['discount_method'] == 1) { // Percentage
                                 $actualCost -= ($actualCost * $stockInfo['discount'] / 100);
                             } else if ($stockInfo['discount_method'] == 2) { // Fixed amount
                                 $actualCost -= $stockInfo['discount'];
                             }
                             
                             // Add to total cost for this sale
                             $saleProductCost += $quantityToTake * $actualCost;
                             
                             // Reduce the available quantity
                             $stockInfo['quantity'] -= $quantityToTake;
                             $quantityToMatch -= $quantityToTake;
                             
                             // If we've matched all the quantity needed, stop
                             if ($quantityToMatch <= 0) {
                                 break;
                             }
                         }
                     }
                 }
                 
                 // If we couldn't match all quantity with purchases, use the product's base cost for the remainder
                 if ($quantityToMatch > 0) {
                     $product = Product::find($productId->product_id);
                     $saleProductCost += $quantityToMatch * $product->cost;
                 }
                 
                 $total_real_costs += $saleProductCost;
             }
         }
         
         return $total_real_costs;
     }


     //-------------------- report_top_products -------------\\

     public function report_top_products(request $request)
     {
 
        $this->authorizeForUser($request->user('api'), 'Top_products', Product::class);

        $Role = Auth::user()->roles()->first();
        $view_records = Role::findOrFail($Role->id)->inRole('record_view');
        // How many items do you want to display.
        $perPage = $request->limit;
        $pageStart = \Request::get('page', 1);
        // Start displaying items from this number;
        $offSet = ($pageStart * $perPage) - $perPage;

        $products_data = SaleDetail::join('sales', 'sale_details.sale_id', '=', 'sales.id')
        ->join('products', 'sale_details.product_id', '=', 'products.id')
        ->where(function ($query) use ($view_records) {
            if (!$view_records) {
                return $query->where('sales.user_id', '=', Auth::user()->id);
            }
        })
        ->whereBetween('sale_details.date', array($request->from, $request->to))
        ->where(function ($query) use ($request) {
            return $query->when($request->filled('search'), function ($query) use ($request) {
                    return $query->where('products.name','LIKE', "%{$request->search}%")
                    ->orWhere('products.code', 'LIKE', "%{$request->search}%");
            });
        })
        ->select(
            DB::raw('products.name as name'),
            DB::raw('products.code as code'),
            DB::raw('count(*) as total_sales'),
            DB::raw('sum(total) as total'),
        )
        ->groupBy('products.name');

        $totalRows = $products_data->count();
        if($perPage == "-1"){
            $perPage = $totalRows;
        }
        
        
        $products = $products_data->offset($offSet)
        ->limit($perPage)
        ->orderBy('total_sales', 'desc')
        ->get();


        return response()->json([
            'products' => $products,
            'totalRows' => $totalRows,
        ]);

     }


    //-------------------- report_top_customers -------------\\

    public function report_top_customers(request $request)
    {

        $this->authorizeForUser($request->user('api'), 'Top_customers', Client::class);

        $role = Auth::user()->roles()->first();
        $view_records = Role::findOrFail($role->id)->inRole('record_view');
        // How many items do you want to display.
        $perPage = $request->limit;
        $pageStart = \Request::get('page', 1);
        // Start displaying items from this number;
        $offSet = ($pageStart * $perPage) - $perPage;

        $customers_count = Sale::where('sales.deleted_at', '=', null)
        ->where(function ($query) use ($view_records) {
            if (!$view_records) {
                return $query->where('sales.user_id', '=', Auth::user()->id);
            }
        })

        ->join('clients', 'sales.client_id', '=', 'clients.id')
        ->select(DB::raw('clients.name'), DB::raw("count(*) as total_sales"))
        ->groupBy('clients.name')->get();

        $totalRows = $customers_count->count();
        if($perPage == "-1"){
            $perPage = $totalRows;
        }

        $customers_data = Sale::where('sales.deleted_at', '=', null)
        ->where(function ($query) use ($view_records) {
            if (!$view_records) {
                return $query->where('sales.user_id', '=', Auth::user()->id);
            }
        })

        ->join('clients', 'sales.client_id', '=', 'clients.id')
        ->select(
            DB::raw('clients.name as name'), 
            DB::raw('clients.phone as phone'), 
            DB::raw('clients.email as email'), 
            DB::raw("count(*) as total_sales"),
            DB::raw('sum(GrandTotal) as total'),
        )
        ->groupBy('clients.name');

        $customers = $customers_data->offset($offSet)
            ->limit($perPage)
            ->orderBy('total_sales', 'desc')
            ->get();

        return response()->json([
            'customers' => $customers,
            'totalRows' => $totalRows,
        ]);

    }


     //----------------- Users Report -----------------------\\

     public function users_Report(request $request)
     {
 
         $this->authorizeForUser($request->user('api'), 'users_report', User::class);
 
         // How many items do you want to display.
         $perPage = $request->limit;
         $pageStart = \Request::get('page', 1);
         // Start displaying items from this number;
         $offSet = ($pageStart * $perPage) - $perPage;
         $order = $request->SortField;
         $dir = $request->SortType;
         $data = array();
 
         $users = User::where(function ($query) use ($request) {
            return $query->when($request->filled('search'), function ($query) use ($request) {
                return $query->where('username', 'LIKE', "%{$request->search}%");
                });
            });
 
         $totalRows = $users->count();
         if($perPage == "-1"){
             $perPage = $totalRows;
         }
         $users = $users->offset($offSet)
             ->limit($perPage)
             ->orderBy($order, $dir)
             ->get();
 
         foreach ($users as $user) {
            $item['total_sales'] = DB::table('sales')
                 ->where('deleted_at', '=', null)
                 ->where('user_id', $user->id)
                 ->count();

            $item['total_purchases'] = DB::table('purchases')
                 ->where('deleted_at', '=', null)
                 ->where('user_id', $user->id)
                 ->count();

            $item['total_quotations'] = DB::table('quotations')
                 ->where('deleted_at', '=', null)
                 ->where('user_id', $user->id)
                 ->count();

            $item['total_return_sales'] = DB::table('sale_returns')
                 ->where('deleted_at', '=', null)
                 ->where('user_id', $user->id)
                 ->count();

            $item['total_return_purchases'] = DB::table('purchase_returns')
                 ->where('deleted_at', '=', null)
                 ->where('user_id', $user->id)
                 ->count();

            $item['total_transfers'] = DB::table('transfers')
                 ->where('deleted_at', '=', null)
                 ->where('user_id', $user->id)
                 ->count();

            $item['total_adjustments'] = DB::table('adjustments')
                 ->where('deleted_at', '=', null)
                 ->where('user_id', $user->id)
                 ->count();
 
             $item['id'] = $user->id;
             $item['username'] = $user->username;
             $data[] = $item;
         }
 
         return response()->json([
             'report' => $data,
             'totalRows' => $totalRows,
         ]);
 
     }


      //-------------------- Get Sales By user -------------\\

    public function get_sales_by_user(request $request)
    {

        $this->authorizeForUser($request->user('api'), 'users_report', User::class);
        // How many items do you want to display.
        $perPage = $request->limit;
        $pageStart = \Request::get('page', 1);
        // Start displaying items from this number;
        $offSet = ($pageStart * $perPage) - $perPage;

        $Role = Auth::user()->roles()->first();
        $ShowRecord = Role::findOrFail($Role->id)->inRole('record_view');

        $sales = Sale::where('deleted_at', '=', null)->with('user','client','warehouse')
            ->where(function ($query) use ($ShowRecord) {
                if (!$ShowRecord) {
                    return $query->where('user_id', '=', Auth::user()->id);
                }
            })
            ->where('user_id', $request->id)
             // Search With Multiple Param
             ->where(function ($query) use ($request) {
                return $query->when($request->filled('search'), function ($query) use ($request) {
                    return $query->where('Ref', 'LIKE', "%{$request->search}%")
                        ->orWhere('statut', 'LIKE', "%{$request->search}%")
                        ->orWhere('payment_statut', 'like', "%{$request->search}%")
                        ->orWhere(function ($query) use ($request) {
                            return $query->whereHas('client', function ($q) use ($request) {
                                $q->where('name', 'LIKE', "%{$request->search}%");
                            });
                        })
                        ->orWhere(function ($query) use ($request) {
                            return $query->whereHas('warehouse', function ($q) use ($request) {
                                $q->where('name', 'LIKE', "%{$request->search}%");
                            });
                        });
                });
            });

        $totalRows = $sales->count();
        if($perPage == "-1"){
            $perPage = $totalRows;
        }
        $sales = $sales->offset($offSet)
            ->limit($perPage)
            ->orderBy('id', 'desc')
            ->get();

        $data = [];
        foreach ($sales as $sale) {
            $item['username'] = $sale['user']->username;
            $item['client_name'] = $sale['client']->name;
            $item['warehouse_name'] = $sale['warehouse']->name;
            $item['date'] = $sale->date;
            $item['Ref'] = $sale->Ref;
            $item['sale_id'] = $sale->id;
            $item['statut'] = $sale->statut;
            $item['GrandTotal'] = $sale->GrandTotal;
            $item['paid_amount'] = $sale->paid_amount;
            $item['due'] = $sale->GrandTotal - $sale->paid_amount;
            $item['payment_status'] = $sale->payment_statut;
            $item['shipping_status'] = $sale->shipping_status;

            $data[] = $item;
        }
        return response()->json([
            'totalRows' => $totalRows,
            'sales' => $data,
        ]);

    }

     //-------------------- Get Quotations By user -------------\\

     public function get_quotations_by_user(request $request)
     {
 
        $this->authorizeForUser($request->user('api'), 'users_report', User::class);
         // How many items do you want to display.
         $perPage = $request->limit;
         $pageStart = \Request::get('page', 1);
         // Start displaying items from this number;
         $offSet = ($pageStart * $perPage) - $perPage;
 
         $Role = Auth::user()->roles()->first();
         $ShowRecord = Role::findOrFail($Role->id)->inRole('record_view');
         $data = array();

         $Quotations = Quotation::with('client', 'warehouse','user')
            ->where('deleted_at', '=', null)
             ->where('user_id', $request->id)
             ->where(function ($query) use ($ShowRecord) {
                 if (!$ShowRecord) {
                     return $query->where('user_id', '=', Auth::user()->id);
                 }
             })
              //Search With Multiple Param
            ->where(function ($query) use ($request) {
                return $query->when($request->filled('search'), function ($query) use ($request) {
                    return $query->where('Ref', 'LIKE', "%{$request->search}%")
                        ->orWhere('statut', 'LIKE', "%{$request->search}%")
                        ->orWhere(function ($query) use ($request) {
                            return $query->whereHas('client', function ($q) use ($request) {
                                $q->where('name', 'LIKE', "%{$request->search}%");
                            });
                        })
                        ->orWhere(function ($query) use ($request) {
                            return $query->whereHas('warehouse', function ($q) use ($request) {
                                $q->where('name', 'LIKE', "%{$request->search}%");
                            });
                        });
                });
            });

         $totalRows = $Quotations->count();
         if($perPage == "-1"){
             $perPage = $totalRows;
         }
         $Quotations = $Quotations->offset($offSet)
             ->limit($perPage)
             ->orderBy('id', 'desc')
             ->get();

            foreach ($Quotations as $Quotation) {

                $item['id'] = $Quotation->id;
                $item['date'] = $Quotation->date;
                $item['Ref'] = $Quotation->Ref;
                $item['statut'] = $Quotation->statut;
                $item['username'] = $Quotation['user']->username;
                $item['warehouse_name'] = $Quotation['warehouse']->name;
                $item['client_name'] = $Quotation['client']->name;
                $item['GrandTotal'] = $Quotation->GrandTotal;

                $data[] = $item;
            }
 
         return response()->json([
             'quotations' => $data,
             'totalRows' => $totalRows,
         ]);
     }

      //-------------------- Get Purchases By user -------------\\

    public function get_purchases_by_user(request $request)
    {

        $this->authorizeForUser($request->user('api'), 'users_report', User::class);
        // How many items do you want to display.
        $perPage = $request->limit;
        $pageStart = \Request::get('page', 1);
        // Start displaying items from this number;
        $offSet = ($pageStart * $perPage) - $perPage;
        $data = array();

        $Role = Auth::user()->roles()->first();
        $ShowRecord = Role::findOrFail($Role->id)->inRole('record_view');

        $purchases = Purchase::where('deleted_at', '=', null)
            ->with('user','provider','warehouse')
            ->where('user_id', $request->id)
            ->where(function ($query) use ($ShowRecord) {
                if (!$ShowRecord) {
                    return $query->where('user_id', '=', Auth::user()->id);
                }
            })
            // Search With Multiple Param
            ->where(function ($query) use ($request) {
                return $query->when($request->filled('search'), function ($query) use ($request) {
                    return $query->where('Ref', 'LIKE', "%{$request->search}%")
                        ->orWhere('statut', 'LIKE', "%{$request->search}%")
                        ->orWhere('payment_statut', 'like', "$request->search")
                        ->orWhere(function ($query) use ($request) {
                            return $query->whereHas('provider', function ($q) use ($request) {
                                $q->where('name', 'LIKE', "%{$request->search}%");
                            });
                        })
                        ->orWhere(function ($query) use ($request) {
                            return $query->whereHas('warehouse', function ($q) use ($request) {
                                $q->where('name', 'LIKE', "%{$request->search}%");
                            });
                        });
                });
            });

        $totalRows = $purchases->count();
        if($perPage == "-1"){
            $perPage = $totalRows;
        }
        $purchases = $purchases->offset($offSet)
            ->limit($perPage)
            ->orderBy('id', 'desc')
            ->get();

        foreach ($purchases as $purchase) {
            $item['Ref'] = $purchase->Ref;
            $item['purchase_id'] = $purchase->id;
            $item['username'] = $purchase['user']->username;
            $item['provider_name'] = $purchase['provider']->name;
            $item['warehouse_name'] = $purchase['warehouse']->name;
            $item['statut'] = $purchase->statut;
            $item['GrandTotal'] = $purchase->GrandTotal;
            $item['paid_amount'] = $purchase->paid_amount;
            $item['due'] = $purchase->GrandTotal - $purchase->paid_amount;
            $item['payment_status'] = $purchase->payment_statut;

            $data[] = $item;
        }

        return response()->json([
            'totalRows' => $totalRows,
            'purchases' => $data,
        ]);

    }

     //-------------------- Get sale Returns By user -------------\\

     public function get_sales_return_by_user(request $request)
     {
 
        $this->authorizeForUser($request->user('api'), 'users_report', User::class);
         // How many items do you want to display.
         $perPage = $request->limit;
         $pageStart = \Request::get('page', 1);
         // Start displaying items from this number;
         $offSet = ($pageStart * $perPage) - $perPage;
         $data = array();
 
         //  Check If User Has Permission Show All Records
         $Role = Auth::user()->roles()->first();
         $ShowRecord = Role::findOrFail($Role->id)->inRole('record_view');
 
         $SaleReturn = SaleReturn::where('deleted_at', '=', null)->with('user','client','warehouse')
             ->where('user_id', $request->id)
             ->where(function ($query) use ($ShowRecord) {
                 if (!$ShowRecord) {
                     return $query->where('user_id', '=', Auth::user()->id);
                 }
             })
             // Search With Multiple Param
            ->where(function ($query) use ($request) {
                return $query->when($request->filled('search'), function ($query) use ($request) {
                    return $query->where('Ref', 'LIKE', "%{$request->search}%")
                        ->orWhere('statut', 'LIKE', "%{$request->search}%")
                        ->orWhere('payment_statut', 'like', "$request->search")
                        ->orWhere(function ($query) use ($request) {
                            return $query->whereHas('client', function ($q) use ($request) {
                                $q->where('name', 'LIKE', "%{$request->search}%");
                            });
                        })
                        ->orWhere(function ($query) use ($request) {
                            return $query->whereHas('warehouse', function ($q) use ($request) {
                                $q->where('name', 'LIKE', "%{$request->search}%");
                            });
                        });
                });
            });
 
         $totalRows = $SaleReturn->count();
         if($perPage == "-1"){
             $perPage = $totalRows;
         }
         $SaleReturn = $SaleReturn->offset($offSet)
             ->limit($perPage)
             ->orderBy('id', 'desc')
             ->get();
 
         foreach ($SaleReturn as $Sale_Return) {
             $item['Ref'] = $Sale_Return->Ref;
             $item['return_sale_id'] = $Sale_Return->id;
             $item['statut'] = $Sale_Return->statut;
             $item['username'] = $Sale_Return['user']->username;
             $item['client_name'] = $Sale_Return['client']->name;
             $item['warehouse_name'] = $Sale_Return['warehouse']->name;
             $item['GrandTotal'] = $Sale_Return->GrandTotal;
             $item['paid_amount'] = $Sale_Return->paid_amount;
             $item['due'] = $Sale_Return->GrandTotal - $Sale_Return->paid_amount;
             $item['payment_status'] = $Sale_Return->payment_statut;
 
             $data[] = $item;
         }
 
         return response()->json([
             'totalRows' => $totalRows,
             'sales_return' => $data,
         ]);
     }

    //-------------------- Get purchase Returns By user -------------\\

    public function get_purchase_return_by_user(request $request)
    {

        $this->authorizeForUser($request->user('api'), 'users_report', User::class);

        // How many items do you want to display.
        $perPage = $request->limit;
        $pageStart = \Request::get('page', 1);
        // Start displaying items from this number;
        $offSet = ($pageStart * $perPage) - $perPage;
        $data = array();

        $Role = Auth::user()->roles()->first();
        $ShowRecord = Role::findOrFail($Role->id)->inRole('record_view');

        $PurchaseReturn = PurchaseReturn::where('deleted_at', '=', null)
            ->with('user','provider','warehouse')
            ->where('user_id', $request->id)
            ->where(function ($query) use ($ShowRecord) {
                if (!$ShowRecord) {
                    return $query->where('user_id', '=', Auth::user()->id);
                }
            })
             // Search With Multiple Param
             ->where(function ($query) use ($request) {
                return $query->when($request->filled('search'), function ($query) use ($request) {
                    return $query->where('Ref', 'LIKE', "%{$request->search}%")
                        ->orWhere('statut', 'LIKE', "%{$request->search}%")
                        ->orWhere('payment_statut', 'like', "$request->search")
                        ->orWhere(function ($query) use ($request) {
                            return $query->whereHas('provider', function ($q) use ($request) {
                                $q->where('name', 'LIKE', "%{$request->search}%");
                            });
                        })
                        ->orWhere(function ($query) use ($request) {
                            return $query->whereHas('warehouse', function ($q) use ($request) {
                                $q->where('name', 'LIKE', "%{$request->search}%");
                            });
                        });
                });
            });

        $totalRows = $PurchaseReturn->count();
        if($perPage == "-1"){
            $perPage = $totalRows;
        }
        $PurchaseReturn = $PurchaseReturn->offset($offSet)
            ->limit($perPage)
            ->orderBy('id', 'desc')
            ->get();

        foreach ($PurchaseReturn as $Purchase_Return) {
            $item['Ref'] = $Purchase_Return->Ref;
            $item['return_purchase_id'] = $Purchase_Return->id;
            $item['statut'] = $Purchase_Return->statut;
            $item['username'] = $Purchase_Return['user']->username;
            $item['provider_name'] = $Purchase_Return['provider']->name;
            $item['warehouse_name'] = $Purchase_Return['warehouse']->name;
            $item['GrandTotal'] = $Purchase_Return->GrandTotal;
            $item['paid_amount'] = $Purchase_Return->paid_amount;
            $item['due'] = $Purchase_Return->GrandTotal - $Purchase_Return->paid_amount;
            $item['payment_status'] = $Purchase_Return->payment_statut;

            $data[] = $item;
        }

        return response()->json([
            'totalRows' => $totalRows,
            'purchases_return' => $data,
        ]);

    }

     //-------------------- Get transfers By user -------------\\

     public function get_transfer_by_user(request $request)
     {
 
         $this->authorizeForUser($request->user('api'), 'users_report', User::class);
 
         // How many items do you want to display.
         $perPage = $request->limit;
         $pageStart = \Request::get('page', 1);
         // Start displaying items from this number;
         $offSet = ($pageStart * $perPage) - $perPage;
         $data = array();
 
         $Role = Auth::user()->roles()->first();
         $ShowRecord = Role::findOrFail($Role->id)->inRole('record_view');
 
         $transfers = Transfer::with('from_warehouse', 'to_warehouse')
             ->with('user')
             ->where('user_id', $request->id)
             ->where(function ($query) use ($ShowRecord) {
                 if (!$ShowRecord) {
                     return $query->where('user_id', '=', Auth::user()->id);
                 }
             })
             // Search With Multiple Param
            ->where(function ($query) use ($request) {
                return $query->when($request->filled('search'), function ($query) use ($request) {
                    return $query->where('Ref', 'LIKE', "%{$request->search}%")
                        ->orWhere('statut', 'LIKE', "%{$request->search}%")
                        ->orWhere(function ($query) use ($request) {
                            return $query->whereHas('from_warehouse', function ($q) use ($request) {
                                $q->where('name', 'LIKE', "%{$request->search}%");
                            });
                        })
                        ->orWhere(function ($query) use ($request) {
                            return $query->whereHas('to_warehouse', function ($q) use ($request) {
                                $q->where('name', 'LIKE', "%{$request->search}%");
                            });
                        });
                });
            });
 
         $totalRows = $transfers->count();
         if($perPage == "-1"){
             $perPage = $totalRows;
         }
         $transfers = $transfers->offset($offSet)
             ->limit($perPage)
             ->orderBy('id', 'desc')
             ->get();
 
        foreach ($transfers as $transfer) {
                $item['id'] = $transfer->id;
                $item['date'] = $transfer->date;
                $item['Ref'] = $transfer->Ref;
                $item['username'] = $transfer['user']->username;
                $item['from_warehouse'] = $transfer['from_warehouse']->name;
                $item['to_warehouse'] = $transfer['to_warehouse']->name;
                $item['GrandTotal'] = $transfer->GrandTotal;
                $item['items'] = $transfer->items;
                $item['statut'] = $transfer->statut;

                $data[] = $item;
        }
         return response()->json([
             'totalRows' => $totalRows,
             'transfers' => $data,
         ]);
 
     }

    //-------------------- Get adjustment By user -------------\\

    public function get_adjustment_by_user(request $request)
    {

        $this->authorizeForUser($request->user('api'), 'users_report', User::class);

        // How many items do you want to display.
        $perPage = $request->limit;
        $pageStart = \Request::get('page', 1);
        // Start displaying items from this number;
        $offSet = ($pageStart * $perPage) - $perPage;
        $data = array();

        $Role = Auth::user()->roles()->first();
        $ShowRecord = Role::findOrFail($Role->id)->inRole('record_view');

        $Adjustments = Adjustment::with('warehouse')
            ->with('user')
            ->where('user_id', $request->id)
            ->where(function ($query) use ($ShowRecord) {
                if (!$ShowRecord) {
                    return $query->where('user_id', '=', Auth::user()->id);
                }
            })
            // Search With Multiple Param
            ->where(function ($query) use ($request) {
                return $query->when($request->filled('search'), function ($query) use ($request) {
                    return $query->where('Ref', 'LIKE', "%{$request->search}%")
                        ->orWhere(function ($query) use ($request) {
                            return $query->whereHas('warehouse', function ($q) use ($request) {
                                $q->where('name', 'LIKE', "%{$request->search}%");
                            });
                        });
                });
            });

        $totalRows = $Adjustments->count();
        if($perPage == "-1"){
            $perPage = $totalRows;
        }
        $Adjustments = $Adjustments->offset($offSet)
            ->limit($perPage)
            ->orderBy('id', 'desc')
            ->get();

        foreach ($Adjustments as $Adjustment) {
            $item['id'] = $Adjustment->id;
            $item['username'] = $Adjustment['user']->username;
            $item['date'] = $Adjustment->date;
            $item['Ref'] = $Adjustment->Ref;
            $item['warehouse_name'] = $Adjustment['warehouse']->name;
            $item['items'] = $Adjustment->items;
            $data[] = $item;
        }

        return response()->json([
            'totalRows' => $totalRows,
            'adjustments' => $data,
        ]);

    }


    //----------------- stock Report -----------------------\\

    public function stock_Report(request $request)
    {

        $this->authorizeForUser($request->user('api'), 'stock_report', Product::class);

        // How many items do you want to display.
        $perPage = $request->limit;
        $pageStart = \Request::get('page', 1);
        // Start displaying items from this number;
        $offSet = ($pageStart * $perPage) - $perPage;
        $order = $request->SortField;
        $dir = $request->SortType;
        $data = array();

        
        //get warehouses assigned to user
        $user_auth = auth()->user();
        if($user_auth->is_all_warehouses){
            $warehouses = Warehouse::where('deleted_at', '=', null)->get(['id', 'name']);
            $warehouses_id = Warehouse::where('deleted_at', '=', null)->pluck('id')->toArray();
        }else{
            $warehouses_id = UserWarehouse::where('user_id', $user_auth->id)->pluck('warehouse_id')->toArray();
            $warehouses = Warehouse::where('deleted_at', '=', null)->whereIn('id', $warehouses_id)->get(['id', 'name']);
        }

        $products_data = Product::with('unit', 'category', 'brand')
        ->where('deleted_at', '=', null)
        // ->where('type', '!=', 'is_service')
        // Search With Multiple Param
        ->where(function ($query) use ($request) {
            return $query->when($request->filled('search'), function ($query) use ($request) {
                return $query->where('products.name', 'LIKE', "%{$request->search}%")
                    ->orWhere('products.code', 'LIKE', "%{$request->search}%")
                    ->orWhere(function ($query) use ($request) {
                        return $query->whereHas('category', function ($q) use ($request) {
                            $q->where('name', 'LIKE', "%{$request->search}%");
                        });
                    });
            });
        });

        $totalRows = $products_data->count();
        if($perPage == "-1"){
            $perPage = $totalRows;
        }
        $products = $products_data->offset($offSet)
            ->limit($perPage)
            ->orderBy($order, $dir)
            ->get();

        foreach ($products as $product) {


            if($product->type != 'is_service'){

                $item['id'] = $product->id;
                $item['code'] = $product->code;
                $item['name'] = $product->name;
                $item['category'] = $product['category']->name;

                $current_stock = product_warehouse::where('product_id', $product->id)
                ->where('deleted_at', '=', null)
                ->whereIn('warehouse_id', $warehouses_id)
                ->where(function ($query) use ($request) {
                    return $query->when($request->filled('warehouse_id'), function ($query) use ($request) {
                            return $query->where('warehouse_id', $request->warehouse_id);
                        });
                    })
                ->sum('qte');

                $item['quantity'] = $current_stock .' '.$product['unit']->ShortName;

                $data[] = $item;

            }else{

                $item['id'] = $product->id;
                $item['code'] = $product->code;
                $item['name'] = $product->name;
                $item['category'] = $product['category']->name;
                $item['quantity'] = '---';

                $data[] = $item;
            }



        }


        return response()->json([
            'report' => $data,
            'totalRows' => $totalRows,
            'warehouses' => $warehouses,
        ]);

    }

    //-------------------- Get Sales By product -------------\\

    public function get_sales_by_product(request $request)
    {

        $this->authorizeForUser($request->user('api'), 'stock_report', Product::class);
        // How many items do you want to display.
        $perPage = $request->limit;
        $pageStart = \Request::get('page', 1);
        // Start displaying items from this number;
        $offSet = ($pageStart * $perPage) - $perPage;

        $Role = Auth::user()->roles()->first();
        $ShowRecord = Role::findOrFail($Role->id)->inRole('record_view');

        $sale_details_data = SaleDetail::with('product','sale','sale.client','sale.warehouse')
            ->where(function ($query) use ($ShowRecord) {
                if (!$ShowRecord) {
                    return $query->whereHas('sale', function ($q) use ($request) {
                        $q->where('user_id', '=', Auth::user()->id);
                    });
                }
            })
            ->where('product_id', $request->id)
             // Search With Multiple Param
             ->where(function ($query) use ($request) {
                return $query->when($request->filled('search'), function ($query) use ($request) {
                    return $query->where(function ($query) use ($request) {
                            return $query->whereHas('sale.client', function ($q) use ($request) {
                                $q->where('name', 'LIKE', "%{$request->search}%");
                            });
                        })
                        ->orWhere(function ($query) use ($request) {
                            return $query->whereHas('sale.warehouse', function ($q) use ($request) {
                                $q->where('name', 'LIKE', "%{$request->search}%");
                            });
                        })
                        ->orWhere(function ($query) use ($request) {
                            return $query->whereHas('sale', function ($q) use ($request) {
                                $q->where('Ref', 'LIKE', "%{$request->search}%");
                            });
                        })
                        ->orWhere(function ($query) use ($request) {
                            return $query->whereHas('product', function ($q) use ($request) {
                                $q->where('name', 'LIKE', "%{$request->search}%");
                            });
                        });
                });
            });

        $totalRows = $sale_details_data->count();
        if($perPage == "-1"){
            $perPage = $totalRows;
        }
        $sale_details = $sale_details_data->offset($offSet)
            ->limit($perPage)
            ->orderBy('id', 'desc')
            ->get();

        $data = [];
        foreach ($sale_details as $detail) {

            //check if detail has sale_unit_id Or Null
            if($detail->sale_unit_id !== null){
                $unit = Unit::where('id', $detail->sale_unit_id)->first();
            }else{
                $product_unit_sale_id = Product::with('unitSale')
                ->where('id', $detail->product_id)
                ->first();

                if($product_unit_sale_id['unitSale']){
                    $unit = Unit::where('id', $product_unit_sale_id['unitSale']->id)->first();
                }{
                    $unit = NULL;
                }
            }

            if($detail->product_variant_id){
                $productsVariants = ProductVariant::where('product_id', $detail->product_id)
                ->where('id', $detail->product_variant_id)->first();

                $product_name = '['.$productsVariants->name . ']' . $detail['product']['name'];

            }else{
                $product_name = $detail['product']['name'];
            }

            $item['date'] = $detail->date;
            $item['Ref'] = $detail['sale']->Ref;
            $item['sale_id'] = $detail['sale']->id;
            $item['client_name'] = $detail['sale']['client']->name;
            $item['unit_sale'] = $unit?$unit->ShortName:'';
            $item['warehouse_name'] = $detail['sale']['warehouse']->name;
            $item['quantity'] = $detail->quantity .' '.$item['unit_sale'];
            $item['total'] = $detail->total;
            $item['product_name'] = $product_name;

            $data[] = $item;
        }
        return response()->json([
            'totalRows' => $totalRows,
            'sales' => $data,
        ]);

    }

    //-------------------- Get quotations By product -------------\\

    public function get_quotations_by_product(request $request)
    {

        $this->authorizeForUser($request->user('api'), 'stock_report', Product::class);
        // How many items do you want to display.
        $perPage = $request->limit;
        $pageStart = \Request::get('page', 1);
        // Start displaying items from this number;
        $offSet = ($pageStart * $perPage) - $perPage;

        $Role = Auth::user()->roles()->first();
        $ShowRecord = Role::findOrFail($Role->id)->inRole('record_view');

        $quotation_details_data = QuotationDetail::with('product','quotation','quotation.client','quotation.warehouse')
            ->where(function ($query) use ($ShowRecord) {
                if (!$ShowRecord) {
                    return $query->whereHas('quotation', function ($q) use ($request) {
                        $q->where('user_id', '=', Auth::user()->id);
                    });
                }
            })
            ->where('product_id', $request->id)
            // Search With Multiple Param
            ->where(function ($query) use ($request) {
                return $query->when($request->filled('search'), function ($query) use ($request) {
                    return $query->where(function ($query) use ($request) {
                            return $query->whereHas('quotation.client', function ($q) use ($request) {
                                $q->where('name', 'LIKE', "%{$request->search}%");
                            });
                        })
                        ->orWhere(function ($query) use ($request) {
                            return $query->whereHas('quotation.warehouse', function ($q) use ($request) {
                                $q->where('name', 'LIKE', "%{$request->search}%");
                            });
                        })
                        ->orWhere(function ($query) use ($request) {
                            return $query->whereHas('quotation', function ($q) use ($request) {
                                $q->where('Ref', 'LIKE', "%{$request->search}%");
                            });
                        })
                        ->orWhere(function ($query) use ($request) {
                            return $query->whereHas('product', function ($q) use ($request) {
                                $q->where('name', 'LIKE', "%{$request->search}%");
                            });
                        });
                });
            });

        $totalRows = $quotation_details_data->count();
        if($perPage == "-1"){
            $perPage = $totalRows;
        }
        $quotation_details = $quotation_details_data->offset($offSet)
            ->limit($perPage)
            ->orderBy('id', 'desc')
            ->get();

        $data = [];
        foreach ($quotation_details as $detail) {

            //check if detail has sale_unit_id Or Null
            if($detail->sale_unit_id !== null){
            $unit = Unit::where('id', $detail->sale_unit_id)->first();
        }else{
            $product_unit_sale_id = Product::with('unitSale')
            ->where('id', $detail->product_id)
            ->first();
            if($product_unit_sale_id['unitSale']){
                $unit = Unit::where('id', $product_unit_sale_id['unitSale']->id)->first();
            }{
                $unit = NULL;
            }
        }

            if($detail->product_variant_id){
                $productsVariants = ProductVariant::where('product_id', $detail->product_id)
                ->where('id', $detail->product_variant_id)->first();

                $product_name = '['.$productsVariants->name . ']' . $detail['product']['name'];

            }else{
                $product_name = $detail['product']['name'];
            }

            $item['date'] = $detail['quotation']->date;
            $item['Ref'] = $detail['quotation']->Ref;
            $item['quotation_id'] = $detail['quotation']->id;
            $item['client_name'] = $detail['quotation']['client']->name;
            $item['warehouse_name'] = $detail['quotation']['warehouse']->name;
            $item['unit_sale'] = $unit?$unit->ShortName:'';
            $item['quantity'] = $detail->quantity .' '.$item['unit_sale'];
            $item['total'] = $detail->total;
            $item['product_name'] = $product_name;

            $data[] = $item;
        }
        return response()->json([
            'totalRows' => $totalRows,
            'quotations' => $data,
        ]);

    }

    //-------------------- Get purchases By product -------------\\

    public function get_purchases_by_product(request $request)
    {

        $this->authorizeForUser($request->user('api'), 'stock_report', Product::class);
        // How many items do you want to display.
        $perPage = $request->limit;
        $pageStart = \Request::get('page', 1);
        // Start displaying items from this number;
        $offSet = ($pageStart * $perPage) - $perPage;

        $Role = Auth::user()->roles()->first();
        $ShowRecord = Role::findOrFail($Role->id)->inRole('record_view');

        $purchase_details_data = PurchaseDetail::with('product','purchase','purchase.provider','purchase.warehouse')
            ->where(function ($query) use ($ShowRecord) {
                if (!$ShowRecord) {
                    return $query->whereHas('purchase', function ($q) use ($request) {
                        $q->where('user_id', '=', Auth::user()->id);
                    });
                }
            })
            ->where('product_id', $request->id)
            // Search With Multiple Param
            ->where(function ($query) use ($request) {
                return $query->when($request->filled('search'), function ($query) use ($request) {
                    return $query->where(function ($query) use ($request) {
                            return $query->whereHas('purchase.provider', function ($q) use ($request) {
                                $q->where('name', 'LIKE', "%{$request->search}%");
                            });
                        })
                        ->orWhere(function ($query) use ($request) {
                            return $query->whereHas('purchase.warehouse', function ($q) use ($request) {
                                $q->where('name', 'LIKE', "%{$request->search}%");
                            });
                        })
                        ->orWhere(function ($query) use ($request) {
                            return $query->whereHas('purchase', function ($q) use ($request) {
                                $q->where('Ref', 'LIKE', "%{$request->search}%");
                            });
                        })
                        ->orWhere(function ($query) use ($request) {
                            return $query->whereHas('product', function ($q) use ($request) {
                                $q->where('name', 'LIKE', "%{$request->search}%");
                            });
                        });
                });
            });

        $totalRows = $purchase_details_data->count();
        if($perPage == "-1"){
            $perPage = $totalRows;
        }
        $purchase_details = $purchase_details_data->offset($offSet)
            ->limit($perPage)
            ->orderBy('id', 'desc')
            ->get();

        $data = [];
        foreach ($purchase_details as $detail) {

            //-------check if detail has purchase_unit_id Or Null
            if($detail->purchase_unit_id !== null){
            $unit = Unit::where('id', $detail->purchase_unit_id)->first();
        }else{
            $product_unit_purchase_id = Product::with('unitPurchase')
            ->where('id', $detail->product_id)
            ->first();
            $unit = Unit::where('id', $product_unit_purchase_id['unitPurchase']->id)->first();
        }

            if($detail->product_variant_id){
                $productsVariants = ProductVariant::where('product_id', $detail->product_id)
                ->where('id', $detail->product_variant_id)->first();

                $product_name = '['.$productsVariants->name . ']' . $detail['product']['name'];

            }else{
                $product_name = $detail['product']['name'];
            }

            $item['date'] = $detail['purchase']->date;
            $item['Ref'] = $detail['purchase']->Ref;
            $item['purchase_id'] = $detail['purchase']->id;
            $item['provider_name'] = $detail['purchase']['provider']->name;
            $item['warehouse_name'] = $detail['purchase']['warehouse']->name;
            $item['quantity'] = $detail->quantity .' '.$unit->ShortName;;
            $item['total'] = $detail->total;
            $item['product_name'] = $product_name;
            $item['unit_purchase'] = $unit->ShortName;

            $data[] = $item;
        }
        return response()->json([
            'totalRows' => $totalRows,
            'purchases' => $data,
        ]);

    }

    //-------------------- Get purchases return By product -------------\\

    public function get_purchase_return_by_product(request $request)
    {

        $this->authorizeForUser($request->user('api'), 'stock_report', Product::class);
        // How many items do you want to display.
        $perPage = $request->limit;
        $pageStart = \Request::get('page', 1);
        // Start displaying items from this number;
        $offSet = ($pageStart * $perPage) - $perPage;

        $Role = Auth::user()->roles()->first();
        $ShowRecord = Role::findOrFail($Role->id)->inRole('record_view');

        $purchase_return_details_data = PurchaseReturnDetails::with('product','PurchaseReturn','PurchaseReturn.provider','PurchaseReturn.warehouse')
            ->where(function ($query) use ($ShowRecord) {
                if (!$ShowRecord) {
                    return $query->whereHas('PurchaseReturn', function ($q) use ($request) {
                        $q->where('user_id', '=', Auth::user()->id);
                    });
                }
            })
            ->where('quantity', '>', 0)
            ->where('product_id', $request->id)
            // Search With Multiple Param
            ->where(function ($query) use ($request) {
                return $query->when($request->filled('search'), function ($query) use ($request) {
                    return $query->where(function ($query) use ($request) {
                            return $query->whereHas('PurchaseReturn.provider', function ($q) use ($request) {
                                $q->where('name', 'LIKE', "%{$request->search}%");
                            });
                        })
                        ->orWhere(function ($query) use ($request) {
                            return $query->whereHas('PurchaseReturn.warehouse', function ($q) use ($request) {
                                $q->where('name', 'LIKE', "%{$request->search}%");
                            });
                        })
                        ->orWhere(function ($query) use ($request) {
                            return $query->whereHas('PurchaseReturn', function ($q) use ($request) {
                                $q->where('Ref', 'LIKE', "%{$request->search}%");
                            });
                        })
                        ->orWhere(function ($query) use ($request) {
                            return $query->whereHas('product', function ($q) use ($request) {
                                $q->where('name', 'LIKE', "%{$request->search}%");
                            });
                        });
                });
            });

                $totalRows = $purchase_return_details_data->count();
                if($perPage == "-1"){
                    $perPage = $totalRows;
                }
                $purchase_return_details = $purchase_return_details_data->offset($offSet)
                    ->limit($perPage)
                    ->orderBy('id', 'desc')
                    ->get();

                $data = [];
                foreach ($purchase_return_details as $detail) {

                    //-------check if detail has purchase_unit_id Or Null
                if($detail->purchase_unit_id !== null){
                $unit = Unit::where('id', $detail->purchase_unit_id)->first();
            }else{
                $product_unit_purchase_id = Product::with('unitPurchase')
                ->where('id', $detail->product_id)
                ->first();
                $unit = Unit::where('id', $product_unit_purchase_id['unitPurchase']->id)->first();
            }

            if($detail->product_variant_id){
                $productsVariants = ProductVariant::where('product_id', $detail->product_id)
                ->where('id', $detail->product_variant_id)->first();

                $product_name = '['.$productsVariants->name . ']' . $detail['product']['name'];

            }else{
                $product_name = $detail['product']['name'];
            }

            $item['date'] = $detail['PurchaseReturn']->date;
            $item['Ref'] = $detail['PurchaseReturn']->Ref;
            $item['return_purchase_id'] = $detail['PurchaseReturn']->id;
            $item['provider_name'] = $detail['PurchaseReturn']['provider']->name;
            $item['warehouse_name'] = $detail['PurchaseReturn']['warehouse']->name;
            $item['quantity'] = $detail->quantity .' '.$unit->ShortName;;
            $item['total'] = $detail->total;
            $item['product_name'] = $product_name;
            $item['unit_purchase'] = $unit->ShortName;

            $data[] = $item;
        }
        return response()->json([
            'totalRows' => $totalRows,
            'purchases_return' => $data,
        ]);

    }

    //-------------------- Get sales return By product -------------\\

    public function get_sales_return_by_product(request $request)
    {

        $this->authorizeForUser($request->user('api'), 'stock_report', Product::class);
        // How many items do you want to display.
        $perPage = $request->limit;
        $pageStart = \Request::get('page', 1);
        // Start displaying items from this number;
        $offSet = ($pageStart * $perPage) - $perPage;

        $Role = Auth::user()->roles()->first();
        $ShowRecord = Role::findOrFail($Role->id)->inRole('record_view');

        $Sale_Return_details_data = SaleReturnDetails::with('product','SaleReturn','SaleReturn.client','SaleReturn.warehouse')
            ->where(function ($query) use ($ShowRecord) {
                if (!$ShowRecord) {
                    return $query->whereHas('SaleReturn', function ($q) use ($request) {
                        $q->where('user_id', '=', Auth::user()->id);
                    });
                }
            })
            ->where('quantity', '>', 0)
            ->where('product_id', $request->id)
            // Search With Multiple Param
            ->where(function ($query) use ($request) {
                return $query->when($request->filled('search'), function ($query) use ($request) {
                    return $query->where(function ($query) use ($request) {
                            return $query->whereHas('SaleReturn.client', function ($q) use ($request) {
                                $q->where('name', 'LIKE', "%{$request->search}%");
                            });
                        })
                        ->orWhere(function ($query) use ($request) {
                            return $query->whereHas('SaleReturn.warehouse', function ($q) use ($request) {
                                $q->where('name', 'LIKE', "%{$request->search}%");
                            });
                        })
                        ->orWhere(function ($query) use ($request) {
                            return $query->whereHas('SaleReturn', function ($q) use ($request) {
                                $q->where('Ref', 'LIKE', "%{$request->search}%");
                            });
                        })
                        ->orWhere(function ($query) use ($request) {
                            return $query->whereHas('product', function ($q) use ($request) {
                                $q->where('name', 'LIKE', "%{$request->search}%");
                            });
                        });
                });
            });

        $totalRows = $Sale_Return_details_data->count();
        if($perPage == "-1"){
            $perPage = $totalRows;
        }
        $Sale_Return_details = $Sale_Return_details_data->offset($offSet)
            ->limit($perPage)
            ->orderBy('id', 'desc')
            ->get();

        $data = [];
        foreach ($Sale_Return_details as $detail) {

            //check if detail has sale_unit_id Or Null
            if($detail->sale_unit_id !== null){
                $unit = Unit::where('id', $detail->sale_unit_id)->first();
            }else{
                $product_unit_sale_id = Product::with('unitSale')
                ->where('id', $detail->product_id)
                ->first();

                if($product_unit_sale_id['unitSale']){
                    $unit = Unit::where('id', $product_unit_sale_id['unitSale']->id)->first();
                }{
                    $unit = NULL;
                }

            }

            if($detail->product_variant_id){
                $productsVariants = ProductVariant::where('product_id', $detail->product_id)
                ->where('id', $detail->product_variant_id)->first();

                $product_name = '['.$productsVariants->name . ']' . $detail['product']['name'];

            }else{
                $product_name = $detail['product']['name'];
            }

            $item['date'] = $detail['SaleReturn']->date;
            $item['Ref'] = $detail['SaleReturn']->Ref;
            $item['return_sale_id'] = $detail['SaleReturn']->id;
            $item['client_name'] = $detail['SaleReturn']['client']->name;
            $item['warehouse_name'] = $detail['SaleReturn']['warehouse']->name;
            $item['unit_sale'] = $unit?$unit->ShortName:'';
            $item['quantity'] = $detail->quantity .' '.$item['unit_sale'];
            $item['total'] = $detail->total;
            $item['product_name'] = $product_name;

            $data[] = $item;
        }
        return response()->json([
            'totalRows' => $totalRows,
            'sales_return' => $data,
        ]);

    }

    //-------------------- Get transfers By product -------------\\

    public function get_transfer_by_product(request $request)
    {

        $this->authorizeForUser($request->user('api'), 'stock_report', Product::class);
        // How many items do you want to display.
        $perPage = $request->limit;
        $pageStart = \Request::get('page', 1);
        // Start displaying items from this number;
        $offSet = ($pageStart * $perPage) - $perPage;

        $Role = Auth::user()->roles()->first();
        $ShowRecord = Role::findOrFail($Role->id)->inRole('record_view');

        $transfer_details_data = TransferDetail::with('product','transfer','transfer.from_warehouse','transfer.to_warehouse')
            ->where(function ($query) use ($ShowRecord) {
                if (!$ShowRecord) {
                    return $query->whereHas('transfer', function ($q) use ($request) {
                        $q->where('user_id', '=', Auth::user()->id);
                    });
                }
            })
            ->where('product_id', $request->id)
            // Search With Multiple Param
            ->where(function ($query) use ($request) {
                return $query->when($request->filled('search'), function ($query) use ($request) {
                    return $query->where(function ($query) use ($request) {
                            return $query->whereHas('transfer.from_warehouse', function ($q) use ($request) {
                                $q->where('name', 'LIKE', "%{$request->search}%");
                            });
                        })
                        ->orWhere(function ($query) use ($request) {
                            return $query->whereHas('transfer.to_warehouse', function ($q) use ($request) {
                                $q->where('name', 'LIKE', "%{$request->search}%");
                            });
                        })
                        ->orWhere(function ($query) use ($request) {
                            return $query->whereHas('transfer', function ($q) use ($request) {
                                $q->where('Ref', 'LIKE', "%{$request->search}%");
                            });
                        })
                        ->orWhere(function ($query) use ($request) {
                            return $query->whereHas('product', function ($q) use ($request) {
                                $q->where('name', 'LIKE', "%{$request->search}%");
                            });
                        });
                });
            });

        $totalRows = $transfer_details_data->count();
        if($perPage == "-1"){
            $perPage = $totalRows;
        }
        $transfer_details = $transfer_details_data->offset($offSet)
            ->limit($perPage)
            ->orderBy('id', 'desc')
            ->get();

        $data = [];
        foreach ($transfer_details as $detail) {

            if($detail->product_variant_id){
                $productsVariants = ProductVariant::where('product_id', $detail->product_id)
                ->where('id', $detail->product_variant_id)->first();

                $product_name = '['.$productsVariants->name . ']' . $detail['product']['name'];

            }else{
                $product_name = $detail['product']['name'];
            }

            $item['date'] = $detail['transfer']->date;
            $item['Ref'] = $detail['transfer']->Ref;
            $item['from_warehouse'] = $detail['transfer']['from_warehouse']->name;
            $item['to_warehouse'] = $detail['transfer']['to_warehouse']->name;
            $item['product_name'] = $product_name;

            $data[] = $item;
        }
        return response()->json([
            'totalRows' => $totalRows,
            'transfers' => $data,
        ]);

    }

    //-------------------- Get adjustments By product -------------\\

    public function get_adjustment_by_product(request $request)
    {

        $this->authorizeForUser($request->user('api'), 'stock_report', Product::class);
        // How many items do you want to display.
        $perPage = $request->limit;
        $pageStart = \Request::get('page', 1);
        // Start displaying items from this number;
        $offSet = ($pageStart * $perPage) - $perPage;

        $Role = Auth::user()->roles()->first();
        $ShowRecord = Role::findOrFail($Role->id)->inRole('record_view');

        $adjustment_details_data = AdjustmentDetail::with('product','adjustment','adjustment.warehouse')
            ->where(function ($query) use ($ShowRecord) {
                if (!$ShowRecord) {
                    return $query->whereHas('adjustment', function ($q) use ($request) {
                        $q->where('user_id', '=', Auth::user()->id);
                    });
                }
            })
            ->where('product_id', $request->id)
                // Search With Multiple Param
                ->where(function ($query) use ($request) {
                return $query->when($request->filled('search'), function ($query) use ($request) {
                    return $query->where(function ($query) use ($request) {
                            return $query->whereHas('adjustment.warehouse', function ($q) use ($request) {
                                $q->where('name', 'LIKE', "%{$request->search}%");
                            });
                        })
                        ->orWhere(function ($query) use ($request) {
                            return $query->whereHas('adjustment', function ($q) use ($request) {
                                $q->where('Ref', 'LIKE', "%{$request->search}%");
                            });
                        })
                        ->orWhere(function ($query) use ($request) {
                            return $query->whereHas('product', function ($q) use ($request) {
                                $q->where('name', 'LIKE', "%{$request->search}%");
                            });
                        });
                });
            });

        $totalRows = $adjustment_details_data->count();
        if($perPage == "-1"){
            $perPage = $totalRows;
        }
        $adjustment_details = $adjustment_details_data->offset($offSet)
            ->limit($perPage)
            ->orderBy('id', 'desc')
            ->get();

        $data = [];
        foreach ($adjustment_details as $detail) {

            if($detail->product_variant_id){
                $productsVariants = ProductVariant::where('product_id', $detail->product_id)
                ->where('id', $detail->product_variant_id)->first();

                $product_name = '['.$productsVariants->name . ']' . $detail['product']['name'];

            }else{
                $product_name = $detail['product']['name'];
            }

            $item['date'] = $detail['adjustment']->date;
            $item['Ref'] = $detail['adjustment']->Ref;
            $item['warehouse_name'] = $detail['adjustment']['warehouse']->name;
            $item['product_name'] = $product_name;

            $data[] = $item;
        }
        return response()->json([
            'totalRows' => $totalRows,
            'adjustments' => $data,
        ]);

    }

    //------------- download_report_client_pdf -----------\\

    public function download_report_client_pdf(Request $request, $id)
    {

        $this->authorizeForUser($request->user('api'), 'Reports_customers', Client::class);

        $helpers = new helpers();
        $client = Client::where('deleted_at', '=', null)->findOrFail($id);

        $Sales = Sale::where('deleted_at', '=', null)
        ->where([
            ['payment_statut', '!=', 'paid'],
            ['client_id', $id]
        ])->get();

        $sales_details = [];

        foreach ($Sales as $Sale) {
            
            $item_sale['date'] = $Sale['date'];
            $item_sale['Ref'] = $Sale['Ref'];
            $item_sale['GrandTotal'] = number_format($Sale['GrandTotal'], 2, '.', '');
            $item_sale['paid_amount'] = number_format($Sale['paid_amount'], 2, '.', '');
            $item_sale['due'] = number_format($item_sale['GrandTotal'] - $item_sale['paid_amount'], 2, '.', '');
            $item_sale['payment_status'] = $Sale['payment_statut'];
            
            $sales_details[] = $item_sale;
        }

        $data['client_name'] = $client->name;
        $data['phone'] = $client->phone;

        $data['total_sales'] = DB::table('sales')->where('deleted_at', '=', null)->where('client_id', $id)->count();

        $data['total_amount'] = DB::table('sales')
                ->where('deleted_at', '=', null)
                ->where('statut', 'completed')
                ->where('client_id', $client->id)
                ->sum('GrandTotal');

        $data['total_paid'] = DB::table('sales')
            ->where('deleted_at', '=', null)
            ->where('statut', 'completed')
            ->where('client_id', $client->id)
            ->sum('paid_amount');

        $data['due'] = $data['total_amount'] - $data['total_paid'];

        $data['total_amount_return'] = DB::table('sale_returns')
            ->where('deleted_at', '=', null)
            ->where('client_id', $client->id)
            ->sum('GrandTotal');

        $data['total_paid_return'] = DB::table('sale_returns')
            ->where('deleted_at', '=', null)
            ->where('client_id', $client->id)
            ->sum('paid_amount');

        $data['return_Due'] = $data['total_amount_return'] - $data['total_paid_return'];
     
        $symbol = $helpers->Get_Currency();
        $settings = Setting::where('deleted_at', '=', null)->first();

        $pdf = \PDF::loadView('pdf.report_client_pdf', [
            'symbol' => $symbol,
            'client' => $data,
            'sales' => $sales_details,
            'setting' => $settings,
        ]);

        return $pdf->download('report_client.pdf');

    }

     //------------- download_report_provider_pdf -----------\\

     public function download_report_provider_pdf(Request $request, $id)
     {
 
        $this->authorizeForUser($request->user('api'), 'Reports_suppliers', Provider::class);
 
         $helpers = new helpers();
         $provider = Provider::where('deleted_at', '=', null)->findOrFail($id);
 
         $purchases = Purchase::where('deleted_at', '=', null)
         ->where('payment_statut', '!=', 'paid')
         ->where('provider_id', $id)
         ->get();

         $purchases_details = [];
 
         foreach ($purchases as $purchase) {
             
             $item_purchase['date'] = $purchase['date'];
             $item_purchase['Ref'] = $purchase['Ref'];
             $item_purchase['GrandTotal'] = number_format($purchase['GrandTotal'], 2, '.', '');
             $item_purchase['paid_amount'] = number_format($purchase['paid_amount'], 2, '.', '');
             $item_purchase['due'] = number_format($item_purchase['GrandTotal'] - $item_purchase['paid_amount'], 2, '.', '');
             $item_purchase['payment_status'] = $purchase['payment_statut'];
             
             $purchases_details[] = $item_purchase;
         }
 
         $data['provider_name'] = $provider->name;
         $data['phone'] = $provider->phone;
 
        $data['total_purchase'] = DB::table('purchases')->where('deleted_at', '=', null)->where('provider_id', $id)->count();

        $data['total_amount'] = DB::table('purchases')
            ->where('deleted_at', '=', null)
            ->where('statut', 'received')
            ->where('provider_id', $id)
            ->sum('GrandTotal');

        $data['total_paid'] = DB::table('purchases')
            ->where('deleted_at', '=', null)
            ->where('statut', 'received')
            ->where('provider_id', $id)
            ->sum('paid_amount');

        $data['due'] = $data['total_amount'] - $data['total_paid'];

        $data['total_amount_return'] = DB::table('purchase_returns')
            ->where('deleted_at', '=', null)
            ->where('provider_id', $id)
            ->sum('GrandTotal');

        $data['total_paid_return'] = DB::table('purchase_returns')
            ->where('deleted_at', '=', null)
            ->where('provider_id', $id)
            ->sum('paid_amount');

        $data['return_Due'] = $data['total_amount_return'] - $data['total_paid_return'];
      
         $symbol = $helpers->Get_Currency();
         $settings = Setting::where('deleted_at', '=', null)->first();
 
         $pdf = \PDF::loadView('pdf.report_provider_pdf', [
             'symbol' => $symbol,
             'provider' => $data,
             'purchases' => $purchases_details,
             'setting' => $settings,
         ]);
 
         return $pdf->download('report_provider.pdf');
 
     }


    //-------------------- product_report -------------\\

    public function product_report(request $request)
    {
        $this->authorizeForUser($request->user('api'), 'product_report', Product::class);

        $Role = Auth::user()->roles()->first();
        $view_records = Role::findOrFail($Role->id)->inRole('record_view');
        // How many items do you want to display.
        $perPage = $request->limit;
        $pageStart = \Request::get('page', 1);
        // Start displaying items from this number;
        $offSet = ($pageStart * $perPage) - $perPage;

        //get warehouses assigned to user
        $user_auth = auth()->user();
        if($user_auth->is_all_warehouses){
            $warehouses = Warehouse::where('deleted_at', '=', null)->get(['id', 'name']);
            $array_warehouses_id = Warehouse::where('deleted_at', '=', null)->pluck('id')->toArray();
        }else{
            $array_warehouses_id = UserWarehouse::where('user_id', $user_auth->id)->pluck('warehouse_id')->toArray();
            $warehouses = Warehouse::where('deleted_at', '=', null)->whereIn('id', $array_warehouses_id)->get(['id', 'name']);
        }
    
        $products_data = Product::where('deleted_at', '=', null)->select('id', 'name','code', 'is_variant','unit_id','type')
    
        // Filter by specific product if provided
        ->where(function ($query) use ($request) {
            return $query->when($request->filled('product_id'), function ($query) use ($request) {
                return $query->where('id', $request->product_id);
            });
        })
        
        // Search functionality
        ->where(function ($query) use ($request) {
            return $query->when($request->filled('search'), function ($query) use ($request) {
                return $query->where('name','LIKE', "%{$request->search}%")
                    ->orWhere('code', 'LIKE', "%{$request->search}%");
                });
        });
        
        $totalRows = $products_data->count();
        if($perPage == "-1"){
            $perPage = $totalRows;
        }
    
                
        $products = $products_data->offset($offSet)
        ->limit($perPage)
        ->get();


        $product_details = [];
        $total_sales = 0;
        foreach ($products as $product) {
            
            if($product->type != 'is_service'){
                $nestedData['id'] = $product->id;
                $nestedData['name'] = $product->name;
                $nestedData['code'] = $product->code;

                $nestedData['sold_amount'] = SaleDetail::with('sale')->where('product_id', $product->id)
                ->where(function ($query) use ($request, $view_records) {
                    if (!$view_records) {
                        return $query->whereHas('sale', function ($q) use ($request) {
                            $q->where('user_id', '=', Auth::user()->id);
                        });

                    }
                })
                ->where(function ($query) use ($request, $array_warehouses_id) {
                    if ($request->warehouse_id) {
                        return $query->whereHas('sale', function ($q) use ($request, $array_warehouses_id) {
                            $q->where('warehouse_id', $request->warehouse_id);
                        });
                    }else{
                        return $query->whereHas('sale', function ($q) use ($request, $array_warehouses_id) {
                            $q->whereIn('warehouse_id', $array_warehouses_id);
                        });

                    }
                })
                ->whereBetween('date', array($request->from, $request->to))
                ->sum('total');

                $lims_product_sale_data = SaleDetail::select('sale_unit_id', 'quantity')->with('sale')->where('product_id', $product->id)
                    ->where(function ($query) use ($request, $view_records) {
                        if (!$view_records) {
                            return $query->whereHas('sale', function ($q) use ($request) {
                                $q->where('user_id', '=', Auth::user()->id);
                            });

                        }
                    })
                    ->where(function ($query) use ($request, $array_warehouses_id) {
                        if ($request->warehouse_id) {
                            return $query->whereHas('sale', function ($q) use ($request, $array_warehouses_id) {
                                $q->where('warehouse_id', $request->warehouse_id);
                            });
                        }else{
                            return $query->whereHas('sale', function ($q) use ($request, $array_warehouses_id) {
                                $q->whereIn('warehouse_id', $array_warehouses_id);
                            });

                        }
                    })
                ->whereBetween('date', array($request->from, $request->to))
                ->get();

                $sold_qty = 0;
                if(count($lims_product_sale_data)) {
                    foreach ($lims_product_sale_data as $product_sale) {
                        $unit =  Unit::find($product_sale->sale_unit_id);

                        if($unit->operator == '*'){
                            $sold_qty += $product_sale->quantity * $unit->operator_value;
                        }
                        elseif($unit->operator == '/'){
                            $sold_qty += $product_sale->quantity / $unit->operator_value;
                        }
                    
                    }
                }
            
                $unit_shortname = Unit::where('id', $product->unit_id)->first();
                
                $nestedData['sold_qty'] = $sold_qty .' '. $unit_shortname->ShortName;

                $product_details[] = $nestedData;

            }else{

                $nestedData['id'] = $product->id;
                $nestedData['name'] = $product->name;
                $nestedData['code'] = $product->code;

                $nestedData['sold_amount'] = SaleDetail::with('sale')->where('product_id', $product->id)
                ->where(function ($query) use ($view_records) {
                    if (!$view_records) {
                        return $query->whereHas('sale', function ($q) use ($request) {
                            $q->where('user_id', '=', Auth::user()->id);
                        });

                    }
                })
                ->where(function ($query) use ($request, $array_warehouses_id) {
                    if ($request->warehouse_id) {
                        return $query->whereHas('sale', function ($q) use ($request, $array_warehouses_id) {
                            $q->where('warehouse_id', $request->warehouse_id);
                        });
                    }else{
                        return $query->whereHas('sale', function ($q) use ($request, $array_warehouses_id) {
                            $q->whereIn('warehouse_id', $array_warehouses_id);
                        });

                    }
                })
                ->whereBetween('date', array($request->from, $request->to))
                ->sum('total');

                $sold_qty = SaleDetail::select('sale_unit_id', 'quantity')->with('sale')->where('product_id', $product->id)
                ->where(function ($query) use ($request, $view_records) {
                    if (!$view_records) {
                        return $query->whereHas('sale', function ($q) use ($request) {
                            $q->where('user_id', '=', Auth::user()->id);
                        });

                    }
                })
                ->where(function ($query) use ($request, $array_warehouses_id) {
                    if ($request->warehouse_id) {
                        return $query->whereHas('sale', function ($q) use ($request, $array_warehouses_id) {
                            $q->where('warehouse_id', $request->warehouse_id);
                        });
                    }else{
                        return $query->whereHas('sale', function ($q) use ($request, $array_warehouses_id) {
                            $q->whereIn('warehouse_id', $array_warehouses_id);
                        });

                    }
                })
                ->whereBetween('date', array($request->from, $request->to))
                ->sum('quantity');

                $nestedData['sold_qty'] = $sold_qty;

                $product_details[] = $nestedData;
            }
        }

        // Get all products for filter dropdown
        $all_products = Product::where('deleted_at', null)
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
            'products' => $product_details,
            'totalRows' => $totalRows,
            'warehouses' => $warehouses,
            'all_products' => $all_products,
        ]);
    
    }


    //-------------------- sale product details -------------\\

    public function sale_products_details(request $request)
    {

        $this->authorizeForUser($request->user('api'), 'product_report', Product::class);
        // How many items do you want to display.
        $perPage = $request->limit;
        $pageStart = \Request::get('page', 1);
        // Start displaying items from this number;
        $offSet = ($pageStart * $perPage) - $perPage;

        $Role = Auth::user()->roles()->first();
        $ShowRecord = Role::findOrFail($Role->id)->inRole('record_view');

        $sale_details_data = SaleDetail::with('product','sale','sale.client','sale.warehouse','sale.user')
            ->where(function ($query) use ($ShowRecord) {
                if (!$ShowRecord) {
                    return $query->whereHas('sale', function ($q) use ($request) {
                        $q->where('user_id', '=', Auth::user()->id);
                    });
                }
            })
            ->whereBetween('date', array($request->from, $request->to))
            ->where('product_id', $request->id)

             //Filters
             ->where(function ($query) use ($request) {
                return $query->when($request->filled('Ref'), function ($query) use ($request) {
                    return $query->where(function ($query) use ($request) {
                        return $query->whereHas('sale', function ($q) use ($request) {
                            $q->where('Ref', 'LIKE', "{$request->Ref}");
                        });
                    });
                });
            })

            ->where(function ($query) use ($request) {
                return $query->when($request->filled('client_id'), function ($query) use ($request) {
                    return $query->where(function ($query) use ($request) {
                        return $query->whereHas('sale.client', function ($q) use ($request) {
                            $q->where('client_id', $request->client_id);
                        });
                    });
                });
            })

            ->where(function ($query) use ($request) {
                return $query->when($request->filled('warehouse_id'), function ($query) use ($request) {
                    return $query->where(function ($query) use ($request) {
                        return $query->whereHas('sale.warehouse', function ($q) use ($request) {
                            $q->where('warehouse_id', $request->warehouse_id);
                        });
                    });
                });
            })

            ->where(function ($query) use ($request) {
                return $query->when($request->filled('user_id'), function ($query) use ($request) {
                    return $query->where(function ($query) use ($request) {
                        return $query->whereHas('sale.user', function ($q) use ($request) {
                            $q->where('user_id', $request->user_id);
                        });
                    });
                });
            })

            //search
             ->where(function ($query) use ($request) {
                return $query->when($request->filled('search'), function ($query) use ($request) {
                    return $query->where(function ($query) use ($request) {
                            return $query->whereHas('sale.client', function ($q) use ($request) {
                                $q->where('name', 'LIKE', "%{$request->search}%");
                            });
                        })
                        ->orWhere(function ($query) use ($request) {
                            return $query->whereHas('sale.warehouse', function ($q) use ($request) {
                                $q->where('name', 'LIKE', "%{$request->search}%");
                            });
                        })
                        ->orWhere(function ($query) use ($request) {
                            return $query->whereHas('sale', function ($q) use ($request) {
                                $q->where('Ref', 'LIKE', "%{$request->search}%");
                            });
                        })
                        ->orWhere(function ($query) use ($request) {
                            return $query->whereHas('product', function ($q) use ($request) {
                                $q->where('name', 'LIKE', "%{$request->search}%");
                            });
                        });
                });
            });

        $totalRows = $sale_details_data->count();
        if($perPage == "-1"){
            $perPage = $totalRows;
        }
        $sale_details = $sale_details_data->offset($offSet)
            ->limit($perPage)
            ->orderBy('id', 'desc')
            ->get();

        $data = [];
        foreach ($sale_details as $detail) {

            //check if detail has sale_unit_id Or Null
            if($detail->sale_unit_id !== null){
                $unit = Unit::where('id', $detail->sale_unit_id)->first();
            }else{
                $product_unit_sale_id = Product::with('unitSale')
                ->where('id', $detail->product_id)
                ->first();

                if($product_unit_sale_id['unitSale']){
                    $unit = Unit::where('id', $product_unit_sale_id['unitSale']->id)->first();
                }{
                    $unit = NULL;
                }
            }


            if($detail->product_variant_id){
                $productsVariants = ProductVariant::where('product_id', $detail->product_id)
                ->where('id', $detail->product_variant_id)->first();

                $product_name = '['.$productsVariants->name . ']' . $detail['product']['name'];

            }else{
                $product_name = $detail['product']['name'];
            }

            $item['date'] = $detail->date;
            $item['Ref'] = $detail['sale']->Ref;
            $item['created_by'] = $detail['sale']['user']->username;
            $item['sale_id'] = $detail['sale']->id;
            $item['client_name'] = $detail['sale']['client']->name;
            $item['warehouse_name'] = $detail['sale']['warehouse']->name;
            $item['unit_sale'] = $unit?$unit->ShortName:'';
            $item['quantity'] = $detail->quantity .' '.$item['unit_sale'];
            $item['price'] = $detail->price;
            $item['total'] = $detail->total;
            $item['product_name'] = $product_name;

            $data[] = $item;
        }

        $customers = client::where('deleted_at', '=', null)->get(['id', 'name']);
        $users = User::get(['id', 'username']);

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
            'sales' => $data,
            'customers' => $customers,
            'warehouses' => $warehouses,
            'users' => $users,
            'summary' => [
                'total_count' => $totalRows,
                'total_amount' => $sale_details_data->sum('total'),
                'total_quantity' => $sale_details_data->sum('quantity'),
            ],
        ]);

    }

    //-------------------- sale product details -------------\\

    public function purchase_products_details(request $request)
    {

        $this->authorizeForUser($request->user('api'), 'product_report', Product::class);
        // How many items do you want to display.
        $perPage = $request->limit;
        $pageStart = \Request::get('page', 1);
        // Start displaying items from this number;
        $offSet = ($pageStart * $perPage) - $perPage;

        $Role = Auth::user()->roles()->first();
        $ShowRecord = Role::findOrFail($Role->id)->inRole('record_view');

        $purchase_details_data = PurchaseDetail::with('product','purchase','purchase.provider','purchase.warehouse','purchase.user')
            ->where(function ($query) use ($ShowRecord) {
                if (!$ShowRecord) {
                    return $query->whereHas('purchase', function ($q) use ($request) {
                        $q->where('user_id', '=', Auth::user()->id);
                    });
                }
            })
            ->where(function ($query) use ($request) {
                return $query->whereHas('purchase', function ($q) use ($request) {
                    $q->whereBetween('date', array($request->from, $request->to));
                });
            })
            ->where('product_id', $request->id)

             //Filters
             ->where(function ($query) use ($request) {
                return $query->when($request->filled('Ref'), function ($query) use ($request) {
                    return $query->where(function ($query) use ($request) {
                        return $query->whereHas('purchase', function ($q) use ($request) {
                            $q->where('Ref', 'LIKE', "{$request->Ref}");
                        });
                    });
                });
            })

            ->where(function ($query) use ($request) {
                return $query->when($request->filled('provider_id'), function ($query) use ($request) {
                    return $query->where(function ($query) use ($request) {
                        return $query->whereHas('purchase.provider', function ($q) use ($request) {
                            $q->where('provider_id', $request->provider_id);
                        });
                    });
                });
            })

            ->where(function ($query) use ($request) {
                return $query->when($request->filled('warehouse_id'), function ($query) use ($request) {
                    return $query->where(function ($query) use ($request) {
                        return $query->whereHas('purchase.warehouse', function ($q) use ($request) {
                            $q->where('warehouse_id', $request->warehouse_id);
                        });
                    });
                });
            })

            ->where(function ($query) use ($request) {
                return $query->when($request->filled('user_id'), function ($query) use ($request) {
                    return $query->where(function ($query) use ($request) {
                        return $query->whereHas('purchase.user', function ($q) use ($request) {
                            $q->where('user_id', $request->user_id);
                        });
                    });
                });
            })

            //search
             ->where(function ($query) use ($request) {
                return $query->when($request->filled('search'), function ($query) use ($request) {
                    return $query->where(function ($query) use ($request) {
                            return $query->whereHas('purchase.provider', function ($q) use ($request) {
                                $q->where('name', 'LIKE', "%{$request->search}%");
                            });
                        })
                        ->orWhere(function ($query) use ($request) {
                            return $query->whereHas('purchase.warehouse', function ($q) use ($request) {
                                $q->where('name', 'LIKE', "%{$request->search}%");
                            });
                        })
                        ->orWhere(function ($query) use ($request) {
                            return $query->whereHas('purchase', function ($q) use ($request) {
                                $q->where('Ref', 'LIKE', "%{$request->search}%");
                            });
                        })
                        ->orWhere(function ($query) use ($request) {
                            return $query->whereHas('product', function ($q) use ($request) {
                                $q->where('name', 'LIKE', "%{$request->search}%");
                            });
                        });
                });
            });

        $totalRows = $purchase_details_data->count();
        if($perPage == "-1"){
            $perPage = $totalRows;
        }
        $purchase_details = $purchase_details_data->offset($offSet)
            ->limit($perPage)
            ->orderBy('id', 'desc')
            ->get();

        $data = [];
        foreach ($purchase_details as $detail) {

            //check if detail has purchase_unit_id Or Null
            if($detail->purchase_unit_id !== null){
                $unit = Unit::where('id', $detail->purchase_unit_id)->first();
            }else{
                $product_unit_purchase_id = Product::with('unitPurchase')
                ->where('id', $detail->product_id)
                ->first();

                if($product_unit_purchase_id['unitPurchase']){
                    $unit = Unit::where('id', $product_unit_purchase_id['unitPurchase']->id)->first();
                }{
                    $unit = NULL;
                }
            }


            if($detail->product_variant_id){
                $productsVariants = ProductVariant::where('product_id', $detail->product_id)
                ->where('id', $detail->product_variant_id)->first();

                $product_name = '['.$productsVariants->name . ']' . $detail['product']['name'];

            }else{
                $product_name = $detail['product']['name'];
            }

            $item['date'] = $detail['purchase']->date;
            $item['Ref'] = $detail['purchase']->Ref;
            $item['created_by'] = $detail['purchase']['user']->username;
            $item['purchase_id'] = $detail['purchase']->id;
            $item['provider_name'] = $detail['purchase']['provider']->name;
            $item['warehouse_name'] = $detail['purchase']['warehouse']->name;
            $item['unit_purchase'] = $unit?$unit->ShortName:'';
            $item['quantity'] = $detail->quantity .' '.$item['unit_purchase'];
            $item['price'] = $detail->price;
            $item['total'] = $detail->total;
            $item['product_name'] = $product_name;

            $data[] = $item;
        }

        $providers = provider::where('deleted_at', '=', null)->get(['id', 'name']);
        $users = User::get(['id', 'username']);

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
            'purchases' => $data,
            'providers' => $providers,
            'warehouses' => $warehouses,
            'users' => $users,
            'summary' => [
                'total_count' => $totalRows,
                'total_amount' => $purchase_details_data->sum('total'),
                'total_quantity' => $purchase_details_data->sum('quantity'),
            ],
        ]);

    }

    //-------------------- sale returns product details -------------\\

    public function sale_returns_products_details(request $request)
    {

        $this->authorizeForUser($request->user('api'), 'product_report', Product::class);
        // How many items do you want to display.
        $perPage = $request->limit;
        $pageStart = \Request::get('page', 1);
        // Start displaying items from this number;
        $offSet = ($pageStart * $perPage) - $perPage;

        $Role = Auth::user()->roles()->first();
        $ShowRecord = Role::findOrFail($Role->id)->inRole('record_view');

        $sale_return_details_data = SaleReturnDetails::with('product','SaleReturn','SaleReturn.client','SaleReturn.warehouse','SaleReturn.user')
            ->where(function ($query) use ($ShowRecord) {
                if (!$ShowRecord) {
                    return $query->whereHas('SaleReturn', function ($q) use ($request) {
                        $q->where('user_id', '=', Auth::user()->id);
                    });
                }
            })
            ->whereHas('SaleReturn', function ($q) use ($request) {
                $q->whereBetween('date', array($request->from, $request->to));
            })
            ->where('product_id', $request->id)

             //Filters
             ->where(function ($query) use ($request) {
                return $query->when($request->filled('Ref'), function ($query) use ($request) {
                    return $query->where(function ($query) use ($request) {
                        return $query->whereHas('SaleReturn', function ($q) use ($request) {
                            $q->where('Ref', 'LIKE', "{$request->Ref}");
                        });
                    });
                });
            })

            ->where(function ($query) use ($request) {
                return $query->when($request->filled('client_id'), function ($query) use ($request) {
                    return $query->where(function ($query) use ($request) {
                        return $query->whereHas('SaleReturn', function ($q) use ($request) {
                            $q->where('client_id', '=', $request->client_id);
                        });
                    });
                });
            })

            ->where(function ($query) use ($request) {
                return $query->when($request->filled('warehouse_id'), function ($query) use ($request) {
                    return $query->where(function ($query) use ($request) {
                        return $query->whereHas('SaleReturn', function ($q) use ($request) {
                            $q->where('warehouse_id', '=', $request->warehouse_id);
                        });
                    });
                });
            })

            ->where(function ($query) use ($request) {
                return $query->when($request->filled('user_id'), function ($query) use ($request) {
                    return $query->where(function ($query) use ($request) {
                        return $query->whereHas('SaleReturn', function ($q) use ($request) {
                            $q->where('user_id', '=', $request->user_id);
                        });
                    });
                });
            })

            // Search With Multiple Param
            ->where(function ($query) use ($request) {
                return $query->when($request->filled('search'), function ($query) use ($request) {
                    return $query->where(function ($query) use ($request) {
                        return $query->whereHas('SaleReturn.client', function ($q) use ($request) {
                            $q->where('name', 'LIKE', "%{$request->search}%");
                        });
                    })
                    ->orWhere(function ($query) use ($request) {
                        return $query->whereHas('SaleReturn.warehouse', function ($q) use ($request) {
                            $q->where('name', 'LIKE', "%{$request->search}%");
                        });
                    })
                    ->orWhere(function ($query) use ($request) {
                        return $query->whereHas('SaleReturn', function ($q) use ($request) {
                            $q->where('Ref', 'LIKE', "%{$request->search}%");
                        });
                    });
                });
            });

        $totalRows = $sale_return_details_data->count();

        if($perPage == "-1"){
            $perPage = $totalRows;
        }

        $sale_return_details = $sale_return_details_data->offset($offSet)
            ->limit($perPage)
            ->orderBy('id', 'desc')
            ->get();

        $data = array();

        foreach ($sale_return_details as $detail) {

            //check if detail has sale_unit_id Or Null
            if($detail->sale_unit_id !== null){
                $unit = Unit::where('id', $detail->sale_unit_id)->first();
            }else{
                $product_unit_sale_id = Product::with('unitSale')
                ->where('id', $detail->product_id)
                ->first();

                if($product_unit_sale_id['unitSale']){
                    $unit = Unit::where('id', $product_unit_sale_id['unitSale']->id)->first();
                }{
                    $unit = NULL;
                }
            }


            if($detail->product_variant_id){
                $productsVariants = ProductVariant::where('product_id', $detail->product_id)
                ->where('id', $detail->product_variant_id)->first();

                $product_name = '['.$productsVariants->name . ']' . $detail['product']['name'];

            }else{
                $product_name = $detail['product']['name'];
            }

            $item['date'] = $detail['SaleReturn']->date;
            $item['Ref'] = $detail['SaleReturn']->Ref;
            $item['created_by'] = $detail['SaleReturn']['user']->username;
            $item['sale_return_id'] = $detail['SaleReturn']->id;
            $item['client_name'] = $detail['SaleReturn']['client']->name;
            $item['warehouse_name'] = $detail['SaleReturn']['warehouse']->name;
            $item['unit_sale'] = $unit?$unit->ShortName:'';
            $item['quantity'] = $detail->quantity .' '.$item['unit_sale'];
            $item['price'] = $detail->price;
            $item['total'] = $detail->total;
            $item['product_name'] = $product_name;

            $data[] = $item;
        }

        $customers = client::where('deleted_at', '=', null)->get(['id', 'name']);
        $users = User::get(['id', 'username']);

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
            'sales_returns' => $data,
            'customers' => $customers,
            'warehouses' => $warehouses,
            'users' => $users,
            'summary' => [
                'total_count' => $totalRows,
                'total_amount' => $sale_return_details_data->sum('total'),
                'total_quantity' => $sale_return_details_data->sum('quantity'),
            ],
        ]);

    }

    //-------------------- purchase returns product details -------------\\

    public function purchase_returns_products_details(request $request)
    {

        $this->authorizeForUser($request->user('api'), 'product_report', Product::class);
        // How many items do you want to display.
        $perPage = $request->limit;
        $pageStart = \Request::get('page', 1);
        // Start displaying items from this number;
        $offSet = ($pageStart * $perPage) - $perPage;

        $Role = Auth::user()->roles()->first();
        $ShowRecord = Role::findOrFail($Role->id)->inRole('record_view');

        $purchase_return_details_data = PurchaseReturnDetails::with('product','PurchaseReturn','PurchaseReturn.provider','PurchaseReturn.warehouse','PurchaseReturn.user')
            ->where(function ($query) use ($ShowRecord) {
                if (!$ShowRecord) {
                    return $query->whereHas('PurchaseReturn', function ($q) use ($request) {
                        $q->where('user_id', '=', Auth::user()->id);
                    });
                }
            })
            ->whereHas('PurchaseReturn', function ($q) use ($request) {
                $q->whereBetween('date', array($request->from, $request->to));
            })
            ->where('product_id', $request->id)

             //Filters
             ->where(function ($query) use ($request) {
                return $query->when($request->filled('Ref'), function ($query) use ($request) {
                    return $query->where(function ($query) use ($request) {
                        return $query->whereHas('PurchaseReturn', function ($q) use ($request) {
                            $q->where('Ref', 'LIKE', "{$request->Ref}");
                        });
                    });
                });
            })

            ->where(function ($query) use ($request) {
                return $query->when($request->filled('provider_id'), function ($query) use ($request) {
                    return $query->where(function ($query) use ($request) {
                        return $query->whereHas('PurchaseReturn', function ($q) use ($request) {
                            $q->where('provider_id', '=', $request->provider_id);
                        });
                    });
                });
            })

            ->where(function ($query) use ($request) {
                return $query->when($request->filled('warehouse_id'), function ($query) use ($request) {
                    return $query->where(function ($query) use ($request) {
                        return $query->whereHas('PurchaseReturn', function ($q) use ($request) {
                            $q->where('warehouse_id', '=', $request->warehouse_id);
                        });
                    });
                });
            })

            ->where(function ($query) use ($request) {
                return $query->when($request->filled('user_id'), function ($query) use ($request) {
                    return $query->where(function ($query) use ($request) {
                        return $query->whereHas('PurchaseReturn', function ($q) use ($request) {
                            $q->where('user_id', '=', $request->user_id);
                        });
                    });
                });
            })

            // Search With Multiple Param
            ->where(function ($query) use ($request) {
                return $query->when($request->filled('search'), function ($query) use ($request) {
                    return $query->where(function ($query) use ($request) {
                        return $query->whereHas('PurchaseReturn.provider', function ($q) use ($request) {
                            $q->where('name', 'LIKE', "%{$request->search}%");
                        });
                    })
                    ->orWhere(function ($query) use ($request) {
                        return $query->whereHas('PurchaseReturn.warehouse', function ($q) use ($request) {
                            $q->where('name', 'LIKE', "%{$request->search}%");
                        });
                    })
                    ->orWhere(function ($query) use ($request) {
                        return $query->whereHas('PurchaseReturn', function ($q) use ($request) {
                            $q->where('Ref', 'LIKE', "%{$request->search}%");
                        });
                    });
                });
            });

        $totalRows = $purchase_return_details_data->count();

        if($perPage == "-1"){
            $perPage = $totalRows;
        }

        $purchase_return_details = $purchase_return_details_data->offset($offSet)
            ->limit($perPage)
            ->orderBy('id', 'desc')
            ->get();

        $data = array();

        foreach ($purchase_return_details as $detail) {

            //check if detail has purchase_unit_id Or Null
            if($detail->purchase_unit_id !== null){
                $unit = Unit::where('id', $detail->purchase_unit_id)->first();
            }else{
                $product_unit_purchase_id = Product::with('unitPurchase')
                ->where('id', $detail->product_id)
                ->first();

                if($product_unit_purchase_id['unitPurchase']){
                    $unit = Unit::where('id', $product_unit_purchase_id['unitPurchase']->id)->first();
                }{
                    $unit = NULL;
                }
            }


            if($detail->product_variant_id){
                $productsVariants = ProductVariant::where('product_id', $detail->product_id)
                ->where('id', $detail->product_variant_id)->first();

                $product_name = '['.$productsVariants->name . ']' . $detail['product']['name'];

            }else{
                $product_name = $detail['product']['name'];
            }

            $item['date'] = $detail['PurchaseReturn']->date;
            $item['Ref'] = $detail['PurchaseReturn']->Ref;
            $item['created_by'] = $detail['PurchaseReturn']['user']->username;
            $item['purchase_return_id'] = $detail['PurchaseReturn']->id;
            $item['provider_name'] = $detail['PurchaseReturn']['provider']->name;
            $item['warehouse_name'] = $detail['PurchaseReturn']['warehouse']->name;
            $item['unit_purchase'] = $unit?$unit->ShortName:'';
            $item['quantity'] = $detail->quantity .' '.$item['unit_purchase'];
            $item['price'] = $detail->price;
            $item['total'] = $detail->total;
            $item['product_name'] = $product_name;

            $data[] = $item;
        }

        $providers = provider::where('deleted_at', '=', null)->get(['id', 'name']);
        $users = User::get(['id', 'username']);

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
            'providers' => $providers,
            'warehouses' => $warehouses,
            'users' => $users,
            'summary' => [
                'total_count' => $totalRows,
                'total_amount' => $purchase_return_details_data->sum('total'),
                'total_quantity' => $purchase_return_details_data->sum('quantity'),
            ],
        ]);

    }


    //-------------------- product_sales_report  -------------\\

    public function product_sales_report(request $request)
    {
 
         $this->authorizeForUser($request->user('api'), 'product_sales_report', Sale::class);
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
             0 => '=',
             1 => '=',
         );
         $columns = array(
             0 => 'client_id',
             1 => 'warehouse_id',
         );
         $data = array();

         $sale_details_data = SaleDetail::with('product','sale','sale.client','sale.warehouse')
            ->where(function ($query) use ($view_records) {
                if (!$view_records) {
                    return $query->whereHas('sale', function ($q) use ($request) {
                        $q->where('user_id', '=', Auth::user()->id);
                    });
                }
            })
         ->whereBetween('date', array($request->from, $request->to));

         // Filter
         $sale_details_Filtred = $sale_details_data->where(function ($query) use ($request) {
             return $query->when($request->filled('client_id'), function ($query) use ($request) {
                 return $query->whereHas('sale.client', function ($q) use ($request) {
                     $q->where('client_id', '=', $request->client_id);
                 });
             });
         })
        
         ->where(function ($query) use ($request) {
             return $query->when($request->filled('warehouse_id'), function ($query) use ($request) {
                 return $query->whereHas('sale.warehouse', function ($q) use ($request) {
                     $q->where('warehouse_id', '=', $request->warehouse_id);
                 });
             });
         })

         ->where(function ($query) use ($request) {
             return $query->when($request->filled('product_id'), function ($query) use ($request) {
                 return $query->where('product_id', '=', $request->product_id);
             });
         })

        // Search With Multiple Param
        ->where(function ($query) use ($request) {
            return $query->when($request->filled('search'), function ($query) use ($request) {
                return $query->where(function ($query) use ($request) {
                        return $query->whereHas('sale.client', function ($q) use ($request) {
                            $q->where('name', 'LIKE', "%{$request->search}%");
                        });
                    })
                    ->orWhere(function ($query) use ($request) {
                        return $query->whereHas('sale.warehouse', function ($q) use ($request) {
                            $q->where('name', 'LIKE', "%{$request->search}%");
                        });
                    })
                    ->orWhere(function ($query) use ($request) {
                        return $query->whereHas('sale', function ($q) use ($request) {
                            $q->where('Ref', 'LIKE', "%{$request->search}%");
                        });
                    })
                    ->orWhere(function ($query) use ($request) {
                        return $query->whereHas('product', function ($q) use ($request) {
                            $q->where('name', 'LIKE', "%{$request->search}%");
                        });
                    });
            });
        });



         $totalRows = $sale_details_Filtred->count();
         if($perPage == "-1"){
             $perPage = $totalRows;
         }

         $sale_details = $sale_details_Filtred
         ->offset($offSet)
         ->limit($perPage)
         ->orderBy($order, $dir)
         ->get();

         foreach ($sale_details as $detail) {

             //check if detail has sale_unit_id Or Null
             if($detail->sale_unit_id !== null){
                 $unit = Unit::where('id', $detail->sale_unit_id)->first();
             }else{
                 $product_unit_sale_id = Product::with('unitSale')
                 ->where('id', $detail->product_id)
                 ->first();

                 if($product_unit_sale_id['unitSale']){
                    $unit = Unit::where('id', $product_unit_sale_id['unitSale']->id)->first();
                }{
                    $unit = NULL;
                }
             }
 
 
             if($detail->product_variant_id){
                 $productsVariants = ProductVariant::where('product_id', $detail->product_id)
                 ->where('id', $detail->product_variant_id)->first();
 
                 $product_name = '['.$productsVariants->name . ']' . $detail['product']['name'];
 
             }else{
                 $product_name = $detail['product']['name'];
             }
 
             $item['date'] = $detail->date;
             $item['Ref'] = $detail['sale']->Ref;
             $item['client_name'] = $detail['sale']['client']->name;
             $item['warehouse_name'] = $detail['sale']['warehouse']->name;
             $item['quantity'] = $detail->quantity;
             $item['total'] = $detail->total;
             $item['product_name'] = $product_name;
             $item['unit_sale'] = $unit?$unit->ShortName:'';
 
             $data[] = $item;
         }


        //get warehouses assigned to user
       $user_auth = auth()->user();
       if($user_auth->is_all_warehouses){
           $warehouses = Warehouse::where('deleted_at', '=', null)->get(['id', 'name']);
       }else{
           $warehouses_id = UserWarehouse::where('user_id', $user_auth->id)->pluck('warehouse_id')->toArray();
           $warehouses = Warehouse::where('deleted_at', '=', null)->whereIn('id', $warehouses_id)->get(['id', 'name']);
       }

       $customers = client::where('deleted_at', '=', null)->get(['id', 'name']);
       $products = Product::where('deleted_at', '=', null)->get(['id', 'name']);

        return response()->json([
            'totalRows' => $totalRows,
            'sales' => $data,
            'customers' => $customers,
            'warehouses' => $warehouses,
            'products' => $products,
        ]);

    }


    //-------------------- product_purchases_report  -------------\\

    public function product_purchases_report(request $request)
    {
 
         $this->authorizeForUser($request->user('api'), 'product_purchases_report', Purchase::class);
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
             0 => '=',
             1 => '=',
         );
         $columns = array(
             0 => 'provider_id',
             1 => 'warehouse_id',
         );
         $data = array();

         $purchase_details_data = PurchaseDetail::with('product','purchase','purchase.provider','purchase.warehouse')
            ->where(function ($query) use ($view_records) {
                if (!$view_records) {
                    return $query->whereHas('purchase', function ($q) use ($request) {
                        $q->where('user_id', '=', Auth::user()->id);
                    });
                }
            })

            ->where(function ($query) use ($request) {
                return $query->whereHas('purchase', function ($q) use ($request) {
                    $q->whereBetween('date', array($request->from, $request->to));
                });
            });

        // Filter
        $purchase_details_Filtred = $purchase_details_data->where(function ($query) use ($request) {
            return $query->when($request->filled('provider_id'), function ($query) use ($request) {
                return $query->whereHas('purchase.provider', function ($q) use ($request) {
                    $q->where('provider_id', '=', $request->provider_id);
                });
            });
        })
        
        ->where(function ($query) use ($request) {
            return $query->when($request->filled('warehouse_id'), function ($query) use ($request) {
                return $query->whereHas('purchase.warehouse', function ($q) use ($request) {
                    $q->where('warehouse_id', '=', $request->warehouse_id);
                });
            });
        })

        ->where(function ($query) use ($request) {
            return $query->when($request->filled('product_id'), function ($query) use ($request) {
                return $query->where('product_id', '=', $request->product_id);
            });
        })

        // Search With Multiple Param
            ->where(function ($query) use ($request) {
            return $query->when($request->filled('search'), function ($query) use ($request) {
                return $query->where(function ($query) use ($request) {
                        return $query->whereHas('purchase.provider', function ($q) use ($request) {
                            $q->where('name', 'LIKE', "%{$request->search}%");
                        });
                    })
                    ->orWhere(function ($query) use ($request) {
                        return $query->whereHas('purchase', function ($q) use ($request) {
                            $q->where('Ref', 'LIKE', "%{$request->search}%");
                        });
                    })
                    ->orWhere(function ($query) use ($request) {
                        return $query->whereHas('product', function ($q) use ($request) {
                            $q->where('name', 'LIKE', "%{$request->search}%");
                        });
                    })
                    ->orWhere(function ($query) use ($request) {
                        return $query->whereHas('purchase.warehouse', function ($q) use ($request) {
                            $q->where('name', 'LIKE', "%{$request->search}%");
                        });
                    });
            });
        });



         $totalRows = $purchase_details_Filtred->count();
         if($perPage == "-1"){
             $perPage = $totalRows;
         }

         $purchase_details = $purchase_details_Filtred
         ->offset($offSet)
         ->limit($perPage)
         ->orderBy($order, $dir)
         ->get();

         foreach ($purchase_details as $detail) {

            //-------check if detail has purchase_unit_id Or Null
            if($detail->purchase_unit_id !== null){
               $unit = Unit::where('id', $detail->purchase_unit_id)->first();
           }else{
               $product_unit_purchase_id = Product::with('unitPurchase')
               ->where('id', $detail->product_id)
               ->first();
               $unit = Unit::where('id', $product_unit_purchase_id['unitPurchase']->id)->first();
           }
  
              if($detail->product_variant_id){
                  $productsVariants = ProductVariant::where('product_id', $detail->product_id)
                  ->where('id', $detail->product_variant_id)->first();
  
                  $product_name = '['.$productsVariants->name . ']' . $detail['product']['name'];
  
              }else{
                  $product_name = $detail['product']['name'];
              }
  
              $item['date'] = $detail['purchase']->date;
              $item['Ref'] = $detail['purchase']->Ref;
              $item['provider_name'] = $detail['purchase']['provider']->name;
              $item['warehouse_name'] = $detail['purchase']['warehouse']->name;
              $item['quantity'] = $detail->quantity;
              $item['total'] = $detail->total;
              $item['product_name'] = $product_name;
              $item['unit_purchase'] = $unit->ShortName;

              $data[] = $item;
          }

        //get warehouses assigned to user
       $user_auth = auth()->user();
       if($user_auth->is_all_warehouses){
           $warehouses = Warehouse::where('deleted_at', '=', null)->get(['id', 'name']);
       }else{
           $warehouses_id = UserWarehouse::where('user_id', $user_auth->id)->pluck('warehouse_id')->toArray();
           $warehouses = Warehouse::where('deleted_at', '=', null)->whereIn('id', $warehouses_id)->get(['id', 'name']);
       }

       $suppliers = Provider::where('deleted_at', '=', null)->get(['id', 'name']);
       $products = Product::where('deleted_at', '=', null)->get(['id', 'name']);

        return response()->json([
            'totalRows' => $totalRows,
            'purchases' => $data,
            'suppliers' => $suppliers,
            'warehouses' => $warehouses,
            'products' => $products,
        ]);

    }



    //----------------- inventory_valuation_summary -----------------------\\

    public function inventory_valuation_summary(request $request)
    {

        $this->authorizeForUser($request->user('api'), 'inventory_valuation', Product::class);

        // How many items do you want to display.
        $perPage = $request->limit;
        $pageStart = \Request::get('page', 1);
        // Start displaying items from this number;
        $offSet = ($pageStart * $perPage) - $perPage;
        $order = $request->SortField;
        $dir = $request->SortType;
        $data = array();

        $helpers = new helpers();
        $currency_code = $helpers->Get_Currency_Code();
        
        //get warehouses assigned to user
        $user_auth = auth()->user();
        $warehouses = Warehouse::where('deleted_at', '=', null)->get(['id', 'name']);
        $array_warehouses_id = Warehouse::where('deleted_at', '=', null)->pluck('id')->toArray();

        if(empty($request->warehouse_id) || $request->warehouse_id === 0){
            $warehouse_id = 0;
        }else{
            $warehouse_id = $request->warehouse_id;
        }

        $products_data = Product::with('unit')->where('deleted_at', '=', null)

        // Search With Multiple Param
        ->where(function ($query) use ($request) {
            return $query->when($request->filled('search'), function ($query) use ($request) {
                return $query->where('products.name', 'LIKE', "%{$request->search}%")
                    ->orWhere('products.code', 'LIKE', "%{$request->search}%");
            });
        });

        $totalRows = $products_data->count();
        if($perPage == "-1"){
            $perPage = $totalRows;
        }
        $products = $products_data->offset($offSet)
            ->limit($perPage)
            ->orderBy('id', 'desc')
            ->get();

            
        foreach ($products as $product) {
            $inventory_value = 0;
            $stock = 0;

            $item['id']               = $product->id;
            $item['code']             = $product->code;
            $item['name']             = $product->name;
            $item['unit_name']        = $product['unit']?$product['unit']->ShortName:'';


        if($product->type == 'is_variant'){

            $product_variant_data = ProductVariant::where('product_id', $product->id)
            ->where('deleted_at', '=', null)
            ->get();

            $item['variant_name'] = '';
            $item['stock_hand'] = '';
            $item['inventory_value'] = '';

            foreach ($product_variant_data as $product_variant) {
                $item['variant_name']  .= $product_variant->name.' ('.$item['unit_name'].' )';
                $item['variant_name']  .= '<br>';


                $current_stock = product_warehouse::where('product_id', $product->id)
                ->where('product_variant_id', $product_variant->id)
                ->where(function ($query) use ($warehouse_id, $array_warehouses_id) {
                    if ($warehouse_id !== 0) {
                        return  $query->where('warehouse_id', $warehouse_id);
                    }else{
                        return  $query->whereIn('warehouse_id', $array_warehouses_id);
        
                    }
                })
                ->where('deleted_at', '=', null)
                ->sum('qte');
            

                $item['stock_hand'] .= number_format($current_stock, 2, '.', ',');
                $item['stock_hand']  .= '<br>';

                $average_cost = $this->get_average_cost_by_product($product->id, $product_variant->id, $warehouse_id);
                
                $item['inventory_value'] .= $current_stock * $average_cost;
                $item['inventory_value']  .= '<br>';

            }
        
        }else{

            $item['variant_name'] = '---';

            $current_stock = product_warehouse::where('product_id', $product->id)
            ->where(function ($query) use ($warehouse_id, $array_warehouses_id) {
                if ($warehouse_id !== 0) {
                    return  $query->where('warehouse_id', $warehouse_id);
                }else{
                    return  $query->whereIn('warehouse_id', $array_warehouses_id);
    
                }
            })
            ->where('deleted_at', '=', null)
            ->sum('qte');


            //calcule average Cost
            $average_cost = $this->get_average_cost_by_product($product->id, null, $warehouse_id);

            $inventory_value += $current_stock * $average_cost;

            $item['stock_hand'] = $product->type != 'is_service'?number_format($current_stock, 2, '.', ',') :'---';
            $item['inventory_value'] =  $product->type !='is_service'?$inventory_value:'0.0';
            $item['inventory_value']  .= '<br>';

        }
            
            $data[] = $item;

        }
    

        return response()->json([
            'reports'   => $data,
            'totalRows'  => $totalRows,
            'warehouses' => $warehouses,
        ]);

    }


              // Calculate the average cost of a product.
     public function get_average_cost_by_product($product_id ,$product_variant_id , $warehouse_id)
     {        
            // Get the cost of the product
            if($product_variant_id){
                $product = ProductVariant::where('product_id', $product_id)->find($product_variant_id);
                $product_cost = $product->cost;
            }else{
                $product = Product::find($product_id);
                $product_cost = $product->cost;
            }

            $array_warehouses_id = Warehouse::where('deleted_at', '=', null)->pluck('id')->toArray();

            $purchases = PurchaseDetail::where('product_id', $product_id)
            ->where('product_variant_id', $product_variant_id)
            ->join('purchases', 'purchases.id', '=', 'purchase_details.purchase_id')
            ->where('purchases.statut' , 'received')
            ->where(function ($query) use ($warehouse_id, $array_warehouses_id) {
                if ($warehouse_id !== 0) {
                    return  $query->where('purchases.warehouse_id', $warehouse_id);
                }else{
                    return  $query->whereIn('purchases.warehouse_id', $array_warehouses_id);
    
                }
            })

            ->select('purchase_details.quantity as quantity',
                    'purchase_details.cost as cost',
                    'purchase_details.purchase_unit_id as purchase_unit_id')
            ->get();
 
            $purchase_cost = 0;
            $purchase_quantity = 0;
            foreach ($purchases as $purchase) {
               
                $unit = Unit::where('id', $purchase->purchase_unit_id)->first();
            
                if ($unit) {
                    if ($unit->operator == '/') {
                        $purchase_quantity += $purchase->quantity / $unit->operator_value;
                        $purchase_cost += ($purchase->quantity / $unit->operator_value) * ($purchase->cost / $unit->operator_value);
                    } else {
                        $purchase_quantity += $purchase->quantity * $unit->operator_value;
                        $purchase_cost += ($purchase->quantity * $unit->operator_value) * ($purchase->cost * $unit->operator_value);

                    }
                }else{
                    $purchase_quantity += $purchase->quantity;
                    $purchase_cost += $purchase->quantity * $purchase->cost;
                }

            }
 
            // Get the total cost and quantity for all adjustments of the product
            $adjustments = AdjustmentDetail::with('adjustment')
            ->where(function ($query) use ($warehouse_id, $array_warehouses_id) {
                if ($warehouse_id !== 0) {
                    return $query->whereHas('adjustment', function ($q) use ($array_warehouses_id, $warehouse_id) {
                        $q->where('warehouse_id', $warehouse_id);
                    });
                }else{
                    return $query->whereHas('adjustment', function ($q) use ($array_warehouses_id, $warehouse_id) {
                        $q->whereIn('warehouse_id', $array_warehouses_id);
                    });
    
                }
            })
            ->where('product_id', $product_id)
            ->where('product_variant_id', $product_variant_id)
            ->get();
 
            $adjustment_cost = 0;
            $adjustment_quantity = 0;
            foreach ($adjustments as $adjustment) {
                if($adjustment->type == 'add'){
                    $adjustment_quantity += $adjustment->quantity;
                }else{
                    $adjustment_quantity -= $adjustment->quantity;
                }
            }
    
            // Calculate the average cost of purchase

            if($purchase_quantity === 0 || $purchase_quantity == 0 || $purchase_quantity == '0'){
                $average_cost_purchase = $product_cost;
            }else{
                $average_cost_purchase = $purchase_cost / $purchase_quantity;
            }

             // Calculate adjustment_cost multiply by the average cost of purchase
            if($adjustment_quantity === 0 || $adjustment_quantity == 0 || $adjustment_quantity == '0'){
                $adjustment_cost = 0;
            }else{
                $adjustment_cost = $adjustment_quantity * $average_cost_purchase;
            }

            // Calculate the total  average cost
            $total_cost = $purchase_cost + $adjustment_cost;
            $total_quantity = $purchase_quantity + $adjustment_quantity;

            
            if($total_quantity === 0 || $total_quantity == 0 || $total_quantity == '0'){
                $average_cost = $product_cost;
            }else{
                $average_cost = $total_cost / $total_quantity;
            }

      
        return $average_cost;
     }


     //----------------- expenses_report -----------------------\\

    public function expenses_report(request $request)
    {

        $this->authorizeForUser($request->user('api'), 'expenses_report', Expense::class);

        // How many items do you want to display.
        $perPage = $request->limit;
        $pageStart = \Request::get('page', 1);
        // Start displaying items from this number;
        $offSet = ($pageStart * $perPage) - $perPage;
        $order = $request->SortField;
        $dir = $request->SortType;
        $data = array();

        $helpers = new helpers();
        
        //get warehouses assigned to user
        $user_auth = auth()->user();
        $warehouses = Warehouse::where('deleted_at', '=', null)->get(['id', 'name']);
        $array_warehouses_id = Warehouse::where('deleted_at', '=', null)->pluck('id')->toArray();

        if(empty($request->warehouse_id) || $request->warehouse_id === 0){
            $warehouse_id = 0;
        }else{
            $warehouse_id = $request->warehouse_id;
        }

        $expenses_data = Expense::join('expense_categories', 'expenses.expense_category_id', '=', 'expense_categories.id')
        ->where('expenses.deleted_at', '=', null)
        ->where(function ($query) use ($request, $warehouse_id, $array_warehouses_id) {
            if ($warehouse_id !== 0) {
                return $query->where('expenses.warehouse_id', $warehouse_id);
            }else{
                return $query->whereIn('expenses.warehouse_id', $array_warehouses_id);

            }
        })
        ->whereBetween('expenses.date', array($request->from, $request->to))

        // Search With Multiple Param
        ->where(function ($query) use ($request) {
            return $query->when($request->filled('search'), function ($query) use ($request) {
                return $query->where(function ($query) use ($request) {
                    return $query->whereHas('expense_category', function ($q) use ($request) {
                        $q->where('name', 'LIKE', "%{$request->search}%");
                    });
                });
            });
        })
        
        ->select(
            DB::raw('expenses.id as id'),
            DB::raw('expense_categories.name as category_name'),
            DB::raw('sum(expenses.amount) as total_expenses'),
        )
        ->groupBy('expense_categories.name');

        $totalRows = $expenses_data->count();
        if($perPage == "-1"){
            $perPage = $totalRows;
        }
        $expenses = $expenses_data->offset($offSet)
        ->limit($perPage)
        ->orderBy('id', 'desc')
        ->get();

        foreach ($expenses as $expense) {

            $item['id'] = $expense->id;
            $item['category_name'] = $expense->category_name;
            $item['total_expenses'] = $expense->total_expenses;

            $data[] = $item;
        }

        return response()->json([
            'reports'   => $data,
            'totalRows'  => $totalRows,
            'warehouses' => $warehouses,
        ]);

    }

    
     //----------------- deposits_report -----------------------\\

     public function deposits_report(request $request)
     {
 
         $this->authorizeForUser($request->user('api'), 'deposits_report', Deposit::class);
 
         // How many items do you want to display.
         $perPage = $request->limit;
         $pageStart = \Request::get('page', 1);
         // Start displaying items from this number;
         $offSet = ($pageStart * $perPage) - $perPage;
         $order = $request->SortField;
         $dir = $request->SortType;
         $data = array();
 
         $helpers = new helpers();
         
         $deposits_data = Deposit::join('deposit_categories', 'deposits.deposit_category_id', '=', 'deposit_categories.id')
         ->where('deposits.deleted_at', '=', null)
         ->whereBetween('deposits.date', array($request->from, $request->to))
 
         // Search With Multiple Param
         ->where(function ($query) use ($request) {
             return $query->when($request->filled('search'), function ($query) use ($request) {
                 return $query->where(function ($query) use ($request) {
                     return $query->whereHas('deposit_categories', function ($q) use ($request) {
                         $q->where('title', 'LIKE', "%{$request->search}%");
                     });
                 });
             });
         })
         
         ->select(
             DB::raw('deposits.id as id'),
             DB::raw('deposit_categories.title as category_name'),
             DB::raw('sum(deposits.amount) as total_deposits'),
         )
         ->groupBy('deposit_categories.title');
 
         $totalRows = $deposits_data->count();
         if($perPage == "-1"){
             $perPage = $totalRows;
         }
         $deposits = $deposits_data->offset($offSet)
         ->limit($perPage)
         ->orderBy('id', 'desc')
         ->get();
 
         foreach ($deposits as $deposit) {
 
             $item['id'] = $deposit->id;
             $item['category_name'] = $deposit->category_name;
             $item['total_deposits'] = $deposit->total_deposits;
 
             $data[] = $item;
         }
 
         return response()->json([
             'reports'   => $data,
             'totalRows'  => $totalRows,
         ]);
 
     }

     // Calculate the profit from returned products
     public function CalculeReturnSalesProfit($start_date, $end_date, $warehouse_id, $array_warehouses_id)
     {
         $total_return_profit = 0;

         // Get all sale return details within the date range
         $return_details = DB::table('sale_return_details')
             ->join('sale_returns', 'sale_return_details.sale_return_id', '=', 'sale_returns.id')
             ->join('products', 'sale_return_details.product_id', '=', 'products.id')
             ->leftJoin('product_variants', 'sale_return_details.product_variant_id', '=', 'product_variants.id')
             ->where('sale_returns.deleted_at', '=', null)
             ->where('sale_returns.date', '>=', $start_date)
             ->where('sale_returns.date', '<=', $end_date)
             ->where(function ($query) use ($warehouse_id, $array_warehouses_id) {
                 if ($warehouse_id !== 0) {
                     $query->where('sale_returns.warehouse_id', $warehouse_id);
                 } else if ($array_warehouses_id[0] != 0) {
                     $query->whereIn('sale_returns.warehouse_id', $array_warehouses_id);
                 }
             })
             ->select(
                 'sale_return_details.id',
                 'sale_return_details.product_id',
                 'sale_return_details.product_variant_id',
                 'sale_return_details.price',
                 'sale_return_details.quantity',
                 'sale_return_details.total',
                 'sale_returns.date as return_date',
                 'products.cost as product_cost'
             )
             ->get();

         foreach ($return_details as $detail) {
             // Get the actual purchase cost for this product at the time of return
             $purchase_cost = $this->getPurchaseCostForReturnedProduct(
                 $detail->product_id, 
                 $detail->product_variant_id, 
                 $detail->return_date, 
                 $detail->quantity,
                 $warehouse_id,
                 $array_warehouses_id
             );
             
             // Calculate profit: selling price - actual purchase cost
             $return_profit = $detail->total - $purchase_cost;
             $total_return_profit += $return_profit;
         }

         return $total_return_profit;
     }
     
     // Helper function to get the purchase cost for returned products
     private function getPurchaseCostForReturnedProduct($product_id, $variant_id, $return_date, $quantity, $warehouse_id, $array_warehouses_id)
     {
         // Query to get purchase details for this product before the return date
         // Using FIFO principle to match with the same approach as sales
         $purchase_query = DB::table('purchase_details')
             ->join('purchases', 'purchase_details.purchase_id', '=', 'purchases.id')
             ->where('purchases.deleted_at', '=', null)
             ->where('purchases.statut', 'received')
             ->where('purchase_details.product_id', $product_id)
             ->where('purchases.date', '<=', $return_date)
             ->where(function ($query) use ($warehouse_id, $array_warehouses_id) {
                 if ($warehouse_id !== 0) {
                     $query->where('purchases.warehouse_id', $warehouse_id);
                 } else if ($array_warehouses_id[0] != 0) {
                     $query->whereIn('purchases.warehouse_id', $array_warehouses_id);
                 }
             });
             
         if ($variant_id) {
             $purchase_query->where('purchase_details.product_variant_id', $variant_id);
         } else {
             $purchase_query->whereNull('purchase_details.product_variant_id');
         }
         
         $purchases = $purchase_query
             ->select(
                 'purchase_details.cost as unit_cost',
                 'purchase_details.quantity',
                 'purchase_details.total',
                 'purchases.date'
             )
             ->orderBy('purchases.date', 'asc')
             ->get();
             
         $total_cost = 0;
         $remaining_qty = $quantity;
         
         // Use FIFO method to calculate the cost
         foreach ($purchases as $purchase) {
             if ($remaining_qty <= 0) {
                 break;
             }
             
             $used_qty = min($remaining_qty, $purchase->quantity);
             
             // Calculate the actual cost with tax and discount
             $actual_cost = $purchase->unit_cost;
             $total_cost += $used_qty * $actual_cost;
             $remaining_qty -= $used_qty;
         }
         
         // If we couldn't find enough purchase history, use the product's base cost for the remainder
         if ($remaining_qty > 0) {
             $product = DB::table('products')->find($product_id);
             $total_cost += $remaining_qty * $product->cost;
         }
         
         return $total_cost;
     }

    //-------------------- Get Sale Payments By Client Payment -------------\\

    public function Sale_Payments_By_Client_Payment(request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'Reports_customers', Client::class);

        $payment = ClientPayment::findOrFail($id);
        
        // Get all payment_sales for this client_payment
        $paymentSales = DB::table('payment_sales')
            ->where('payment_sales.deleted_at', '=', null)
            ->where('payment_sales.client_payment_id', $id)
            ->leftJoin('sales', 'payment_sales.sale_id', '=', 'sales.id')
            ->select(
                'payment_sales.id', 'payment_sales.date', 'payment_sales.Ref',
                'payment_sales.sale_id', 'payment_sales.montant', 'payment_sales.Reglement',
                'payment_sales.type_credit', 'sales.Ref as sale_ref', 'sales.payment_statut as payment_status'
            )
            ->get();
        
        return response()->json([
            'sale_payments' => $paymentSales,
        ]);
    }

    //-------------------- Get Direct Payments By Clients -------------\\

    public function Direct_Payments_Client(request $request)
    {
        $this->authorizeForUser($request->user('api'), 'Reports_customers', Client::class);
        // How many items do you want to display.
        $perPage = $request->limit;
        $pageStart = \Request::get('page', 1);
        // Start displaying items from this number;
        $offSet = ($pageStart * $perPage) - $perPage;

        // Check if user is authenticated
        $ShowRecord = true; // Default to true
        if (Auth::check()) {
            $Role = Auth::user()->roles()->first();
            if ($Role) {
                $ShowRecord = Role::findOrFail($Role->id)->inRole('record_view');
            }
        }

        // Old payments from payment_sales table that are linked to sales for this client
        $oldPaymentsQuery = DB::table('payment_sales')
            ->where(function ($query) use ($ShowRecord) {
                if (!$ShowRecord && Auth::check()) {
                    return $query->where('payment_sales.user_id', '=', Auth::user()->id);
                }
            })
            ->where('payment_sales.deleted_at', '=', null)
            ->whereNull('payment_sales.client_payment_id') // Only get old payments without client_payment_id
            ->join('sales', 'payment_sales.sale_id', '=', 'sales.id')
            ->where('sales.client_id', $request->id)
            // Search With Multiple Param
            ->where(function ($query) use ($request) {
                return $query->when($request->filled('search'), function ($query) use ($request) {
                    return $query->where('payment_sales.Ref', 'LIKE', "%{$request->search}%")
                        ->orWhere('payment_sales.date', 'LIKE', "%{$request->search}%")
                        ->orWhere('payment_sales.Reglement', 'LIKE', "%{$request->search}%");
                });
            })
            ->join('clients', 'sales.client_id', '=', 'clients.id')
            ->select(
                'payment_sales.id', 
                'payment_sales.date', 
                'payment_sales.Ref AS Ref',
                'payment_sales.Reglement', 
                'payment_sales.montant', 
                'payment_sales.notes',
                'clients.name as client_name', 
                'sales.Ref as sale_ref',
                'payment_sales.sale_id',
                DB::raw("'payment_sale' as payment_type")
            );
            
        // Count total rows
        $totalRows = $oldPaymentsQuery->count();
            
        if($perPage == "-1"){
            $perPage = $totalRows;
        }
        
        // Get results
        $oldPayments = $oldPaymentsQuery
            ->offset($offSet)
            ->limit($perPage)
            ->orderBy('payment_sales.date', 'desc')
            ->get();
        
        // Convert to array format for consistent processing
        $paymentsArray = [];
        
        foreach ($oldPayments as $payment) {
            $paymentsArray[] = [
                'id' => $payment->id,
                'date' => $payment->date,
                'Ref' => $payment->Ref,
                'Reglement' => $payment->Reglement,
                'montant' => $payment->montant,
                'notes' => $payment->notes,
                'client_name' => $payment->client_name,
                'sale_ref' => $payment->sale_ref,
                'sale_id' => $payment->sale_id,
                'payment_type' => 'payment_sale'
            ];
        }

        return response()->json([
            'payments' => $paymentsArray,
            'totalRows' => $totalRows,
        ]);
    }

    //-------------------- Get Global Payments By Clients -------------\\

    public function Global_Payments_Client(request $request)
    {
        $this->authorizeForUser($request->user('api'), 'Reports_customers', Client::class);
        // How many items do you want to display.
        $perPage = $request->limit;
        $pageStart = \Request::get('page', 1);
        // Start displaying items from this number;
        $offSet = ($pageStart * $perPage) - $perPage;

        // Check if user is authenticated
        $ShowRecord = true; // Default to true
        if (Auth::check()) {
            $Role = Auth::user()->roles()->first();
            if ($Role) {
                $ShowRecord = Role::findOrFail($Role->id)->inRole('record_view');
            }
        }

        // New payments from client_payments table
        $newPaymentsQuery = DB::table('client_payments')
            ->where(function ($query) use ($ShowRecord) {
                if (!$ShowRecord && Auth::check()) {
                    return $query->where('client_payments.user_id', '=', Auth::user()->id);
                }
            })
            ->where('client_payments.deleted_at', '=', null)
            ->where('client_payments.client_id', $request->id)
             // Search With Multiple Param
             ->where(function ($query) use ($request) {
                return $query->when($request->filled('search'), function ($query) use ($request) {
                    return $query->where('client_payments.Ref', 'LIKE', "%{$request->search}%")
                        ->orWhere('client_payments.date', 'LIKE', "%{$request->search}%")
                        ->orWhere('client_payments.Reglement', 'LIKE', "%{$request->search}%");
                });
            })
            ->join('clients', 'client_payments.client_id', '=', 'clients.id')
            ->select(
                'client_payments.id', 
                'client_payments.date', 
                'client_payments.Ref AS Ref',
                'client_payments.Reglement', 
                'client_payments.montant', 
                'client_payments.notes',
                'clients.name as client_name', 
                DB::raw("'client_payment' as payment_type")
            );
            
        // Count total rows
        $totalRows = $newPaymentsQuery->count();
            
        if($perPage == "-1"){
            $perPage = $totalRows;
        }
        
        // Get results
        $newPayments = $newPaymentsQuery
            ->offset($offSet)
            ->limit($perPage)
            ->orderBy('client_payments.date', 'desc')
            ->get();
        
        // Convert to array format for consistent processing
        $paymentsArray = [];
        
        foreach ($newPayments as $payment) {
            $paymentsArray[] = [
                'id' => $payment->id,
                'date' => $payment->date,
                'Ref' => $payment->Ref,
                'Reglement' => $payment->Reglement,
                'montant' => $payment->montant,
                'notes' => $payment->notes,
                'client_name' => $payment->client_name,
                'payment_type' => 'client_payment'
            ];
        }

        return response()->json([
            'payments' => $paymentsArray,
            'totalRows' => $totalRows,
        ]);
    }

    //-------------------- Get Global Client Payments -------------\\

    public function Global_Client_Payments(request $request)
    {
        // $this->authorizeForUser($request->user('api'), 'Reports_sales', Sale::class);
        // How many items do you want to display.
        $perPage = $request->limit;
        $pageStart = \Request::get('page', 1);
        // Start displaying items from this number;
        $offSet = ($pageStart * $perPage) - $perPage;

        // Check if user is authenticated
        $ShowRecord = true; // Default to true
        if (Auth::check()) {
            $Role = Auth::user()->roles()->first();
            if ($Role) {
                $ShowRecord = Role::findOrFail($Role->id)->inRole('record_view');
            }
        }

        // Query from client_payments table
        $clientPaymentsQuery = DB::table('client_payments')
            ->where(function ($query) use ($ShowRecord) {
                if (!$ShowRecord && Auth::check()) {
                    return $query->where('client_payments.user_id', '=', Auth::user()->id);
                }
            })
            ->where('client_payments.deleted_at', '=', null)
            // filter by client id if provided
            ->where(function ($query) use ($request) {
                if ($request->filled('client_id')) {
                    return $query->where('client_payments.client_id', '=', $request->client_id);
                }
            })
            // filter by date range
            ->where(function ($query) use ($request) {
                if ($request->filled('from') && $request->filled('to')) {
                    return $query->whereBetween('client_payments.date', [$request->from, $request->to]);
                }
            })
            // Search With Multiple Param
            ->where(function ($query) use ($request) {
                return $query->when($request->filled('search'), function ($query) use ($request) {
                    return $query->where('client_payments.Ref', 'LIKE', "%{$request->search}%")
                        ->orWhere('client_payments.date', 'LIKE', "%{$request->search}%")
                        ->orWhere('client_payments.Reglement', 'LIKE', "%{$request->search}%");
                });
            })
            ->join('clients', 'client_payments.client_id', '=', 'clients.id')
            ->select(
                'client_payments.id', 
                'client_payments.date', 
                'client_payments.Ref AS Ref',
                'client_payments.Reglement', 
                'client_payments.montant', 
                'client_payments.notes',
                'clients.name as client_name', 
                DB::raw("'client_payment' as payment_type")
            );
            
        // Count total rows
        $totalRows = $clientPaymentsQuery->count();
            
        if($perPage == "-1"){
            $perPage = $totalRows;
        }
        
        // Get results
        $clientPayments = $clientPaymentsQuery
            ->offset($offSet)
            ->limit($perPage)
            ->orderBy('client_payments.date', 'desc')
            ->get();
        
        // Convert to array format for consistent processing
        $paymentsArray = [];
        
        foreach ($clientPayments as $payment) {
            $paymentsArray[] = [
                'id' => $payment->id,
                'date' => $payment->date,
                'Ref' => $payment->Ref,
                'Reglement' => $payment->Reglement,
                'montant' => $payment->montant,
                'notes' => $payment->notes,
                'client_name' => $payment->client_name,
                'payment_type' => 'client_payment'
            ];
        }

        return response()->json([
            'payments' => $paymentsArray,
            'totalRows' => $totalRows,
        ]);
    }

    //-------------------- Get Payments Client Report -------------\\

    public function Client_Payments_Report(request $request)
    {
        //$this->authorizeForUser($request->user('api'), 'Reports_payments_Sales', Client::class);
        
        // How many items do you want to display.
        $perPage = $request->limit;
        $pageStart = \Request::get('page', 1);
        // Start displaying items from this number;
        $offSet = ($pageStart * $perPage) - $perPage;

        // Check if user is authenticated
        $ShowRecord = true; // Default to true
        if (Auth::check()) {
            $Role = Auth::user()->roles()->first();
            if ($Role) {
                $ShowRecord = Role::findOrFail($Role->id)->inRole('record_view');
            }
        }

        $date_range = [];
        $clients = [];
        $sales = [];        

        $query_global = DB::table('client_payments')
            ->where(function ($query) use ($ShowRecord) {
                if (!$ShowRecord && Auth::check()) {
                    return $query->where('client_payments.user_id', '=', Auth::user()->id);
                }
            })
            ->where('client_payments.deleted_at', '=', null)
            ->join('clients', 'client_payments.client_id', '=', 'clients.id')
            // Search by client
            ->where(function ($query) use ($request) {
                return $query->when($request->filled('client_id'), function ($query) use ($request) {
                    return $query->where('client_payments.client_id', '=', $request->client_id);
                });
            })
            // Search by payment method
            ->where(function ($query) use ($request) {
                return $query->when($request->filled('Reglement'), function ($query) use ($request) {
                    return $query->where('client_payments.Reglement', '=', $request->Reglement);
                });
            })
            // Search by reference
            ->where(function ($query) use ($request) {
                return $query->when($request->filled('Ref'), function ($query) use ($request) {
                    return $query->where('client_payments.Ref', 'LIKE', "%{$request->Ref}%");
                });
            })
            // Search by payment type
            ->where(function ($query) use ($request) {
                return $query->when($request->filled('payment_type') && $request->payment_type === 'global', function ($query) {
                    return $query->whereNotNull('client_payments.id');
                });
            })
            // Search general
            ->where(function ($query) use ($request) {
                return $query->when($request->filled('search'), function ($query) use ($request) {
                    return $query->where('client_payments.Ref', 'LIKE', "%{$request->search}%")
                        ->orWhere('client_payments.date', 'LIKE', "%{$request->search}%")
                        ->orWhere('client_payments.Reglement', 'LIKE', "%{$request->search}%")
                        ->orWhere('clients.name', 'LIKE', "%{$request->search}%");
                });
            })
            // Filter by date range
            ->where(function ($query) use ($request) {
                return $query->when($request->filled('from') && $request->filled('to'), function ($query) use ($request) {
                    return $query->whereDate('client_payments.date', '>=', $request->from)
                        ->whereDate('client_payments.date', '<=', $request->to);
                });
            });

        // Skip payment_type filter if it's not provided
        if (!$request->filled('payment_type')) {
            $query_global = $query_global->whereNotNull('client_payments.id');
        }       

        // Select fields for global payments
        $global_payments = $query_global->select(
            'client_payments.id',
            'client_payments.date',
            'client_payments.Ref',
            'client_payments.Reglement',
            'client_payments.montant',
            'client_payments.notes',
            'clients.name as client_name',
            DB::raw("NULL as sale_ref"),
            DB::raw("NULL as sale_id"),
            DB::raw("'global' as payment_type")
        )->get();

        // Sort by date (newest first)
        $global_payments = $global_payments->sortByDesc('date');

        // Calculate total rows
        $totalRows = $global_payments->count();

        // Get list of clients for filter
        $clients = Client::where('deleted_at', '=', null)->get(['id', 'name']);

        // Get list of sales for filter
        $sales = Sale::where('deleted_at', '=', null)->get(['id', 'Ref']);

        // Handle pagination manually
        if ($perPage == "-1") {
            $perPage = $totalRows;
        }

        // Apply pagination
        $payments = $global_payments->slice($offSet, $perPage)->values();

        return response()->json([
            'payments' => $payments,
            'sales' => $sales,
            'clients' => $clients,
            'totalRows' => $totalRows,
        ]);
    }

    // Helper function to get the purchase cost for a specific product sale
    private function getProductPurchaseCost($product_id, $variant_id, $quantity, $warehouse_id, $sale_date)
    {
        // First, check if there's warehouse-specific pricing
        $warehouse_pricing = DB::table('product_warehouse')
            ->where('product_id', $product_id)
            ->where('warehouse_id', $warehouse_id)
            ->where('deleted_at', '=', null)
            ->first();
        
        // If warehouse-specific cost exists, use it
        if ($warehouse_pricing && $warehouse_pricing->cost > 0) {
            return $quantity * $warehouse_pricing->cost;
        }
        
        // Otherwise, get the actual purchase cost using FIFO method
        $purchase_query = DB::table('purchase_details')
            ->join('purchases', 'purchase_details.purchase_id', '=', 'purchases.id')
            ->where('purchases.deleted_at', '=', null)
            ->where('purchases.statut', 'received')
            ->where('purchase_details.product_id', $product_id)
            ->where('purchases.date', '<=', $sale_date)
            ->where('purchases.warehouse_id', $warehouse_id);
        
        if ($variant_id) {
            $purchase_query->where('purchase_details.product_variant_id', $variant_id);
        } else {
            $purchase_query->whereNull('purchase_details.product_variant_id');
        }
        
        $purchases = $purchase_query
            ->select(
                'purchase_details.cost as unit_cost',
                'purchase_details.quantity',
                'purchase_details.TaxNet',
                'purchase_details.tax_method',
                'purchase_details.discount',
                'purchase_details.discount_method',
                'purchases.date'
            )
            ->orderBy('purchases.date', 'asc')
            ->get();
        
        $total_cost = 0;
        $remaining_qty = $quantity;
        
        // Use FIFO method to calculate the cost
        foreach ($purchases as $purchase) {
            if ($remaining_qty <= 0) {
                break;
            }
            
            $used_qty = min($remaining_qty, $purchase->quantity);
            
            // Calculate the actual cost with tax and discount
            $actual_cost = $purchase->unit_cost;
            
            // Apply tax if tax method is exclusive (1)
            if ($purchase->tax_method == 1) {
                $actual_cost += ($actual_cost * $purchase->TaxNet / 100);
            }
            
            // Apply discount
            if ($purchase->discount_method == 1) { // Percentage
                $actual_cost -= ($actual_cost * $purchase->discount / 100);
            } else if ($purchase->discount_method == 2) { // Fixed amount
                $actual_cost -= $purchase->discount;
            }
            
            $total_cost += $used_qty * $actual_cost;
            $remaining_qty -= $used_qty;
        }
        
        // If we couldn't find enough purchase history, use the product's base cost for the remainder
        if ($remaining_qty > 0) {
            $product = DB::table('products')->find($product_id);
            $total_cost += $remaining_qty * $product->cost;
        }
        
        return $total_cost;
    }

}
