<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cat extends Model
{
    protected $connection = 'mongodb'; // Define MongoDB as the connection
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
    ];
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
