<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;
use Illuminate\Auth\Authenticatable;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Foundation\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;

class User extends Model implements
    AuthenticatableContract,
    AuthorizableContract,
    CanResetPasswordContract
{
    use Authenticatable, Authorizable, CanResetPassword, HasApiTokens, Notifiable;
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'offer_id'
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token',
    ];


    /* ========================================================================= *\
     * Relations
    \* ========================================================================= */
    /**
     * User has many file entries
     */
    public function fileEntries()
    {
        return $this->hasMany(Fileentry::class);
    }

    /**
     * User has one offer
     */
    public function offer()
    {
        return Offer::find($this->offer_id);
    }

    /* ========================================================================= *\
     * Helpers
    \* ========================================================================= */
    /**
     * Get existing or make new access token
     */
    public function makeApiToken()
    {
        return $this->createToken('API')->accessToken;
    }

    public function size(){
        return $this->fileEntries()->sum('size');
    }

    /**
     * Is user admin
     *
     * @return bool
     */
    public function isAdmin()
    {
        return $this->role === 'admin';
    }
}
