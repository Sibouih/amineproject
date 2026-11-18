<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;

/**
 * Users marked "POS only" (users.pos_only = 1) may call only the API the POS screen uses.
 * Everything else answers 403.
 */
class Pos_Only
{
    // API paths (without the "api/" prefix) the POS screen needs, per HTTP method
    protected $allowed = [
        '*' => [
            'pos/*',                // products, sale, drafts, product info...
            'get_draft_sales',
            'remove_draft_sale/*',
            'show_product_data/*',
            'sale_pdf/*',           // invoice after a sale
            'get_user_auth',
        ],
        'GET' => [
            'sales',                // "recent sales" list
            'returns/sale',         // "recent returns" list
        ],
        'POST' => [
            'clients',              // quick "add customer"
        ],
    ];

    public function handle($request, Closure $next)
    {
        $user = Auth::guard('api')->user();

        if ($user && $user->pos_only) {
            $patterns = array_merge($this->allowed['*'], $this->allowed[$request->method()] ?? []);
            $patterns = array_map(function ($p) {
                return 'api/' . $p;
            }, $patterns);

            if (!$request->is(...$patterns)) {
                return response()->json([
                    'message' => 'This account can only use the point of sale.',
                    'code' => 'pos_only',
                ], 403);
            }
        }

        return $next($request);
    }
}
