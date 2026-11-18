<?php

namespace App\Http\Controllers;
use App\Models\Account;
use App\Models\Expense;
use App\Models\User;
use App\Models\UserWarehouse;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Client;
use App\Models\PaymentSale;
use App\Models\Product;
use App\Models\Setting;
use App\Models\PosSetting;
use App\Models\ProductVariant;
use App\Models\product_warehouse;
use App\Models\PaymentWithCreditCard;
use App\Models\Role;
use App\Models\Unit;
use App\Models\Sale;
use App\Models\SaleDetail;
use App\Models\DraftSale;
use App\Models\DraftSaleDetail;
use App\Models\Warehouse;
use App\utils\helpers;
use Carbon\Carbon;
use DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Stripe;

class PosController extends BaseController
{

    //------------ Create New  POS --------------\\

    public function CreatePOS(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'Sales_pos', Sale::class);

        request()->validate([
            'client_id' => 'required',
            'warehouse_id' => 'required',
            'payment.amount' => 'required',
            // charges of the day recorded with the sale (expenses)
            'charges' => 'nullable|array|max:50',
            'charges.*.amount' => 'required|numeric|min:0.01',
            'charges.*.category_id' => 'required|integer|exists:expense_categories,id',
            'charges.*.details' => 'nullable|string|max:150',
        ]);
        $this->checkPosAccess($request);

        // users without "change the price" sell at the catalogue price (or the price saved in their cart)
        if ($bad = $this->posPriceRefused((array) $request->input('details', []), $request->warehouse_id,
            DB::table('pos_carts')->where('user_id', Auth::id())->first())) {
            return $bad;
        }

        // a charge must use one of the categories chosen in Settings > POS settings
        $allowed_categories = $this->posChargeCategories()->pluck('id')->toArray();
        foreach ((array) $request->input('charges', []) as $charge) {
            if (!in_array((int) ($charge['category_id'] ?? 0), $allowed_categories)) {
                return response()->json(['message' => 'This charge category is not offered in the POS.', 'code' => 'pos_charge_category'], 422);
            }
        }

        $item = \DB::transaction(function () use ($request) {
            $helpers = new helpers();
            $role = Auth::user()->roles()->first();
            $view_records = Role::findOrFail($role->id)->inRole('record_view');
            $order = new Sale;

            $order->is_pos = 1;
            $order->date = Carbon::now();
            $order->Ref = app('App\Http\Controllers\SalesController')->getNumberOrder();
            $order->client_id = $request->client_id;
            $order->warehouse_id = $request->warehouse_id;
            $order->tax_rate = $request->tax_rate;
            $order->TaxNet = $request->TaxNet;
            $order->discount = $request->discount;
            $order->shipping = $request->shipping;
            $order->GrandTotal = $request->GrandTotal;
            $order->notes = $request->notes;
            $order->statut = 'completed';
            $order->payment_statut = 'unpaid';
            $order->user_id = Auth::user()->id;

            $order->save();

            $data = $request['details'];
            foreach ($data as $key => $value) {

                $unit = Unit::where('id', $value['sale_unit_id'])
                    ->first();
                $orderDetails[] = [
                    'date' => Carbon::now(),
                    'sale_id' => $order->id,
                    'sale_unit_id' =>  $value['sale_unit_id'],
                    'quantity' => $value['quantity'],
                    'product_id' => $value['product_id'],
                    'product_variant_id' => $value['product_variant_id'],
                    'total' => $value['subtotal'],
                    'price' => $value['Unit_price'],
                    'TaxNet' => $value['tax_percent'],
                    'tax_method' => $value['tax_method'],
                    'discount' => $value['discount'],
                    'discount_method' => $value['discount_Method'],
                    'imei_number' => $value['imei_number'],
                    // POS day cart: when the product was added / confirmed
                    'added_at' => $this->cartTime($value['added_at'] ?? null),
                    'confirmed_at' => $this->cartTime($value['confirmed_at'] ?? null),
                ];

                if ($value['product_variant_id'] !== null) {
                    $product_warehouse = product_warehouse::where('warehouse_id', $order->warehouse_id)
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
                    $product_warehouse = product_warehouse::where('warehouse_id', $order->warehouse_id)
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

            SaleDetail::insert($orderDetails);

            $this->createPosCharges($request, $order);

            $sale = Sale::findOrFail($order->id);
            // Check If User Has Permission view All Records
            if (!$view_records) {
                // Check If User->id === sale->id
                $this->authorizeForUser($request->user('api'), 'check_record', $sale);
            }

            try {

                $total_paid = $sale->paid_amount + $request['amount'];
                $due = $sale->GrandTotal - $total_paid;

                if ($due === 0.0 || $due < 0.0) {
                    $payment_statut = 'paid';
                } else if ($due != $sale->GrandTotal) {
                    $payment_statut = 'partial';
                } else if ($due == $sale->GrandTotal) {
                    $payment_statut = 'unpaid';
                }
                              
                if($request['amount'] > 0){
                    if ($request->payment['Reglement'] == 'credit card') {
                        $Client = Client::whereId($request->client_id)->first();
                        Stripe\Stripe::setApiKey(config('app.STRIPE_SECRET'));

                        // Check if the payment record exists
                        $PaymentWithCreditCard = PaymentWithCreditCard::where('customer_id', $request->client_id)->first();
                        if (!$PaymentWithCreditCard) {

                            // Create a new customer and charge the customer with a new credit card
                            $customer = \Stripe\Customer::create([
                                'source' => $request->token,
                                'email'  => $Client->email,
                                'name'   => $Client->name,
                            ]);

                            // Charge the Customer instead of the card:
                            $charge = \Stripe\Charge::create([
                                'amount'   => $request['amount'] * 100,
                                'currency' => 'usd',
                                'customer' => $customer->id,
                            ]);
                            $PaymentCard['customer_stripe_id'] = $customer->id;

                        // Check if the payment record not exists
                        } else {

                             // Retrieve the customer ID and card ID
                            $customer_id = $PaymentWithCreditCard->customer_stripe_id;
                            $card_id = $request->card_id;

                            // Charge the customer with the new credit card or the selected card
                            if ($request->is_new_credit_card || $request->is_new_credit_card == 'true' || $request->is_new_credit_card === 1) {
                                // Retrieve the customer
                                $customer = \Stripe\Customer::retrieve($customer_id);

                                // Create New Source
                                $card = \Stripe\Customer::createSource(
                                    $customer_id,
                                    [
                                      'source' => $request->token,
                                    ]
                                  );

                                $charge = \Stripe\Charge::create([
                                    'amount'   => $request['amount'] * 100,
                                    'currency' => 'usd',
                                    'customer' => $customer_id,
                                    'source'   => $card->id,
                                ]);
                                $PaymentCard['customer_stripe_id'] = $customer_id;

                            } else {
                                $charge = \Stripe\Charge::create([
                                    'amount'   => $request['amount'] * 100,
                                    'currency' => 'usd',
                                    'customer' => $customer_id,
                                    'source'   => $card_id,
                                ]);
                                $PaymentCard['customer_stripe_id'] = $customer_id;
                            }
                        }

                        $PaymentSale            = new PaymentSale();
                        $PaymentSale->sale_id   = $order->id;
                        $PaymentSale->Ref       = app('App\Http\Controllers\PaymentSalesController')->getNumberOrder();
                        $PaymentSale->date      = Carbon::now();
                        $PaymentSale->Reglement = $request->payment['Reglement'];
                        $PaymentSale->montant   = $request['amount'];
                        $PaymentSale->change    = $request['change'];
                        $PaymentSale->notes     = $request->payment['notes'];
                        $PaymentSale->user_id   = Auth::user()->id;
                        $PaymentSale->account_id   = $request->payment['account_id']?$request->payment['account_id']:NULL;

                        $PaymentSale->save();

                        $account = Account::where('id', $request->payment['account_id'])->exists();

                        if ($account) {
                            // Account exists, perform the update
                            $account = Account::find($request->payment['account_id']);
                            $account->update([
                                'balance' => $account->balance + $request['amount'],
                            ]);
                        }

                        $sale->update([
                            'paid_amount'    => $total_paid,
                            'payment_statut' => $payment_statut,
                        ]);

                        $PaymentCard['customer_id'] = $request->client_id;
                        $PaymentCard['payment_id']  = $PaymentSale->id;
                        $PaymentCard['charge_id']   = $charge->id;
                        PaymentWithCreditCard::create($PaymentCard);

                        // Paying Method Cash
                    } else {

                        PaymentSale::create([
                            'sale_id' => $order->id,
                            'account_id' => $request->payment['account_id']?$request->payment['account_id']:NULL,
                            'Ref' => app('App\Http\Controllers\PaymentSalesController')->getNumberOrder(),
                            'date' => Carbon::now(),
                            'Reglement' => $request->payment['Reglement'],
                            'montant' => $request['amount'],
                            'change' => $request['change'],
                            'notes' => $request->payment['notes'],
                            'user_id' => Auth::user()->id,
                        ]);

                        $account = Account::where('id', $request->payment['account_id'])->exists();

                        if ($account) {
                            // Account exists, perform the update
                            $account = Account::find($request->payment['account_id']);
                            $account->update([
                                'balance' => $account->balance + $request['amount'],
                            ]);
                        }

                        $sale->update([
                            'paid_amount' => $total_paid,
                            'payment_statut' => $payment_statut,
                        ]);
                    }

                }
              
            } catch (Exception $e) {
                return response()->json(['message' => $e->getMessage()], 500);
            }

            // the saved cart of the day became this sale
            if ($request->boolean('clear_cart')) {
                DB::table('pos_carts')->where('user_id', Auth::id())->delete();
            }

            return $order->id;

        }, 10);

        return response()->json(['success' => true, 'id' => $item]);

    }

    // 'Y-m-d H:i:s' from the cart, or null
    private function cartTime($value)
    {
        if (empty($value)) {
            return null;
        }
        try {
            return Carbon::parse($value)->format('Y-m-d H:i:s');
        } catch (\Exception $e) {
            return null;
        }
    }

    //------------ Products sold in the POS on one day (list to print) --------------\\

    public function day_products(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'Sales_pos', Sale::class);

        try {
            $date = Carbon::parse($request->get('date') ?: Carbon::today())->toDateString();
        } catch (\Exception $e) {
            $date = Carbon::today()->toDateString();
        }

        $role = Auth::user()->roles()->first();
        $view_records = Role::findOrFail($role->id)->inRole('record_view');
        $warehouse_ids = $this->posWarehouses()->pluck('id')->toArray();

        $sales = DB::table('sales as s')
            ->leftJoin('clients as c', 'c.id', '=', 's.client_id')
            ->whereNull('s.deleted_at')
            ->where('s.is_pos', 1)
            ->where('s.date', $date)
            ->whereIn('s.warehouse_id', $warehouse_ids)
            ->when(!$view_records, function ($q) {
                // without "view all records" a user only sees his own sales
                return $q->where('s.user_id', Auth::id());
            })
            ->orderBy('s.id')
            ->get(['s.id', 's.Ref', 's.GrandTotal', 's.paid_amount', 's.created_at', 'c.name as client']);

        $lines = [];
        if ($sales->count()) {
            $lines = DB::table('sale_details as sd')
                ->join('sales as s', 's.id', '=', 'sd.sale_id')
                ->join('products as p', 'p.id', '=', 'sd.product_id')
                ->leftJoin('product_variants as pv', 'pv.id', '=', 'sd.product_variant_id')
                ->leftJoin('units as u', 'u.id', '=', 'sd.sale_unit_id')
                ->whereIn('sd.sale_id', $sales->pluck('id')->toArray())
                ->orderByRaw('COALESCE(sd.added_at, s.created_at)')
                ->orderBy('sd.id')
                ->get([
                    'sd.sale_id', 's.Ref', 'sd.quantity', 'sd.total', 'sd.imei_number',
                    DB::raw('COALESCE(sd.added_at, s.created_at) AS added_at'), 'sd.confirmed_at',
                    'p.name', 'p.code', 'pv.name as variant', 'pv.code as variant_code', 'u.ShortName as unit',
                ])
                ->map(function ($l) {
                    $quantity = (float) $l->quantity;
                    return [
                        'sale_id' => $l->sale_id,
                        'Ref' => $l->Ref,
                        'time' => $l->added_at ? Carbon::parse($l->added_at)->format('H:i') : '',
                        'confirmed' => $l->confirmed_at ? Carbon::parse($l->confirmed_at)->format('H:i') : null,
                        'name' => $l->variant ? '[' . $l->variant . ']' . $l->name : $l->name,
                        'code' => $l->variant_code ?: $l->code,
                        'quantity' => $quantity,
                        'unit' => $l->unit ?: '',
                        'price' => $quantity ? round($l->total / $quantity, 2) : 0,
                        'total' => (float) $l->total,
                        'imei' => $l->imei_number,
                    ];
                })
                ->values();
        }

        // charges entered with these sales (their description ends with "(SL_xxx)")
        $charges = collect();
        if ($sales->count()) {
            $refs = $sales->pluck('Ref')->toArray();
            $charges = DB::table('expenses as e')
                ->leftJoin('expense_categories as ec', 'ec.id', '=', 'e.expense_category_id')
                ->whereNull('e.deleted_at')
                ->where('e.date', $date)
                ->where(function ($q) use ($refs) {
                    foreach ($refs as $ref) {
                        $q->orWhere('e.details', 'like', '%(' . $ref . ')');
                    }
                })
                ->orderBy('e.id')
                ->get(['e.Ref', 'e.amount', 'e.details', 'ec.name as category']);
        }

        $settings = Setting::where('deleted_at', '=', null)->first();

        return response()->json([
            'date' => $date,
            'company' => ['name' => $settings ? $settings->CompanyName : '', 'logo' => $settings ? $settings->logo : null],
            'sales' => $sales,
            'lines' => $lines,
            'charges' => $charges,
            'totals' => [
                'lines' => count($lines),
                'quantity' => round(collect($lines)->sum('quantity'), 2),
                'amount' => round((float) $sales->sum('GrandTotal'), 2),
                'paid' => round((float) $sales->sum('paid_amount'), 2),
                'charges' => round((float) $charges->sum('amount'), 2),
            ],
        ]);
    }

