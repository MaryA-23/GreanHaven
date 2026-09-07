<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Cart;
use App\Models\Product;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;

class CartController extends Controller
{
    /**
     * Add product to cart.
     */
    public function add(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
        ]);

        $user = auth()->user();

        /*
        |--------------------------------------------------------------------------
        | Only customers can use cart
        |--------------------------------------------------------------------------
        */

        if ($user->role !== 'user') {
            return response()->json([
                'message' => 'Only customers can use the shopping cart.'
            ], 403);
        }

        $cart = $user->cart ?? Cart::create([
            'user_id' => $user->id
        ]);

        $product = Product::findOrFail(
            $request->product_id
        );

        /*
        |--------------------------------------------------------------------------
        | Product must belong to a company
        |--------------------------------------------------------------------------
        */

        if (!$product->company_id) {
            return response()->json([
                'message' =>
                    'This product is not assigned to a company.'
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | Prevent products from different companies in one cart
        |--------------------------------------------------------------------------
        */

        $cart->load('items.product');

        foreach ($cart->items as $existingItem) {

            if (
                $existingItem->product &&
                (int) $existingItem->product->company_id !==
                (int) $product->company_id
            ) {
                return response()->json([
                    'message' =>
                        'You cannot add products from different companies to the same cart.'
                ], 422);
            }
        }

        $item = $cart->items()
            ->where(
                'product_id',
                $product->id
            )
            ->first();

        if ($item) {

            $item->increment(
                'quantity',
                $request->quantity
            );

        } else {

            $cart->items()->create([
                'product_id' =>
                    $product->id,

                'quantity' =>
                    $request->quantity,

                'price' =>
                    $product->price,
            ]);
        }

        return response()->json([
            'message' =>
                'Item added to cart successfully',

            'cart' =>
                $cart->fresh()
                    ->load('items.product')
        ], 200);
    }


    /**
     * View cart.
     */
    public function index()
    {
        $user = auth()->user();

        if ($user->role !== 'user') {
            return response()->json([
                'message' =>
                    'Only customers can use the shopping cart.'
            ], 403);
        }

        $cart = $user->cart;

        if (
            !$cart ||
            $cart->items()->count() === 0
        ) {

            return response()->json([
                'message' => 'Cart is empty',
                'cart' => [],
                'total' => 0
            ], 200);
        }

        $cart->load('items.product');

        $total = $cart->items->sum(
            function ($item) {

                return
                    $item->price *
                    $item->quantity;
            }
        );

        return response()->json([
            'cart' => $cart,
            'total' => $total
        ], 200);
    }


    /**
     * Update cart quantity.
     */
    public function update(
        Request $request,
        $id
    ) {
        $request->validate([
            'quantity' =>
                'required|integer|min:1',
        ]);

        $user = auth()->user();

        if ($user->role !== 'user') {
            return response()->json([
                'message' =>
                    'Only customers can use the shopping cart.'
            ], 403);
        }

        $cart = $user->cart;

        if (!$cart) {

            return response()->json([
                'message' =>
                    'Cart is empty'
            ], 404);
        }

        $item = $cart->items()
            ->findOrFail($id);

        $item->update([
            'quantity' =>
                $request->quantity
        ]);

        $cart->load('items.product');

        $total = $cart->items->sum(
            function ($item) {

                return
                    $item->price *
                    $item->quantity;
            }
        );

        return response()->json([
            'message' =>
                'Cart item updated successfully',

            'cart' => $cart,

            'total' => $total
        ], 200);
    }


    /**
     * Remove cart item.
     */
    public function remove($id)
    {
        $user = auth()->user();

        if ($user->role !== 'user') {
            return response()->json([
                'message' =>
                    'Only customers can use the shopping cart.'
            ], 403);
        }

        $cart = $user->cart;

        if (!$cart) {

            return response()->json([
                'message' =>
                    'Cart is empty'
            ], 404);
        }

        $item = $cart->items()
            ->findOrFail($id);

        $item->delete();

        $cart->load('items.product');

        $total = $cart->items->sum(
            function ($item) {

                return
                    $item->price *
                    $item->quantity;
            }
        );

        return response()->json([
            'message' =>
                'Item removed from cart successfully',

            'cart' => $cart,

            'total' => $total
        ], 200);
    }


    /**
     * Clear cart.
     */
    public function clear()
    {
        $user = auth()->user();

        if ($user->role !== 'user') {
            return response()->json([
                'message' =>
                    'Only customers can use the shopping cart.'
            ], 403);
        }

        $cart = $user->cart;

        if (!$cart) {

            return response()->json([
                'message' =>
                    'Cart is already empty'
            ], 200);
        }

        $cart->items()->delete();

        return response()->json([
            'message' =>
                'Cart cleared successfully',

            'cart' => [],

            'total' => 0
        ], 200);
    }


    /**
     * Checkout.
     */
    public function checkout(
        Request $request
    ) {
        $request->validate([

            'delivery_address' =>
                'required|string|max:255',

            'city' =>
                'required|string|max:100',

            'notes' =>
                'nullable|string|max:500',
        ]);

        $user = auth()->user();


        /*
        |--------------------------------------------------------------------------
        | Only customers can checkout
        |--------------------------------------------------------------------------
        */

        if ($user->role !== 'user') {

            return response()->json([
                'message' =>
                    'Only customers can place orders.'
            ], 403);
        }


        /*
        |--------------------------------------------------------------------------
        | Email must be verified
        |--------------------------------------------------------------------------
        */

        if (!$user->hasVerifiedEmail()) {

            return response()->json([
                'message' =>
                    'Please verify your email before checkout'
            ], 403);
        }


        /*
        |--------------------------------------------------------------------------
        | Load cart
        |--------------------------------------------------------------------------
        */

        $cart = $user->cart()
            ->with('items.product')
            ->first();


        if (
            !$cart ||
            $cart->items->isEmpty()
        ) {

            return response()->json([
                'message' =>
                    'Cart is empty'
            ], 400);
        }


        /*
        |--------------------------------------------------------------------------
        | Find companies from cart products
        |--------------------------------------------------------------------------
        */

        $companyIds = $cart->items
            ->map(
                function ($item) {
                    return
                        $item->product
                            ?->company_id;
                }
            )
            ->filter()
            ->unique()
            ->values();


        /*
        |--------------------------------------------------------------------------
        | Make sure every product has a company
        |--------------------------------------------------------------------------
        */

        $productsWithoutCompany =
            $cart->items->filter(
                function ($item) {

                    return
                        !$item->product ||
                        !$item->product
                            ->company_id;
                }
            );


        if (
            $productsWithoutCompany
                ->isNotEmpty()
        ) {

            return response()->json([
                'message' =>
                    'One or more products are not assigned to a company.'
            ], 422);
        }


        /*
        |--------------------------------------------------------------------------
        | Cart must contain products from one company only
        |--------------------------------------------------------------------------
        */

        if (
            $companyIds->count() !== 1
        ) {

            return response()->json([
                'message' =>
                    'You cannot checkout products from different companies in the same order.'
            ], 422);
        }


        $companyId =
            $companyIds->first();


        /*
        |--------------------------------------------------------------------------
        | Begin checkout
        |--------------------------------------------------------------------------
        */

        DB::beginTransaction();

        try {

            /*
            |--------------------------------------------------------------------------
            | Calculate total
            |--------------------------------------------------------------------------
            */

            $total = 0;

            foreach (
                $cart->items
                as $item
            ) {

                /*
                | Use current product price.
                */

                $price =
                    $item->product->price;

                $total +=
                    $price *
                    $item->quantity;
            }


            /*
            |--------------------------------------------------------------------------
            | Create order
            |--------------------------------------------------------------------------
            */

            $order = Order::create([

                'user_id' =>
                    $user->id,

                'company_id' =>
                    $companyId,

                'status' =>
                    'pending_payment',

                'total_price' =>
                    $total,

                'delivery_address' =>
                    $request
                        ->delivery_address,

                'city' =>
                    $request->city,

                'notes' =>
                    $request->notes,
            ]);


            /*
            |--------------------------------------------------------------------------
            | Create order items
            |--------------------------------------------------------------------------
            */

            foreach (
                $cart->items
                as $item
            ) {

                $price =
                    $item->product->price;

                $subtotal =
                    $price *
                    $item->quantity;


                $order->items()->create([

                    'product_id' =>
                        $item->product_id,

                    'quantity' =>
                        $item->quantity,

                    'price' =>
                        $price,

                    'subtotal' =>
                        $subtotal,
                ]);
            }


            /*
            |--------------------------------------------------------------------------
            | Create payment
            |--------------------------------------------------------------------------
            */

            $payment = Payment::create([

                'order_id' =>
                    $order->id,

                'user_id' =>
                    $user->id,

                'amount' =>
                    $total,

                'status' =>
                    'pending',

                'payment_method' =>
                    'paystack',

                'gateway_reference' =>
                    null,

                'paid_at' =>
                    null,

                'expires_at' =>
                    now()->addMinutes(15),

                'expired_at' =>
                    null,
            ]);


            /*
            |--------------------------------------------------------------------------
            | Clear cart
            |--------------------------------------------------------------------------
            */

            $cart->items()->delete();


            DB::commit();


            /*
            |--------------------------------------------------------------------------
            | Success
            |--------------------------------------------------------------------------
            */

            return response()->json([

                'message' =>
                    'Checkout successful',

                'order' =>
                    $order->fresh()
                        ->load(
                            'items.product',
                            'payment',
                            'company'
                        ),

                'payment' =>
                    $payment

            ], 201);


        } catch (\Exception $e) {

            DB::rollBack();


            return response()->json([

                'message' =>
                    'Checkout failed',

                'error' =>
                    $e->getMessage()

            ], 500);
        }
    }
}