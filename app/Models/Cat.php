<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class Cat extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'cats';
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
        'imageVector'
    ];
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
