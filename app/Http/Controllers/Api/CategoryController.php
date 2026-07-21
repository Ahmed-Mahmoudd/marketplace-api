<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Services\CategoryService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
  public function __construct(
    private CategoryService $categoryService
  ) {}

  public function index(Request $request)
  {
    $categories = $this->categoryService->list(
      $request->only(['q', 'sort'])
    );

    return $this->success([
      'items' => CategoryResource::collection($categories->items()),
      'meta' => [
        'current_page' => $categories->currentPage(),
        'last_page' => $categories->lastPage(),
        'per_page' => $categories->perPage(),
        'total' => $categories->total(),
      ],
    ]);
  }

  public function show(string $slug)
  {
    try {
      $category = $this->categoryService->findBySlug($slug);
    } catch (ModelNotFoundException) {
      return $this->error('Category not found.', 404);
    }

    return $this->success(new CategoryResource($category));
  }
}
