<?php

namespace App\Services;

use App\Models\Vendor;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Builds the analytics dataset consumed by the Streamlit dashboard.
 *
 * Every query here is scoped to a single vendor. The column names deliberately
 * mirror the shape the dashboard's services and notebooks already expect
 * (product_id / product_name / order_date / item_total / ...), so the dashboard
 * can swap its CSV loader for this API without rewriting any view.
 *
 * Revenue note: `orders.total` covers the whole basket, which in a multi-vendor
 * marketplace spans several vendors. A vendor's `total_amount` is therefore
 * recomputed as the sum of *their own* line items on that order — never the
 * order total, which would leak other vendors' revenue into their dashboard.
 */
class VendorAnalyticsService
{
  public const TABLES = [
    'products',
    'customers',
    'orders',
    'order_items',
    'reviews',
    'events',
  ];

  /** Hard ceiling per table, so one call can never pull an unbounded result set. */
  private const MAX_ROWS = 50000;

  /**
   * @param  string[]|null  $only  Restrict to a subset of self::TABLES.
   * @return array<string, array<int, array<string, mixed>>>
   */
  public function dataset(
    Vendor $vendor,
    ?Carbon $since = null,
    ?Carbon $until = null,
    ?array $only = null
  ): array {
    $tables = $only ? array_intersect(self::TABLES, $only) : self::TABLES;
    $out = [];

    foreach ($tables as $table) {
      $out[$table] = match ($table) {
        'products'    => $this->products($vendor),
        'customers'   => $this->customers($vendor),
        'orders'      => $this->orders($vendor, $since, $until),
        'order_items' => $this->orderItems($vendor, $since, $until),
        'reviews'     => $this->reviews($vendor, $since, $until),
        'events'      => $this->events($vendor, $since, $until),
      };
    }

    return $out;
  }

  /**
   * The catalog. `brand` has no column in this schema — the vendor's store name
   * is the meaningful equivalent, and keeps the dashboard's brand-level charts
   * working. `rating` is derived from reviews.
   */
  private function products(Vendor $vendor): array
  {
    $rows = DB::table('products as p')
      ->leftJoin('categories as c', 'c.id', '=', 'p.category_id')
      ->where('p.vendor_id', $vendor->id)
      ->select([
        'p.id as product_id',
        'p.name as product_name',
        'p.slug',
        DB::raw('coalesce(c.name, \'Uncategorised\') as category'),
        'p.price',
        'p.stock',
        'p.status',
        'p.created_at',
        DB::raw('(select avg(r.rating) from reviews r where r.product_id = p.id) as rating'),
        DB::raw('(select count(*) from reviews r where r.product_id = p.id) as review_count'),
      ])
      ->orderBy('p.id')
      ->limit(self::MAX_ROWS)
      ->get();

    return $rows->map(fn($r) => [
      'product_id'   => $r->product_id,
      'product_name' => $r->product_name,
      'slug'         => $r->slug,
      'category'     => $r->category,
      'brand'        => $vendor->store_name,
      'price'        => (float) $r->price,
      'stock'        => (int) $r->stock,
      'status'       => $r->status,
      'rating'       => $r->rating === null ? null : round((float) $r->rating, 2),
      'review_count' => (int) $r->review_count,
      'created_at'   => $r->created_at,
    ])->all();
  }

  /** Customers who have actually bought from this vendor. */
  private function customers(Vendor $vendor): array
  {
    return DB::table('users as u')
      ->join('orders as o', 'o.user_id', '=', 'u.id')
      ->join('order_items as oi', 'oi.order_id', '=', 'o.id')
      ->where('oi.vendor_id', $vendor->id)
      ->select([
        'u.id as user_id',
        'u.name',
        'u.email',
        'u.gender',
        'u.city',
        'u.created_at as signup_date',
      ])
      ->distinct()
      ->orderBy('u.id')
      ->limit(self::MAX_ROWS)
      ->get()
      ->map(fn($r) => (array) $r)
      ->all();
  }

