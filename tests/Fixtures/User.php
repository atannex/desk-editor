<?php

declare(strict_types=1);

namespace Atannex\DeskEditor\Tests\Fixtures;

use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    protected $guarded = [];
}
