<?php

namespace Workbench\App\Models;

use Arzcode\Finisterre\Contracts\FinisterreContract;
use Arzcode\Finisterre\Contracts\FinisterreReportable;
use Arzcode\Finisterre\Traits\FinisterreUserTrait;
use Arzcode\Finisterre\Traits\InteractsWithFinisterreReports;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * @method bool canArchiveTasks()
 * @method string getUserNameColumn()
 */
class User extends Authenticatable implements FinisterreContract, FinisterreReportable
{
    use FinisterreUserTrait;
    use HasFactory;
    use InteractsWithFinisterreReports;
    use Notifiable;

    protected $fillable = ['name', 'email', 'password'];
    protected $hidden = ['password', 'remember_token'];
    protected $casts = ['email_verified_at' => 'datetime', 'password' => 'hashed'];
}
