<?php

namespace Workbench\App\Models;

use Buzkall\Finisterre\Contracts\FinisterreContract;
use Buzkall\Finisterre\Traits\FinisterreUserTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * @method bool canAccessFinisterre()
 * @method bool canArchiveTasks()
 * @method string getUserNameColumn()
 */
class User extends Authenticatable implements FinisterreContract
{
    use FinisterreUserTrait;
    use HasFactory;

    protected $fillable = ['name', 'email', 'password'];
    protected $hidden = ['password', 'remember_token'];
    protected $casts = ['email_verified_at' => 'datetime', 'password' => 'hashed'];
}