    // Price of one sale unit in the catalogue, as show_product_data computes it (warehouse price first)
    private function catalogUnitPrice($product_id, $variant_id, $warehouse_id)
    {
        $product = Product::with('unitSale')->where('deleted_at', '=', null)->find($product_id);
        if (!$product) {
            return null;
        }
        $pw = product_warehouse::where('warehouse_id', $warehouse_id)
            ->where('product_id', $product_id)
            ->where('deleted_at', '=', null)
            ->where(function ($q) use ($variant_id) {
                if ($variant_id) {
                    $q->where('product_variant_id', $variant_id);
                } else {
                    $q->whereNull('product_variant_id');
                }
            })
            ->first();

        if ($product->type == 'is_variant') {
            $variant = ProductVariant::find($variant_id);
            $price = $pw ? $pw->price : ($variant ? $variant->price : $product->price);
        } elseif ($product->type == 'is_single') {
            $price = $pw && $pw->price ? $pw->price : $product->price;
        } else {
            $price = $pw ? $pw->price : $product->price;
        }

        if ($product['unitSale'] && $product['unitSale']->operator_value) {
            $price = $product['unitSale']->operator == '/'
                ? $price / $product['unitSale']->operator_value
                : $price * $product['unitSale']->operator_value;
        }
        return (float) $price;
    }

    // Refuses (422) a price the user may not set: allowed are the catalogue price and a price already in the saved cart
    private function posPriceRefused(array $lines, $warehouse_id, $saved_cart)
    {
        if (Auth::user()->pos_edit_price) {
            return null;
        }
        $saved = [];
        foreach ($saved_cart ? (json_decode($saved_cart->lines, true) ?: []) : [] as $old) {
            $saved[] = ($old['product_id'] ?? 0) . '|' . ($old['product_variant_id'] ?? '') . '|' . round((float) ($old['Unit_price'] ?? 0), 2);
        }
        $catalog = [];
        foreach ($lines as $line) {
            if (!is_array($line) || empty($line['product_id'])) {
                continue;
            }
            $variant = $line['product_variant_id'] ?? null;
            $price = round((float) ($line['Unit_price'] ?? 0), 2);
            if (in_array($line['product_id'] . '|' . ($variant ?? '') . '|' . $price, $saved, true)) {
                continue;
            }
            $key = $line['product_id'] . '|' . $variant;
            if (!array_key_exists($key, $catalog)) {
                $catalog[$key] = $this->catalogUnitPrice($line['product_id'], $variant, $warehouse_id);
            }
            if ($catalog[$key] !== null && abs($price - round($catalog[$key], 2)) > 0.01) {
                return response()->json([
                    'message' => 'You are not allowed to change the price of a product.',
                    'code' => 'pos_price_locked',
                ], 422);
            }
        }
        return null;
    }

