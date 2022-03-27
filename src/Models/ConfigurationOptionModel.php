<?php

declare(strict_types=1);

namespace App\Models;

class ConfigurationOptionModel extends Model
{
    protected function getTableName(): string
    {
        return 'configuration_options';
    }

    protected function getTableKey(): string
    {
        return 'name';
    }
}
