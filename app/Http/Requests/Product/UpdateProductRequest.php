<?php

namespace App\Http\Requests\Product;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
  public function authorize(): bool
  {
    return true;
  }

  public function rules(): array
  {
    return [
      'category_id' => ['sometimes', 'required', 'integer', 'exists:categories,id'],
      'name' => ['sometimes', 'required', 'string', 'max:255'],
      'description' => ['nullable', 'string', 'max:5000'],
      'price' => ['sometimes', 'required', 'numeric', 'min:0.01'],
      'stock' => ['sometimes', 'required', 'integer', 'min:0'],
      'status' => ['sometimes', Rule::in(Product::STATUSES)],
    ];
  }
}
