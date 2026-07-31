<?php

namespace Gohari\RepositoryPattern\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

class DtoUser extends Model
{
    protected $fillable = [
        'name',
        'age',
        'is_active',
        'settings',
    ];

    protected $casts = [
        'age' => 'integer',
        'is_active' => 'boolean',
        'settings' => 'array',
    ];
}
