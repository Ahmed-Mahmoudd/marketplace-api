<?php

namespace App\Http\Requests\Review;

use Illuminate\Foundation\Http\FormRequest;

class UpdateReviewRequest extends FormRequest
{
  public function authorize(): bool
  {
    return true;
  }

  public function rules(): array
  {
    return [
      'rating'  => ['sometimes', 'integer', 'between:1,5'],
      'comment' => ['nullable', 'string', 'max:2000'],
    ];
  }
}
