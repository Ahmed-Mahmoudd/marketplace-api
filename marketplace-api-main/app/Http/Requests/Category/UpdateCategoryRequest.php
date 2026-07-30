<?php

namespace App\Http\Requests\Category;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCategoryRequest extends FormRequest
{
  public function authorize(): bool
  {
    return true;
  }

  public function rules(): array
  {
    $category = $this->route('category');

    return [
      'name' => [
        'sometimes',
        'required',
        'string',
        'max:255',
        Rule::unique('categories', 'name')->ignore($category),
      ],
      'description' => ['nullable', 'string', 'max:2000'],
      'is_active' => ['sometimes', 'boolean'],
    ];
  }
}
