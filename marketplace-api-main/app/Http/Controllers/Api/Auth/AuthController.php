<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Services\AuthService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(
        private AuthService $authService
    ) {}

    public function register(RegisterRequest $request)
    {
        $data = $this->authService->register($request->validated());

        return $this->success([
            'user' => new UserResource($data['user']),
            'token' => $data['token'],
        ], 'Registered successfully.', 201);
    }

    public function login(LoginRequest $request)
    {
        try {

            $data = $this->authService->login($request->validated());

            return $this->success([
                'user' => new UserResource($data['user']),
                'token' => $data['token'],
            ], 'Logged in successfully.');
        } catch (ValidationException $e) {

            return $this->error('Invalid credentials.', 401);
        }
    }

    public function logout(Request $request)
    {
        $this->authService->logout($request->user());

        return $this->success(
            null,
            'Logged out successfully.'
        );
    }

    public function me(Request $request)
    {
        return $this->success(
            new UserResource(
                $this->authService->me($request->user())
            )
        );
    }
}