    // expense categories offered for the charges (Settings > POS settings)
    private function posChargeCategories()
    {
        $settings = PosSetting::where('deleted_at', '=', null)->first();
        $ids = $settings ? $settings->chargeCategoryIds() : null;
        $query = DB::table('expense_categories')->whereNull('deleted_at')->orderBy('name');
        if ($ids !== null) {
            $query->whereIn('id', $ids);
        }
        return $query->get(['id', 'name']);
    }

    //------------ Charges (expenses) entered when confirming the sales of the day --------------\\

    private function createPosCharges(Request $request, Sale $order)
    {
        $expenses = app('App\Http\Controllers\ExpensesController');
        foreach ((array) $request->input('charges', []) as $charge) {
            $details = trim((string) ($charge['details'] ?? ''));
            Expense::create([
                'user_id' => Auth::user()->id,
                'date' => Carbon::now()->toDateString(),
                'Ref' => $expenses->getNumberOrder(),
                'warehouse_id' => $order->warehouse_id,
                'expense_category_id' => (int) $charge['category_id'],
                'account_id' => null,
                // the sale reference keeps the link between the charge and the POS sale
                'details' => mb_substr(($details !== '' ? $details . ' ' : '') . '(' . $order->Ref . ')', 0, 190),
                'amount' => round((float) $charge['amount'], 2),
            ]);
        }
    }

    //------------ Cart saved on the server (one per user, kept until "Confirm sales") --------------\\

    public function get_cart(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'Sales_pos', Sale::class);

        $cart = DB::table('pos_carts')->where('user_id', Auth::id())->first();
        if (!$cart) {
            return response()->json(['cart' => null]);
        }

        $lines = json_decode($cart->lines, true) ?: [];

        // stock may have changed since the line was added: send today's stock (in the sale unit)
        foreach ($lines as &$line) {
            if (($line['product_type'] ?? '') === 'is_service') {
                $line['stock'] = null;
                continue;
            }
            $row = product_warehouse::where('warehouse_id', $cart->warehouse_id)
                ->where('product_id', $line['product_id'] ?? 0)
                ->where('deleted_at', '=', null)
                ->where(function ($q) use ($line) {
                    if (!empty($line['product_variant_id'])) {
                        $q->where('product_variant_id', $line['product_variant_id']);
                    } else {
                        $q->whereNull('product_variant_id');
                    }
                })
                ->first();
            $qte = $row ? (float) $row->qte : 0;
            $unit = !empty($line['sale_unit_id']) ? Unit::find($line['sale_unit_id']) : null;
            if ($unit && $unit->operator_value) {
                $qte = $unit->operator == '/' ? $qte * $unit->operator_value : $qte / $unit->operator_value;
            }
            $line['stock'] = round($qte, 4);
            $line['manage_stock'] = $row ? (int) $row->manage_stock : 1;
        }
        unset($line);

