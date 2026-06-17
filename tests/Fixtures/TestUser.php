<?php

namespace Gohari\RepositoryPattern\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

class TestUser extends Model
{
    protected $table = 'repository_pattern_test_users';

    protected $guarded = [];
}
