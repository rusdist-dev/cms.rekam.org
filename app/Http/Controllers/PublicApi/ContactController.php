<?php

namespace App\Http\Controllers\PublicApi;

use App\Http\Controllers\Controller;
use App\Http\Requests\PublicApi\StorePublicContactRequest;
use App\Models\ContactMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class ContactController extends Controller
{
    public function store(StorePublicContactRequest $request): JsonResponse
    {
        // A tripped honeypot is accepted-but-discarded — telling a bot it was
        // caught only teaches it to stop filling that field (plan.md Fase 6:
        // "throttle + honeypot").
        if ($request->isHoneypotTripped()) {
            // The 201 below is a lie told to bots, so the log is the only place
            // a discard is visible — without it, an integrator whose form has a
            // real `website` input sees "berhasil" and an empty inbox forever.
            Log::info('Pesan kontak dibuang: honeypot `website` terisi.', [
                'ip' => $request->ip(),
                'email' => $request->validated('email'),
            ]);
        } else {
            ContactMessage::create([
                'name' => $request->validated('name'),
                'email' => $request->validated('email'),
                'phone' => $request->validated('phone'),
                'subject' => $request->validated('subject'),
                'message' => $request->validated('message'),
                'address' => $request->validated('address'),
                'is_private' => $request->boolean('is_private'),
                'status' => 'unread',
                'ip' => $request->ip(),
            ]);
        }

        return response()->json(['message' => 'Pesan Anda telah terkirim.'], 201);
    }
}
