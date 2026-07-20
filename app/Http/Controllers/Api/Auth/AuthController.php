<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function register(RegisterRequest $request)
    {
        $user = User::create([
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'password' => Hash::make($request->validated('password')),
            'gender' => $request->validated('gender'),
            'city' => $request->validated('city'),
        ]);

        // Everyone starts as a customer; the vendor role is granted on admin approval (see VendorAdminController).
        $user->assignRole('customer');

        $token = $user->createToken('api')->plainTextToken;

        return $this->success([
            'user' => new UserResource($user->load('roles')),
            'token' => $token,
        ], 'Registered successfully.', 201);
    }

    public function login(LoginRequest $request)
    {
        $user = User::where('email', $request->validated('email'))->first();

        if (! $user || ! Hash::check($request->validated('password'), $user->password)) {
            return $this->error('Invalid credentials.', 401);
        }

        $token = $user->createToken('api')->plainTextToken;

        return $this->success([
            'user' => new UserResource($user->load('roles')),
            'token' => $token,
        ], 'Logged in successfully.');
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return $this->success(null, 'Logged out successfully.');
    }

    public function me(Request $request)
    {
        return $this->success(
            new UserResource($request->user()->load(['roles', 'vendor']))
        );
    }
}
