<?php

namespace Gohari\RepositoryPattern\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SoftDeleteUser extends Model
{
    use SoftDeletes;

    protected $table = 'repository_pattern_test_users';

    protected $guarded = [];
}
