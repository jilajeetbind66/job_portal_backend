<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /**
     * Register a new candidate.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create([
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'phone' => $request->validated('phone'),
            'password' => Hash::make($request->validated('password')),
            'partner_id' => null,
            'status' => 'active',
        ]);

        $user->assignRole('candidate');

        Auth::login($user);

        $request->session()->regenerate();

        $user->update([
            'last_login_at' => now(),
        ]);

        return response()->json([
            'message' => 'Registration successful.',
            'user' => $this->userData($user),
        ], 201);
    }

    /**
     * Login an existing user.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->validated();

        $user = User::where('email', $credentials['email'])->first();

        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            return response()->json([
                'message' => 'Invalid email or password.',
            ], 422);
        }

        if ($user->status !== 'active') {
            return response()->json([
                'message' => 'Your account is suspended.',
            ], 403);
        }

        if (
            $user->partner_id !== null &&
            $user->partner &&
            $user->partner->status !== 'active'
        ) {
            return response()->json([
                'message' => 'Your partner account is suspended.',
            ], 403);
        }

        Auth::login($user);

        $request->session()->regenerate();

        $user->update([
            'last_login_at' => now(),
        ]);

        return response()->json([
            'message' => 'Login successful.',
            'user' => $this->userData($user),
        ]);
    }

    /**
     * Return the currently authenticated user.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'user' => $this->userData($user),
        ]);
    }

    /**
     * Logout the current user.
     */
    public function logout(Request $request): JsonResponse
{
    Auth::guard('web')->logout();

    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return response()->json([
        'message' => 'Logout successful.',
    ]);
}

    /**
     * Format authenticated user data.
     */
    private function userData(User $user): array
    {
        $user->loadMissing('roles', 'partner');

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'status' => $user->status,
            'partner_id' => $user->partner_id,
            'partner' => $user->partner ? [
                'id' => $user->partner->id,
                'name' => $user->partner->name,
                'slug' => $user->partner->slug,
                'status' => $user->partner->status,
            ] : null,
            'role' => $user->roles->first()?->name,
            'last_login_at' => $user->last_login_at,
        ];
    }
}