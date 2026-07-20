<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,                                                          // رقم اليوزر
            'name' => $this->name,                                                       // اسمه
            'email' => $this->email,                                                     // إيميله
            'gender' => $this->gender,                                                    // النوع
            'city' => $this->city,                                                        // المدينة
            'roles' => $this->whenLoaded('roles', fn() => $this->roles->pluck('name')),  // أدواره: customer/vendor/admin (لو العلاقة متحمّلة بس)
            'vendor' => $this->whenLoaded('vendor', fn() => new VendorResource($this->vendor)), // بيانات محل البائع لو عنده واحد ومتحمّلة
            'created_at' => $this->created_at,                                            // تاريخ التسجيل
        ];
    }
}
