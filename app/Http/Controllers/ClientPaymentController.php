<?php

namespace App\Http\Controllers;

use App\Models\ClientPayment;
use App\Models\Client;
use App\Models\Setting;
use App\utils\helpers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ClientPaymentController extends BaseController
{
    //------------- Get All Client Payments -------------\\
    public function index(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'view', ClientPayment::class);

        // How many items do you want to display
        $perPage = $request->limit;
        $pageStart = $request->get('page', 1);
        // Start displaying items from this number
        $offSet = ($pageStart * $perPage) - $perPage;
        $order = $request->SortField;
        $dir = $request->SortType;
        $helpers = new helpers();

        // Filter fields with params
        $columns = array(0 => 'Ref', 1 => 'date', 2 => 'client_id', 3 => 'montant');
        $param = array(0 => 'like', 1 => 'like', 2 => 'like', 3 => 'like');
        $data = array();

        $payments = ClientPayment::with('client', 'user')
            ->where('deleted_at', '=', null);

        //Multiple Filter
        $Filtred = $helpers->filter($payments, $columns, $param, $request)
            ->where(function ($query) use ($request) {
                return $query->when($request->filled('search'), function ($query) use ($request) {
                    return $query->where('Ref', 'LIKE', "%{$request->search}%")
                        ->orWhere('date', 'LIKE', "%{$request->search}%")
                        ->orWhere('montant', 'LIKE', "%{$request->search}%")
                        ->orWhere(function ($query) use ($request) {
                            return $query->whereHas('client', function ($q) use ($request) {
                                $q->where('name', 'LIKE', "%{$request->search}%");
                            });
                        });
                });
            });

        // Filter by date
        $Filtred = $helpers->filter_date($Filtred, 'date', $request);
        
        // Filter by client
        if ($request->filled('client_id')) {
            $Filtred = $Filtred->where('client_id', '=', $request->client_id);
        }

        // Filter by payment method
        if ($request->filled('Reglement')) {
            $Filtred = $Filtred->where('Reglement', '=', $request->Reglement);
        }

        $totalRows = $Filtred->count();
        if ($perPage == "-1") {
            $perPage = $totalRows;
        }

        $payments = $Filtred->offset($offSet)
            ->limit($perPage)
            ->orderBy($order, $dir)
            ->get();

        foreach ($payments as $payment) {
            $item['id'] = $payment->id;
            $item['date'] = $payment->date;
            $item['Ref'] = $payment->Ref;
            $item['Reglement'] = $payment->Reglement;
            $item['montant'] = $payment->montant;
            $item['notes'] = $payment->notes;
            $item['client_name'] = $payment->client->name;
            $item['client_id'] = $payment->client_id;
            $item['user_name'] = $payment->user ? $payment->user->username : '';
            
            $data[] = $item;
        }

        return response()->json([
            'payments' => $data,
            'totalRows' => $totalRows,
        ]);
    }

    //------------- Get Payment Details -------------\\
    public function show($id)
    {
        $this->authorizeForUser(Auth::user(), 'view', ClientPayment::class);

        $payment = ClientPayment::with('client', 'user', 'account')->findOrFail($id);
        
        // Get the payment sales for this client payment
        $paymentSales = $payment->paymentSales()->with('sale')->get();
        
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
        ];
        
        $payment_details = [];
        $total_allocated = 0;
        
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
            } else {
                $allocation['allocation_type'] = 'Initial Credit';
            }
            
            $payment_details[] = $allocation;
            $total_allocated += $detail->montant;
        }
        
        return response()->json([
            'payment' => $payment_data,
            'payment_details' => $payment_details,
            'total_allocated' => $total_allocated,
            'unallocated' => $payment->montant - $total_allocated,
        ]);
    }

    //------------- DELETE CLIENT PAYMENT -------------\\
    public function destroy(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'delete', ClientPayment::class);

        $payment = ClientPayment::findOrFail($id);
        
        // First, reverse the distributions
        foreach ($payment->paymentSales as $paymentSale) {
            // If this payment was for a sale, update the sale status
            if ($paymentSale->sale_id) {
                $sale = $paymentSale->sale;
                if ($sale) {
                    $sale->paid_amount -= $paymentSale->montant;
                    
                    // Update payment status
                    if ($sale->paid_amount <= 0) {
                        $sale->payment_statut = 'unpaid';
                    } else if ($sale->paid_amount < $sale->GrandTotal) {
                        $sale->payment_statut = 'partial';
                    }
                    
                    $sale->save();
                }
            } else if ($paymentSale->type_credit == 'credit_initial' && $paymentSale->client_id) {
                // If this payment was for an initial credit, add it back to the client
                $client = Client::find($paymentSale->client_id);
                if ($client) {
                    $client->credit_initial += $paymentSale->montant;
                    $client->save();
                }
            }
            
            // Delete the payment sale record
            $paymentSale->delete();
        }
        
        // If payment used an account, update the account balance
        if ($payment->account_id) {
            $account = $payment->account;
            if ($account) {
                $account->balance -= $payment->montant;
                $account->save();
            }
        }
        
        // Delete the client payment
        $payment->delete();
        
        return response()->json(['success' => true]);
    }
}
