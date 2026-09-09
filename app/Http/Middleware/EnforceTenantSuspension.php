<?php

namespace App\Http\Middleware;

use App\Models\Company;
use App\Models\Product;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class EnforceTenantSuspension
{
    public function handle(Request $request, Closure $next)
    {
        // Lock the company during a valid tenant login so suspension
        // cannot race with issuing a new access token.
        if (
            $request->isMethod('post') &&
            $request->is('api/tenant/login', 'api/login')
        ) {
            $email = $request->input('email');
            $password = $request->input('password');

            $user = is_string($email)
                ? User::where('email', $email)
                    ->where('role', 'company')
                    ->first()
                : null;

            if (
                $user &&
                is_string($password) &&
                Hash::check($password, $user->password)
            ) {
                return DB::transaction(
                    function () use ($user, $request, $next) {
                        $company = Company::lockForUpdate()
                            ->find($user->company_id);

                        if (!$company || $company->is_suspended) {
                            return $this->denied();
                        }

                        return $next($request);
                    }
                );
            }
        }

        $actor = Auth::guard('sanctum')->user();

        // Also blocks cookie sessions and any surviving tenant tokens.
        if ($actor && $actor->role === 'company') {
            $company = Company::find($actor->company_id);

            if (!$company || $company->is_suspended) {
                return $this->denied();
            }
        }

        // Protect new cart additions and both order-creation endpoints.
        if (
            $actor &&
            $actor->role === 'user' &&
            $request->isMethod('post') &&
            $request->is(
                'api/orders',
                'api/cart/checkout',
                'api/cart/items'
            )
        ) {
            if ($request->is('api/cart/checkout')) {
                $ids = DB::table('cart_items')
                    ->join(
                        'carts',
                        'carts.id',
                        '=',
                        'cart_items.cart_id'
                    )
                    ->where('carts.user_id', $actor->id)
                    ->pluck('cart_items.product_id')
                    ->all();
            } elseif ($request->is('api/cart/items')) {
                $ids = [$request->input('product_id')];
            } else {
                $items = $request->input('items', []);

                $ids = is_array($items)
                    ? array_column($items, 'product_id')
                    : [];
            }

            $ids = array_values(
                array_filter($ids, fn ($id) => is_numeric($id))
            );

            $companyIds = Product::whereIn('id', $ids)
                ->whereNotNull('company_id')
                ->distinct()
                ->pluck('company_id')
                ->all();

            return DB::transaction(
                function () use ($companyIds, $request, $next) {
                    $companies = Company::whereIn('id', $companyIds)
                        ->orderBy('id')
                        ->lockForUpdate()
                        ->get();

                    $suspended = $companies->contains(
                        fn ($company) => (bool) $company->is_suspended
                    );

                    if ($suspended) {
                        return response()->json([
                            'message' =>
                                'A supplier is suspended. Remove their products before checking out.',
                        ], 409);
                    }

                    return $next($request);
                }
            );
        }

        return $next($request);
    }

    private function denied()
    {
        return response()->json([
            'message' =>
                'Your company account is suspended. Contact GreenHaven support.',
        ], 403);
    }
}