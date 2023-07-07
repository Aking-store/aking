<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Stancl\VirtualColumn\VirtualColumn;

class Product extends Model
{
    use VirtualColumn;

    public $guarded = [];

    public static function getCustomColumns(): array
    {
        return [
            'id',
            'offer_id',
            'title',
            'price',
            'seller_outer_id',
            'seller_outer_name',
            'game_outer_id',
            'game_outer_name',
            'category_outer_id',
            'category_outer_name',
            'site_name',
            'iteration',
            'updated_at',
            'score',
            'updated',
        ];
    }

    protected $dates = [
        'created_at',
        'updated_at',
        'updated'
    ];
}
