<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WhiteList extends Model
{
    use HasFactory;

    protected $table = 'white_lists';

    protected $fillable = [
        'username',
    ];
}