        return response()->json(['cart' => [
            'warehouse_id' => $cart->warehouse_id,
            'client_id' => $cart->client_id,
            'lines' => $lines,
            'started_at' => $cart->started_at,
            'updated_at' => $cart->updated_at,
        ]]);
    }

    public function save_cart(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'Sales_pos', Sale::class);

        request()->validate([
            'lines' => 'present|array|max:500',
            'warehouse_id' => 'nullable|integer',
            'client_id' => 'nullable|integer',
        ]);

        $user_id = Auth::id();
        $existing = DB::table('pos_carts')->where('user_id', $user_id)->first();

        // keep only the fields the POS needs to rebuild a line
        $keep = ['uid', 'confirmed', 'added_at', 'confirmed_at', 'product_id', 'product_variant_id', 'name', 'code', 'image', 'product_type', 'unitSale', 'sale_unit_id',
            'is_imei', 'imei_number', 'Unit_price', 'base_price', 'tax_percent', 'tax_method', 'discount', 'discount_Method', 'quantity'];
        $lines = [];
        foreach ($request->lines as $line) {
            if (!is_array($line) || empty($line['product_id'])) {
                continue;
            }
            $lines[] = array_intersect_key($line, array_flip($keep));
        }

        // confirmed lines are locked: they must stay, unchanged, until "Confirm sales"
        // (except for users allowed to edit confirmed products)
        if ($existing && !Auth::user()->pos_edit_confirmed) {
            $incoming = [];
            foreach ($lines as $line) {
                if (!empty($line['uid'])) {
                    $incoming[$line['uid']] = $line;
                }
            }
            foreach (json_decode($existing->lines, true) ?: [] as $old) {
                if (empty($old['confirmed']) || empty($old['uid'])) {
                    continue;
                }
                $new = $incoming[$old['uid']] ?? null;
                $same = $new
                    && !empty($new['confirmed'])
                    && abs((float) ($new['quantity'] ?? 0) - (float) ($old['quantity'] ?? 0)) < 0.0001
                    && abs((float) ($new['Unit_price'] ?? 0) - (float) ($old['Unit_price'] ?? 0)) < 0.0001
                    && (string) ($new['imei_number'] ?? '') === (string) ($old['imei_number'] ?? '');
                if (!$same) {
                    return response()->json([
                        'message' => 'A confirmed product cannot be changed or removed.',
                        'code' => 'pos_line_locked',
                    ], 422);
                }
            }
        }

        // users without "change the price" keep the catalogue price (or a price already saved in the cart)
        if ($bad = $this->posPriceRefused($lines, $request->warehouse_id, $existing)) {
            return $bad;
        }

        // times are set by the server: added when the line first appears, confirmed when it is locked;
        // a time already saved never changes
        $saved = [];
        foreach ($existing ? (json_decode($existing->lines, true) ?: []) : [] as $old) {
            if (!empty($old['uid'])) {
                $saved[$old['uid']] = $old;
            }
        }
        $stamp = Carbon::now()->format('Y-m-d H:i:s');
        $stamps = [];
        foreach ($lines as &$line) {
            $old = !empty($line['uid']) ? ($saved[$line['uid']] ?? null) : null;
            $line['added_at'] = $old['added_at'] ?? $stamp;
            $line['confirmed_at'] = !empty($line['confirmed'])
                ? (!empty($old['confirmed']) && !empty($old['confirmed_at']) ? $old['confirmed_at'] : $stamp)
                : null;
            if (!empty($line['uid'])) {
                $stamps[$line['uid']] = ['added_at' => $line['added_at'], 'confirmed_at' => $line['confirmed_at']];
            }
        }
        unset($line);

        if (!count($lines)) {
            DB::table('pos_carts')->where('user_id', $user_id)->delete();
            return response()->json(['success' => true, 'cart' => null]);
        }

        $now = Carbon::now();
        $values = [
            'warehouse_id' => $request->warehouse_id,
            'client_id' => $request->client_id,
            'lines' => json_encode($lines),
            'updated_at' => $now,
        ];
        if ($existing) {
            DB::table('pos_carts')->where('user_id', $user_id)->update($values);
            $started_at = $existing->started_at;
        } else {
            $started_at = $now;
            DB::table('pos_carts')->insert($values + ['user_id' => $user_id, 'started_at' => $now, 'created_at' => $now]);
        }

        return response()->json(['success' => true, 'started_at' => (string) $started_at, 'updated_at' => (string) $now, 'stamps' => $stamps]);
    }

    //------------- get_draft_sales -----------\\

    public function get_draft_sales(request $request)
    {
        $this->authorizeForUser($request->user('api'), 'Sales_pos', Sale::class);
        $role = Auth::user()->roles()->first();
        $view_records = Role::findOrFail($role->id)->inRole('record_view');
        // How many items do you want to display.
        $perPage = $request->limit;

        $pageStart = \Request::get('page', 1);
        // Start displaying items from this number;
        $offSet = ($pageStart * $perPage) - $perPage;
        $order = 'id';
        $dir = 'DESC';
        $helpers = new helpers();
        
        $data = array();

        // Check If User Has Permission View  All Records
        $draft_sales = DraftSale::with('client', 'warehouse','user')
            ->where('deleted_at', '=', null)
            ->where(function ($query) use ($view_records) {
                if (!$view_records) {
                    return $query->where('user_id', '=', Auth::user()->id);
                }
            });
    

        $totalRows = $draft_sales->count();
        if($perPage == "-1"){
            $perPage = $totalRows;
        }
        
        $drafts = $draft_sales->offset($offSet)
            ->limit($perPage)
            ->orderBy($order, $dir)
            ->get();

        foreach ($drafts as $draft) {
            
            $item['id'] = $draft['id'];
            $item['date'] = $draft['date'];
            $item['Ref'] = $draft['Ref'];
            $item['warehouse_name'] = $draft['warehouse']['name'];
            $item['client_name'] = $draft['client']['name'];
            $item['GrandTotal'] = number_format($draft['GrandTotal'], 2, '.', '');
            $item['actions'] = '';
            
            $data[] = $item;
        }
        
    
        return response()->json([
            'totalRows' => $totalRows,
            'draft_sales' => $data,
        ]);
    }
  

    //------------ Create Draft --------------\\

    public function CreateDraft(Request $request)
    {
    //   dd($request->all());
        $this->authorizeForUser($request->user('api'), 'Sales_pos', Sale::class);

        request()->validate([
            'client_id' => 'required',
            'warehouse_id' => 'required',
        ]);
        $this->checkPosAccess($request);

        \DB::transaction(function () use ($request) {
            $helpers = new helpers();
            $role = Auth::user()->roles()->first();
            $view_records = Role::findOrFail($role->id)->inRole('record_view');
            $order = new DraftSale;

            $order->date = Carbon::now();
            $order->Ref = $this->getNumberOrderDraft();
            $order->client_id = $request->client_id;
            $order->warehouse_id = $request->warehouse_id;
            $order->tax_rate = $request->tax_rate;
            $order->TaxNet = $request->TaxNet;
            $order->discount = $request->discount;
            $order->shipping = $request->shipping;
            $order->GrandTotal = $request->GrandTotal;
            $order->user_id = Auth::user()->id;

            $order->save();

            $data = $request['details'];
            foreach ($data as $key => $value) {

                $unit = Unit::where('id', $value['sale_unit_id'])->first();
                $orderDetails[] = [
                'date' => Carbon::now(),
                'draft_sale_id' => $order->id,
                'sale_unit_id' =>  $value['sale_unit_id'],
                'quantity' => $value['quantity'],
                'product_id' => $value['product_id'],
                'product_variant_id' => $value['product_variant_id'],
                'total' => $value['subtotal'],
                'price' => $value['Unit_price'],
                'TaxNet' => $value['tax_percent'],
                'tax_method' => $value['tax_method'],
                'discount' => $value['discount'],
                'discount_method' => $value['discount_Method'],
                'imei_number' => $value['imei_number'],
                ];
            }

            DraftSaleDetail::insert($orderDetails);

        }, 10);

        return response()->json(['success' => true]);
    }

     //------------ remove_draft_sale -------------\\

     public function remove_draft_sale(Request $request, $id)
     {
        $this->authorizeForUser($request->user('api'), 'Sales_pos', Sale::class);
 
         \DB::transaction(function () use ($id, $request) {
 
             $role = Auth::user()->roles()->first();
             $view_records = Role::findOrFail($role->id)->inRole('record_view');
             $draft = DraftSale::findOrFail($id);
 
             // Check If User Has Permission view All Records
             if (!$view_records) {
                 // Check If User->id === draft->id
                 $this->authorizeForUser($request->user('api'), 'check_record', $draft);
             }
             $draft->details()->delete();
             $draft->update([
                 'deleted_at' => Carbon::now(),
             ]);
 
         }, 10);
 
         return response()->json(['success' => true]);
     }

      //------------ submit_sale_from_draft --------------\\

    public function submit_sale_from_draft(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'Sales_pos', Sale::class);

        request()->validate([
            'client_id' => 'required',
            'warehouse_id' => 'required',
            'payment.amount' => 'required',
        ]);
        $this->checkPosAccess($request);

        $draft = DraftSale::findOrFail($request['draft_sale_id']);
        if($draft){

            $item = \DB::transaction(function () use ($request, $draft) {
                $helpers = new helpers();
                $role = Auth::user()->roles()->first();
                $view_records = Role::findOrFail($role->id)->inRole('record_view');
                $order = new Sale;

                $order->is_pos = 1;
                $order->date = Carbon::now();
                $order->Ref = app('App\Http\Controllers\SalesController')->getNumberOrder();
                $order->client_id = $request->client_id;
                $order->warehouse_id = $request->warehouse_id;
                $order->tax_rate = $request->tax_rate;
                $order->TaxNet = $request->TaxNet;
                $order->discount = $request->discount;
                $order->shipping = $request->shipping;
                $order->GrandTotal = $request->GrandTotal;
                $order->notes = $request->notes;
                $order->statut = 'completed';
                $order->payment_statut = 'unpaid';
                $order->user_id = Auth::user()->id;

                $order->save();

                $data = $request['details'];
                foreach ($data as $key => $value) {

                    $unit = Unit::where('id', $value['sale_unit_id'])
                        ->first();
                    $orderDetails[] = [
                        'date' => Carbon::now(),
                        'sale_id' => $order->id,
                        'sale_unit_id' =>  $value['sale_unit_id'],
                        'quantity' => $value['quantity'],
                        'product_id' => $value['product_id'],
                        'product_variant_id' => $value['product_variant_id'],
                        'total' => $value['subtotal'],
                        'price' => $value['Unit_price'],
                        'TaxNet' => $value['tax_percent'],
                        'tax_method' => $value['tax_method'],
                        'discount' => $value['discount'],
                        'discount_method' => $value['discount_Method'],
                        'imei_number' => $value['imei_number'],
                    ];

                    if ($value['product_variant_id'] !== null) {
                        $product_warehouse = product_warehouse::where('warehouse_id', $order->warehouse_id)
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
                        $product_warehouse = product_warehouse::where('warehouse_id', $order->warehouse_id)
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

                SaleDetail::insert($orderDetails);

                $sale = Sale::findOrFail($order->id);
                // Check If User Has Permission view All Records
                if (!$view_records) {
                    // Check If User->id === sale->id
                    $this->authorizeForUser($request->user('api'), 'check_record', $sale);
                }

                try {

                    $total_paid = $sale->paid_amount + $request['amount'];
                    $due = $sale->GrandTotal - $total_paid;

                    if ($due === 0.0 || $due < 0.0) {
                        $payment_statut = 'paid';
                    } else if ($due != $sale->GrandTotal) {
                        $payment_statut = 'partial';
                    } else if ($due == $sale->GrandTotal) {
                        $payment_statut = 'unpaid';
                    }
                                
                    if($request['amount'] > 0){
                        if ($request->payment['Reglement'] == 'credit card') {
                            $Client = Client::whereId($request->client_id)->first();
                            Stripe\Stripe::setApiKey(config('app.STRIPE_SECRET'));

                            // Check if the payment record exists
                            $PaymentWithCreditCard = PaymentWithCreditCard::where('customer_id', $request->client_id)->first();
                            if (!$PaymentWithCreditCard) {

                                // Create a new customer and charge the customer with a new credit card
                                $customer = \Stripe\Customer::create([
                                    'source' => $request->token,
                                    'email'  => $Client->email,
                                    'name'   => $Client->name,
                                ]);

                                // Charge the Customer instead of the card:
                                $charge = \Stripe\Charge::create([
                                    'amount'   => $request['amount'] * 100,
                                    'currency' => 'usd',
                                    'customer' => $customer->id,
                                ]);
                                $PaymentCard['customer_stripe_id'] = $customer->id;

                            // Check if the payment record not exists
                            } else {

                                // Retrieve the customer ID and card ID
                                $customer_id = $PaymentWithCreditCard->customer_stripe_id;
                                $card_id = $request->card_id;

                                // Charge the customer with the new credit card or the selected card
                                if ($request->is_new_credit_card || $request->is_new_credit_card == 'true' || $request->is_new_credit_card === 1) {
                                    // Retrieve the customer
                                    $customer = \Stripe\Customer::retrieve($customer_id);

                                    // Create New Source
                                    $card = \Stripe\Customer::createSource(
                                        $customer_id,
                                        [
                                        'source' => $request->token,
                                        ]
                                    );

                                    $charge = \Stripe\Charge::create([
                                        'amount'   => $request['amount'] * 100,
                                        'currency' => 'usd',
                                        'customer' => $customer_id,
                                        'source'   => $card->id,
                                    ]);
                                    $PaymentCard['customer_stripe_id'] = $customer_id;

                                } else {
                                    $charge = \Stripe\Charge::create([
                                        'amount'   => $request['amount'] * 100,
                                        'currency' => 'usd',
                                        'customer' => $customer_id,
                                        'source'   => $card_id,
                                    ]);
                                    $PaymentCard['customer_stripe_id'] = $customer_id;
                                }
                            }

                            $PaymentSale            = new PaymentSale();
                            $PaymentSale->sale_id   = $order->id;
                            $PaymentSale->Ref       = app('App\Http\Controllers\PaymentSalesController')->getNumberOrder();
                            $PaymentSale->date      = Carbon::now();
                            $PaymentSale->Reglement = $request->payment['Reglement'];
                            $PaymentSale->montant   = $request['amount'];
                            $PaymentSale->change    = $request['change'];
                            $PaymentSale->notes     = $request->payment['notes'];
                            $PaymentSale->user_id   = Auth::user()->id;
                            $PaymentSale->account_id   = $request->payment['account_id']?$request->payment['account_id']:NULL;

                            $PaymentSale->save();

                            $account = Account::where('id', $request->payment['account_id'])->exists();

                            if ($account) {
                                // Account exists, perform the update
                                $account = Account::find($request->payment['account_id']);
                                $account->update([
                                    'balance' => $account->balance + $request['amount'],
                                ]);
                            }

                            $sale->update([
                                'paid_amount'    => $total_paid,
                                'payment_statut' => $payment_statut,
                            ]);

                            $PaymentCard['customer_id'] = $request->client_id;
                            $PaymentCard['payment_id']  = $PaymentSale->id;
                            $PaymentCard['charge_id']   = $charge->id;
                            PaymentWithCreditCard::create($PaymentCard);

                            // Paying Method Cash
                        } else {

                            PaymentSale::create([
                                'sale_id' => $order->id,
                                'account_id' => $request->payment['account_id']?$request->payment['account_id']:NULL,
                                'Ref' => app('App\Http\Controllers\PaymentSalesController')->getNumberOrder(),
                                'date' => Carbon::now(),
                                'Reglement' => $request->payment['Reglement'],
                                'montant' => $request['amount'],
                                'change' => $request['change'],
                                'notes' => $request->payment['notes'],
                                'user_id' => Auth::user()->id,
                            ]);

                            $account = Account::where('id', $request->payment['account_id'])->exists();

                            if ($account) {
                                // Account exists, perform the update
                                $account = Account::find($request->payment['account_id']);
                                $account->update([
                                    'balance' => $account->balance + $request['amount'],
                                ]);
                            }

                            $sale->update([
                                'paid_amount' => $total_paid,
                                'payment_statut' => $payment_statut,
                            ]);
                        }

                    }
                
                } catch (Exception $e) {
                    return response()->json(['message' => $e->getMessage()], 500);
                }

                $draft->details()->delete();
                $draft->update([
                    'deleted_at' => Carbon::now(),
                ]);

                return $order->id;

            }, 10);

            return response()->json(['success' => true, 'id' => $item]);
        }else{
            return response()->json(['success' => false], 404);
        }

    }

    //--------------------- data_draft_convert_sale ------------------------\\

    public function data_draft_convert_sale(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'Sales_pos', Sale::class);
        $clients = $this->posClientsList();
        $settings = Setting::where('deleted_at', '=', null)->with('Client')->first();
        $accounts = Account::where('deleted_at', '=', null)->orderBy('id', 'desc')->get(['id','account_name']);

        $draft_sale_data = DraftSale::with('details.product.unitSale')->where('deleted_at', '=', null)->findOrFail($id);

          //get warehouses assigned to user
          $user_auth = auth()->user();
          if($user_auth->is_all_warehouses){
             $warehouses = Warehouse::where('deleted_at', '=', null)->get(['id', 'name']);

          }else{
             $warehouses_id = UserWarehouse::where('user_id', $user_auth->id)->pluck('warehouse_id')->toArray();
             $warehouses = Warehouse::where('deleted_at', '=', null)->whereIn('id', $warehouses_id)->get(['id', 'name']);
          }
        

        if ($draft_sale_data->client_id) {
            if (Client::where('id', $draft_sale_data->client_id)->where('deleted_at', '=', null)->first()) {
                $sale['client_id'] = $draft_sale_data->client_id;
                $client_name = $draft_sale_data['Client']->name;
            } else {
                $sale['client_id'] = '';
                $client_name = '';
            }
        } else {
            $sale['client_id'] = '';
            $client_name = '';
        }

        if ($draft_sale_data->warehouse_id) {
            if (Warehouse::where('id', $draft_sale_data->warehouse_id)->where('deleted_at', '=', null)->first()) {
                $sale['warehouse_id'] = $draft_sale_data->warehouse_id;
            } else {
                $sale['warehouse_id'] = '';
            }
        } else {
            $sale['warehouse_id'] = '';
        }

        $sale['tax_rate'] = $draft_sale_data->tax_rate;
        $sale['TaxNet']   = $draft_sale_data->TaxNet;
        $sale['discount'] = $draft_sale_data->discount;
        $sale['shipping'] = $draft_sale_data->shipping;
        $GrandTotal = $draft_sale_data->GrandTotal;

        $detail_id = 0;
        foreach ($draft_sale_data['details'] as $detail) {

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
                }{
                    $unit = NULL;
                }

                $data['no_unit'] = 0;
            }

    
            if ($detail->product_variant_id) {
                $item_product = product_warehouse::where('product_id', $detail->product_id)
                    ->where('deleted_at', '=', null)
                    ->where('product_variant_id', $detail->product_variant_id)
                    ->where('warehouse_id', $draft_sale_data->warehouse_id)
                    ->first();

                $productsVariants = ProductVariant::where('product_id', $detail->product_id)
                    ->where('id', $detail->product_variant_id)->first();

                $item_product ? $data['del'] = 0 : $data['del'] = 1;
                $data['product_variant_id'] = $detail->product_variant_id;
                $data['code'] = $productsVariants->code;
                $data['name'] = '['.$productsVariants->name . ']' . $detail['product']['name'];
                
                if ($unit && $unit->operator == '/') {
                $stock = $item_product ? $item_product->qte * $unit->operator_value : 0;
                } else if ($unit && $unit->operator == '*') {
                $stock = $item_product ? $item_product->qte / $unit->operator_value : 0;
                } else {
                $stock = 0;
                }

            } else {
                $item_product = product_warehouse::where('product_id', $detail->product_id)
                    ->where('deleted_at', '=', null)->where('warehouse_id', $draft_sale_data->warehouse_id)
                    ->where('product_variant_id', '=', null)->first();

                $item_product ? $data['del'] = 0 : $data['del'] = 1;
                $data['product_variant_id'] = null;
                $data['code'] = $detail['product']['code'];
                $data['name'] = $detail['product']['name'];

                if ($unit && $unit->operator == '/') {
                    $stock= $item_product ? $item_product->qte * $unit->operator_value : 0;
                } else if ($unit && $unit->operator == '*') {
                $stock = $item_product ? $item_product->qte / $unit->operator_value : 0;
                } else {
                $stock = 0;
                }

            }
            
            $data['id'] = $detail->id;
            $data['fix_stock'] = $detail['product']['type'] !='is_service'?$stock:'---';
            $data['current'] = $detail['product']['type'] !='is_service'?$stock:'---';

            $data['product_type'] = $detail['product']['type'];
            $data['detail_id'] = $detail_id += 1;
            $data['product_id'] = $detail->product_id;
            $data['total'] = $detail->total;
            $data['quantity'] = $detail->quantity;
            $data['qte_copy'] = $detail->quantity;
            $data['etat'] = 'current';
            $data['unitSale'] = $unit?$unit->ShortName:'';
            $data['sale_unit_id'] = $unit?$unit->id:'';
            $data['is_imei'] = $detail['product']['is_imei'];
            $data['imei_number'] = $detail->imei_number;
            $data['subtotal'] = $detail->total;

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
                $data['Total_price'] = $data['Net_price'] +  $data['taxe'];
            } else {
                $data['Net_price'] = ($detail->price - $data['DiscountNet']) / (($detail->TaxNet / 100) + 1);
                $data['taxe'] = $detail->price - $data['Net_price'] - $data['DiscountNet'];
                $data['Total_price'] = $data['Net_price'] +  $data['taxe'];
            }

            $details[] = $data;
        }
        
        $categories = Category::where('deleted_at', '=', null)->get(['id', 'name']);
        $brands = Brand::where('deleted_at', '=', null)->get();
        $stripe_key = config('app.STRIPE_KEY');

        return response()->json([
            'stripe_key'     => $stripe_key,
            'brands'         => $brands,
            'warehouse_id'   => $sale['warehouse_id'],
            'client_id'      => $sale['client_id'],
            'client_name'    => $client_name,
            'clients'        => $clients,
            'warehouses'     => $warehouses,
            'categories'     => $categories,
            'accounts'       => $accounts,
            'sale'           => $sale,
            'GrandTotal'           => $GrandTotal,
            'details'        => $details,
        ]);
    }
 

    //------------ Get Products--------------\\

    public function GetProductsByParametre(request $request)
    {
        $this->authorizeForUser($request->user('api'), 'Sales_pos', Sale::class);
        // How many items do you want to display.
        // Optional page size (new POS screen); defaults to 8 as before
        $perPage = (int) $request->get('per_page', 8);
        if ($perPage < 1 || $perPage > 100) {
            $perPage = 8;
        }
        $pageStart = \Request::get('page', 1);
        // Start displaying items from this number;
        $offSet = ($pageStart * $perPage) - $perPage;
        $data = array();

        $product_warehouse_data = product_warehouse::where('warehouse_id', $request->warehouse_id)
            ->with('product', 'product.unitSale')
            ->where('deleted_at', '=', null)
            ->where(function ($query) use ($request) {
                return $query->whereHas('product', function ($q) use ($request) {
                    $q->where('not_selling', '=', 0);
                })
                ->where(function ($query) use ($request) {
                    if ($request->stock == '1' && $request->product_service == '1') {
                        return $query->where('qte', '>', 0)->orWhere('manage_stock', false);
    
                    }elseif($request->stock == '1' && $request->product_service == '0') {
                        return $query->where('qte', '>', 0)->orWhere('manage_stock', true);
    
                    }else{
                        return $query->where('manage_stock', true);
                    }
                });
            })

        // Filter
            ->where(function ($query) use ($request) {
                return $query->when($request->filled('category_id'), function ($query) use ($request) {
                    return $query->whereHas('product', function ($q) use ($request) {
                        $q->where('category_id', '=', $request->category_id);
                    });
                });
            })
            ->where(function ($query) use ($request) {
                return $query->when($request->filled('brand_id'), function ($query) use ($request) {
                    return $query->whereHas('product', function ($q) use ($request) {
                        $q->where('brand_id', '=', $request->brand_id);
                    });
                });
            })
            // Hide soft-deleted products
            ->whereHas('product', function ($q) {
                $q->whereNull('deleted_at');
            })
            // Search by product name / code or variant code (new POS screen)
            ->where(function ($query) use ($request) {
                return $query->when($request->filled('search'), function ($query) use ($request) {
                    $search = trim($request->search);
                    return $query->where(function ($query) use ($search) {
                        $query->whereHas('product', function ($q) use ($search) {
                            $q->where('name', 'LIKE', "%{$search}%")
                                ->orWhere('code', 'LIKE', "%{$search}%");
                        })->orWhereHas('productVariant', function ($q) use ($search) {
                            $q->where('code', 'LIKE', "%{$search}%")
                                ->orWhere('name', 'LIKE', "%{$search}%");
                        });
                    });
                });
            });

        $totalRows = $product_warehouse_data->count();

        $product_warehouse_data = $product_warehouse_data
            ->orderBy('id', 'desc')
            ->offset($offSet)
            ->limit($perPage)
            ->get();

        foreach ($product_warehouse_data as $product_warehouse) {
            if ($product_warehouse->product_variant_id) {
                $productsVariants = ProductVariant::where('product_id', $product_warehouse->product_id)
                    ->where('id', $product_warehouse->product_variant_id)
                    ->where('deleted_at', null)
                    ->first();

                $item['product_variant_id'] = $product_warehouse->product_variant_id;
                $item['Variant'] = '['.$productsVariants->name . ']' . $product_warehouse['product']->name;
                $item['name'] = '['.$productsVariants->name . ']' . $product_warehouse['product']->name;

                $item['code'] = $productsVariants->code;
                $item['barcode'] = $productsVariants->code;

                // Use warehouse-specific price if available, otherwise use variant price
                $product_price = $product_warehouse->price ?: $product_warehouse['productVariant']->price;

            } else {
                $item['product_variant_id'] = null;
                $item['Variant'] = null;
                $item['code'] = $product_warehouse['product']->code;
                $item['name'] = $product_warehouse['product']->name;
                $item['barcode'] = $product_warehouse['product']->code;

                // Use warehouse-specific price if available, otherwise use global product price
                $product_price = $product_warehouse->price ?: $product_warehouse['product']->price;

            }
            $item['id'] = $product_warehouse->product_id;
            $firstimage = explode(',', $product_warehouse['product']->image);
            $item['image'] = $firstimage[0];

            if($product_warehouse['product']['unitSale']){

                if ($product_warehouse['product']['unitSale']->operator == '/') {
                    $item['qte_sale'] = $product_warehouse->qte * $product_warehouse['product']['unitSale']->operator_value;
                    $price = $product_price / $product_warehouse['product']['unitSale']->operator_value;

                } else {
                    $item['qte_sale'] = $product_warehouse->qte / $product_warehouse['product']['unitSale']->operator_value;
                    $price = $product_price * $product_warehouse['product']['unitSale']->operator_value;

                }

            }else{
                $item['qte_sale'] = $product_warehouse['product']->type!='is_service'?$product_warehouse->qte:'---';
                $price = $product_price;
            }

            $item['unitSale'] = $product_warehouse['product']['unitSale']?$product_warehouse['product']['unitSale']->ShortName:'';
            $item['qte'] = $product_warehouse['product']->type!='is_service'?$product_warehouse->qte:'---';
            $item['product_type'] = $product_warehouse['product']->type;
            $item['manage_stock'] = $product_warehouse->manage_stock;
            $item['is_imei'] = $product_warehouse['product']->is_imei;
            
            if ($product_warehouse['product']->TaxNet !== 0.0) {

                //Exclusive
                if ($product_warehouse['product']->tax_method == '1') {
                    $tax_price = $price * $product_warehouse['product']->TaxNet / 100;

                    $item['Net_price'] = $price + $tax_price;

                    // Inxclusive
                } else {
                    $item['Net_price'] = $price;
                }
            } else {
                $item['Net_price'] = $price;
            }

            $data[] = $item;
        }

        return response()->json([
            'products' => $data,
            'totalRows' => $totalRows,
        ]);
    }

    //--------------------- Get Element POS ------------------------\\

    public function GetELementPos(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'Sales_pos', Sale::class);
        // only the customers this user may sell to (POS settings of the user)
        $clients = $this->posClientsList();
        $settings = Setting::where('deleted_at', '=', null)->with('Client')->first();
        $accounts = Account::where('deleted_at', '=', null)->orderBy('id', 'desc')->get(['id','account_name']);

          //get warehouses assigned to user
          $user_auth = auth()->user();
          if($user_auth->is_all_warehouses){
             $warehouses = Warehouse::where('deleted_at', '=', null)->get(['id', 'name']);

             if ($settings->warehouse_id) {
                if (Warehouse::where('id', $settings->warehouse_id)->where('deleted_at', '=', null)->first()) {
                    $defaultWarehouse = $settings->warehouse_id;
                } else {
                    $defaultWarehouse = '';
                }
            } else {
                $defaultWarehouse = '';
            }

          }else{
             $warehouses_id = UserWarehouse::where('user_id', $user_auth->id)->pluck('warehouse_id')->toArray();
             $warehouses = Warehouse::where('deleted_at', '=', null)->whereIn('id', $warehouses_id)->get(['id', 'name']);

             if ($settings->warehouse_id) {
                if (Warehouse::where('id', $settings->warehouse_id)->whereIn('id', $warehouses_id)->where('deleted_at', '=', null)->first()) {
                    $defaultWarehouse = $settings->warehouse_id;
                } else {
                    $defaultWarehouse = '';
                }
            } else {
                $defaultWarehouse = '';
            }
          }


      
        

        if ($settings->client_id) {
            if (Client::where('id', $settings->client_id)->where('deleted_at', '=', null)->first()) {
                $defaultClient = $settings->client_id;
                $default_client_name = $settings['Client']->name;
            } else {
                $defaultClient = '';
                $default_client_name = '';
            }
        } else {
            $defaultClient = '';
            $default_client_name = '';
        }

        // POS settings of the user override the company defaults
        $allowed_clients = $this->posAllowedClientIds();
        if ($user_auth->pos_default_client_id && $clients->contains('id', $user_auth->pos_default_client_id)) {
            $defaultClient = $user_auth->pos_default_client_id;
        } elseif ($allowed_clients !== null && !$clients->contains('id', $defaultClient)) {
            $defaultClient = $clients->count() ? $clients->first()->id : '';
        }
        $default_client = $clients->firstWhere('id', $defaultClient);
        $default_client_name = $default_client ? $default_client->name : $default_client_name;

        $pos_warehouse = $user_auth->pos_default_warehouse_id && $warehouses->contains('id', $user_auth->pos_default_warehouse_id)
            ? $user_auth->pos_default_warehouse_id : null;

        $categories = Category::where('deleted_at', '=', null)->get(['id', 'name']);
        $brands = Brand::where('deleted_at', '=', null)->get();
        $stripe_key = config('app.STRIPE_KEY');

        return response()->json([
            'stripe_key' => $stripe_key,
            'brands' => $brands,
            'defaultWarehouse' => $defaultWarehouse,
            'defaultClient' => $defaultClient,
            'default_client_name' => $default_client_name,
            'clients' => $clients,
            'warehouses' => $warehouses,
            'categories' => $categories,
            'accounts'           => $accounts,
            // new POS screen
            'pos_warehouse' => $pos_warehouse,
            'client_locked' => $allowed_clients !== null && $clients->count() == 1,
            'can_add_client' => $allowed_clients === null,
            // cart permissions of the user (People > Users > Point of sale)
            'can_edit_price' => (bool) $user_auth->pos_edit_price,
            'can_edit_confirmed' => (bool) $user_auth->pos_edit_confirmed,
            // categories for the charges entered when confirming the sales
            'expense_categories' => $this->posChargeCategories(),
        ]);
    }


    //------------ Sale ready to print as a ticket or an A5 invoice (new POS screen) --------------\\

    public function sale_print(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'Sales_pos', Sale::class);

        $sale = Sale::with('details.product.unitSale', 'client', 'warehouse', 'user')
            ->where('deleted_at', '=', null)
            ->findOrFail($id);

        // users without "view all records" only print their own sales
        $role = Auth::user()->roles()->first();
        $view_records = Role::findOrFail($role->id)->inRole('record_view');
        if (!$view_records) {
            $this->authorizeForUser($request->user('api'), 'check_record', $sale);
        }

        $settings = Setting::where('deleted_at', '=', null)->first();
        $pos_settings = PosSetting::where('deleted_at', '=', null)->first();

        $details = [];
        foreach ($sale['details'] as $detail) {
            $unit = $detail->sale_unit_id ? Unit::where('id', $detail->sale_unit_id)->first() : null;
            if ($detail->product_variant_id) {
                $variant = ProductVariant::where('id', $detail->product_variant_id)->first();
                $name = '[' . ($variant ? $variant->name : '') . ']' . $detail['product']['name'];
                $code = $variant ? $variant->code : $detail['product']['code'];
            } else {
                $name = $detail['product']['name'];
                $code = $detail['product']['code'];
            }
            $quantity = (float) $detail->quantity;
            $details[] = [
                'name' => $name,
                'code' => $code,
                'quantity' => $quantity,
                'unit' => $unit ? $unit->ShortName : '',
                // price actually paid per unit (after line discount, with line tax)
                'price' => $quantity ? round($detail->total / $quantity, 2) : (float) $detail->price,
                'total' => (float) $detail->total,
                'imei' => $detail->imei_number,
            ];
        }

        $payments = PaymentSale::where('sale_id', $sale->id)
            ->where('deleted_at', '=', null)
            ->orderBy('id')
            ->get(['date', 'Reglement', 'montant', 'change']);

        return response()->json([
            'company' => [
                'name' => $settings ? $settings->CompanyName : '',
                'phone' => $settings ? $settings->CompanyPhone : '',
                'address' => $settings ? $settings->CompanyAdress : '',
                'email' => $settings ? $settings->email : '',
                'logo' => $settings ? $settings->logo : null,
                'invoice_footer' => $settings && $settings->is_invoice_footer ? $settings->invoice_footer : null,
            ],
            // receipt options from Settings > POS settings
            'pos_settings' => $pos_settings ? [
                'note_customer' => $pos_settings->note_customer,
                'show_note' => (int) $pos_settings->show_note,
                'show_barcode' => (int) $pos_settings->show_barcode,
                'show_discount' => (int) $pos_settings->show_discount,
                'show_customer' => (int) $pos_settings->show_customer,
                'show_email' => (int) $pos_settings->show_email,
                'show_phone' => (int) $pos_settings->show_phone,
                'show_address' => (int) $pos_settings->show_address,
                'show_warehouse' => (int) $pos_settings->show_Warehouse,
            ] : null,
            'sale' => [
                'id' => $sale->id,
                'Ref' => $sale->Ref,
                'date' => $sale->date,
                'time' => $sale->created_at ? Carbon::parse($sale->created_at)->format('H:i') : null,
                'seller' => $sale['user'] ? $sale['user']->username : '',
                'warehouse' => $sale['warehouse'] ? $sale['warehouse']->name : '',
                // the customer's phone number is never printed
                'client' => [
                    'name' => $sale['client'] ? $sale['client']->name : '',
                    'email' => $sale['client'] ? $sale['client']->email : '',
                    'address' => $sale['client'] ? $sale['client']->adresse : '',
                ],
                'subtotal' => (float) array_sum(array_column($details, 'total')),
                'tax_rate' => (float) $sale->tax_rate,
                'TaxNet' => (float) $sale->TaxNet,
                'discount' => (float) $sale->discount,
                'shipping' => (float) $sale->shipping,
                'GrandTotal' => (float) $sale->GrandTotal,
                'paid_amount' => (float) $sale->paid_amount,
                'due' => max(0, round($sale->GrandTotal - $sale->paid_amount, 2)),
                'payment_status' => $sale->payment_statut,
                'notes' => $sale->notes,
            ],
            'details' => $details,
            'payments' => $payments,
        ]);
    }

    //------------ Customers the current user may sell to in the POS --------------\\

    // null = every customer, otherwise the ids chosen in the user's POS settings
    private function posAllowedClientIds()
    {
        $user = auth()->user();
        if ($user->pos_all_clients === null || (int) $user->pos_all_clients === 1) {
            return null;
        }
        $ids = $user->posClients()->where('clients.deleted_at', '=', null)->pluck('clients.id')->toArray();
        return count($ids) ? $ids : null;
    }

    private function posClientsList()
    {
        $ids = $this->posAllowedClientIds();
        $query = Client::where('deleted_at', '=', null);
        if ($ids !== null) {
            $query->whereIn('id', $ids);
        }
        return $query->get(['id', 'name']);
    }

    // Stops the request when the customer or the warehouse is not allowed for the current user
    private function checkPosAccess(Request $request)
    {
        $ids = $this->posAllowedClientIds();
        if ($ids !== null && !in_array((int) $request->client_id, $ids)) {
            abort(response()->json([
                'message' => 'This customer is not allowed for your account in the POS.',
                'code' => 'pos_client_not_allowed',
            ], 422));
        }
        if ($request->filled('warehouse_id') && !$this->posWarehouses()->contains('id', (int) $request->warehouse_id)) {
            abort(response()->json([
                'message' => 'This warehouse is not assigned to your account.',
                'code' => 'pos_warehouse_not_allowed',
            ], 422));
        }
    }

    //------------ Warehouses the current user can see (new POS screen) --------------\\

    private function posWarehouses()
    {
        $user_auth = auth()->user();
        $query = Warehouse::where('deleted_at', '=', null);
        if (!$user_auth->is_all_warehouses) {
            $query->whereIn('id', UserWarehouse::where('user_id', $user_auth->id)->pluck('warehouse_id')->toArray());
        }
        return $query->get(['id', 'name']);
    }

    //------------ Product lookup: search every product, in stock or not (new POS screen) --------------\\

    public function product_lookup(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'Sales_pos', Sale::class);

        $search = trim((string) $request->search);
        if ($search === '') {
            return response()->json(['products' => []]);
        }

        $products = Product::with('unit')->where('deleted_at', '=', null)
            ->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('code', 'LIKE', "%{$search}%")
                    ->orWhereIn('id', function ($sub) use ($search) {
                        $sub->select('product_id')->from('product_variants')
                            ->whereNull('deleted_at')
                            ->where(function ($v) use ($search) {
                                $v->where('code', 'LIKE', "%{$search}%")->orWhere('name', 'LIKE', "%{$search}%");
                            });
                    });
            })
            ->orderByRaw('code = ? DESC', [$search])
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name', 'code', 'image', 'type', 'price', 'unit_id']);

        $warehouseIds = $this->posWarehouses()->pluck('id')->toArray();
        $stock = DB::table('product_warehouse')
            ->whereNull('deleted_at')
            ->whereIn('product_id', $products->pluck('id')->toArray())
            ->whereIn('warehouse_id', $warehouseIds)
            ->groupBy('product_id')
            ->select('product_id', DB::raw('SUM(qte) AS qte'))
            ->pluck('qte', 'product_id');

        $data = [];
        foreach ($products as $product) {
            $data[] = [
                'id' => $product->id,
                'name' => $product->name,
                'code' => $product->code,
                'image' => explode(',', (string) $product->image)[0],
                'type' => $product->type,
                'price' => (float) $product->price,
                'unit' => $product['unit'] ? $product['unit']->ShortName : '',
                'qte' => $product->type == 'is_service' ? null : (float) ($stock[$product->id] ?? 0),
            ];
        }

        return response()->json(['products' => $data]);
    }

    //------------ Product info sheet: prices, cost, stock per warehouse, history (new POS screen) --------------\\

    public function product_info(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'Sales_pos', Sale::class);

        $product = Product::with('category', 'brand', 'unit', 'unitSale', 'unitPurchase')
            ->where('deleted_at', '=', null)
            ->findOrFail($id);

        $warehouses = $this->posWarehouses();
        $warehouseNames = $warehouses->pluck('name', 'id');

        $variants = ProductVariant::where('product_id', $product->id)->whereNull('deleted_at')->get()->keyBy('id');

        $rows = product_warehouse::where('product_id', $product->id)
            ->whereNull('deleted_at')
            ->whereIn('warehouse_id', $warehouses->pluck('id')->toArray())
            ->orderBy('warehouse_id')
            ->get();

        $stock = [];
        $totalQte = 0;
        foreach ($rows as $row) {
            $variant = $row->product_variant_id ? ($variants[$row->product_variant_id] ?? null) : null;
            if ($row->product_variant_id && !$variant) {
                continue;
            }
            $basePrice = $variant ? $variant->price : $product->price;
            $baseCost = $variant ? $variant->cost : $product->cost;
            $stock[] = [
                'warehouse_id' => $row->warehouse_id,
                'warehouse' => $warehouseNames[$row->warehouse_id] ?? '',
                'variant' => $variant ? $variant->name : null,
                'code' => $variant ? $variant->code : $product->code,
                'qte' => $product->type == 'is_service' ? null : (float) $row->qte,
                'price' => (float) ($row->price > 0 ? $row->price : $basePrice),
                'cost' => (float) ($row->cost > 0 ? $row->cost : $baseCost),
                'manage_stock' => (int) $row->manage_stock,
            ];
            $totalQte += (float) $row->qte;
        }

        // Stock in the warehouse open in the POS, in sale units (used by "Add to cart")
        $current = null;
        if ($request->filled('warehouse_id') && $product->type != 'is_variant') {
            $row = $rows->first(function ($r) use ($request) {
                return $r->warehouse_id == $request->warehouse_id && !$r->product_variant_id;
            });
            if ($row) {
                $qteSale = $row->qte;
                if ($product['unitSale'] && $product['unitSale']->operator_value) {
                    $qteSale = $product['unitSale']->operator == '/'
                        ? $row->qte * $product['unitSale']->operator_value
                        : $row->qte / $product['unitSale']->operator_value;
                }
                $current = [
                    'qte_sale' => $product->type == 'is_service' ? '---' : (float) $qteSale,
                    'manage_stock' => (int) $row->manage_stock,
                ];
            }
        }

        $lastPurchase = DB::table('purchase_details')
            ->join('purchases', 'purchases.id', '=', 'purchase_details.purchase_id')
            ->leftJoin('providers', 'providers.id', '=', 'purchases.provider_id')
            ->whereNull('purchases.deleted_at')
            ->where('purchase_details.product_id', $product->id)
            ->orderBy('purchases.date', 'desc')
            ->orderBy('purchases.id', 'desc')
            ->select('purchases.Ref', 'purchases.date', 'purchase_details.cost', 'purchase_details.quantity', 'providers.name as provider')
            ->first();

        $sales = DB::table('sale_details')
            ->join('sales', 'sales.id', '=', 'sale_details.sale_id')
            ->whereNull('sales.deleted_at')
            ->where('sale_details.product_id', $product->id);
        $lastSaleDate = (clone $sales)->max('sales.date');
        $sold30 = (clone $sales)->where('sales.date', '>=', Carbon::now()->subDays(30)->toDateString())->sum('sale_details.quantity');

        $images = array_values(array_filter(explode(',', (string) $product->image)));

        return response()->json([
            'id' => $product->id,
            'name' => $product->name,
            'code' => $product->code,
            'type' => $product->type,
            'category' => $product['category'] ? $product['category']->name : '',
            'brand' => $product['brand'] ? $product['brand']->name : '',
            'unit' => $product['unit'] ? $product['unit']->ShortName : '',
            'unit_sale' => $product['unitSale'] ? $product['unitSale']->ShortName : '',
            'unit_purchase' => $product['unitPurchase'] ? $product['unitPurchase']->ShortName : '',
            'price' => (float) $product->price,
            'cost' => (float) $product->cost,
            'tax' => (float) $product->TaxNet,
            'tax_method' => (string) $product->tax_method,
            'stock_alert' => (float) $product->stock_alert,
            'is_imei' => (int) $product->is_imei,
            'not_selling' => (int) $product->not_selling,
            'note' => $product->note,
            'image' => $images[0] ?? null,
            'total_qte' => $product->type == 'is_service' ? null : $totalQte,
            'stock' => $stock,
            'current' => $current,
            'last_purchase' => $lastPurchase,
            'last_sale_date' => $lastSaleDate,
            'sold_30_days' => (float) $sold30,
        ]);
    }

      //------------- Reference Number Draft -----------\\

      public function getNumberOrderDraft()
      {
  
          $last = DB::table('draft_sales')->latest('id')->first();
  
          if ($last) {
              $item = $last->Ref;
              $nwMsg = explode("_", $item);
              $inMsg = $nwMsg[1] + 1;
              $code = $nwMsg[0] . '_' . $inMsg;
          } else {
              $code = 'DR_1111';
          }
          return $code;
      }

}
