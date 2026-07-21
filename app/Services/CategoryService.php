<?php

namespace App\Services;

use App\Models\Category;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class CategoryService
{
  private const PER_PAGE = 15;

  /**
   * Public catalog listing: active categories only, with search + sort + pagination.
   *
   * @param  array{q?: string, sort?: string}  $filters
   */
  public function list(array $filters): LengthAwarePaginator
  {
    $query = Category::query()->where('is_active', true);

    $this->applySearch($query, $filters['q'] ?? null);
    $this->applySort($query, $filters['sort'] ?? null);

    return $query->paginate(self::PER_PAGE)->withQueryString();
  }

  public function findBySlug(string $slug, bool $onlyActive = true): Category
  {
    return Category::query()
      ->where('slug', $slug)
      ->when($onlyActive, fn(Builder $query) => $query->where('is_active', true))
      ->firstOrFail();
  }

  public function create(array $data): Category
  {
    return Category::create([
      'name' => $data['name'],
      'slug' => $this->uniqueSlug($data['name']),
      'description' => $data['description'] ?? null,
      'is_active' => true,   // ← جديد
    ]);
  }

  public function update(Category $category, array $data): Category
  {
    $attributes = [];

    if (array_key_exists('name', $data)) {
      $attributes['name'] = $data['name'];

      if ($data['name'] !== $category->name) {
        $attributes['slug'] = $this->uniqueSlug($data['name'], $category->id);
      }
    }

    if (array_key_exists('description', $data)) {
      $attributes['description'] = $data['description'];
    }

    if (array_key_exists('is_active', $data)) {
      $attributes['is_active'] = $data['is_active'];
    }

    $category->update($attributes);

    return $category->fresh();
  }

  /**
   * Hard delete — blocked while the category still has products attached.
   * Prefer disabling it (is_active = false) via update() instead.
   */
  public function delete(Category $category): void
  {
    if ($category->products()->exists()) {
      throw new \DomainException('Cannot delete a category that still has products. Disable it instead.');
    }

    $category->delete();
  }

  private function applySearch(Builder $query, ?string $search): void
  {
    if (! $search) {
      return;
    }

    $query->where('name', 'like', '%' . $search . '%');
  }

  private function applySort(Builder $query, ?string $sort): void
  {
    match ($sort) {
      'name' => $query->orderBy('name'),
      '-name' => $query->orderByDesc('name'),
      'oldest' => $query->oldest(),
      default => $query->latest(), // covers 'latest' and no sort given
    };
  }

  /**
   * Generate a unique slug for the given name, appending -2, -3, ...
   * when the base slug is already taken (excluding the given category id).
   */
  private function uniqueSlug(string $name, ?int $ignoreId = null): string
  {
    $base = Str::slug($name);
    $slug = $base;
    $suffix = 2;

    while (
      Category::query()
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
