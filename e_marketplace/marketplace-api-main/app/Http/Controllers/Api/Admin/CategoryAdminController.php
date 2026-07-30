<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Category\StoreCategoryRequest;
use App\Http\Requests\Category\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Services\CategoryService;
use Illuminate\Http\Request;

class CategoryAdminController extends Controller
{
  public function __construct(
    private CategoryService $categoryService
  ) {}

  public function index(Request $request)
  {
    $categories = $this->categoryService->adminList(
      $request->only(['q', 'sort', 'per_page'])
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

  public function store(StoreCategoryRequest $request)
  {
    $category = $this->categoryService->create($request->validated());

    return $this->success(
      new CategoryResource($category),
      'Category created.',
      201
    );
  }

  public function update(UpdateCategoryRequest $request, Category $category)
  {
    $category = $this->categoryService->update($category, $request->validated());

    return $this->success(
      new CategoryResource($category),
      'Category updated.'
    );
  }

  public function destroy(Category $category)
  {
    try {
      $this->categoryService->delete($category);
    } catch (\DomainException $e) {
      return $this->error($e->getMessage(), 422);
    }

    return $this->success(null, 'Category deleted.');
  }
}
