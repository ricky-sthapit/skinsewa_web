<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json(['data' => ['user' => $this->payload($request)]]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone' => ['nullable', 'string', 'max:32'],
            'facility_name' => ['nullable', 'string', 'max:150'],
            'specialty' => ['nullable', 'string', 'max:120'],
        ]);
        $request->user()->fill($data)->save();
        return response()->json(['message' => 'Profile updated.', 'data' => ['user' => $this->payload($request)]]);
    }

    private function payload(Request $request): array
    {
        $user = $request->user();
        return [
            'id' => $user->id, 'first_name' => $user->first_name, 'last_name' => $user->last_name,
            'name' => $user->full_name, 'email' => $user->email, 'role' => $user->role(),
            'phone' => $user->phone, 'facility_name' => $user->facility_name, 'specialty' => $user->specialty,
        ];
    }
}
