<?php

namespace App\Http\Controllers;

use App\Models\TenantNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;


class TenantNotificationController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Get tenant notifications
    |--------------------------------------------------------------------------
    */

    public function index(
        Request $request
    ): JsonResponse
    {
        $user = $request->user();

        if (
            $user->role !== 'company' ||
            !$user->company_id
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Company account required.',
            ], 403);
        }

        $limit = (int) $request->get(
            'limit',
            15
        );

        $limit = max(
            1,
            min($limit, 50)
        );


        $notifications =
            TenantNotification::where(
                'company_id',
                $user->company_id
            )
            ->latest()
            ->limit($limit)
            ->get();


        $unreadCount =
            TenantNotification::where(
                'company_id',
                $user->company_id
            )
            ->where(
                'is_read',
                false
            )
            ->count();


        return response()->json([
            'success' => true,

            'data' => $notifications,

            'unread_count' =>
                $unreadCount,
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | Unread count
    |--------------------------------------------------------------------------
    */

    public function unreadCount(
        Request $request
    ): JsonResponse
    {
        $user = $request->user();

        if (
            $user->role !== 'company' ||
            !$user->company_id
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Company account required.',
            ], 403);
        }


        $count =
            TenantNotification::where(
                'company_id',
                $user->company_id
            )
            ->where(
                'is_read',
                false
            )
            ->count();


        return response()->json([
            'success' => true,
            'unread_count' => $count,
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | Mark one notification as read
    |--------------------------------------------------------------------------
    */

    public function markAsRead(
        Request $request,
        int $id
    ): JsonResponse
    {
        $user = $request->user();

        $notification =
            TenantNotification::where(
                'company_id',
                $user->company_id
            )
            ->findOrFail($id);


        if (!$notification->is_read) {

            $notification->update([
                'is_read' => true,
                'read_at' => now(),
            ]);

        }


        return response()->json([
            'success' => true,
            'message' =>
                'Notification marked as read.',
            'data' => $notification,
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | Mark all notifications as read
    |--------------------------------------------------------------------------
    */

    public function markAllAsRead(
        Request $request
    ): JsonResponse
    {
        $user = $request->user();


        TenantNotification::where(
            'company_id',
            $user->company_id
        )
        ->where(
            'is_read',
            false
        )
        ->update([
            'is_read' => true,
            'read_at' => now(),
        ]);


        return response()->json([
            'success' => true,
            'message' =>
                'All notifications marked as read.',
        ]);
    }

    public function destroy(
    Request $request,
    int $id
): JsonResponse
{
    $user = $request->user();

    if (
        $user->role !== 'company' ||
        !$user->company_id
    ) {
        return response()->json([
            'success' => false,
            'message' => 'Company account required.',
        ], 403);
    }

    $notification =
        TenantNotification::where(
            'company_id',
            $user->company_id
        )
        ->findOrFail($id);

    $notification->delete();

    return response()->json([
        'success' => true,
        'message' => 'Notification deleted.',
    ]);
}


    public function clearAll(
        Request $request
    ): JsonResponse
    {
        $user = $request->user();

        if (
            $user->role !== 'company' ||
            !$user->company_id
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Company account required.',
            ], 403);
        }

        TenantNotification::where(
            'company_id',
            $user->company_id
        )->delete();

        return response()->json([
            'success' => true,
            'message' => 'All notifications cleared.',
        ]);
    }
}