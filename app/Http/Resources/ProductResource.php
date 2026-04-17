<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
     public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'status' => $this->status,
            'customer_name' => $this->customer_name,
            'customer_contact' => $this->customer_contact,
            'request_status' => $this->request_status,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            
            'price' => $this->price,
            'quantity' => $this->quantity,
            'category' => $this->category,
            'description' => $this->description,
            'unit' => $this->unit,
            'is_available' => $this->is_available,

        ];
    }
}
