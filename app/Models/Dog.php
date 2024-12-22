<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class Dog extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'dogs';
    protected $fillable = [
        'pet_name',
        'year',
        'month',
        'address',
        'weight',
        'description',
        'user_id',
        'gender',
        'status',
        'images',
        'imageVector',
    ];
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
