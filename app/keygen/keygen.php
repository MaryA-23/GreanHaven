<?php

namespace Keygen;

class Keygen
{
    protected int $length = 8;
    protected string $prefix = '';

    public static function numeric(int $length): self
    {
        $instance = new self();
        $instance->length = $length;

        return $instance;
    }

    public function prefix(string $prefix): self
    {
        $this->prefix = $prefix;

        return $this;
    }

    public function generate(): string
    {
        $digits = '';

        for ($i = 0; $i < $this->length; $i++) {
            $digits .= random_int(0, 9);
        }

        return $this->prefix . $digits;
    }
}