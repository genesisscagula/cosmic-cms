<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Models\Website; // Siguroha nga sakto ang namespace sa imong Model
use Intervention\Image\Facades\Image; // Import ni sa taas sa imong controller

class ImageController extends Controller
{
    // Function para sa pag-upload sa file
    public function uploadImage(Request $request)
    {
        $request->validate([
            'image' => 'required|image|mimes:jpeg,jpg,png,gif,webp,avif|max:4096',
            'website_id' => 'required'
        ]);

        $websiteId = $request->website_id;
        $path = $request->file('image')->store("websites/{$websiteId}", 'public');

        return response()->json([
            'url' => asset('storage/' . $path)
        ]);
    }

    public function update(Request $request)
	{
	    try {

	        $request->validate([
	            'website_id' => 'required|integer|exists:websites,id',
	            'block_index' => 'required|integer',

	            // Allow all common image formats including AVIF
	            'image' => 'required|file|mimes:jpeg,jpg,png,gif,webp,avif|max:4096',
	        ]);

	        $website = Website::findOrFail($request->website_id);

	        if (!$request->hasFile('image')) {
	            return response()->json([
	                'message' => 'No image uploaded.'
	            ], 422);
	        }

	        $file = $request->file('image');

	        if (!$file->isValid()) {
	            return response()->json([
	                'message' => 'Uploaded file is invalid.'
	            ], 422);
	        }

	        $path = $file->store(
	            "websites/{$website->id}",
	            'public'
	        );

	        return response()->json([
	            'success' => true,
	            'url' => asset('storage/' . $path)
	        ]);

	    } catch (\Illuminate\Validation\ValidationException $e) {

	        return response()->json([
	            'message' => 'Validation failed.',
	            'errors' => $e->errors()
	        ], 422);

	    } catch (\Throwable $e) {

	        return response()->json([
	            'message' => $e->getMessage(),
	            'line' => $e->getLine(),
	            'file' => basename($e->getFile())
	        ], 500);

	    }
	}

	public function uploadBlockImage(Request $request)
	{
	    $request->validate([
	        'website_id' => 'required',
	        'image' => 'required|image|mimes:jpeg,jpg,png,gif,webp,avif|max:4096'
	    ]);

	    // Upload ra gyud ni siya
	    $path = $request->file('image')->store("websites/{$request->website_id}", 'public');
	    $imageUrl = asset('storage/' . $path);

	    // I-return lang ang URL
	    return response()->json(['url' => $imageUrl]);
	}
}