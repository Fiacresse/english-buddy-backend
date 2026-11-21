<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Mistake extends Model
{
    protected $fillable = ['user_id', 'category', 'original', 'corrected', 'explanation'];
}
