<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Stancl\VirtualColumn\VirtualColumn;

class Iteration extends Model
{
    use VirtualColumn;

    public $guarded = [];

    public static function getCustomColumns(): array
    {
        return [
            'id',
            'site_name',
            'status',
            'updated_at'
        ];
    }
}
