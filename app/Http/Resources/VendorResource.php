<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VendorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,                                                        // رقم طلب/محل البائع
            'store_name' => $this->store_name,                                        // اسم المتجر
            'store_slug' => $this->store_slug,                                        // الرابط المختصر للمتجر
            'status' => $this->status,                                                // pending / approved / suspended
            'commission_rate' => $this->commission_rate,                              // نسبة العمولة
            'approved_at' => $this->approved_at,                                      // تاريخ الموافقة (لو اتوافق عليه)
            'user' => $this->whenLoaded('user', fn() => new UserResource($this->user)), // بيانات اليوزر صاحب المتجر ده لو متحمّلة
            'created_at' => $this->created_at,                                        // تاريخ تقديم الطلب
        ];
    }
}
