<?php

namespace App\Http\Controllers;
use App\Models\User;
use App\Models\UserWarehouse;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\product_warehouse;
use App\Models\ProductWarehousePriceHistory;
use App\Models\Unit;
use App\Models\Warehouse;
use App\Models\CountStock;
use App\utils\helpers;
use Carbon\Carbon;
use DB;
use Excel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use \Gumlet\ImageResize;
use App\Exports\StockExport;
use Illuminate\Support\Facades\File;
use App\Models\SaleDetail;
use App\Models\PurchaseDetail;


class ProductsController extends BaseController
{

    //------------ Get ALL Products --------------\\

    public function index(request $request)
    {
        $this->authorizeForUser($request->user('api'), 'view', Product::class);
        // How many items do you want to display.
        $perPage = $request->limit;
        $pageStart = \Request::get('page', 1);
        // Start displaying items from this number;
        $offSet = ($pageStart * $perPage) - $perPage;
        $order = $request->SortField;
        $dir = $request->SortType;
        $helpers = new helpers();
        // Filter fields With Params to retrieve
        $columns = array(0 => 'name', 1 => 'category_id', 2 => 'brand_id', 3 => 'code');
        $param = array(0 => 'like', 1 => '=', 2 => '=', 3 => 'like');
        $data = array();

        $products = Product::with('unit', 'category', 'brand')
            ->where('deleted_at', '=', null);

        //Multiple Filter
        $Filtred = $helpers->filter($products, $columns, $param, $request)
        
        // Add specific filter for product_id
        ->where(function ($query) use ($request) {
            return $query->when($request->filled('product_id'), function ($query) use ($request) {
                return $query->where('id', '=', $request->product_id);
            });
        })
        // Search With Multiple Param
            ->where(function ($query) use ($request) {
                return $query->when($request->filled('search'), function ($query) use ($request) {
                    return $query->where('products.name', 'LIKE', "%{$request->search}%")
                        ->orWhere('products.code', 'LIKE', "%{$request->search}%")
                        ->orWhere(function ($query) use ($request) {
                            return $query->whereHas('category', function ($q) use ($request) {
                                $q->where('name', 'LIKE', "%{$request->search}%");
                            });
                        })
                        ->orWhere(function ($query) use ($request) {
                            return $query->whereHas('brand', function ($q) use ($request) {
                                $q->where('name', 'LIKE', "%{$request->search}%");
                            });
                        });
                });
            });
        $totalRows = $Filtred->count();
        if($perPage == "-1"){
            $perPage = $totalRows;
        }
        $products = $Filtred->offset($offSet)
            ->limit($perPage)
            ->orderBy($order, $dir)
            ->get();

        foreach ($products as $product) {
            $item['id'] = $product->id;
            $item['code'] = $product->code;
            $item['category'] = $product['category']->name;


            $firstimage = explode(',', $product->image);
            $item['image'] = $firstimage[0];


            if($product->type == 'is_single'){
                $product_warehouse_total_qty = product_warehouse::where('product_id', $product->id)
                ->where('deleted_at', '=', null)
                ->where(function ($query) use ($request) {
                    return $query->when($request->filled('warehouse_id'), function ($query) use ($request) {
                            return $query->where('warehouse_id', $request->warehouse_id);
                        });
                        
                    })
                ->sum('qte');
                $item['name']  = $product->name;
                if ($request->filled('warehouse_id')) {
                    $cost_value = $this->getCostWarehouse($product->id, $request->warehouse_id);
                    $price_value = $this->getPriceWarehouse($product->id, $request->warehouse_id);
                    $benefice_value = $price_value - $cost_value;
                    
                    $item['cost']  = number_format($cost_value, 2, '.', '');
                    $item['price'] = number_format($price_value, 2, '.', '');
                    $item['benefice'] = number_format($benefice_value, 2, '.', '');
                } else {                          
                    $cost_value = $product->cost;
                    $price_value = $product->price;
                    $benefice_value = $product->price - $product->cost;
                    
                    $item['cost']  = number_format($cost_value, 2, '.', '');
                    $item['price'] = number_format($price_value, 2, '.', '');
                    $item['benefice'] = number_format($benefice_value, 2, '.', '');
                }
              $item['quantity'] = $product_warehouse_total_qty .' '.$product['unit']->ShortName;
              $item['total_cost'] = number_format($cost_value * $product_warehouse_total_qty, 2, '.', '');
              $item['total_amount'] = number_format($price_value * $product_warehouse_total_qty, 2, '.', '');
              $item['total_profit'] = number_format($benefice_value * $product_warehouse_total_qty, 2, '.', '');

            }elseif($product->type == 'is_variant'){

                  $product_variant_data = ProductVariant::where('product_id', $product->id)
                  ->where('deleted_at', '=', null)
                  ->get();

                  $item['cost'] = '';
                  $item['price'] = '';
                  $item['name'] = '';

                  foreach ($product_variant_data as $product_variant) {
                      $item['cost']  .= number_format($product_variant->cost, 2, '.', '');
                      $item['cost']  .= '<br>';
                      $item['price'] .= number_format($product_variant->price, 2, '.', '');
                      $item['price'] .= '<br>';
                      $item['name']  .= $product_variant->name.'-'.$product->name;
                      $item['name']  .= '<br>';
                  }

                  $product_warehouse_total_qty = product_warehouse::where('product_id', $product->id)
                  ->where('deleted_at', '=', null)
                  ->sum('qte');
                 
                  $item['quantity'] = $product_warehouse_total_qty .' '.$product['unit']->ShortName;
                  
                  // Calculate totals for variant products (use last variant for calculations)
                  $total_cost = $product_variant->cost * $product_warehouse_total_qty;
                  $total_amount = $product_variant->price * $product_warehouse_total_qty;
                  $total_profit = ($product_variant->price - $product_variant->cost) * $product_warehouse_total_qty;
                  
                  $item['total_cost'] = number_format($total_cost, 2, '.', '');
                  $item['total_amount'] = number_format($total_amount, 2, '.', '');
                  $item['total_profit'] = number_format($total_profit, 2, '.', '');

            }else{
                  $item['name'] = $product->name;
                  $item['cost'] = '----';
                  $item['quantity'] = '----';
                  $item['benefice'] = '----';
                  $item['total_cost'] = '----';
                  $item['total_profit'] = '----';

                  $item['price'] = number_format($product->price, 2, '.', '');
                  $item['total_amount'] = '----';
            }


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

        $categories = Category::where('deleted_at', null)->get(['id', 'name']);
        $brands = Brand::where('deleted_at', null)->get(['id', 'name']);
        $products_list = Product::where('deleted_at', null)->get(['id', 'name']);

        return response()->json([
            'warehouses' => $warehouses,
            'categories' => $categories,
            'brands' => $brands,
            'products' => $data,
            'products_list' => $products_list,
            'totalRows' => $totalRows,
        ]);
    }

    //-------------- Store new  Product  ---------------\\

    public function store(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'create', Product::class);

        try {
           
            // define validation rules for product
            $productRules = [
                'code'         => [
                    'required',
                    Rule::unique('products')->where(function ($query) {
                        return $query->where('deleted_at', '=', null);
                    }),

                    Rule::unique('product_variants')->where(function ($query) {
                        return $query->where('deleted_at', '=', null);
                    }),
                ],
                'name'         => 'required',
                'Type_barcode' => 'required',
                'category_id'  => 'required',
                'type'         => 'required',
                'tax_method'   => 'required',
                'unit_id'      => Rule::requiredIf($request->type != 'is_service'),
                'cost'         => Rule::requiredIf($request->type == 'is_single'),
                'price'        => Rule::requiredIf($request->type != 'is_variant'),
            ];


           // if type is not is_variant, add validation for variants array
            if ($request->type == 'is_variant') {
                $productRules['variants'] = [
                    'required',
                    function ($attribute, $value, $fail) use ($request) {
                        // check if array is not empty
                        if (empty($value)) {
                            $fail('The variants array is required.');
                            return;
                        }

                        // check for duplicate codes in variants array
                        $variants = json_decode($request->variants, true);

                        if($variants){
                            foreach ($variants as $variant) {
                                if (!array_key_exists('text', $variant) || empty($variant['text'])) {
                                    $fail('Variant Name cannot be empty.');
                                    return;
                                }else if(!array_key_exists('code', $variant) || empty($variant['code'])) {
                                    $fail('Variant code cannot be empty.');
                                    return;
                                }else if(!array_key_exists('cost', $variant) || empty($variant['cost'])) {
                                    $fail('Variant cost cannot be empty.');
                                    return;
                                }else if(!array_key_exists('price', $variant) || empty($variant['price'])) {
                                    $fail('Variant price cannot be empty.');
                                    return;
                                }
                            }
                        }else{
                            $fail('The variants data is invalid.');
                            return;
                        }

                       

                        //check if variant name empty
                        $names = array_column($variants, 'text');
                        if($names){
                            foreach ($names as $name) {
                                if (empty($name)) {
                                    $fail('Variant Name cannot be empty.');
                                    return;
                                }
                            }
                        }else{
                            $fail('Variant Name cannot be empty.');
                            return;
                        }

                        //check if variant cost empty
                        $all_cost = array_column($variants, 'cost');
                        if($all_cost){
                            foreach ($all_cost as $cost) {
                                if (empty($cost)) {
                                    $fail('Variant Cost cannot be empty.');
                                    return;
                                }
                            }
                        }else{
                            $fail('Variant Cost cannot be empty.');
                            return;
                        }

                        //check if variant price empty
                        $all_price = array_column($variants, 'price');
                        if($all_price){
                            foreach ($all_price as $price) {
                                if (empty($price)) {
                                    $fail('Variant Price cannot be empty.');
                                    return;
                                }
                            }
                        }else{
                            $fail('Variant Price cannot be empty.');
                            return;
                        }

                        //check if code empty
                        $codes = array_column($variants, 'code');
                        if($codes){
                            foreach ($codes as $code) {
                                if (empty($code)) {
                                    $fail('Variant code cannot be empty.');
                                    return;
                                }
                            }
                        }else{
                            $fail('Variant code cannot be empty.');
                            return;
                        }

                        //check if code Duplicate
                        if (count(array_unique($codes)) !== count($codes)) {
                            $fail('Duplicate codes found in variants array.');
                            return;
                        }

                        // check for duplicate codes in product_variants table
                        $duplicateCodes = DB::table('product_variants')
                            ->whereIn('code', $codes)
                            ->whereNull('deleted_at')
                            ->pluck('code')
                            ->toArray();
                        if (!empty($duplicateCodes)) {
                            $fail('This code : '.implode(', ', $duplicateCodes).' already used');
                        }

                        // check for duplicate codes in products table
                        $duplicateCodes_products = DB::table('products')
                            ->whereIn('code', $codes)
                            ->whereNull('deleted_at')
                            ->pluck('code')
                            ->toArray();
                        if (!empty($duplicateCodes_products)) {
                            $fail('This code : '.implode(', ', $duplicateCodes_products).' already used');
                        }
                    },
                ];
            }



            // validate the request data
            $validatedData = $request->validate($productRules, [
                'code.unique'   => 'Product code already used.',
                'code.required' => 'This field is required',
            ]);


            \DB::transaction(function () use ($request) {

                //-- Create New Product
                $Product = new Product;

                //-- Field Required
                $Product->type         = $request['type'];
                $Product->name         = $request['name'];
                $Product->code         = $request['code'];
                $Product->Type_barcode = $request['Type_barcode'];
                $Product->category_id  = $request['category_id'];
                $Product->brand_id     = $request['brand_id'];
                $Product->note         = $request['note'];
                $Product->TaxNet       = $request['TaxNet'] ? $request['TaxNet'] : 0;
                $Product->tax_method   = $request['tax_method'];


                 //-- check if type is_single
                 if($request['type'] == 'is_single'){
                    $Product->price = $request['price'];
                    $Product->cost  = $request['cost'];

                    $Product->unit_id = $request['unit_id'];
                    $Product->unit_sale_id = $request['unit_sale_id'] ? $request['unit_sale_id'] : $request['unit_id'];
                    $Product->unit_purchase_id = $request['unit_purchase_id'] ? $request['unit_purchase_id'] : $request['unit_id'];


                    $Product->stock_alert = $request['stock_alert'] ? $request['stock_alert'] : 0;

                    $manage_stock = 1;

                //-- check if type is_variant
                }elseif($request['type'] == 'is_variant'){

                    $Product->price = 0;
                    $Product->cost  = 0;

                    $Product->unit_id = $request['unit_id'];
                    $Product->unit_sale_id = $request['unit_sale_id'] ? $request['unit_sale_id'] : $request['unit_id'];
                    $Product->unit_purchase_id = $request['unit_purchase_id'] ? $request['unit_purchase_id'] : $request['unit_id'];

                    $Product->stock_alert = $request['stock_alert'] ? $request['stock_alert'] : 0;

                    $manage_stock = 1;

                //-- check if type is_service
                }else{
                    $Product->price = $request['price'];
                    $Product->cost  = 0;

                    $Product->unit_id = NULL;
                    $Product->unit_sale_id = NULL;
                    $Product->unit_purchase_id = NULL;

                    $Product->stock_alert = 0;

                    $manage_stock = 0;

                }
                
                $Product->is_variant = $request['is_variant'] == 'true' ? 1 : 0;
                $Product->is_imei = $request['is_imei'] == 'true' ? 1 : 0;
                $Product->not_selling = $request['not_selling'] == 'true' ? 1 : 0;

                if ($request['images']) {
                    $files = $request['images'];
                    foreach ($files as $file) {
                        $fileData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $file['path']));

                        // Check if base64 decoding was successful
                        if ($fileData === false) {
                            throw new ImageResizeException('Failed to decode base64 image data');
                        }

                        // Create an image resource from the decoded data
                        $imageResource = imagecreatefromstring($fileData);

                        // Check if imagecreatefromstring was successful
                        if ($imageResource === false) {
                            throw new ImageResizeException('Failed to create image resource from decoded data');
                        }

                        // Resize the image
                        $newImageResource = imagescale($imageResource, 200, 200);

                        // Save the resized image to a file
                        $name = rand(11111111, 99999999) . $file['name'];
                        $path = public_path() . '/images/products/';
                        $success = imagepng($newImageResource, $path . $name);

                        // Check if saving the image was successful
                        if (!$success) {
                            throw new ImageResizeException('Failed to save resized image');
                        }

                        // Free up memory by destroying the image resources
                        imagedestroy($imageResource);
                        imagedestroy($newImageResource);

                        $images[] = $name;

                    }
                    $filename = implode(",", $images);
                } else {
                    $filename = 'no-image.png';
                }

                $Product->image = $filename;
                $Product->save();               

                //--Store Product Warehouse
                $warehouses = Warehouse::where('deleted_at', null)->pluck('id')->toArray();
                if ($warehouses) {
                    $Product_variants = ProductVariant::where('product_id', $Product->id)
                        ->where('deleted_at', null)
                        ->get();
                    foreach ($warehouses as $warehouse) {
                        if ($request['is_variant'] == 'true') {
                            foreach ($Product_variants as $product_variant) {

                                $product_warehouse[] = [
                                    'product_id'         => $Product->id,
                                    'warehouse_id'       => $warehouse,
                                    'product_variant_id' => $product_variant->id,
                                    'manage_stock'       => $manage_stock,
                                ];
                            }
                        } else {
                            $product_warehouse[] = [
                                'product_id'   => $Product->id,
                                'warehouse_id' => $warehouse,
                                'manage_stock' => $manage_stock,
                            ];
                        }
                    }
                    product_warehouse::insert($product_warehouse);
                }

                // Handle warehouse-specific pricing if provided (this will override the defaults above)
                if ($request->has('warehouse_pricing') && $request['warehouse_pricing']) {
                    $warehouse_pricing = json_decode($request['warehouse_pricing'], true);
                    
                    foreach ($warehouse_pricing as $warehouse_id => $pricing) {
                        // Update warehouse-specific pricing for single products
                        if ($request['type'] == 'is_single' || $request['type'] == 'is_service') {
                            $this->updateWarehousePricingWithHistory(
                                $Product->id, 
                                $warehouse_id, 
                                null, // product_variant_id
                                $pricing,
                                'Product_create'
                            );
                        }
                        
                        // Handle variant pricing if needed
                        if (isset($pricing['variants']) && is_array($pricing['variants'])) {
                            foreach ($pricing['variants'] as $variant_id => $variant_pricing) {
                                $this->updateWarehousePricingWithHistory(
                                    $Product->id, 
                                    $warehouse_id, 
                                    $variant_id,
                                    $variant_pricing,
                                    'Product_create'
                                );
                            }
                        }
                    }
                }

            }, 10);

