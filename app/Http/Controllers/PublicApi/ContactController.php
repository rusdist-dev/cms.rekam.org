<?php

namespace App\Http\Controllers\PublicApi;

use App\Http\Controllers\Controller;
use App\Http\Requests\PublicApi\StorePublicContactRequest;
use App\Models\ContactMessage;
use Illuminate\Http\JsonResponse;

class ContactController extends Controller
{
    public function store(StorePublicContactRequest $request): JsonResponse
    {
        // A tripped honeypot is accepted-but-discarded — telling a bot it was
        // caught only teaches it to stop filling that field (plan.md Fase 6:
        // "throttle + honeypot").
        if (! $request->isHoneypotTripped()) {
            ContactMessage::create([
                'name' => $request->validated('name'),
                'email' => $request->validated('email'),
                'phone' => $request->validated('phone'),
                'subject' => $request->validated('subject'),
                'message' => $request->validated('message'),
                'status' => 'unread',
                'ip' => $request->ip(),
            ]);
        }

        return response()->json(['message' => 'Pesan Anda telah terkirim.'], 201);
    }
}
