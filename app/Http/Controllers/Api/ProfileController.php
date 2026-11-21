<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:80'],
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'goals' => ['sometimes', 'array', 'max:8'],
            'goals.*' => ['string', 'max:40'],
            'daily_goal_minutes' => ['sometimes', 'integer', 'min:5', 'max:120'],
            'level' => ['sometimes', 'nullable', Rule::in(['A1', 'A2', 'B1', 'B2', 'C1', 'C2'])],
            'onboarded' => ['sometimes', 'boolean'],
        ]);

        $user->update($data);

        return response()->json(AuthController::present($user->fresh()));
    }

    public function password(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);

        $request->user()->update(['password' => $request->input('password')]);

        return response()->json(['ok' => true]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $request->validate(['password' => ['required', 'current_password']]);

        $user = $request->user();
        $user->tokens()->delete();
        $user->delete();

        return response()->json(['ok' => true]);
    }
}
