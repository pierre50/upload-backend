<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Fileentry extends Model
{    /**
 * The attributes that are mass assignable.
 *
 * @var array
 */
    protected $fillable = [
        'user_id',
        'mime',
        'original_filename',
        'filename',
        'hash',
        'path',
        'size',
        'downloads',
    ];


    /* ========================================================================= *\
     * Relations
    \* ========================================================================= */
    /**
     * User has many file entries
     */
    public function creator()
    {
        return User::find($this->user_id);
    }
}
