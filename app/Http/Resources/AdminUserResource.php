<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AdminUserResource extends JsonResource
{
    public function toArray($request)
    {
        return [
        'id' => $this->id,
        'uuid' => $this->uuid,
        'name' => $this->name,
        'first_name' => $this->first_name,
        'last_name' => $this->last_name,
        'email' => $this->email,
        'phone' => $this->phone,
        'role' => $this->role,
        'status' => $this->status,
        'last_login' => $this->last_login,
        'created_at' => $this->created_at,
        'gender' => $this->gender,
        'profile_picture' => $this->profile_picture,    
        ];
    }
}