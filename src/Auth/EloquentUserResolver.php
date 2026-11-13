<?php

declare(strict_types=1);

namespace NeaMatrix\Connex\Auth;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use LogicException;
use NeaMatrix\Connex\Contracts\ResolvesUsers;
use NeaMatrix\Connex\Data\LoginConfirmation;
use NeaMatrix\Connex\Support\Msisdn;

/**
 * Default resolver: finds the user whose phone column matches the msisdn, or
 * creates one with only that column and a random password. Existing users are
 * never modified.
 */
class EloquentUserResolver implements ResolvesUsers
{
    /**
     * @param  string  $model  an Eloquent model class that implements Authenticatable
     * @param  'international'|'national'  $format  how phone numbers are stored in $column
     */
    public function __construct(
        private readonly string $model,
        private readonly string $column = 'phone',
        private readonly string $format = 'international',
        private readonly ?string $passwordColumn = 'password',
    ) {}

    public function resolve(Msisdn $msisdn, LoginConfirmation $confirmation): Model&Authenticatable
    {
        $phone = $this->format === 'national' ? $msisdn->national() : $msisdn->international;
        $user = $this->newUser();

        $existing = $user->newQuery()->where($this->column, $phone)->first();

        if ($existing instanceof Authenticatable) {
            return $existing;
        }

        $attributes = [$this->column => $phone];

        if ($this->passwordColumn !== null) {
            $attributes[$this->passwordColumn] = Hash::make(Str::random(40));
        }

        $user->forceFill($attributes)->save();

        return $user;
    }

    private function newUser(): Model&Authenticatable
    {
        $user = class_exists($this->model) ? new $this->model : null;

        if (! $user instanceof Model || ! $user instanceof Authenticatable) {
            throw new LogicException("connex.user.model [{$this->model}] must be an Eloquent model implementing Authenticatable.");
        }

        return $user;
    }
}