            return response()->json(['success' => true]);

        } catch (ValidationException $e) {
            return response()->json([
                'status' => 422,
                'msg' => 'error',
                'errors' => $e->errors(),
            ], 422);
        }

    }

    //-------------- Update Product  ---------------\\
    //-----------------------------------------------\\

    public function update(Request $request, $id)
    {

        $this->authorizeForUser($request->user('api'), 'update', Product::class);
        try {
            
             // define validation rules for product
             $productRules = [
                'code'         => [
                    'required',

                    Rule::unique('products')->ignore($id)->where(function ($query) {
                        return $query->where('deleted_at', '=', null);
                    }),

                    Rule::unique('product_variants')->ignore($id, 'product_id')->where(function ($query) {
                        return $query->where('deleted_at', '=', null);
                    }),
                ],
                'name'        => 'required',
                'category_id' => 'required',
                'tax_method'  => 'required',
                'type'        => 'required',
                'unit_id'     => Rule::requiredIf($request->type != 'is_service'),
                'cost'        => Rule::requiredIf($request->type == 'is_single'),
                'price'       => Rule::requiredIf($request->type != 'is_variant'),
            ];



           // if type is not is_variant, add validation for variants array
            if ($request->type == 'is_variant') {
                $productRules['variants'] = [
                    'required',
                    function ($attribute, $value, $fail) use ($request, $id) {
                        // check if array is not empty
                        if (empty($value)) {
                            $fail('The variants array is required.');
                            return;
                        }
                        // check for duplicate codes in variants array
                        $variants = $request->variants;
                       

                        if($variants){
                            foreach ($variants as $variant) {
                                if (!array_key_exists('text', $variant) || empty($variant['text'])) {
                                    $fail('Variant Name cannot be empty.');
                                    return;
                                }else if(!array_key_exists('code', $variant) || empty($variant['code'])) {
                                    $fail('Variant code cannot be empty.');
                                    return;
                                }else if(!array_key_exists('cost', $variant) || empty($variant['cost'])) {
                                    $fail('Variant cost cannot be empty.');
                                    return;
                                }else if(!array_key_exists('price', $variant) || empty($variant['price'])) {
                                    $fail('Variant price cannot be empty.');
                                    return;
                                }
                            }
                        }else{
                            $fail('The variants data is invalid.');
                            return;
                        }

                        //check if variant name empty
                        $names = array_column($variants, 'text');
                        if($names){
                            foreach ($names as $name) {
                                if (empty($name)) {
                                    $fail('Variant Name cannot be empty.');
                                    return;
                                }
                            }
                        }else{
                            $fail('Variant Name cannot be empty.');
                            return;
                        }

                        //check if variant cost empty
                        $all_cost = array_column($variants, 'cost');
                        if($all_cost){
                            foreach ($all_cost as $cost) {
                                if (empty($cost)) {
                                    $fail('Variant Cost cannot be empty.');
                                    return;
                                }
                            }
                        }else{
                            $fail('Variant Cost cannot be empty.');
                            return;
                        }

                        //check if variant price empty
                        $all_price = array_column($variants, 'price');
                        if($all_price){
                            foreach ($all_price as $price) {
                                if (empty($price)) {
                                    $fail('Variant Price cannot be empty.');
                                    return;
                                }
                            }
                        }else{
                            $fail('Variant Price cannot be empty.');
                            return;
                        }

                        //check if code empty
                        $codes = array_column($variants, 'code');
                        if($codes){
                            foreach ($codes as $code) {
                                if (empty($code)) {
                                    $fail('Variant code cannot be empty.');
                                    return;
                                }
                            }
                        }else{
                            $fail('Variant code cannot be empty.');
                            return;
                        }

                        //check if code Duplicate
                        if (count(array_unique($codes)) !== count($codes)) {
                            $fail('Duplicate codes found in variants array.');
                            return;
                        }

                        
                        // check for duplicate codes in product_variants table
                        $duplicateCodes = DB::table('product_variants')
                            ->where(function ($query) use ($id) {
                                $query->where('product_id', '<>', $id);
                            })
                            ->whereIn('code', $codes)
                            ->whereNull('deleted_at')
                            ->pluck('code')
                            ->toArray();
                        if (!empty($duplicateCodes)) {
                            $fail('This code : '.implode(', ', $duplicateCodes).' already used');
                        }

                        // check for duplicate codes in products table
                        $duplicateCodes_products = DB::table('products')
                            ->where('id', '!=', $id)
                            ->whereIn('code', $codes)
                            ->whereNull('deleted_at')
                            ->pluck('code')
                            ->toArray();
                        if (!empty($duplicateCodes_products)) {
                            $fail('This code : '.implode(', ', $duplicateCodes_products).' already used');
                        }
                    },
                ];
            }



            // validate the request data
            $validatedData = $request->validate($productRules, [
                'code.unique'   => 'Product code already used.',
                'code.required' => 'This field is required',
            ]);









            \DB::transaction(function () use ($request, $id) {

                $Product = Product::where('id', $id)
                    ->where('deleted_at', '=', null)
                    ->first();

                //-- Update Product
                $Product->type = $request['type'];
                $Product->name = $request['name'];
                $Product->code = $request['code'];
                $Product->Type_barcode = $request['Type_barcode'];
                $Product->category_id = $request['category_id'];
                $Product->brand_id = $request['brand_id'] == 'null' ?Null: $request['brand_id'];
                $Product->TaxNet = $request['TaxNet'];
                $Product->tax_method = $request['tax_method'];
                $Product->note = $request['note'];

                 //-- check if type is_single
                 if($request['type'] == 'is_single'){
                    $Product->price = $request['price'];
                    $Product->cost  = $request['cost'];

                    $Product->unit_id = $request['unit_id'];
                    $Product->unit_sale_id = $request['unit_sale_id'] ? $request['unit_sale_id'] : $request['unit_id'];
                    $Product->unit_purchase_id = $request['unit_purchase_id'] ? $request['unit_purchase_id'] : $request['unit_id'];


                    $Product->stock_alert = $request['stock_alert'] ? $request['stock_alert'] : 0;
                    $Product->is_variant = 0;

                    $manage_stock = 1;

                //-- check if type is_variant
                }elseif($request['type'] == 'is_variant'){

                    $Product->price = 0;
                    $Product->cost  = 0;

                    $Product->unit_id = $request['unit_id'];
                    $Product->unit_sale_id = $request['unit_sale_id'] ? $request['unit_sale_id'] : $request['unit_id'];
                    $Product->unit_purchase_id = $request['unit_purchase_id'] ? $request['unit_purchase_id'] : $request['unit_id'];

                    $Product->stock_alert = $request['stock_alert'] ? $request['stock_alert'] : 0;
                    $Product->is_variant = 1;
                    $manage_stock = 1;

                //-- check if type is_service
                }else{
                    $Product->price = $request['price'];
                    $Product->cost  = 0;

                    $Product->unit_id = NULL;
                    $Product->unit_sale_id = NULL;
                    $Product->unit_purchase_id = NULL;

                    $Product->stock_alert = 0;
                    $Product->is_variant = 0;
                    $manage_stock = 0;

                }

                $Product->is_imei = $request['is_imei'] == 'true' ? 1 : 0;
                $Product->not_selling = $request['not_selling'] == 'true' ? 1 : 0;

                // Handle warehouse-specific pricing
                if ($request->has('warehouse_pricing') && $request['warehouse_pricing']) {
                    $warehouse_pricing = json_decode($request['warehouse_pricing'], true);
                    
                    // If a specific warehouse is selected, only update that warehouse
                    if ($request->has('selected_warehouse') && $request['selected_warehouse']) {
                        $selected_warehouse_id = $request['selected_warehouse'];
                        
                        if (isset($warehouse_pricing[$selected_warehouse_id])) {
                            $pricing = $warehouse_pricing[$selected_warehouse_id];
                            
                            // Update warehouse-specific pricing for single products
                            if ($request['type'] == 'is_single' || $request['type'] == 'is_service') {
                                $this->updateWarehousePricingWithHistory(
                                    $id, 
                                    $selected_warehouse_id, 
                                    null, // product_variant_id
                                    $pricing,
                                    'Product_edit'
                                );
                            }
                            
                            // Handle variant pricing if needed
                            if (isset($pricing['variants']) && is_array($pricing['variants'])) {
                                foreach ($pricing['variants'] as $variant_id => $variant_pricing) {
                                    $this->updateWarehousePricingWithHistory(
                                        $id, 
                                        $selected_warehouse_id, 
                                        $variant_id,
                                        $variant_pricing,
                                        'Product_edit'
                                    );
                                }
                            }
                        }
                    } else {
                        // If no specific warehouse is selected, update all warehouses in the pricing data
                        foreach ($warehouse_pricing as $warehouse_id => $pricing) {
                            // Update warehouse-specific pricing for single products
                            if ($request['type'] == 'is_single' || $request['type'] == 'is_service') {
                                $this->updateWarehousePricingWithHistory(
                                    $id, 
                                    $warehouse_id, 
                                    null, // product_variant_id
                                    $pricing,
                                    'Product_edit'
                                );
                            }
                            
                            // Handle variant pricing if needed
                            if (isset($pricing['variants']) && is_array($pricing['variants'])) {
                                foreach ($pricing['variants'] as $variant_id => $variant_pricing) {
                                    $this->updateWarehousePricingWithHistory(
                                        $id, 
                                        $warehouse_id, 
                                        $variant_id,
                                        $variant_pricing,
                                        'Product_edit'
                                    );
                                }
                            }
                        }
                    }
                } else {
                    // If warehouse_pricing is not provided, but we have a current_warehouse_id from the list context,
                    // update the warehouse-specific pricing for that warehouse
                    if ($request->has('current_warehouse_id') && $request['current_warehouse_id'] && 
                        ($request['type'] == 'is_single' || $request['type'] == 'is_service')) {
                        
                        $pricing = [];
                        if ($request['type'] == 'is_single') {
                            $pricing['price'] = $request['price'];
                            $pricing['cost'] = $request['cost'];
                        } else {
                            $pricing['price'] = $request['price'];
                            $pricing['cost'] = 0;
                        }
                        
                        $this->updateWarehousePricingWithHistory(
                            $id, 
                            $request['current_warehouse_id'], 
                            null, // product_variant_id
                            $pricing,
                            'Product_edit_from_list'
                        );
                    }
                }
                
                // Store Variants Product
                $oldVariants = ProductVariant::where('product_id', $id)
                    ->where('deleted_at', null)
                    ->get();

                $warehouses = Warehouse::where('deleted_at', null)
                    ->pluck('id')
                    ->toArray();


                if ($request['type'] == 'is_variant') {

                    if ($oldVariants->isNotEmpty()) {
                        $new_variants_id = [];
                        $var = 'id';

                        foreach ($request['variants'] as $new_id) {
                            if (array_key_exists($var, $new_id)) {
                                $new_variants_id[] = $new_id['id'];
                            } else {
                                $new_variants_id[] = 0;
                            }
                        }

                        foreach ($oldVariants as $key => $value) {
                            $old_variants_id[] = $value->id;

                            // Delete Variant
                            if (!in_array($old_variants_id[$key], $new_variants_id)) {
                                $ProductVariant = ProductVariant::findOrFail($value->id);
                                $ProductVariant->deleted_at = Carbon::now();
                                $ProductVariant->save();

                                $ProductWarehouse = product_warehouse::where('product_variant_id', $value->id)
                                    ->update(['deleted_at' => Carbon::now()]);
                            }
                        }

                        foreach ($request['variants'] as $key => $variant) {
                            if (array_key_exists($var, $variant)) {

                                $ProductVariantDT = new ProductVariant;
                                //-- Field Required
                                $ProductVariantDT->product_id = $variant['product_id'];
                                $ProductVariantDT->name = $variant['text'];
                                $ProductVariantDT->price = $variant['price'];
                                $ProductVariantDT->cost = $variant['cost'];
                                $ProductVariantDT->code = $variant['code'];

                                $ProductVariantUP['product_id'] = $variant['product_id'];
                                $ProductVariantUP['code'] = $variant['code'];
                                $ProductVariantUP['name'] = $variant['text'];
                                $ProductVariantUP['price'] = $variant['price'];
                                $ProductVariantUP['cost'] = $variant['cost'];

                            } else {
                                $ProductVariantDT = new ProductVariant;

                                 //-- Field Required
                                 $ProductVariantDT->product_id = $id;
                                 $ProductVariantDT->code = $variant['code'];
                                 $ProductVariantDT->name = $variant['text'];
                                 $ProductVariantDT->price = $variant['price'];
                                 $ProductVariantDT->cost = $variant['cost'];

                                 $ProductVariantUP['product_id'] = $id;
                                 $ProductVariantUP['code'] = $variant['code'];
                                 $ProductVariantUP['name'] = $variant['text'];
                                 $ProductVariantUP['price'] = $variant['price'];
                                 $ProductVariantUP['cost'] = $variant['cost'];
                                 $ProductVariantUP['qty'] = 0.00;
                            }

                            if (!in_array($new_variants_id[$key], $old_variants_id)) {
                                $ProductVariantDT->save();

                                //--Store Product warehouse
                                if ($warehouses) {
                                    $product_warehouse= [];
                                    foreach ($warehouses as $warehouse) {

                                        $product_warehouse[] = [
                                            'product_id'         => $id,
                                            'warehouse_id'       => $warehouse,
                                            'product_variant_id' => $ProductVariantDT->id,
                                            'manage_stock'       => $manage_stock,
                                        ];

                                    }
                                    product_warehouse::insert($product_warehouse);
                                }
                            } else {
                                ProductVariant::where('id', $variant['id'])->update($ProductVariantUP);
                            }
                        }

                    } else {
                        $ProducttWarehouse = product_warehouse::where('product_id', $id)
                            ->update([
                                'deleted_at' => Carbon::now(),
                            ]);

                        foreach ($request['variants'] as $variant) {
                            $product_warehouse_DT = [];
                            $ProductVarDT = new ProductVariant;

                           //-- Field Required
                           $ProductVarDT->product_id = $id;
                           $ProductVarDT->code = $variant['code'];
                           $ProductVarDT->name = $variant['text'];
                           $ProductVarDT->cost = $variant['cost'];
                           $ProductVarDT->price = $variant['price'];
                           $ProductVarDT->save();


                            //-- Store Product warehouse
                            if ($warehouses) {
                                foreach ($warehouses as $warehouse) {

                                    $product_warehouse_DT[] = [
                                        'product_id'         => $id,
                                        'warehouse_id'       => $warehouse,
                                        'product_variant_id' => $ProductVarDT->id,
                                        'manage_stock'       => $manage_stock,
                                    ];
                                }

                                product_warehouse::insert($product_warehouse_DT);
                            }
                        }

                    }
                } else {
                    if ($oldVariants->isNotEmpty()) {
                        foreach ($oldVariants as $old_var) {
                            $var_old = ProductVariant::where('product_id', $old_var['product_id'])
                                ->where('deleted_at', null)
                                ->first();
                            $var_old->deleted_at = Carbon::now();
                            $var_old->save();

                            $ProducttWarehouse = product_warehouse::where('product_variant_id', $old_var['id'])
                                ->update([
                                    'deleted_at' => Carbon::now(),
                                ]);
                        }

                        if ($warehouses) {
                            foreach ($warehouses as $warehouse) {

                                $product_warehouse[] = [
                                    'product_id'         => $id,
                                    'warehouse_id'       => $warehouse,
                                    'product_variant_id' => null,
                                    'manage_stock'       => $manage_stock,
                                ];

                            }
                            product_warehouse::insert($product_warehouse);
                        }
                    }
                }

                if ($request['images'] === null) {

                    if ($Product->image !== null) {
                        foreach (explode(',', $Product->image) as $img) {
                            $pathIMG = public_path() . '/images/products/' . $img;
                            if (file_exists($pathIMG)) {
                                if ($img != 'no-image.png') {
                                    @unlink($pathIMG);
                                }
                            }
                        }
                    }
                    $filename = 'no-image.png';
                } else {
                    if ($Product->image !== null) {
                        foreach (explode(',', $Product->image) as $img) {
                            $pathIMG = public_path() . '/images/products/' . $img;
                            if (file_exists($pathIMG)) {
                                if ($img != 'no-image.png') {
                                    @unlink($pathIMG);
                                }
                            }
                        }
                    }
                    $files = $request['images'];
                    foreach ($files as $file) {
                        $fileData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $file['path']));

                        // Check if base64 decoding was successful
                        if ($fileData === false) {
                            throw new ImageResizeException('Failed to decode base64 image data');
                        }
                        
                        // Create an image resource from the decoded data
                        $imageResource = imagecreatefromstring($fileData);
                        
                        // Check if imagecreatefromstring was successful
                        if ($imageResource === false) {
                            throw new ImageResizeException('Failed to create image resource from decoded data');
                        }
                        
                        // Resize the image
                        $newImageResource = imagescale($imageResource, 200, 200);
                        
                        // Save the resized image to a file
                        $name = rand(11111111, 99999999) . $file['name'];
                        $path = public_path() . '/images/products/';
                        $success = imagepng($newImageResource, $path . $name);
                        
                        // Check if saving the image was successful
                        if (!$success) {
                            throw new ImageResizeException('Failed to save resized image');
                        }
                        
                        // Free up memory by destroying the image resources
                        imagedestroy($imageResource);
                        imagedestroy($newImageResource);
                        
                        $images[] = $name;
                        
                    }
                    $filename = implode(",", $images);
                }

                $Product->image = $filename;
                $Product->save();

            }, 10);

            return response()->json(['success' => true]);

        } catch (ValidationException $e) {
            return response()->json([
                'status' => 422,
                'msg' => 'error',
                'errors' => $e->errors(),
            ], 422);
        }

    }

    //-------------- Remove Product  ---------------\\
    //-----------------------------------------------\\

    public function destroy(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'delete', Product::class);

        \DB::transaction(function () use ($id) {

            $Product = Product::findOrFail($id);
            $Product->deleted_at = Carbon::now();
            $Product->save();

            foreach (explode(',', $Product->image) as $img) {
                $pathIMG = public_path() . '/images/products/' . $img;
                if (file_exists($pathIMG)) {
                    if ($img != 'no-image.png') {
                        @unlink($pathIMG);
                    }
                }
            }

            product_warehouse::where('product_id', $id)->update([
                'deleted_at' => Carbon::now(),
            ]);

            ProductVariant::where('product_id', $id)->update([
                'deleted_at' => Carbon::now(),
            ]);

        }, 10);

        return response()->json(['success' => true]);

    }

    //-------------- Delete by selection  ---------------\\

    public function delete_by_selection(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'delete', Product::class);

        \DB::transaction(function () use ($request) {
            $selectedIds = $request->selectedIds;
            foreach ($selectedIds as $product_id) {

                $Product = Product::findOrFail($product_id);
                $Product->deleted_at = Carbon::now();
                $Product->save();

                foreach (explode(',', $Product->image) as $img) {
                    $pathIMG = public_path() . '/images/products/' . $img;
                    if (file_exists($pathIMG)) {
                        if ($img != 'no-image.png') {
                            @unlink($pathIMG);
                        }
                    }
                }

                product_warehouse::where('product_id', $product_id)->update([
                    'deleted_at' => Carbon::now(),
                ]);

                ProductVariant::where('product_id', $product_id)->update([
                    'deleted_at' => Carbon::now(),
                ]);
            }

        }, 10);

        return response()->json(['success' => true]);

    }

   
    //--------------  Show Product Details ---------------\\

    public function Get_Products_Details(Request $request, $id)
    {

        $this->authorizeForUser($request->user('api'), 'view', Product::class);

        $Product = Product::where('deleted_at', '=', null)->findOrFail($id);
        //get warehouses assigned to user
        $user_auth = auth()->user();
        if($user_auth->is_all_warehouses){
            $warehouses = Warehouse::where('deleted_at', '=', null)->get(['id', 'name']);
        }else{
            $warehouses_id = UserWarehouse::where('user_id', $user_auth->id)->pluck('warehouse_id')->toArray();
            $warehouses = Warehouse::where('deleted_at', '=', null)->whereIn('id', $warehouses_id)->get(['id', 'name']);
        }

        $item['id'] = $Product->id;
        $item['type'] = $Product->type;
        $item['code'] = $Product->code;
        $item['Type_barcode'] = $Product->Type_barcode;
        $item['name'] = $Product->name;
        $item['note'] = $Product->note;
        $item['category'] = $Product['category']->name;
        $item['brand'] = $Product['brand'] ? $Product['brand']->name : 'N/D';
        $item['price'] = $Product->price;
        $item['cost'] = $Product->cost;
        $item['stock_alert'] = $Product->stock_alert;
        $item['taxe'] = $Product->TaxNet;
        $item['tax_method'] = $Product->tax_method == '1' ? 'Exclusive' : 'Inclusive';

        if($Product->type == 'is_single'){
            $item['type_name']  = 'Single';
            $item['unit'] = $Product['unit']->ShortName;

        }elseif($Product->type == 'is_variant'){
            $item['type_name'] = 'Variable';
            $item['unit'] = $Product['unit']->ShortName;

        }else{
            $item['type_name'] = 'Service';
            $item['unit'] = '----';

        }

        if ($Product->is_variant) {
            $item['is_variant'] = 'yes';
            $productsVariants = ProductVariant::where('product_id', $id)
                ->where('deleted_at', null)
                ->get();
            foreach ($productsVariants as $variant) {
                $ProductVariant['code'] = $variant->code;
                $ProductVariant['name'] = $variant->name;
                $ProductVariant['cost'] = number_format($variant->cost, 2, '.', ',');
                $ProductVariant['price'] = number_format($variant->price, 2, '.', ',');

                $item['products_variants_data'][] = $ProductVariant;

                foreach ($warehouses as $warehouse) {
                    $product_warehouse = DB::table('product_warehouse')
                        ->where('product_id', $id)
                        ->where('deleted_at', '=', null)
                        ->where('warehouse_id', $warehouse->id)
                        ->where('product_variant_id', $variant->id)
                        ->select(DB::raw('SUM(product_warehouse.qte) AS sum'))
                        ->first();

                    $war_var['mag'] = $warehouse->name;
                    $war_var['variant'] = $variant->name;
                    $war_var['qte'] = $product_warehouse->sum;
                    $item['CountQTY_variants'][] = $war_var;
                }

            }
        } else {
            $item['is_variant'] = 'no';
            $item['CountQTY_variants'] = [];
        }

        foreach ($warehouses as $warehouse) {
            $product_warehouse_data = DB::table('product_warehouse')
                ->where('deleted_at', '=', null)
                ->where('product_id', $id)
                ->where('warehouse_id', $warehouse->id)
                ->select(DB::raw('SUM(product_warehouse.qte) AS sum'))
                ->first();

            $war['mag'] = $warehouse->name;
            $war['qte'] = $product_warehouse_data->sum;
            $item['CountQTY'][] = $war;
        }

        if ($Product->image != '') {
            foreach (explode(',', $Product->image) as $img) {
                $item['images'][] = $img;
            }
        }

        $data[] = $item;

        return response()->json($data[0]);

    }

    //------------ Get products By Warehouse -----------------\\

    public function Products_by_Warehouse(request $request, $id)
    {
        $data = [];
        $product_warehouse_data = product_warehouse::with('warehouse', 'product', 'productVariant')

        ->where(function ($query) use ($request , $id) {
                return $query->where('warehouse_id', $id)
                    ->where('deleted_at', '=', null)
                    ->where(function ($query) use ($request) {
                        return $query->whereHas('product', function ($q) use ($request) {
                            if ($request->is_sale == '1') {
                                $q->where('not_selling', '=', 0);
                            }
                        });
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
        })->get();

        foreach ($product_warehouse_data as $product_warehouse) {

            if ($product_warehouse->product_variant_id) {
                $item['product_variant_id'] = $product_warehouse->product_variant_id;

                $item['code'] = $product_warehouse['productVariant']->code;
                $item['Variant'] = '['.$product_warehouse['productVariant']->name . ']' . $product_warehouse['product']->name;
                $item['name'] = '['.$product_warehouse['productVariant']->name . ']' . $product_warehouse['product']->name;
                $item['barcode'] = $product_warehouse['productVariant']->code;

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
            $item['product_type'] = $product_warehouse['product']->type;
            $item['Type_barcode'] = $product_warehouse['product']->Type_barcode;
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

            if($product_warehouse['product']['unitPurchase']) {

                if ($product_warehouse['product']['unitPurchase']->operator == '/') {
                    $item['qte_purchase'] = round($product_warehouse->qte * $product_warehouse['product']['unitPurchase']->operator_value, 5);
                } else {
                    $item['qte_purchase'] = round($product_warehouse->qte / $product_warehouse['product']['unitPurchase']->operator_value, 5);
                }

            }else{
                $item['qte_purchase'] = $product_warehouse->qte;
            }

            $item['manage_stock'] = $product_warehouse->manage_stock;
            $item['qte'] = $product_warehouse['product']->type!='is_service'?$product_warehouse->qte:'---';
            $item['unitSale'] = $product_warehouse['product']['unitSale']?$product_warehouse['product']['unitSale']->ShortName:'';
            $item['unitPurchase'] = $product_warehouse['product']['unitPurchase']?$product_warehouse['product']['unitPurchase']->ShortName:'';

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

        return response()->json($data);
    }

    
    public function show($id)
    {
        //
    }
    
    
    //------------ Get product By ID -----------------\\
    public function show_product_data($id , $variant_id, $warehouse_id = null)
    {

        $Product_data = Product::with('unit')
            ->where('id', $id)
            ->where('deleted_at', '=', null)
            ->first();

        $data = [];
        $item['id']           = $Product_data['id'];
        $item['image']        = $Product_data['image'];
        $item['product_type'] = $Product_data['type'];
        $item['Type_barcode'] = $Product_data['Type_barcode'];

        $item['unit_id'] = $Product_data['unit']?$Product_data['unit']->id:'';
        $item['unit']    = $Product_data['unit']?$Product_data['unit']->ShortName:'';

        $item['purchase_unit_id'] = $Product_data['unitPurchase']?$Product_data['unitPurchase']->id:'';
        $item['unitPurchase']     = $Product_data['unitPurchase']?$Product_data['unitPurchase']->ShortName:'';

        $item['sale_unit_id'] = $Product_data['unitSale']?$Product_data['unitSale']->id:'';
        $item['unitSale']     = $Product_data['unitSale']?$Product_data['unitSale']->ShortName:'';

        $item['tax_method']  = $Product_data['tax_method'];
        $item['tax_percent'] = $Product_data['TaxNet'];

        $item['is_imei']     = $Product_data['is_imei'];
        $item['not_selling'] = $Product_data['not_selling'];

        // Get warehouse-specific pricing if warehouse_id is provided
        $warehouse_pricing = null;
        if ($warehouse_id) {
            $query = product_warehouse::where('warehouse_id', $warehouse_id)
                ->where('product_id', $id)
                ->where('deleted_at', null);
            
            if ($variant_id && $variant_id !== 'null') {
                $query->where('product_variant_id', $variant_id);
            } else {
                $query->whereNull('product_variant_id');
            }
            
            $warehouse_pricing = $query->first();
        }


        //product single
        if($Product_data['type'] == 'is_single'){
            // Use warehouse-specific price if available, otherwise use global price
            $product_price = $warehouse_pricing && $warehouse_pricing->price ? $warehouse_pricing->price : $Product_data['price'];
            $product_cost  = $warehouse_pricing && $warehouse_pricing->cost ? $warehouse_pricing->cost : $Product_data['cost'];

            $item['code'] = $Product_data['code'];
            $item['name'] = $Product_data['name'];

        //product is_variant
        }elseif($Product_data['type'] == 'is_variant'){

            $product_variant_data = ProductVariant::where('product_id', $id)
            ->where('id', $variant_id)->first();

            // Use warehouse-specific price if available, otherwise use variant price
            $product_price = $warehouse_pricing ? $warehouse_pricing->price : $product_variant_data['price'];
            $product_cost  = $warehouse_pricing ? $warehouse_pricing->cost : $product_variant_data['cost'];
            $item['code'] = $product_variant_data['code'];
            $item['name'] = '['.$product_variant_data['name'].']'.$Product_data['name'];

         //product is_service
        }else{
            // Use warehouse-specific price if available, otherwise use global price
            $product_price = $warehouse_pricing ? $warehouse_pricing->price : $Product_data['price'];
            $product_cost  = 0;

            $item['code'] = $Product_data['code'];
            $item['name'] = $Product_data['name'];
        }

       
        //check if product has Unit sale
        if ($Product_data['unitSale']) {

            if ($Product_data['unitSale']->operator == '/') {
                $price = $product_price / $Product_data['unitSale']->operator_value;

            } else {
                $price = $product_price * $Product_data['unitSale']->operator_value;
            }

        }else{
            $price = $product_price;
        }

        //check if product has Unit Purchase

        if ($Product_data['unitPurchase']) {

            if ($Product_data['unitPurchase']->operator == '/') {
                $cost = $product_cost / $Product_data['unitPurchase']->operator_value;
            } else {
                $cost = $product_cost * $Product_data['unitPurchase']->operator_value;
            }

        }else{
            $cost = 0;
        }

        $item['Unit_cost'] = $cost;
        $item['fix_cost'] = $product_cost;
        $item['Unit_price'] = $price;
        $item['fix_price'] = $product_price;

        if ($Product_data->TaxNet !== 0.0) {
            //Exclusive
            if ($Product_data['tax_method'] == '1') {
                $tax_price = $price * $Product_data['TaxNet'] / 100;
                $tax_cost = $cost * $Product_data['TaxNet'] / 100;

                $item['Total_cost'] = $cost + $tax_cost;
                $item['Total_price'] = $price + $tax_price;
                $item['Net_cost'] = $cost;
                $item['Net_price'] = $price;
                $item['tax_price'] = $tax_price;
                $item['tax_cost'] = $tax_cost;

                // Inxclusive
            } else {
                $item['Total_cost'] = $cost;
                $item['Total_price'] = $price;
                $item['Net_cost'] = $cost / (($Product_data['TaxNet'] / 100) + 1);
                $item['Net_price'] = $price / (($Product_data['TaxNet'] / 100) + 1);
                $item['tax_cost'] = $item['Total_cost'] - $item['Net_cost'];
                $item['tax_price'] = $item['Total_price'] - $item['Net_price'];
            }
        } else {
            $item['Total_cost'] = $cost;
            $item['Total_price'] = $price;
            $item['Net_cost'] = $cost;
            $item['Net_price'] = $price;
            $item['tax_price'] = 0;
            $item['tax_cost'] = 0;
        }

        $data[] = $item;

        return response()->json($data[0]);
    }

    //--------------  Product Quantity Alerts ---------------\\

    public function Products_Alert(request $request)
    {
        $this->authorizeForUser($request->user('api'), 'Stock_Alerts', Product::class);

        $product_warehouse_data = product_warehouse::with('warehouse', 'product', 'productVariant')
            ->join('products', 'product_warehouse.product_id', '=', 'products.id')
            ->where('manage_stock', true)
            ->whereRaw('qte <= stock_alert')
            ->where(function ($query) use ($request) {
                return $query->when($request->filled('warehouse'), function ($query) use ($request) {
                    return $query->where('warehouse_id', $request->warehouse);
                });
            })->where('product_warehouse.deleted_at', null)->get();

        $data = [];

        if ($product_warehouse_data->isNotEmpty()) {

            foreach ($product_warehouse_data as $product_warehouse) {
                if ($product_warehouse->qte <= $product_warehouse['product']->stock_alert) {
                    if ($product_warehouse->product_variant_id !== null) {
                        $item['code'] = $product_warehouse['productVariant']->code ;
                        $item['name'] = '['.$product_warehouse['productVariant']->name . ']' . $product_warehouse['product']->name;
                    } else {
                        $item['code'] = $product_warehouse['product']->code;
                        $item['name'] = $product_warehouse['product']->name;
                    }
                    $item['quantity'] = $product_warehouse->qte;
                    $item['warehouse'] = $product_warehouse['warehouse']->name;
                    $item['stock_alert'] = $product_warehouse['product']->stock_alert;
                    $data[] = $item;
                }
            }
        }

        $perPage = $request->limit; // How many items do you want to display.
        $pageStart = \Request::get('page', 1);
        // Start displaying items from this number;
        $offSet = ($pageStart * $perPage) - $perPage;
        $collection = collect($data);
        // Get only the items you need using array_slice
        $data_collection = $collection->slice($offSet, $perPage)->values();

        $products = new LengthAwarePaginator($data_collection, count($data), $perPage, Paginator::resolveCurrentPage(), array('path' => Paginator::resolveCurrentPath()));
       
         //get warehouses assigned to user
         $user_auth = auth()->user();
         if($user_auth->is_all_warehouses){
             $warehouses = Warehouse::where('deleted_at', '=', null)->get(['id', 'name']);
         }else{
             $warehouses_id = UserWarehouse::where('user_id', $user_auth->id)->pluck('warehouse_id')->toArray();
             $warehouses = Warehouse::where('deleted_at', '=', null)->whereIn('id', $warehouses_id)->get(['id', 'name']);
         }
 
        return response()->json([
            'products' => $products,
            'warehouses' => $warehouses,
        ]);
    }

    //---------------- Show Form Create Product ---------------\\

    public function create(Request $request)
    {

        $this->authorizeForUser($request->user('api'), 'create', Product::class);

        $categories = Category::where('deleted_at', null)->get(['id', 'name']);
        $brands = Brand::where('deleted_at', null)->get(['id', 'name']);
        $units = Unit::where('deleted_at', null)->where('base_unit', null)->get();
        return response()->json([
            'categories' => $categories,
            'brands' => $brands,
            'units' => $units,
        ]);

    }

    //---------------- Show Elements Barcode ---------------\\

    public function Get_element_barcode(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'barcode', Product::class);

         //get warehouses assigned to user
         $user_auth = auth()->user();
         if($user_auth->is_all_warehouses){
             $warehouses = Warehouse::where('deleted_at', '=', null)->get(['id', 'name']);
         }else{
             $warehouses_id = UserWarehouse::where('user_id', $user_auth->id)->pluck('warehouse_id')->toArray();
             $warehouses = Warehouse::where('deleted_at', '=', null)->whereIn('id', $warehouses_id)->get(['id', 'name']);
         }
        
        return response()->json(['warehouses' => $warehouses]);

    }

    //---------------- Show Form Edit Product ---------------\\

    public function edit(Request $request, $id)
    {

        $this->authorizeForUser($request->user('api'), 'update', Product::class);

        $Product = Product::where('deleted_at', '=', null)->findOrFail($id);

        $item['id'] = $Product->id;
        $item['type'] = $Product->type;
        $item['code'] = $Product->code;
        $item['Type_barcode'] = $Product->Type_barcode;
        $item['name'] = $Product->name;
        if ($Product->category_id) {
            if (Category::where('id', $Product->category_id)
                ->where('deleted_at', '=', null)
                ->first()) {
                $item['category_id'] = $Product->category_id;
            } else {
                $item['category_id'] = '';
            }
        } else {
            $item['category_id'] = '';
        }

        if ($Product->brand_id) {
            if (Brand::where('id', $Product->brand_id)
                ->where('deleted_at', '=', null)
                ->first()) {
                $item['brand_id'] = $Product->brand_id;
            } else {
                $item['brand_id'] = '';
            }
        } else {
            $item['brand_id'] = '';
        }

        if ($Product->unit_id) {
            if (Unit::where('id', $Product->unit_id)
                ->where('deleted_at', '=', null)
                ->first()) {
                $item['unit_id'] = $Product->unit_id;
            } else {
                $item['unit_id'] = '';
            }

            if (Unit::where('id', $Product->unit_sale_id)
                ->where('deleted_at', '=', null)
                ->first()) {
                $item['unit_sale_id'] = $Product->unit_sale_id;
            } else {
                $item['unit_sale_id'] = '';
            }

            if (Unit::where('id', $Product->unit_purchase_id)
                ->where('deleted_at', '=', null)
                ->first()) {
                $item['unit_purchase_id'] = $Product->unit_purchase_id;
            } else {
                $item['unit_purchase_id'] = '';
            }

        } else {
            $item['unit_id'] = '';
        }

        $item['tax_method'] = $Product->tax_method;
        $item['price'] = $Product->price;
        $item['cost'] = $Product->cost;
        $item['stock_alert'] = $Product->stock_alert;
        $item['TaxNet'] = $Product->TaxNet;
        $item['note'] = $Product->note ? $Product->note : '';
        $item['images'] = [];
        if ($Product->image != '' && $Product->image != 'no-image.png') {
            foreach (explode(',', $Product->image) as $img) {
                $path = public_path() . '/images/products/' . $img;
                if (file_exists($path)) {
                    $itemImg['name'] = $img;
                    $type = pathinfo($path, PATHINFO_EXTENSION);
                    $data = file_get_contents($path);
                    $itemImg['path'] = 'data:image/' . $type . ';base64,' . base64_encode($data);

                    $item['images'][] = $itemImg;
                }
            }
        } else {
            $item['images'] = [];
        }

        if ($Product->type == 'is_variant') {
            $item['is_variant'] = true;
            $productsVariants = ProductVariant::where('product_id', $id)
                ->where('deleted_at', null)
                ->get();

            $var_id = 0;
            foreach ($productsVariants as $variant) {
                $variant_item['var_id'] = $var_id += 1;
                $variant_item['id'] = $variant->id;
                $variant_item['text'] = $variant->name;
                $variant_item['code'] = $variant->code;
                $variant_item['price'] = $variant->price;
                $variant_item['cost'] = $variant->cost;
                $variant_item['product_id'] = $variant->product_id;
                $item['ProductVariant'][] = $variant_item;
            }
        } else {
            $item['is_variant'] = false;
            $item['ProductVariant'] = [];
        }

        $item['is_imei'] = $Product->is_imei?true:false;
        $item['not_selling'] = $Product->not_selling?true:false;

        $data = $item;
        $categories = Category::where('deleted_at', null)->get(['id', 'name']);
        $brands = Brand::where('deleted_at', null)->get(['id', 'name']);

        $product_units = Unit::where('id', $Product->unit_id)
                              ->orWhere('base_unit', $Product->unit_id)
                              ->where('deleted_at', null)
                              ->get();

      
        $units = Unit::where('deleted_at', null)
            ->where('base_unit', null)
            ->get();

        //get warehouses assigned to user
        $user_auth = auth()->user();
        if($user_auth->is_all_warehouses){
            $warehouses = Warehouse::where('deleted_at', '=', null)->get(['id', 'name']);
        }else{
            $warehouses_id = UserWarehouse::where('user_id', $user_auth->id)->pluck('warehouse_id')->toArray();
            $warehouses = Warehouse::where('deleted_at', '=', null)->whereIn('id', $warehouses_id)->get(['id', 'name']);
        }

        // Get warehouse-specific pricing
        $warehouse_pricing = [];
        $product_warehouses = product_warehouse::where('product_id', $id)
            ->where('deleted_at', null)
            ->whereNotNull('price')
            ->get();

        foreach ($product_warehouses as $pw) {
            if ($pw->product_variant_id) {
                // For variant products, group by variant
                if (!isset($warehouse_pricing[$pw->warehouse_id])) {
                    $warehouse_pricing[$pw->warehouse_id] = [];
                }
                $warehouse_pricing[$pw->warehouse_id]['variants'][$pw->product_variant_id] = [
                    'price' => $pw->price,
                    'cost' => $pw->cost
                ];
            } else {
                // For single products
                $warehouse_pricing[$pw->warehouse_id] = [
                    'price' => $pw->price,
                    'cost' => $pw->cost
                ];
            }
        }

        return response()->json([
            'product' => $data,
            'categories' => $categories,
            'brands' => $brands,
            'units' => $units,
            'units_sub' => $product_units,
            'warehouses' => $warehouses,
            'warehouse_pricing' => $warehouse_pricing,
        ]);

    }

    // import Products
    public function import_products(Request $request)
    {
        ini_set('max_execution_time', 600); //600 seconds = 10 minutes 
       
        $file = $request->file('products');
        $ext = pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION);
        if ($ext != 'csv') {
            return response()->json([
                'msg' => 'must be in csv format',
                'status' => false,
            ]);
        } else {
            // Read the CSV file
            $data = [];
            $rowcount = 0;
            if (($handle = fopen($file->getPathname(), "r")) !== false) {
                $max_line_length = defined('MAX_LINE_LENGTH') ? MAX_LINE_LENGTH : 10000;
                $header = fgetcsv($handle, $max_line_length, ';'); // Use semicolon as the delimiter

                // Process the header row
                $escapedHeader = [];
                foreach ($header as $key => $value) {
                    $lheader = strtolower($value);
                    $escapedItem = preg_replace('/[^a-z]/', '', $lheader);
                    $escapedHeader[] = $escapedItem;
                }

                $header_colcount = count($header);
                while (($row = fgetcsv($handle, $max_line_length, ';')) !== false) { // Use semicolon as the delimiter
                    $row_colcount = count($row);
                    if ($row_colcount == $header_colcount) {
                        $entry = array_combine($escapedHeader, $row);
                        $data[] = $entry;
                    } else {
                        return null;
                    }
                    $rowcount++;
                }
                fclose($handle);
            } else {
                return null;
            }


            $warehouses = Warehouse::where('deleted_at', null)->pluck('id')->toArray();

              // Create a new instance of Illuminate\Http\Request and pass the imported data to it.

            $cleanedData = [];

            foreach ($data as $row) {
                $cleanedRow = [];
                foreach ($row as $key => $value) {
                    $cleanedKey = trim($key);
                    $cleanedRow[$cleanedKey] = $value;
                }
                $cleanedData[] = $cleanedRow;
            }

            $rules = [];
            foreach ($cleanedData as $index => $row) {
                $rules[$index . '.name'] = 'required';
                $rules[$index . '.code'] = [
                    'required',
                    Rule::unique('products', 'code')->where(function ($query) {
                        return $query->where('deleted_at', '=', null);
                    }),
                ];
            }

            $validator = validator()->make($cleanedData, $rules);
        
            if ($validator->fails()) {
                // Validation failed
                return response()->json([
                    'msg' => 'Validation failed',
                    'errors' => $validator->errors(),
                    'status' => false,
                ]);
            }
           
            try {
                \DB::transaction(function () use ($cleanedData , $warehouses) {


                    //-- Create New Product
                    foreach ($cleanedData as $key => $value) {

                        $category = Category::where('deleted_at', null)->firstOrCreate(['name' => $value['category']]);
                        $category_id = $category->id;

                        $unit = Unit::where(['ShortName' => $value['unit']])
                            ->orWhere(['name' => $value['unit']])
                            ->where('deleted_at', null)->first();
                        $unit_id = $unit->id;

                        if ($value['brand'] != 'N/A' && $value['brand'] != '') {
                            $brand = Brand::where('deleted_at', null)->firstOrCreate(['name' => $value['brand']]);
                            $brand_id = $brand->id;
                        } else {
                            $brand_id = NULL;
                        }

                      
                        $Product = new Product;
                        $Product->name = htmlspecialchars(trim($value['name']));;
                        $Product->code = $this->check_code_exist($value['code']);
                        $Product->Type_barcode = 'CODE128';
                        $Product->type = 'is_single';
                        $Product->price = str_replace(",","",$value['price']);
                        $Product->cost = str_replace(",","",$value['cost']);
                        $Product->category_id = $category_id;
                        $Product->brand_id = $brand_id;
                        $Product->TaxNet = 0;
                        $Product->tax_method = 1;
                        $Product->note = $value['note'] ? $value['note'] : '';
                        $Product->unit_id = $unit_id;
                        $Product->unit_sale_id = $unit_id;
                        $Product->unit_purchase_id = $unit_id;
                        $Product->stock_alert = $value['stockalert'] ? $value['stockalert'] : 0;
                        $Product->is_variant = 0;
                        $Product->image = 'no-image.png';
                        $Product->save();

                        if ($warehouses) {
                            foreach ($warehouses as $warehouse) {
                                $product_warehouse = new product_warehouse;
                                $product_warehouse->product_id = $Product->id;
                                $product_warehouse->warehouse_id = $warehouse;
                                $product_warehouse->manage_stock = 1;
                                $product_warehouse->save();

                            }
                        }
                    }


                }, 10);

                // Return success response
                return response()->json(['status' => true]);

            } catch (QueryException $e) {
                // Transaction failed, handle the exception
                $errorCode = $e->getCode();
                $errorMessage = $e->getMessage();
                
                // Additional error handling or logging can be performed here
                
                return response()->json(['status' => false, 'error' => $errorMessage]);
            }
            
        }


    

    }

    // Generate_random_code
    public function generate_random_code($value_code)
    {
        if($value_code == ''){
            $gen_code = substr(number_format(time() * mt_rand(), 0, '', ''), 0, 8);
            $this->check_code_exist($gen_code);
        }else{
            $this->check_code_exist($value_code);
        }
    }


    // check_code_exist
    public function check_code_exist($code)
    {
        $check_code = Product::where('code', $code)->where('deleted_at', '=', null)->first();
        if ($check_code) {
            $this->generate_random_code($code);
        } else {
            return $code;
        }

    }

     //----------------- count_stock_list

    public function count_stock_list(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'count_stock', Product::class);
        // How many items do you want to display.
        $perPage = $request->limit;
        $pageStart = \Request::get('page', 1);
        // Start displaying items from this number;
        $offSet = ($pageStart * $perPage) - $perPage;
        $order = $request->SortField;
        $dir = $request->SortType;
        $helpers = new helpers();

        $count_stock = CountStock::where('deleted_at', '=', null)->with('warehouse','user')

        // Search With Multiple Param
            ->where(function ($query) use ($request) {
                return $query->when($request->filled('search'), function ($query) use ($request) {
                    return $query->whereHas('warehouse', function ($q) use ($request) {
                        $q->where('name', 'LIKE', "%{$request->search}%");
                    });
                });
            });
        $totalRows = $count_stock->count();
        if($perPage == "-1"){
            $perPage = $totalRows;
        }
        $stocks = $count_stock->offset($offSet)
            ->limit($perPage)
            ->orderBy($order, $dir)
            ->get();

        $data = array();

        foreach ($stocks as $stock) {

            $item['id']     = $stock->id;
            $item['date']   = $stock->date;
            $item['warehouse_name'] = $stock['warehouse']->name;
            $item['file_stock'] = $stock->file_stock;

            $data[]= $item;
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
            'totalRows' => $totalRows,
            'stocks' => $data,
            'warehouses' => $warehouses,
        ]);
    }
 
      //----------------- store_count_stock
 
      public function store_count_stock(Request $request)
      {

        $this->authorizeForUser($request->user('api'), 'count_stock', Product::class);
 
         $request->validate([
             'date' => 'required',
             'warehouse_id' => 'required',
         ]);

        $products = product_warehouse::join('products', 'product_warehouse.product_id', '=', 'products.id')
        ->where('product_warehouse.deleted_at', '=', null)
        ->where('product_warehouse.warehouse_id', '=', $request->warehouse_id)
        ->select('product_warehouse.product_id as productID' ,'products.name',
                'product_warehouse.product_variant_id as productVariantID', 'product_warehouse.qte')
        ->get();
 
        $stock = [];
        $incorrect_stock= [];

        foreach ($products as $product) {
            
            if($product->productVariantID){
                $variant = ProductVariant::where('product_id', $product->productID)->where('id', $product->productVariantID)->first();
                $item['product_name'] = $variant->name . '-' . $product->name;
            }else{
                $item['product_name'] = $product->name;
            }
            
            $item['quantity'] = $product->qte === 0.0?'0':$product->qte;
        

            $stock[] = $item;
        } 
 
        // Create an instance of StockExport with the warehouse name
        $stockExport = new StockExport($stock);

        $excelFileName = 'stock_export_' . now()->format('YmdHis') . '.xlsx';
        $excelFolderPath = public_path() . '/images/count_stock/';
        $excelFilePath = $excelFolderPath . $excelFileName;
        
        // Check if the directory exists, if not, create it
        if (!File::exists($excelFolderPath)) {
            File::makeDirectory($excelFolderPath, 0755, true, true);
        }
        
        // Use File::put to store the file directly in the desired public directory
        File::put($excelFilePath, Excel::raw($stockExport, \Maatwebsite\Excel\Excel::XLSX));
        
        // Save the file name in the count_stock table
        CountStock::create([
            'date'         => $request->date,
            'warehouse_id' => $request->warehouse_id,
            'user_id'      => Auth::user()->id,
            'file_stock'   => $excelFileName
        ]);
 
        return response()->json(['success' => true]);
 
    }
 
    public function getCostWarehouse($productId, $warehouseId)
    {
        $product = Product::find($productId);
        
        if (!$product) {
            return 0;
        }
        
        // Get the warehouse-specific cost for this product in this warehouse
        $product_warehouse = product_warehouse::where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->whereNull('product_variant_id')
            ->first();
            
        // If we have a warehouse record and cost is explicitly set (not null), use it
        // Even if it's 0, because 0 could be a valid warehouse-specific cost
        if ($product_warehouse && $product_warehouse->cost !== null) {
            return $product_warehouse->cost;
        }
        
        // If no warehouse-specific cost or cost is null, fall back to global product cost
        return $product->cost ?: 0;
    }

    public function getPriceWarehouse($productId, $warehouseId)
    {
        $product = Product::find($productId);
        
        if (!$product) {
            return 0;
        }
        
        // Get the warehouse-specific price for this product in this warehouse
        $product_warehouse = product_warehouse::where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->whereNull('product_variant_id')
            ->first();
            
        // If we have a warehouse record and price is explicitly set (not null), use it
        // Even if it's 0, because 0 could be a valid warehouse-specific price
        if ($product_warehouse && $product_warehouse->price !== null) {
            return $product_warehouse->price;
        }
        
        // If no warehouse-specific price or price is null, fall back to global product price
        return $product->price ?: 0;
    }

    /**
     * Update warehouse pricing with history logging
     */
    private function updateWarehousePricingWithHistory($productId, $warehouseId, $productVariantId, $pricing, $reason = null)
    {
        // Get the current pricing record
        $query = product_warehouse::where('product_id', $productId)
            ->where('warehouse_id', $warehouseId);
            
        if ($productVariantId) {
            $query->where('product_variant_id', $productVariantId);
        } else {
            $query->whereNull('product_variant_id');
        }
        
        $productWarehouse = $query->first();
        
        if (!$productWarehouse) {
            // If no record exists, we can't log history, just return
            return;
        }
        
        $oldPrice = $productWarehouse->price;
        $oldCost = $productWarehouse->cost;
        $changeType = [];
        
        $newPrice = isset($pricing['price']) ? $pricing['price'] : $oldPrice;
        $newCost = isset($pricing['cost']) ? $pricing['cost'] : $oldCost;
        
        // Check if price changed
        if (isset($pricing['price']) && $pricing['price'] != $oldPrice) {
            $changeType[] = 'price';
        }
        
        // Check if cost changed
        if (isset($pricing['cost']) && $pricing['cost'] != $oldCost) {
            $changeType[] = 'cost';
        }
        
        // Only update and log if there are actual changes
        if (!empty($changeType)) {
            // Update the pricing
            $productWarehouse->update([
                'price' => $newPrice,
                'cost' => $newCost,
            ]);
            
            // Log the change in history
            ProductWarehousePriceHistory::create([
                'product_id' => $productId,
                'warehouse_id' => $warehouseId,
                'product_variant_id' => $productVariantId,
                'old_price' => in_array('price', $changeType) ? $oldPrice : null,
                'new_price' => in_array('price', $changeType) ? $newPrice : null,
                'old_cost' => in_array('cost', $changeType) ? $oldCost : null,
                'new_cost' => in_array('cost', $changeType) ? $newCost : null,
                'change_type' => implode(',', $changeType),
                'reason' => $reason,
                'changed_by' => Auth::id(),
            ]);
        }
    }

}
