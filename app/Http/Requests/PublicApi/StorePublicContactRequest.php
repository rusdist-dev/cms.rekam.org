<?php

namespace App\Http\Requests\PublicApi;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The only public write in the whole system (plan.md Fase 6), so this is
 * deliberately narrow: no `status`/`ip` field accepted from the caller — the
 * controller forces those server-side, matching ContactMessage's fillable
 * and migration default exactly.
 */
class StorePublicContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'subject' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
            // Honeypot: hidden via CSS on the real form, so a genuine visitor
            // never fills it — any value here means a bot did.
            'website' => ['nullable', 'string'],
        ];
    }

    public function isHoneypotTripped(): bool
    {
        return filled($this->input('website'));
    }
}
