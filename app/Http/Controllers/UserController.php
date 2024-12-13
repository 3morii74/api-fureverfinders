<?php

namespace App\Http\Controllers;

use App\Models\Cat;
use App\Models\Dog;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    public function show()
    {
        try {
            // Fetch the authenticated user
            $user = auth()->user();

            // Fetch dogs and cats related to the authenticated user, along with images
            $dogs = Dog::where('user_id', $user->id)->get();
            $cats = Cat::where('user_id', $user->id)->get();

            // Include the pets in the response
            return response()->json([
                'user' => $user,  // Include user details
                'dogs' => $dogs,  // Include dogs related to the user
                'cats' => $cats,  // Include cats related to the user
            ]);
        } catch (Exception $e) {
            // Catch any exception and return a response with the error message
            return response()->json(['error' => 'Something went wrong. Please try again.'], 500);
        }
    }
    public function destroy(Request $request)
    {
        try {
            // Get the authenticated user
            $user = auth()->user();

            // Validate the input for pet type and id
            $validatedData = $request->validate([
                'type' => 'required|in:dog,cat',  // Ensure 'type' is either 'dog' or 'cat'
                'id' => 'required|integer', // Ensure 'id' is an integer
            ]);

            // Check if the pet exists based on the type and id
            if ($validatedData['type'] === 'dog') {
                $pet = $user->dogs()->find($validatedData['id']);  // Find the dog belonging to the user
            } else {
                $pet = $user->cats()->find($validatedData['id']);  // Find the cat belonging to the user
            }

            // If pet not found, return an error
            if (!$pet) {
                return response()->json(['message' => 'Pet not found or does not belong to the user.'], 404);
            }

            // Proceed to delete the pet
            $pet->delete();

            return response()->json(['message' => 'Pet deleted successfully.'], 200);
        } catch (ValidationException $e) {
            // Catch validation exceptions and return the errors
            return response()->json(['error' => $e->errors()], 422);
        } catch (Exception $e) {
            // Catch any other exception and return a generic error message
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
