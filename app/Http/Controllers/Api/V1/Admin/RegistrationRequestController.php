<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Services\RegistrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RegistrationRequestController extends Controller
{
    public function __construct(private readonly RegistrationService $registrations) {}

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email:rfc', 'max:100'],
            'role' => ['required', 'in:MEDICAL_OFFICER,DERMATOLOGIST'],
            'facility_name' => ['nullable', 'string', 'max:150'],
        ]);

        $invitation = $this->registrations->invite(
            $data['email'],
            $data['role'],
            $data['facility_name'] ?? null,
            (int) $request->user()->id,
        );

        return response()->json([
            'message' => 'Registration invitation sent.',
            'data' => [
                'email' => $invitation->email,
                'role' => $invitation->role,
                'facility_name' => $invitation->facility_name,
                'expires_at' => $invitation->otp_expires_at,
            ],
        ], 201);
    }
}
