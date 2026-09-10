<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminNotificationController extends Controller
{
    /**
     * Get Admin/Super Admin notifications.
     */
    public function index(Request $request): JsonResponse
    {
        $limit = (int) $request->get('limit', 15);

        if ($limit < 1) {
            $limit = 15;
        }

        if ($limit > 50) {
            $limit = 50;
        }

        $notifications = AdminNotification::query()
            ->latest()
            ->limit($limit)
            ->get();

        $unreadCount = AdminNotification::where(
            'is_read',
            false
        )->count();

        return response()->json([
            'success' => true,
            'data' => $notifications,
            'unread_count' => $unreadCount,
        ]);
    }

    /**
     * Get unread notification count.
     */
    public function unreadCount(Request $request): JsonResponse
    {
        $unreadCount = AdminNotification::where(
            'is_read',
            false
        )->count();

        return response()->json([
            'success' => true,
            'unread_count' => $unreadCount,
        ]);
    }

    /**
     * Mark one notification as read.
     */
    public function markAsRead(
        Request $request,
        int $id
    ): JsonResponse {
        $notification = AdminNotification::findOrFail($id);

        if (! $notification->is_read) {
            $notification->update([
                'is_read' => true,
                'read_at' => now(),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Notification marked as read.',
            'data' => $notification->fresh(),
        ]);
    }

    /**
     * Mark all notifications as read.
     */
    public function markAllAsRead(
        Request $request
    ): JsonResponse {
        AdminNotification::where(
            'is_read',
            false
        )->update([
            'is_read' => true,
            'read_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'All notifications marked as read.',
        ]);
    }

    /**
     * Delete one notification.
     */
    public function destroy(
        Request $request,
        int $id
    ): JsonResponse {
        $notification = AdminNotification::findOrFail($id);

        $notification->delete();

        return response()->json([
            'success' => true,
            'message' => 'Notification deleted successfully.',
        ]);
    }

    /**
     * Delete all notifications.
     */
    public function clearAll(
        Request $request
    ): JsonResponse {
        AdminNotification::query()->delete();

        return response()->json([
            'success' => true,
            'message' => 'All notifications cleared successfully.',
        ]);
    }
}