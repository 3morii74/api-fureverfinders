<?php

namespace App\Http\Controllers;

use App\Models\Cat;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use MongoDB\BSON\Binary;

class PetsController extends Controller
{
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'pet_name'    => 'required|string|max:255',
            'type'        => 'required|in:dog,cat',
            'gender'      => 'required|in:male,female',
            'year'        => 'nullable|integer|min:0|max:30',
            'month'       => 'nullable|integer|min:0|max:12',
            'address'     => 'required|string|max:255',
            'weight'      => 'nullable|numeric|min:0',
            'description' => 'nullable|string|max:1000',
            'images'      => 'required|array', // Validate as an array of images
            'images.*'    => 'image|mimes:jpg,jpeg,png,gif,svg|max:10048', // Validate each file
            'status'      => 'required|in:pairing,adopted',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        // Handle file upload
        $imageBase64s = [];
        foreach ($request->file('images') as $image) {
            // Get image content as a string
            $imageContent = file_get_contents($image->getRealPath());

            // Convert image content to base64
            $imageBase64s[] = base64_encode($imageContent); // Store as base64 string
        }
        if ($request['type'] === 'dog') {
        } else if ($request['type'] === 'cat') {
            $cat = Cat::create([
                'user_id' => auth()->id(), // Store the ID of the authenticated user
                'name' => $request->get('name'),
                'images' => $imageBase64s,
            ]);
            $responseMessage = 'Cat created successfully!';
        }
        // Store the cat's data


        return response()->json([
            'data' => $cat,
            'message' => $responseMessage,
        ], 201);
    }
    public function index()
    {
        // Retrieve all cats and their associated user information
        $cats = Cat::with('user')->get(); // 'user' is the relation defined in the Cat model

        return response()->json($cats);
    }
}
