<?php

namespace App\Http\Controllers;

use App\Models\ConsultantRequest;
use App\Support\PhoneNumberNormalizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ConsultantRequestController extends Controller
{
    public function store(Request $request, PhoneNumberNormalizer $normalizer): RedirectResponse
    {
        $request->merge([
            'phone_number' => $normalizer->normalize((string) $request->input('phone_number')),
        ]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'phone_number' => ['required', 'regex:/^\+[1-9][0-9]{7,14}$/'],
            'concern' => ['nullable', 'string', 'max:1000'],
        ], [
            'phone_number.regex' => 'Enter a valid phone number, for example 024 123 4567.',
        ]);

        $consultantRequest = ConsultantRequest::query()->create([
            'name' => trim($validated['name']),
            'normalized_phone' => $validated['phone_number'],
            'concern' => ($concern = trim((string) ($validated['concern'] ?? ''))) !== '' ? $concern : null,
            'status' => 'NEW',
        ]);

        $request->session()->flash('consultant_request_reference', $consultantRequest->getKey());

        return to_route('home', ['consult' => 'human']);
    }
}
