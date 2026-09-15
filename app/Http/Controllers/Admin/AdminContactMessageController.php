<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminContactMessageController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = ContactMessage::query()->latest();

        if ($request->filled('status')) {
            match ($request->string('status')->toString()) {
                'unread' => $query->where('is_read', false),
                'read' => $query->where('is_read', true),
                'resolved' => $query->where('is_resolved', true),
                'open' => $query->where('is_resolved', false),
                default => null,
            };
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->get('search'));
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('subject', 'like', "%{$search}%");
            });
        }

        return response()->json([
            'success' => true,
            'data' => $query->paginate(20),
            'unread_count' => ContactMessage::where('is_read', false)->count(),
            'open_count' => ContactMessage::where('is_resolved', false)->count(),
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $message = ContactMessage::findOrFail($id);

        if (! $message->is_read) {
            $message->update([
                'is_read' => true,
                'read_at' => now(),
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => $message->fresh(),
        ]);
    }

    public function markAsRead(int $id): JsonResponse
    {
        $message = ContactMessage::findOrFail($id);

        if (! $message->is_read) {
            $message->update([
                'is_read' => true,
                'read_at' => now(),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Contact message marked as read.',
            'data' => $message->fresh(),
        ]);
    }

    public function markAsResolved(int $id): JsonResponse
    {
        $message = ContactMessage::findOrFail($id);

        $message->update([
            'is_read' => true,
            'read_at' => $message->read_at ?? now(),
            'is_resolved' => true,
            'resolved_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Contact message resolved.',
            'data' => $message->fresh(),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        ContactMessage::findOrFail($id)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Contact message deleted.',
        ]);
    }
}
