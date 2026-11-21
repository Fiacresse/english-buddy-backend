<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ProgressService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);

        $user = User::create($data);

        return response()->json([
            'token' => $user->createToken('web')->plainTextToken,
            'user' => self::present($user),
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['email' => 'Email ou mot de passe incorrect.']);
        }

        return response()->json([
            'token' => $user->createToken('web')->plainTextToken,
            'user' => self::present($user),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(self::present($request->user()));
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['ok' => true]);
    }

    public static function present(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'level' => $user->level,
            'goals' => $user->goals ?? [],
            'daily_goal_minutes' => $user->daily_goal_minutes,
            'xp' => $user->xp,
            'streak' => app(ProgressService::class)->currentStreak($user),
            'onboarded' => $user->onboarded,
            'created_at' => $user->created_at?->toIso8601String(),
        ];
    }
}
