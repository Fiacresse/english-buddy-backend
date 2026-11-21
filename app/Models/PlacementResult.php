<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlacementResult extends Model
{
    protected $fillable = ['user_id', 'level', 'score', 'scores'];

    protected function casts(): array
    {
        return ['scores' => 'array'];
    }
}
