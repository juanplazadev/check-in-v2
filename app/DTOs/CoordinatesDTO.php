<?php

declare(strict_types=1);

namespace App\DTOs;

final readonly class CoordinatesDTO
{
    public function __construct(public float $latitude, public float $longitude) {}
}
