<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use NeaMatrix\Connex\Models\Concerns\HasConnexSubscription;

class User extends Authenticatable
{
    use HasConnexSubscription;

    protected $guarded = [];
}