  /**
   * Orders containing at least one of this vendor's items, with `total_amount`
   * reduced to the vendor's own share of the basket.
   */
  private function orders(Vendor $vendor, ?Carbon $since, ?Carbon $until): array
  {
    $query = DB::table('orders as o')
      ->join('order_items as oi', 'oi.order_id', '=', 'o.id')
      ->where('oi.vendor_id', $vendor->id)
      ->select([
        'o.id as order_id',
        'o.user_id',
        'o.created_at as order_date',
        'o.status as order_status',
        DB::raw('sum(oi.subtotal) as total_amount'),
      ])
      ->groupBy('o.id', 'o.user_id', 'o.created_at', 'o.status');

    $this->applyDateRange($query, 'o.created_at', $since, $until);

    return $query->orderBy('o.id')
      ->limit(self::MAX_ROWS)
      ->get()
      ->map(fn($r) => [
        'order_id'     => $r->order_id,
        'user_id'      => $r->user_id,
        'order_date'   => $r->order_date,
        'order_status' => $r->order_status,
        'total_amount' => (float) $r->total_amount,
      ])
      ->all();
  }

  /**
   * Line items belonging to this vendor, with the buyer pulled off the order.
   *
   * Columns are held to exactly the dashboard's contract. `product_name` and
   * `order_status` are deliberately omitted even though they sit right there
   * on the row: the dashboard merges order_items with products and with orders
   * on several pages, and duplicate names would silently become `_x`/`_y`
   * suffixed columns and break those charts.
   */
  private function orderItems(Vendor $vendor, ?Carbon $since, ?Carbon $until): array
  {
    $query = DB::table('order_items as oi')
      ->join('orders as o', 'o.id', '=', 'oi.order_id')
      ->where('oi.vendor_id', $vendor->id)
      ->select([
        'oi.id as order_item_id',
        'oi.order_id',
        'oi.product_id',
        'o.user_id',
        'oi.quantity',
        'oi.unit_price as item_price',
        'oi.subtotal as item_total',
      ]);

    $this->applyDateRange($query, 'o.created_at', $since, $until);

    return $query->orderBy('oi.id')
      ->limit(self::MAX_ROWS)
      ->get()
      ->map(fn($r) => [
        'order_item_id' => $r->order_item_id,
        'order_id'      => $r->order_id,
        'product_id'    => $r->product_id,
        'user_id'       => $r->user_id,
        'quantity'      => (int) $r->quantity,
        'item_price'    => (float) $r->item_price,
        'item_total'    => (float) $r->item_total,
      ])
      ->all();
  }

  /**
   * Reviews on this vendor's products. This schema has no review->order link,
   * so `order_id` is emitted as null to keep the dashboard's column contract.
   */
  private function reviews(Vendor $vendor, ?Carbon $since, ?Carbon $until): array
  {
    $query = DB::table('reviews as r')
      ->join('products as p', 'p.id', '=', 'r.product_id')
      ->where('p.vendor_id', $vendor->id)
      ->select([
        'r.id as review_id',
        DB::raw('null as order_id'),
        'r.product_id',
        'r.user_id',
        'r.rating',
        'r.comment as review_text',
        'r.created_at as review_date',
      ]);

    $this->applyDateRange($query, 'r.created_at', $since, $until);

    return $query->orderBy('r.id')
      ->limit(self::MAX_ROWS)
      ->get()
      ->map(fn($r) => [
        'review_id'   => $r->review_id,
        'order_id'    => $r->order_id,
        'product_id'  => $r->product_id,
        'user_id'     => $r->user_id,
        'rating'      => (int) $r->rating,
        'review_text' => $r->review_text,
        'review_date' => $r->review_date,
      ])
      ->all();
  }

  /** Behavioural funnel events for this vendor's products. */
  private function events(Vendor $vendor, ?Carbon $since, ?Carbon $until): array
  {
    $query = DB::table('events as e')
      ->where('e.vendor_id', $vendor->id)
      ->select([
        'e.id as event_id',
        'e.user_id',
        'e.product_id',
        'e.type as event_type',
        'e.session_id',
        'e.created_at as event_timestamp',
      ]);

    $this->applyDateRange($query, 'e.created_at', $since, $until);

    return $query->orderBy('e.id')
      ->limit(self::MAX_ROWS)
      ->get()
      ->map(fn($r) => (array) $r)
      ->all();
  }

  private function applyDateRange($query, string $column, ?Carbon $since, ?Carbon $until): void
  {
    if ($since) {
      $query->where($column, '>=', $since->startOfDay());
    }

    if ($until) {
      $query->where($column, '<=', $until->endOfDay());
    }
  }
}
