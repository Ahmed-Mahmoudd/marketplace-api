<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Generates twelve months of trading history so the vendor analytics dashboard
 * has something to forecast, segment and recommend from.
 *
 * Deliberately NOT called by DatabaseSeeder — the default seed stays small and
 * predictable for the test suite. Run it explicitly:
 *
 *   php artisan db:seed --class=Database\\Seeders\\AnalyticsDemoSeeder
 */
class AnalyticsDemoSeeder extends Seeder
{
  private const CUSTOMERS = 60;
  private const MONTHS = 12;
  private const ORDERS_PER_MONTH = 45;

  private const CITIES = [
    'Cairo', 'Alexandria', 'Giza', 'Mansoura', 'Tanta',
    'Aswan', 'Luxor', 'Port Said', 'Suez', 'Ismailia',
  ];

  private const GENDERS = ['male', 'female', 'other'];

  public function run(): void
  {
    $products = Product::query()->with('vendor')->get();

    if ($products->isEmpty()) {
      $this->command?->warn('No products found — run the main seeder first.');

      return;
    }

    $customers = $this->createCustomers();

    $this->command?->info('Generating orders...');
    $orders = $this->createOrders($customers, $products);

    $this->command?->info('Generating reviews...');
    $this->createReviews($customers, $products);

    $this->command?->info('Generating funnel events...');
    $this->createEvents($customers, $products, $orders);

    $this->command?->info(sprintf(
      'Done: %d customers, %d orders, %d events.',
      $customers->count(),
      count($orders),
      Event::count()
    ));
  }

  /** @return \Illuminate\Support\Collection<int, User> */
  private function createCustomers()
  {
    $existing = User::query()
      ->where('email', 'like', 'demo-customer-%@example.com')
      ->get();

    if ($existing->count() >= self::CUSTOMERS) {
      return $existing;
    }

    $created = collect();

    for ($i = $existing->count() + 1; $i <= self::CUSTOMERS; $i++) {
      $user = User::create([
        'name'       => fake()->name(),
        'email'      => "demo-customer-{$i}@example.com",
        'password'   => 'password',
        'gender'     => fake()->randomElement(self::GENDERS),
        'city'       => fake()->randomElement(self::CITIES),
        'created_at' => Carbon::now()->subMonths(random_int(0, self::MONTHS))->subDays(random_int(0, 28)),
      ]);

      $user->assignRole('customer');
      $created->push($user);
    }

    return $existing->concat($created);
  }

  /**
   * Orders are spread across the last twelve months with a mild upward trend and
   * a Q4 bump, so the forecasting page has a real seasonal signal to model.
   *
   * @return array<int, array{id: int, user_id: int, created_at: Carbon, items: array}>
   */
  private function createOrders($customers, $products): array
  {
    $statusWeights = [
      Order::STATUS_DELIVERED => 55,
      Order::STATUS_SHIPPED   => 15,
      Order::STATUS_PROCESSING => 10,
      Order::STATUS_CONFIRMED => 8,
      Order::STATUS_PENDING   => 5,
      Order::STATUS_CANCELLED => 7,
    ];

    $summaries = [];

    for ($monthsAgo = self::MONTHS - 1; $monthsAgo >= 0; $monthsAgo--) {
      $month = Carbon::now()->subMonths($monthsAgo)->startOfMonth();

      // Gentle growth over time, plus a seasonal lift in Oct-Dec.
      $growth = 1 + (self::MONTHS - 1 - $monthsAgo) * 0.04;
      $seasonal = in_array($month->month, [10, 11, 12], true) ? 1.35 : 1.0;
      $orderCount = (int) round(self::ORDERS_PER_MONTH * $growth * $seasonal);

      $daysInMonth = $monthsAgo === 0 ? Carbon::now()->day : $month->daysInMonth;

      for ($n = 0; $n < $orderCount; $n++) {
        $placedAt = $month->copy()
          ->addDays(random_int(0, max(0, $daysInMonth - 1)))
          ->addHours(random_int(8, 22))
          ->addMinutes(random_int(0, 59));

        $customer = $customers->random();
        $lineProducts = $products->random(random_int(1, 3));
        $status = $this->weightedRandom($statusWeights);

        $lines = [];
        $subtotal = 0.0;

        foreach ($lineProducts as $product) {
          $quantity = random_int(1, 3);
          $lineTotal = round($product->price * $quantity, 2);
          $subtotal += $lineTotal;

          $lines[] = [
            'product_id'   => $product->id,
            'vendor_id'    => $product->vendor_id,
            'product_name' => $product->name,
            'unit_price'   => $product->price,
            'quantity'     => $quantity,
            'subtotal'     => $lineTotal,
            'created_at'   => $placedAt,
            'updated_at'   => $placedAt,
          ];
        }

        $orderId = DB::table('orders')->insertGetId([
          'order_number'   => 'ORD-' . $placedAt->format('Ymd') . '-' . strtoupper(Str::random(6)),
          'user_id'        => $customer->id,
          'status'         => $status,
          'payment_method' => Order::PAYMENT_METHOD_COD,
          'payment_status' => Order::PAYMENT_STATUS_PENDING,
          'subtotal'       => round($subtotal, 2),
          'total'          => round($subtotal, 2),
          'created_at'     => $placedAt,
          'updated_at'     => $placedAt,
        ]);

        foreach ($lines as $i => $line) {
          $lines[$i]['order_id'] = $orderId;
        }

        DB::table('order_items')->insert($lines);

        $summaries[] = [
          'id'         => $orderId,
          'user_id'    => $customer->id,
          'created_at' => $placedAt,
          'status'     => $status,
          'items'      => $lines,
        ];
      }
    }

    return $summaries;
  }

