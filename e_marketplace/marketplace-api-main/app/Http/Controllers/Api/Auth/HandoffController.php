<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\HandoffCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Single sign-on handoff between the storefront and the analytics dashboard.
 *
 * The storefront (already authenticated) asks for a short-lived code and puts
 * it in the dashboard's URL; the dashboard trades it for its own token. The
 * bearer token itself never travels in a URL, so it can't leak through browser
 * history, referrer headers or proxy logs.
 */
class HandoffController extends Controller
{
  /** POST /api/auth/handoff — authenticated; mints a one-time code. */
  public function issue(Request $request)
  {
    // Opportunistic cleanup; these rows are worthless once they lapse.
    HandoffCode::query()->where('expires_at', '<', now()->subMinutes(5))->delete();

    $code = HandoffCode::issueFor($request->user());

    return $this->success([
      'code'       => $code,
      'expires_in' => HandoffCode::TTL_SECONDS,
    ], 'Handoff code issued.');
  }

  /**
   * POST /api/auth/handoff/redeem — public by necessity: the caller has no
   * token yet, the code *is* the credential. Redemption is atomic so a code
   * can never be spent twice.
   */
  public function redeem(Request $request)
  {
    $validated = $request->validate([
      'code' => ['required', 'string'],
    ]);

    $hash = hash('sha256', $validated['code']);

    $record = DB::transaction(function () use ($hash) {
      $record = HandoffCode::query()
        ->where('code_hash', $hash)
        ->lockForUpdate()
        ->first();

      if (! $record || ! $record->isRedeemable()) {
        return null;
      }

      $record->update(['redeemed_at' => now()]);

      return $record;
    });

    if (! $record) {
      // Deliberately vague: don't reveal whether a code was wrong, already
      // used, or merely expired.
      return $this->error('This sign-in link is invalid or has expired.', 401);
    }

    $user = $record->user;

    if (! $user) {
      return $this->error('This sign-in link is invalid or has expired.', 401);
    }

    $token = $user->createToken('analytics-dashboard')->plainTextToken;

    return $this->success([
      'user'  => new UserResource($user->load(['roles', 'vendor'])),
      'token' => $token,
    ], 'Signed in.');
  }
}
