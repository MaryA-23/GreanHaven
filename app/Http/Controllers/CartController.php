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
    /*
    |--------------------------------------------------------------------------
    | Add item to cart
    |--------------------------------------------------------------------------
    */
    public function add(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
        ]);

        $user = auth()->user();

        $cart = $user->cart ?? Cart::create([
            'user_id' => $user->id
        ]);

        $product = Product::findOrFail(
            $request->product_id
        );

        $item = $cart->items()
            ->where('product_id', $product->id)
            ->first();

        if ($item) {

            $item->increment(
                'quantity',
                $request->quantity
            );

        } else {

            $cart->items()->create([
                'product_id' => $product->id,
                'quantity' => $request->quantity,
                'price' => $product->price,
            ]);
        }

        return response()->json([
            'message' => 'Item added to cart successfully',
            'cart' => $cart->load('items.product')
        ], 200);
    }


    /*
    |--------------------------------------------------------------------------
    | Get cart
    |--------------------------------------------------------------------------
    */
    public function index()
    {
        $user = auth()->user();

        $cart = $user->cart;

        if (
            !$cart ||
            $cart->items()->count() === 0
        ) {
            return response()->json([
                'message' => 'Cart is empty',
                'cart' => []
            ], 200);
        }

        $cart->load('items.product');

        $total = $cart->items->sum(
            function ($item) {
                return $item->price *
                    $item->quantity;
            }
        );

        return response()->json([
            'cart' => $cart,
            'total' => $total
        ], 200);
    }


    /*
    |--------------------------------------------------------------------------
    | Update cart item quantity
    |--------------------------------------------------------------------------
    */
    public function update(
        Request $request,
        $id
    ) {
        $request->validate([
            'quantity' => 'required|integer|min:1',
        ]);

        $cart = auth()->user()->cart;

        if (!$cart) {
            return response()->json([
                'message' => 'Cart is empty'
            ], 200);
        }

        $item = $cart->items()
            ->findOrFail($id);

        $item->update([
            'quantity' => $request->quantity
        ]);

        return response()->json([
            'message' => 'Cart item updated successfully',
            'cart' => $cart->load('items.product')
        ], 200);
    }


    /*
    |--------------------------------------------------------------------------
    | Remove item from cart
    |--------------------------------------------------------------------------
    */
    public function remove($id)
    {
        $cart = auth()->user()->cart;

        if (!$cart) {
            return response()->json([
                'message' => 'Cart is empty'
            ], 200);
        }

        $item = $cart->items()
            ->findOrFail($id);

        $item->delete();

        return response()->json([
            'message' => 'Item removed from cart successfully',
            'cart' => $cart->load('items.product')
        ], 200);
    }


    /*
    |--------------------------------------------------------------------------
    | Clear cart
    |--------------------------------------------------------------------------
    */
    public function clear()
    {
        $cart = auth()->user()->cart;

        if (
            !$cart ||
            $cart->items()->count() === 0
        ) {
            return response()->json([
                'message' => 'Cart is already empty'
            ], 200);
        }

        $cart->items()->delete();

        return response()->json([
            'message' => 'Cart cleared successfully'
        ], 200);
    }


    /*
    |--------------------------------------------------------------------------
    | Checkout
    |--------------------------------------------------------------------------
    */
    public function checkout()
    {
        $user = auth()->user();

        /*
         * User must verify email first.
         */
        if (!$user->hasVerifiedEmail()) {

            return response()->json([
                'message' =>
                    'Please verify your email before checkout'
            ], 403);
        }


        /*
         * Load backend cart.
         */
        $cart = $user->cart()
            ->with('items.product')
            ->first();


        /*
         * Make sure cart is not empty.
         */
        if (
            !$cart ||
            $cart->items->isEmpty()
        ) {
            return response()->json([
                'message' => 'Cart is empty'
            ], 400);
        }


        DB::beginTransaction();

        try {

            /*
             * Calculate total.
             */
            $total = 0;

            foreach ($cart->items as $item) {

                $total +=
                    $item->price *
                    $item->quantity;
            }


            /*
             * Create order.
             */
            $order = Order::create([
                'user_id' => $user->id,
                'status' => 'pending_payment',
                'total_amount' => $total,
            ]);


            /*
             * Create order items.
             */
            foreach ($cart->items as $item) {

                $subtotal =
                    $item->price *
                    $item->quantity;

                $order->items()->create([
                    'product_id' =>
                        $item->product_id,

                    'quantity' =>
                        $item->quantity,

                    'price' =>
                        $item->price,

                    'subtotal' =>
                        $subtotal,
                ]);
            }


            /*
             * Create payment record.
             *
             * Paystack initialize() expects
             * this payment record to exist.
             */
            $payment = Payment::create([
                'order_id' => $order->id,
                'user_id' => $user->id,
                'amount' => $total,
                'status' => 'pending',
                'payment_method' => 'paystack',
            ]);


            /*
             * IMPORTANT:
             *
             * DO NOT clear the cart here.
             *
             * The PaymentController callback
             * will clear it only after Paystack
             * confirms successful payment.
             */


            DB::commit();


            return response()->json([
                'message' =>
                    'Checkout successful, order created',

                'order' =>
                    $order->load(
                        'items.product'
                    ),

                'payment' =>
                    $payment
            ], 201);


        } catch (\Exception $e) {

            DB::rollBack();


            return response()->json([
                'message' => 'Checkout failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}