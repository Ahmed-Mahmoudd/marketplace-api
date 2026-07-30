<?php

namespace App\Http\Middleware;

use App\Enums\IdempotencyStatus;
use App\Models\IdempotencyKey;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class EnsureIdempotency
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header('Idempotency-Key');

        if (! $key) {
            return response()->json([
                'message' => 'The Idempotency-Key header is required for this request.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $endpoint = $request->method() . ' ' . $request->path();
        $userId = $request->user()?->id;
        $requestHash = $this->generateRequestHash($request);

        $record = DB::transaction(function () use ($key, $endpoint, $userId, $requestHash) {
            $existing = IdempotencyKey::query()
                ->where('key', $key)
                ->where('endpoint', $endpoint)
                ->where('user_id', $userId)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                return $existing;
            }

            return IdempotencyKey::create([
                'key' => $key,
                'endpoint' => $endpoint,
                'user_id' => $userId,
                'request_hash' => $requestHash,
                'status' => IdempotencyStatus::InProgress,
                'expires_at' => now()->addHours(config('idempotency.ttl')),
            ]);
        });

        // First request owns the execution.
        if ($record->wasRecentlyCreated) {
            $response = $next($request);

            if ($response instanceof JsonResponse) {
                $record->update([
                    'status' => IdempotencyStatus::Completed,
                    'response_status' => $response->getStatusCode(),
                    'response_body' => $response->getContent(),
                ]);
            }

            return $response;
        }

        // Same key but different payload.
        if ($record->request_hash !== $requestHash) {
            return response()->json([
                'message' => 'This Idempotency-Key was already used with a different request payload.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Replay stored response.
        if ($record->isCompleted()) {
            return response(
                $record->response_body,
                $record->response_status
            )
                ->header('Content-Type', 'application/json')
                ->header('Idempotency-Replayed', 'true');
        }

        // Duplicate request while the first one is still processing.
        return response()->json([
            'message' => 'A request with this Idempotency-Key is already being processed.',
        ], Response::HTTP_CONFLICT);
    }

    /**
     * Generate a deterministic hash for the request payload.
     */
    private function generateRequestHash(Request $request): string
    {
        $payload = $request->except('_token');

        $this->sortRecursive($payload);

        return hash(
            'sha256',
            json_encode(
                $payload,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            )
        );
    }

    /**
     * Recursively sort all associative arrays to ensure
     * deterministic hashing regardless of key order.
     */
    private function sortRecursive(array &$array): void
    {
        if ($this->isAssociative($array)) {
            ksort($array);
        }

        foreach ($array as &$value) {
            if (is_array($value)) {
                $this->sortRecursive($value);
            }
        }
    }

    /**
     * Determine whether the array is associative.
     */
    private function isAssociative(array $array): bool
    {
        return array_keys($array) !== range(0, count($array) - 1);
    }
}
