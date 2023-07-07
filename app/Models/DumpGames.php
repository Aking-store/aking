<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DumpGames extends Model
{
    use HasFactory;

    protected $table = 'dump_games';

    protected $fillable = [
        'outer_id',
        'game_name',
        'name',
        'region',
        'min_stock',
        'max_stock',
        'dump',
        'competitor_current_lowest_price',
        'our_price',
        'link',
        'link2',
    ];
}
