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
    public static function boot()
    {
        parent::boot();

        // Ensure a 2dsphere index is created on the address field
        static::addGlobalScope('geospatial', function ($builder) {
            $builder->raw(function ($query) {
                $query->createIndex(['address' => '2dsphere']);
            });
        });
    }
}
