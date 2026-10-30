<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Whitecube\NovaFlexibleContent\Value\FlexibleCast;

class Page extends Model
{
    use HasFactory;

    //
    protected $casts = [
        'flexible-content' => FlexibleCast::class,
    ];
}