  /** One review per (customer, product) pair, respecting the unique index. */
  private function createReviews($customers, $products): void
  {
    $comments = [
      'Exactly as described, arrived quickly.',
      'Good quality for the price.',
      'Packaging was damaged but the product is fine.',
      'Colour was different from the photos.',
      'Would buy from this store again.',
      'Stopped working after two weeks.',
      'Great value, highly recommended.',
      'Delivery took longer than expected.',
      'Better than I expected honestly.',
      'Does the job, nothing special.',
    ];

    $seen = Review::query()
      ->select('user_id', 'product_id')
      ->get()
      ->map(fn($r) => "{$r->user_id}:{$r->product_id}")
      ->flip();

    $rows = [];

    foreach ($products as $product) {
      foreach ($customers->random(min(random_int(2, 12), $customers->count())) as $customer) {
        $key = "{$customer->id}:{$product->id}";

        if ($seen->has($key)) {
          continue;
        }

        $seen[$key] = true;
        $reviewedAt = Carbon::now()->subDays(random_int(1, 330));

        // Skew positive, as real catalogues do.
        $rating = $this->weightedRandom([5 => 40, 4 => 30, 3 => 15, 2 => 9, 1 => 6]);

        $rows[] = [
          'user_id'    => $customer->id,
          'product_id' => $product->id,
          'rating'     => $rating,
          'comment'    => $comments[array_rand($comments)],
          'created_at' => $reviewedAt,
          'updated_at' => $reviewedAt,
        ];
      }
    }

    foreach (array_chunk($rows, 500) as $chunk) {
      DB::table('reviews')->insert($chunk);
    }
  }

  /**
   * Builds a funnel that actually narrows: every purchase has a matching cart
   * and view event, plus extra views and abandoned carts on top.
   */
  private function createEvents($customers, $products, array $orders): void
  {
    $rows = [];

    // Purchases (and the view + cart that preceded them).
    foreach ($orders as $order) {
      foreach ($order['items'] as $item) {
        $purchasedAt = $order['created_at'];
        $viewedAt = $purchasedAt->copy()->subMinutes(random_int(10, 2880));
        $cartedAt = $purchasedAt->copy()->subMinutes(random_int(2, 9));

        $base = [
          'user_id'    => $order['user_id'],
          'product_id' => $item['product_id'],
          'vendor_id'  => $item['vendor_id'],
          'session_id' => null,
        ];

        $rows[] = $base + ['type' => Event::TYPE_VIEW, 'created_at' => $viewedAt, 'updated_at' => $viewedAt];
        $rows[] = $base + ['type' => Event::TYPE_CART, 'created_at' => $cartedAt, 'updated_at' => $cartedAt];
        $rows[] = $base + ['type' => Event::TYPE_PURCHASE, 'created_at' => $purchasedAt, 'updated_at' => $purchasedAt];
      }
    }

    // Browsing that never converted — this is what makes the funnel interesting.
    foreach ($products as $product) {
      $extraViews = random_int(40, 160);

      for ($i = 0; $i < $extraViews; $i++) {
        $at = Carbon::now()->subDays(random_int(0, 364))->addHours(random_int(0, 23));

        $rows[] = [
          'user_id'    => random_int(1, 4) === 1 ? null : $customers->random()->id,
          'product_id' => $product->id,
          'vendor_id'  => $product->vendor_id,
          'type'       => Event::TYPE_VIEW,
          'session_id' => (string) Str::uuid(),
          'created_at' => $at,
          'updated_at' => $at,
        ];

        // Roughly a fifth of stray views add to cart and then abandon.
        if (random_int(1, 5) === 1) {
          $cartAt = $at->copy()->addMinutes(random_int(1, 30));

          $rows[] = [
            'user_id'    => $customers->random()->id,
            'product_id' => $product->id,
            'vendor_id'  => $product->vendor_id,
            'type'       => Event::TYPE_CART,
            'session_id' => (string) Str::uuid(),
            'created_at' => $cartAt,
            'updated_at' => $cartAt,
          ];
        }
      }
    }

    foreach (array_chunk($rows, 1000) as $chunk) {
      DB::table('events')->insert($chunk);
    }
  }

  /** @param array<int|string, int> $weights value => weight */
  private function weightedRandom(array $weights): int|string
  {
    $total = array_sum($weights);
    $roll = random_int(1, $total);

    foreach ($weights as $value => $weight) {
      $roll -= $weight;

      if ($roll <= 0) {
        return $value;
      }
    }

    return array_key_first($weights);
  }
}
