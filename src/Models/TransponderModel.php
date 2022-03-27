<?php

declare(strict_types=1);

namespace App\Models;

class TransponderModel extends Model
{
    protected function getTableName(): string
    {
        return 'transponders';
    }

    protected function getTableKey(): string
    {
        return 'serial_number';
    }

    protected function getFactory(): array
    {
        return [
            'serial_number' => $this->faker->unique()->randomNumber(5, 10),
        ];
    }
}
