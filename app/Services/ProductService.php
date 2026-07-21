<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Vendor;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ProductService
{
  private const PER_PAGE = 15;

  /**
   * @param  array{q?: string, status?: string, sort?: string}  $filters
   */
  public function listForVendor(Vendor $vendor, array $filters): LengthAwarePaginator
  {
    $query = $vendor->products()->with(['category', 'vendor']);

    $this->applySearch($query, $filters['q'] ?? null);
    $this->applyStatus($query, $filters['status'] ?? null);
    $this->applySort($query, $filters['sort'] ?? null);

    return $query->paginate(self::PER_PAGE)->withQueryString();
  }

  public function create(Vendor $vendor, array $data): Product
  {
    $product = $vendor->products()->create([
      'category_id' => $data['category_id'],
      'name' => $data['name'],
      'slug' => $this->uniqueSlug($data['name']),
      'description' => $data['description'] ?? null,
      'price' => $data['price'],
      'stock' => $data['stock'],
      'status' => $data['status'] ?? Product::STATUS_ACTIVE,
    ]);

    return $product->load(['category', 'vendor']);
  }

  public function update(Product $product, array $data): Product
  {
    $attributes = array_intersect_key($data, array_flip([
      'category_id',
      'description',
      'price',
      'stock',
      'status',
    ]));

    if (array_key_exists('name', $data)) {
      $attributes['name'] = $data['name'];

      if ($data['name'] !== $product->name) {
        $attributes['slug'] = $this->uniqueSlug($data['name'], $product->id);
      }
    }

    $product->update($attributes);

    return $product->fresh(['category', 'vendor']);
  }

  public function delete(Product $product): void
  {
    $product->delete();
  }

  private function applySearch(Builder|HasMany $query, ?string $search): void
  {
    if (! $search) {
      return;
    }

    $query->where('name', 'like', '%' . $search . '%');
  }

  private function applyStatus(Builder|HasMany $query, ?string $status): void
  {
    if (! $status) {
      return;
    }

    $query->where('status', $status);
  }

  private function applySort(Builder|HasMany $query, ?string $sort): void
  {
    match ($sort) {
      'name' => $query->orderBy('name'),
      'price' => $query->orderBy('price'),
      '-price' => $query->orderByDesc('price'),
      default => $query->latest(), // covers 'latest' and no sort given
    };
  }

  /**
   * Generate a unique slug for the given name, appending -2, -3, ...
   * when the base slug is already taken (excluding the given product id).
   */
  private function uniqueSlug(string $name, ?int $ignoreId = null): string
  {
    $base = Str::slug($name);
    $slug = $base;
    $suffix = 2;

    while (
      Product::query()
      ->where('slug', $slug)
      ->when($ignoreId, fn($query) => $query->where('id', '!=', $ignoreId))
      ->exists()
    ) {
      $slug = "{$base}-{$suffix}";
      $suffix++;
    }

    return $slug;
  }
}
