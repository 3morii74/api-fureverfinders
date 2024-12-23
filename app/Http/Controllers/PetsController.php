<?php

namespace App\Http\Controllers;

use App\Models\Cat;
use App\Models\Dog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Validator;
use MongoDB\BSON\Binary;
use MongoDB\Client as MongoClient;

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
            'latitude'       => 'nullable|numeric|min:0',
            'longitude'       => 'nullable|numeric|min:0',
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
        $imageVector = [];
        foreach ($request->file('images') as $image) {
            // Get image content as a string
            $imageContent = file_get_contents($image->getRealPath());

            // Convert image content to base64
            $imageVector[] = $this->extractFeatureVector($imageContent);
        }
        $coordinates = [
            (float) $request->input('longitude'), // Ensure longitude is a float
            (float) $request->input('latitude')  // Ensure latitude is a float
        ];
        if ($request['type'] === 'dog') {
            $dog = Dog::create([
                'user_id' => auth()->id(),
                'pet_name'    => $request['pet_name'],
                'gender'      => $request['gender'],
                'year'        => $request['year'],
                'month'       => $request['month'],
                'address' => [
                    'type' => 'Point',
                    'coordinates' => $coordinates // [longitude, latitude]
                ],
                'weight'      => $request['weight'],
                'description' => $request['description'],
                'status' => $request['status'],
                'images' => $imageBase64s,
                'imageVector' => $imageVector,
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
                'address' => [
                    'type' => 'Point',
                    'coordinates' => $coordinates // [longitude, latitude]
                ],
                'weight'      => $request['weight'],
                'description' => $request['description'],
                'status' => $request['status'],
                'images' => $imageBase64s,
                'imageVector' => $imageVector,

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
        // Step 1: Prepare the base queries for cats and dogs
        $dogsQuery = Dog::with('user');
        $catsQuery = Cat::with('user');

        // Step 2: Filter by pet type (cat or dog)
        if ($request->has('pet_type')) {
            if ($request->pet_type === 'dog') {
                $catsQuery = Cat::whereNull('id'); // Return empty query for cats
            } elseif ($request->pet_type === 'cat') {
                $dogsQuery = Dog::whereNull('id'); // Return empty query for dogs
            }
        }

        // Step 3: Filter by gender
        if ($request->has('gender')) {
            $dogsQuery->where('gender', $request->gender);
            $catsQuery->where('gender', $request->gender);
        }

        // Step 4: Filter by age range
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

        if ($request->has('latitude') && $request->has('longitude')) {
            $latitude = $request->latitude;
            $longitude = $request->longitude;
            $maxDistance = 10000000; // Max distance in meters (e.g., 10000 km)

            // Perform geospatial query to find nearby dogs and cats
            $dogsQuery->whereRaw([
                'address' => [
                    '$nearSphere' => [
                        '$geometry' => [
                            'type' => 'Point',
                            'coordinates' => [(float)$longitude, (float)$latitude],
                        ],
                        '$maxDistance' => $maxDistance, // distance in meters
                    ],
                ],
            ]);

            $catsQuery->whereRaw([
                'address' => [
                    '$nearSphere' => [
                        '$geometry' => [
                            'type' => 'Point',
                            'coordinates' => [(float)$longitude, (float)$latitude],
                        ],
                        '$maxDistance' => $maxDistance, // distance in meters
                    ],
                ],
            ]);
        }

        // Step 5: Filter by text search (name or description)
        if ($request->has('search')) {
            $search = $request->search;
            $dogsQuery->where(function ($query) use ($search) {
                $query->where('pet_name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });

            $catsQuery->where(function ($query) use ($search) {
                $query->where('pet_name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }
        $similarCats = null;
        $similarDogs = null;
        // Step 6: Handle image similarity search
        if ($request->has('image')) {
            $uploadedImage = $request->file('image');
            $imageContent = file_get_contents($uploadedImage->getRealPath());
            // Step 6a: Extract feature vector (call an external Python script or a service)
            $featureVector = $this->extractFeatureVector($imageContent);
            // Step 6b: Query MongoDB for similar cats and dogs
            $similarCats = $this->findSimilarPets('cat', $featureVector);
            $similarDogs = $this->findSimilarPets('dog', $featureVector);
        }

        // Step 7: Paginate or fetch full results
        if (!$request->has('full')) {

            $dogs = $dogsQuery->paginate(3);
            $cats = $catsQuery->paginate(3);
        } else if ($request->has('image')) {

            $dogs = $similarDogs;
            $cats = $similarCats;
        } else {
            $dogs = $dogsQuery->get(); // Fetch all results for dogs
            $cats = $catsQuery->get(); // Fetch all results for cats
        }

        // Step 8: Transform the results to include images
        $catsWithImageUrls = $cats->filter(function ($cat) {
            return $cat !== null; // Keep only non-null dogs
        })->transform(function ($cat) {
            if ($cat) {
                $cat->images = collect($cat->images)->map(function ($base64Image) {
                    return $base64Image;
                });
                return $cat;
            }
        });

        $dogsWithImageUrls = $dogs->filter(function ($dog) {
            return $dog !== null; // Keep only non-null dogs
        })->transform(function ($dog) {
            // If the dog is not null, process it
            $dog->images = collect($dog->images)->map(function ($base64Image) {
                return $base64Image; // Return the base64 image
            });

            return $dog;
        });

        // Step 9: Return the response
        return response()->json([
            'dogs' => $dogsWithImageUrls,
            'cats' => $catsWithImageUrls,
        ]);
    }

    // Helper function to extract feature vector
    private function extractFeatureVector($imageContent)
    {

        // Save the uploaded image temporarily
        $tempImagePath = storage_path('app/temp_image.jpg');
        file_put_contents($tempImagePath, $imageContent);
        if (!file_exists($tempImagePath) || !getimagesize($tempImagePath)) {
            unlink($tempImagePath);
            dd("Invalid image file: ", $tempImagePath);
            return null;
        }
        // Call the Python script to extract features
        $scriptPath = base_path('storage/scripts/extract_features.py');
        // $command = escapeshellcmd("python " . $scriptPath . " " . $tempImagePath);
        $command = escapeshellcmd("python3 " . $scriptPath . " " . $tempImagePath);
        $output = shell_exec($command);
        $output = shell_exec($command . " 2>&1");
        if ($output === null) {
            return response()->json(['error' => 'Failed to execute Python script.'], 500);
        }


        // Parse the output into a PHP array
        $featureVector = json_decode($output, true);

        // Clean up the temporary file

        return $featureVector;
    }


    // Helper function to find similar pets in MongoDB
    private function calculateCosineSimilarity($vector1, $vector2)
    {
        if (is_null($vector2) || is_null($vector1)) {
            // Handle the null case, e.g., return a default value or throw an exception
            // throw new \InvalidArgumentException("Second array cannot be null.");
            return 0;
        }
        $dotProduct = array_sum(array_map(fn($a, $b) => $a * $b, $vector1, $vector2));
        $magnitude1 = sqrt(array_sum(array_map(fn($value) => $value * $value, $vector1)));
        $magnitude2 = sqrt(array_sum(array_map(fn($value) => $value * $value, $vector2)));

        // Avoid division by zero
        if ($magnitude1 == 0 || $magnitude2 == 0) {
            return 0;
        }

        return $dotProduct / ($magnitude1 * $magnitude2);
    }

    private function findSimilarPets($petType, $featureVector): mixed
    {
        // Select the appropriate MongoDB model based on pet type
        $model = $petType === 'cat' ? Cat::class : Dog::class;

        // Fetch all pets
        $pets = $model::all();

        // Calculate the Cosine Similarity for each pet's image-derived feature vector
        $similarPets = $pets->filter(function ($pet) use ($featureVector) {
            // Use the first Base64-encoded image to extract features
            $vectorPet = $pet->imageVector[0];
            $similarity = $this->calculateCosineSimilarity($vectorPet, $featureVector);
            return $similarity >= 0.7; // Only include pets with similarity >= 0.7
        })->values();


        // Sort the pets by similarity (descending) and return the closest ones
        return $similarPets;
    }
}
