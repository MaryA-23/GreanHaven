<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use App\Services\AdminNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContactMessageController extends Controller
{
    public function __construct(
        private readonly AdminNotificationService $adminNotifications
    ) {
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190'],
            'subject' => ['required', 'string', 'max:190'],
            'message' => ['required', 'string', 'max:5000'],
        ]);

        $contactMessage = ContactMessage::create($validated);

        $this->adminNotifications->contactMessage(
            $contactMessage->id,
            $contactMessage->name,
            $contactMessage->subject
        );

        return response()->json([
            'success' => true,
            'message' => 'Your message has been sent successfully.',
            'data' => [
                'id' => $contactMessage->id,
            ],
        ], 201);
    }
}
