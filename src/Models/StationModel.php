<?php

declare(strict_types=1);

namespace App\Models;

class StationModel extends Model
{
    protected function getTableName(): string
    {
        return 'stations';
    }

    protected function getTableKey(): string
    {
        return 'id';
    }

    protected function getFactory(): array
    {
        return [
            'name' => $this->faker->asciify(),
            'lat' => $this->faker->unique()->randomFloat(4, 30, 40),
            'lng' => $this->faker->unique()->randomFloat(4, 30, 40),
        ];
    }
}
