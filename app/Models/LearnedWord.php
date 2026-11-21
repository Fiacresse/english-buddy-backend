<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LearnedWord extends Model
{
    protected $fillable = ['user_id', 'word', 'meaning', 'example'];
}
