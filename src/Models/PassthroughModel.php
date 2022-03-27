<?php

declare(strict_types=1);

namespace App\Models;

class PassthroughModel extends Model
{
    protected function getTableName(): string
    {
        return 'passthroughs';
    }
}
