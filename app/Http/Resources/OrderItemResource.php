<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            'vegetable' => [
                'id' => $this->vegetable->id,
                'name' => $this->vegetable->name,
                'price' => $this->vegetable->price,

                'image' => $this->vegetable->image,

                'image_url' => $this->vegetable->image
                    ? asset(
                        'storage/' .
                        ltrim($this->vegetable->image, '/')
                    )
                    : null,
            ],

            'quantity' => $this->quantity,
            'price' => $this->price,
            'subtotal' => $this->subtotal,
        ];
    }
}