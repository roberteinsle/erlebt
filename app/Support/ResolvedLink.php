<?php

namespace App\Support;

final readonly class ResolvedLink
{
    public function __construct(
        public string $name,
        public ?float $latitude = null,
        public ?float $longitude = null,
    ) {}
}
