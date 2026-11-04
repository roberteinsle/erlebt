<?php

namespace App\Support;

final readonly class Place
{
    /** @param list<string> $reviews Texte fremder Bewertungen, nur für die Fragen-Erzeugung */
    public function __construct(
        public string $name,
        public ?string $placeId = null,
        public ?string $address = null,
        public ?string $type = null,
        public ?float $rating = null,
        public ?int $ratingCount = null,
        public array $reviews = [],
    ) {}

    public function toArray(): array
    {
        return get_object_vars($this);
    }

    public static function fromArray(array $data): self
    {
        return new self(...$data);
    }
}
