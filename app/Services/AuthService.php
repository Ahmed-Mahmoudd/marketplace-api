<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class AuthService
{
  public function register(array $data): array
  {
    return DB::transaction(function () use ($data) {

      $user = User::create([
        'name' => $data['name'],
        'email' => $data['email'],
        'password' => Hash::make($data['password']),
        'gender' => $data['gender'],
        'city' => $data['city'],
      ]);

      $user->assignRole('customer');

      $token = $user->createToken('api')->plainTextToken;

      return [
        'user' => $user->load(['roles', 'vendor']),
        'token' => $token,
      ];
    });
  }

  public function login(array $credentials): array
  {
    $user = User::where('email', $credentials['email'])->first();

    if (! $user || ! Hash::check($credentials['password'], $user->password)) {
      throw ValidationException::withMessages([
        'email' => ['Invalid credentials.'],
      ]);
    }

    $token = $user->createToken('api')->plainTextToken;

    return [
      'user' => $user->load(['roles', 'vendor']),
      'token' => $token,
    ];
  }

  public function logout(User $user): void
  {
    /** @var PersonalAccessToken|null $token */
    $token = $user->currentAccessToken();

    if ($token !== null) {
      $token->delete();
    }
  }

  public function logoutFromAllDevices(User $user): void
  {
    $user->tokens()->delete();
  }

  public function me(User $user): User
  {
    return $user->load(['roles', 'vendor']);
  }
}
