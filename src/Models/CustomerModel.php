<?php

declare(strict_types=1);

namespace App\Models;

class CustomerModel extends Model
{
    protected function getTableName(): string
    {
        return 'customers';
    }

    protected function getTableKey(): string
    {
        return 'id';
    }

    protected function getFactory(): array
    {
        return [
            'first_name' => $this->faker->firstName(),
            'last_name' => $this->faker->lastName(),
            'email' => $this->faker->unique()->email(),
            'password' => $this->faker->password(),
        ];
    }
}
