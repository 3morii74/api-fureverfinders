<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});
Route::get('/test-mongo', function () {
    try {
        $users = App\Models\User::all();  // Retrieve all users from MongoDB
        if ($users->isEmpty()) {
            return 'MongoDB connected, but no users found in the collection.';
        }

        // Return or dump users
        dd($users); // This will dump all users data
        // or you can just return a JSON response
        return response()->json($users);
    } catch (\Exception $e) {
        return 'MongoDB connection failed: ' . $e->getMessage();
    }
});
