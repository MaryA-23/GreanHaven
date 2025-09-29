<?php

namespace App\Events;

use App\Models\Vegetable;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class VegetableReady
{
    use Dispatchable, SerializesModels;

    public $vegetable;

    public function __construct(Vegetable $vegetable)
    {
        $this->vegetable = $vegetable;
    }
}