<?php

namespace App\Http\Controllers;

use App\Models\Cat;
use App\Models\Dog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;
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
        $data = null;
        // Handle file upload
        $imageBase64s = [];
        foreach ($request->file('images') as $image) {
            // Get image content as a string
            $imageContent = file_get_contents($image->getRealPath());

            // Convert image content to base64
            $imageBase64s[] = base64_encode($imageContent); // Store as base64 string
        }
        if ($request['type'] === 'dog') {
            $dog = Dog::create([
                'user_id' => auth()->id(),
                'pet_name'    => $request['pet_name'],
                'gender'      => $request['gender'],
                'year'        => $request['year'],
                'month'       => $request['month'],
                'address'     => $request['address'],
                'weight'      => $request['weight'],
                'description' => $request['description'],
                'images' => $imageBase64s,
            ]);

            $data = $dog;
            $responseMessage = 'Dog created successfully!';
        } else if ($request['type'] === 'cat') {

            $cat = Cat::create([
                'user_id' => auth()->id(),
                'pet_name'    => $request['pet_name'],
                'gender'      => $request['gender'],
                'year'        => $request['year'],
                'month'       => $request['month'],
                'address'     => $request['address'],
                'weight'      => $request['weight'],
                'description' => $request['description'],
                'images' => $imageBase64s,
            ]);

            $data = $cat;
            $responseMessage = 'Cat created successfully!';
        }
        // Store the cat's data


        return response()->json([
            'data' => $data,
            'message' => $responseMessage,
        ], 201);
    }
    public function index(Request $request)
    {
        // Retrieve all cats and their associated user information
        // $cats = Cat::with('user')->get();
        $dogsQuery = Dog::with('user');
        $catsQuery = Cat::with('user');

        if ($request->has('pet_type')) {
            if ($request->pet_type === 'dog') {
                $catsQuery = Dog::whereNull('id'); // Return empty query for cats
            } elseif ($request->pet_type === 'cat') {
                $dogsQuery = Cat::whereNull('id'); // Return empty query for dogs
            }
        }
        if ($request->has('gender')) {
            $dogsQuery->where('gender', $request->gender);
            $catsQuery->where('gender', $request->gender);
        }

        if ($request->has('min_age') || $request->has('max_age')) {
            $dogsQuery->when($request->has('min_age'), function ($query) use ($request) {
                return $query->where('year', '>=', $request->min_age);
            })->when($request->has('max_age'), function ($query) use ($request) {
                return $query->where('year', '<=', $request->max_age);
            });

            $catsQuery->when($request->has('min_age'), function ($query) use ($request) {
                return $query->where('year', '>=', $request->min_age);
            })->when($request->has('max_age'), function ($query) use ($request) {
                return $query->where('year', '<=', $request->max_age);
            });
        }
        if ($request->has('search')) {
            $search = $request->search;
            $dogsQuery->where(function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });

            $catsQuery->where(function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Paginate the results
        $dogs = $dogsQuery->paginate(3);
        $cats = $catsQuery->paginate(3);
        // Transform the collection to include base64-encoded images
        $catsWithImageUrls = $cats->transform(function ($cat) {
            $cat->images = collect($cat->images)->map(function ($base64Image) {
                // Return the base64 image data as it is
                return $base64Image;
            });

            return $cat;
        });
        $dogsWithImageUrls = $dogs->transform(function ($dog) {
            $dog->images = collect($dog->images)->map(function ($base64Image) {
                // Return the base64 image data as it is
                return $base64Image;
            });

            return $dog;
        });

        // Return response as an object with a 'data' key
        return response()->json([
            'dogs' => $dogsWithImageUrls,
            'cats' => $catsWithImageUrls,
            'dogs_pagination' => $dogs->toArray(),
            'cats_pagination' => $cats->toArray(),
        ]);
    }
}
