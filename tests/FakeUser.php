<?php

namespace GrinevStudio\LaravelContentMcp\Tests;

use Illuminate\Foundation\Auth\User as Authenticatable;

class FakeUser extends Authenticatable
{
    protected $table = 'users';

    protected $guarded = [];

    public bool $allowed = true;
}
