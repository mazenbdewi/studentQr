<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Hall extends Model
{
    protected $fillable = [
        'code',
        'name',
        'floor',
        'is_active',
    ];
}