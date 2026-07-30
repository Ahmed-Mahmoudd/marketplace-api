<?php

namespace App\Http\Controllers\Api\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Vendor;
use App\Services\VendorAnalyticsService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Read-only analytics feed for the vendor dashboard.
 *
 * Tenancy is enforced here, not by the client: the vendor is resolved from the
 * authenticated token, never from a request parameter, so a vendor cannot ask
 * for another vendor's data.
 */
class AnalyticsController extends Controller
{
  public function __construct(
    private VendorAnalyticsService $analytics
  ) {}

  public function dataset(Request $request)
  {
    $validated = $request->validate([
      'since'    => ['nullable', 'date'],
      'until'    => ['nullable', 'date', 'after_or_equal:since'],
      'tables'   => ['nullable', 'array'],
      'tables.*' => ['string', 'in:' . implode(',', VendorAnalyticsService::TABLES)],
    ]);

    $vendor = $request->user()->vendor;

    if (! $vendor instanceof Vendor) {
      return $this->error('No vendor profile found for this account.', 404);
    }

    if (! $vendor->isApproved()) {
      return $this->error('Your vendor account is not approved yet.', 403);
    }

    $dataset = $this->analytics->dataset(
      $vendor,
      isset($validated['since']) ? Carbon::parse($validated['since']) : null,
      isset($validated['until']) ? Carbon::parse($validated['until']) : null,
      $validated['tables'] ?? null,
    );

    return $this->success([
      'vendor' => [
        'id'         => $vendor->id,
        'store_name' => $vendor->store_name,
        'store_slug' => $vendor->store_slug,
        'status'     => $vendor->status,
      ],
      'generated_at' => now()->toIso8601String(),
      'tables'       => $dataset,
      'counts'       => array_map('count', $dataset),
    ]);
  }
}
