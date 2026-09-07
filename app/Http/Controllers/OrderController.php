<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\InventoryService;
use App\Mail\OrderCreatedMail;
use Illuminate\Support\Facades\Mail;

class OrderController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    /**
     * User: view own orders.
     * Company: view company orders.
     * Admin: view all orders.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Order::with([
            'items.product',
            'payment',
            'user',
            'company'
        ])->latest();

        if ($user->role === 'company') {
            $query->where(
                'company_id',
                $user->company_id
            );
        }

        if ($user->role === 'user') {
            $query->where(
                'user_id',
                $user->id
            );
        }

        if ($request->filled('status')) {
            $query->where(
                'status',
                $request->status
            );
        }

        if ($request->filled('date_from')) {
            $query->whereDate(
                'created_at',
                '>=',
                $request->date_from
            );
        }

        if ($request->filled('date_to')) {
            $query->whereDate(
                'created_at',
                '<=',
                $request->date_to
            );
        }

        $orders = $query->paginate(
            $request->get('per_page', 10)
        );

        return response()->json([
            'success' => true,
            'data' => $orders,
        ]);
    }


    /**
     * Customer creates an order.
     *
     * The company is taken from the products,
     * NOT from the customer.
     *
     * One checkout can contain products
     * from only one company.
     */
    public function store(
        Request $request,
        InventoryService $inventoryService
    ): JsonResponse {

        $user = $request->user();

        /*
        |--------------------------------------------------------------------------
        | Email verification
        |--------------------------------------------------------------------------
        */

        if (! $user->hasVerifiedEmail()) {

            return response()->json([
                'success' => false,
                'message' =>
                    'Please verify your email before placing an order.'
            ], 403);
        }


        /*
        |--------------------------------------------------------------------------
        | Only public customers can order
        |--------------------------------------------------------------------------
        */

        if ($user->role !== 'user') {

            return response()->json([
                'success' => false,
                'message' =>
                    'Only users can create orders.',
            ], 403);
        }


        /*
        |--------------------------------------------------------------------------
        | Validate checkout
        |--------------------------------------------------------------------------
        */

        $validated = $request->validate([

            'items' =>
                'required|array|min:1',

            'items.*.product_id' =>
                'required|exists:products,id',

            'items.*.quantity' =>
                'required|integer|min:1',

            'delivery_address' =>
                'required|string|max:255',

            'city' =>
                'required|string|max:100',

            'notes' =>
                'nullable|string|max:1000',
        ]);


        /*
        |--------------------------------------------------------------------------
        | Check existing unpaid order
        |--------------------------------------------------------------------------
        */

        $existingOrder = Order::where(
            'user_id',
            $user->id
        )
        ->where(
            'status',
            'pending_payment'
        )
        ->whereHas(
            'payment',
            function ($query) {

                $query
                    ->where(
                        'status',
                        'pending'
                    )
                    ->where(
                        'expires_at',
                        '>',
                        now()
                    );
            }
        )
        ->first();


        if ($existingOrder) {

            return response()->json([
                'success' => false,

                'message' =>
                    'You already have a pending unpaid order. Please complete payment or wait for it to expire.',

                'data' =>
                    $existingOrder->load([
                        'items.product',
                        'payment'
                    ]),
            ], 409);
        }


        /*
        |--------------------------------------------------------------------------
        | Merge duplicate products
        |--------------------------------------------------------------------------
        */

        $mergedItems = [];

        foreach (
            $validated['items']
            as $item
        ) {

            $productId =
                $item['product_id'];

            $quantity =
                (int) $item['quantity'];

            $mergedItems[$productId] =
                ($mergedItems[$productId] ?? 0)
                + $quantity;
        }


        /*
        |--------------------------------------------------------------------------
        | Load selected products
        |--------------------------------------------------------------------------
        */

        $products = Product::whereIn(
            'id',
            array_keys($mergedItems)
        )->get();


        /*
        |--------------------------------------------------------------------------
        | Get company IDs from products
        |--------------------------------------------------------------------------
        */

        $companyIds = $products
            ->pluck('company_id')
            ->filter()
            ->unique()
            ->values();


        /*
        |--------------------------------------------------------------------------
        | Every selected product must belong to a company
        |--------------------------------------------------------------------------
        */

        if (
            $companyIds->isEmpty()
        ) {

            return response()->json([
                'success' => false,

                'message' =>
                    'The selected products are not assigned to a company.',
            ], 422);
        }


        /*
        |--------------------------------------------------------------------------
        | Make sure NONE of the products has company_id = null
        |--------------------------------------------------------------------------
        */

        $productsWithoutCompany =
            $products->filter(
                function ($product) {
                    return empty(
                        $product->company_id
                    );
                }
            );


        if (
            $productsWithoutCompany
                ->isNotEmpty()
        ) {

            return response()->json([
                'success' => false,

                'message' =>
                    'One or more selected products are not assigned to a company.',
            ], 422);
        }


        /*
        |--------------------------------------------------------------------------
        | One checkout = one company
        |--------------------------------------------------------------------------
        */

        if (
            $companyIds->count() > 1
        ) {

            return response()->json([
                'success' => false,

                'message' =>
                    'You cannot order products from different companies in the same checkout.',
            ], 422);
        }


        /*
        |--------------------------------------------------------------------------
        | Company that owns this order
        |--------------------------------------------------------------------------
        */

        $companyId =
            $companyIds->first();


        /*
        |--------------------------------------------------------------------------
        | Create order
        |--------------------------------------------------------------------------
        */

        DB::beginTransaction();

        try {

            $total = 0;


            $order = Order::create([

                'user_id' =>
                    $user->id,

                'company_id' =>
                    $companyId,

                'status' =>
                    'pending_payment',

                'total_price' =>
                    0,

                'delivery_address' =>
                    $validated[
                        'delivery_address'
                    ],

                'city' =>
                    $validated['city'],

                'notes' =>
                    $validated['notes']
                    ?? null,
            ]);


            /*
            |--------------------------------------------------------------------------
            | Create order items
            |--------------------------------------------------------------------------
            */

            foreach (
                $mergedItems
                as $productId => $quantity
            ) {

                $product = Product::where(
                    'id',
                    $productId
                )
                ->lockForUpdate()
                ->firstOrFail();


                /*
                |--------------------------------------------------------------------------
                | Extra company safety check
                |--------------------------------------------------------------------------
                */

                if (
                    (int) $product->company_id
                    !==
                    (int) $companyId
                ) {

                    throw new \Exception(
                        'All products in an order must belong to the same company.'
                    );
                }


                /*
                |--------------------------------------------------------------------------
                | Check inventory
                |--------------------------------------------------------------------------
                */

                $inventoryService
                    ->syncStatus($product);

                $product->save();


                $inventoryService
                    ->checkStock(
                        $product,
                        $quantity
                    );


                /*
                |--------------------------------------------------------------------------
                | Calculate subtotal
                |--------------------------------------------------------------------------
                */

                $subtotal =
                    $product->price
                    * $quantity;


                /*
                |--------------------------------------------------------------------------
                | Create order item
                |--------------------------------------------------------------------------
                */

                $order->items()->create([

                    'product_id' =>
                        $product->id,

                    'quantity' =>
                        $quantity,

                    'price' =>
                        $product->price,

                    'subtotal' =>
                        $subtotal,
                ]);


                $total +=
                    $subtotal;
            }


            /*
            |--------------------------------------------------------------------------
            | Update total
            |--------------------------------------------------------------------------
            */

            $order->update([
                'total_price' =>
                    $total,
            ]);


            /*
            |--------------------------------------------------------------------------
            | Create payment
            |--------------------------------------------------------------------------
            */

            Payment::create([

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


            DB::commit();


            /*
            |--------------------------------------------------------------------------
            | Send order email
            |--------------------------------------------------------------------------
            */

            try {

                Mail::to(
                    $user->email
                )->send(

                    new OrderCreatedMail(

                        $order->fresh([
                            'items.product',
                            'payment',
                            'user',
                            'company'
                        ]),

                        $user
                    )
                );

            } catch (
                \Exception $mailException
            ) {

                Log::error(
                    'Order confirmation email failed',
                    [
                        'message' =>
                            $mailException
                                ->getMessage(),

                        'order_id' =>
                            $order->id,
                    ]
                );
            }


            /*
            |--------------------------------------------------------------------------
            | Success
            |--------------------------------------------------------------------------
            */

            return response()->json([
                'success' => true,

                'message' =>
                    'Order created successfully. Payment is pending.',

                'data' =>
                    $order->fresh([
                        'items.product',
                        'payment',
                        'company'
                    ]),
            ], 201);


        } catch (\Exception $e) {

            DB::rollBack();


            Log::error(
                'Order creation failed',
                [
                    'message' =>
                        $e->getMessage(),

                    'user_id' =>
                        $user->id,
                ]
            );


            return response()->json([
                'success' => false,

                'message' =>
                    'Order creation failed.',

                'error' =>
                    $e->getMessage(),
            ], 400);
        }
    }


    /**
     * Show one order.
     */
    public function show(
        Request $request,
        int $id
    ): JsonResponse {

        $user =
            $request->user();


        $order = Order::with([
            'items.product',
            'payment',
            'user',
            'company'
        ])->findOrFail($id);


        /*
        |--------------------------------------------------------------------------
        | Customer security
        |--------------------------------------------------------------------------
        */

        if (
            $user->role === 'user'
            &&
            (int) $order->user_id
            !==
            (int) $user->id
        ) {

            return response()->json([
                'success' => false,
                'message' =>
                    'Unauthorized.',
            ], 403);
        }


        /*
        |--------------------------------------------------------------------------
        | Company security
        |--------------------------------------------------------------------------
        */

        if (
            $user->role === 'company'
            &&
            (int) $order->company_id
            !==
            (int) $user->company_id
        ) {

            return response()->json([
                'success' => false,
                'message' =>
                    'Unauthorized.',
            ], 403);
        }


        return response()->json([
            'success' => true,
            'data' => $order,
        ]);
    }


    /**
     * Customer cancels unpaid order.
     */
    public function cancel(
        Request $request,
        int $id
    ): JsonResponse {

        $user =
            $request->user();


        if (
            $user->role !== 'user'
        ) {

            return response()->json([
                'success' => false,

                'message' =>
                    'Only users can cancel their own unpaid orders.',
            ], 403);
        }


        DB::beginTransaction();

        try {

            $order = Order::with(
                'payment'
            )
            ->where(
                'id',
                $id
            )
            ->where(
                'user_id',
                $user->id
            )
            ->lockForUpdate()
            ->firstOrFail();


            if (
                ! in_array(
                    $order->status,
                    ['pending_payment']
                )
            ) {

                DB::rollBack();

                return response()->json([
                    'success' => false,

                    'message' =>
                        'Only pending unpaid orders can be cancelled.',
                ], 400);
            }


            if (
                $order->payment
                &&
                $order->payment->status
                === 'paid'
            ) {

                DB::rollBack();

                return response()->json([
                    'success' => false,

                    'message' =>
                        'Paid orders cannot be cancelled here.',
                ], 400);
            }


            $order->update([
                'status' =>
                    'cancelled',
            ]);


            if (
                $order->payment
                &&
                $order->payment->status
                === 'pending'
            ) {

                $order->payment->update([
                    'status' =>
                        'cancelled',
                ]);
            }


            DB::commit();


            return response()->json([
                'success' => true,

                'message' =>
                    'Order cancelled successfully.',

                'data' =>
                    $order->fresh([
                        'items.product',
                        'payment'
                    ]),
            ]);


        } catch (\Exception $e) {

            DB::rollBack();


            return response()->json([
                'success' => false,

                'message' =>
                    'Order cancellation failed.',

                'error' =>
                    $e->getMessage(),
            ], 400);
        }
    }


    /**
     * Company/Admin:
     * Paid -> Processing
     */
    public function markAsProcessing(
        Request $request,
        int $id
    ): JsonResponse {

        $user =
            $request->user();


        if (
            ! in_array(
                $user->role,
                ['admin', 'company']
            )
        ) {

            return response()->json([
                'success' => false,

                'message' =>
                    'Only admins or companies can update fulfilment status.',
            ], 403);
        }


        $order = Order::with(
            'payment'
        )->findOrFail($id);


        /*
        |--------------------------------------------------------------------------
        | Company can only manage its own order
        |--------------------------------------------------------------------------
        */

        if (
            $user->role === 'company'
            &&
            (int) $order->company_id
            !==
            (int) $user->company_id
        ) {

            return response()->json([
                'success' => false,
                'message' =>
                    'Unauthorized.',
            ], 403);
        }


        if (
            $order->status !== 'paid'
        ) {

            return response()->json([
                'success' => false,

                'message' =>
                    'Only paid orders can be moved to processing.',
            ], 400);
        }


        $order->update([
            'status' =>
                'processing',
        ]);


        return response()->json([
            'success' => true,

            'message' =>
                'Order marked as processing.',

            'data' =>
                $order->fresh([
                    'items.product',
                    'payment'
                ]),
        ]);
    }


    /**
     * Company/Admin:
     * Processing -> Completed
     */
    public function markAsCompleted(
        Request $request,
        int $id
    ): JsonResponse {

        $user =
            $request->user();


        if (
            ! in_array(
                $user->role,
                ['admin', 'company']
            )
        ) {

            return response()->json([
                'success' => false,

                'message' =>
                    'Only admins or companies can complete orders.',
            ], 403);
        }


        $order =
            Order::findOrFail($id);


        /*
        |--------------------------------------------------------------------------
        | Company can only manage its own order
        |--------------------------------------------------------------------------
        */

        if (
            $user->role === 'company'
            &&
            (int) $order->company_id
            !==
            (int) $user->company_id
        ) {

            return response()->json([
                'success' => false,
                'message' =>
                    'Unauthorized.',
            ], 403);
        }


        if (
            $order->status
            !== 'processing'
        ) {

            return response()->json([
                'success' => false,

                'message' =>
                    'Only processing orders can be completed.',
            ], 400);
        }


        $order->update([
            'status' =>
                'completed',
        ]);


        return response()->json([
            'success' => true,

            'message' =>
                'Order completed successfully.',

            'data' =>
                $order->fresh([
                    'items.product',
                    'payment'
                ]),
        ]);
    }


    /**
     * Admin cancels unpaid/problematic order.
     */
    public function adminCancel(
        Request $request,
        int $id
    ): JsonResponse {

        if (
            $request->user()->role
            !== 'admin'
        ) {

            return response()->json([
                'success' => false,

                'message' =>
                    'Only admins can cancel orders.',
            ], 403);
        }


        DB::beginTransaction();

        try {

            $order = Order::with(
                'payment'
            )
            ->lockForUpdate()
            ->findOrFail($id);


            if (
                in_array(
                    $order->status,
                    [
                        'paid',
                        'processing',
                        'completed'
                    ]
                )
            ) {

                DB::rollBack();

                return response()->json([
                    'success' => false,

                    'message' =>
                        'Paid, processing, or completed orders should not be cancelled from this endpoint.',
                ], 400);
            }


            $order->update([
                'status' =>
                    'cancelled',
            ]);


            if (
                $order->payment
                &&
                $order->payment->status
                === 'pending'
            ) {

                $order->payment->update([
                    'status' =>
                        'cancelled',
                ]);
            }


            DB::commit();


            return response()->json([
                'success' => true,

                'message' =>
                    'Order cancelled successfully.',

                'data' =>
                    $order->fresh([
                        'items.product',
                        'payment'
                    ]),
            ]);


        } catch (\Exception $e) {

            DB::rollBack();


            return response()->json([
                'success' => false,

                'message' =>
                    'Admin cancellation failed.',

                'error' =>
                    $e->getMessage(),
            ], 400);
        }
    }


    /**
     * Admin expires unpaid order.
     */
    public function adminExpire(
        Request $request,
        int $id
    ): JsonResponse {

        if (
            $request->user()->role
            !== 'admin'
        ) {

            return response()->json([
                'success' => false,

                'message' =>
                    'Only admins can expire orders.',
            ], 403);
        }


        DB::beginTransaction();

        try {

            $order = Order::with(
                'payment'
            )
            ->lockForUpdate()
            ->findOrFail($id);


            if (
                $order->status
                !== 'pending_payment'
            ) {

                DB::rollBack();

                return response()->json([
                    'success' => false,

                    'message' =>
                        'Only pending payment orders can be expired.',
                ], 400);
            }


            $order->update([
                'status' =>
                    'expired',
            ]);


            if (
                $order->payment
                &&
                $order->payment->status
                === 'pending'
            ) {

                $order->payment->update([

                    'status' =>
                        'expired',

                    'expired_at' =>
                        now(),
                ]);
            }


            DB::commit();


            return response()->json([
                'success' => true,

                'message' =>
                    'Order expired successfully.',

                'data' =>
                    $order->fresh([
                        'items.product',
                        'payment'
                    ]),
            ]);


        } catch (\Exception $e) {

            DB::rollBack();


            return response()->json([
                'success' => false,

                'message' =>
                    'Order expiry failed.',

                'error' =>
                    $e->getMessage(),
            ], 400);
        }
    }
}