<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Hall extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'floor',
        'is_active',
    ];
}
