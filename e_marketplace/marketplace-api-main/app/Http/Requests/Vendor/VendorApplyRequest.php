<?php

namespace App\Http\Requests\Vendor;

use Illuminate\Foundation\Http\FormRequest;

class VendorApplyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'store_name' => ['required', 'string', 'max:255', 'unique:vendors,store_name'],
            'payout_details' => ['nullable', 'array'],
            'payout_details.bank_name' => ['required_with:payout_details', 'string'],
            'payout_details.iban' => ['required_with:payout_details', 'string'],
        ];
    }
}
