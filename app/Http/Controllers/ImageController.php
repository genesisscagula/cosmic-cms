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
            'image' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
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
	    $request->validate([
	        'website_id' => 'required',
	        'block_index' => 'required|integer',
	        'image' => 'required|image'
	    ]);

	    $website = \App\Models\Website::find($request->website_id);
	    // 1. Upload
	    $path = $request->file('image')->store("websites/{$website->id}", 'public');
	    $imageUrl = asset('storage/' . $path);

	    // 2. Update JSON
	    $blocks = json_decode($website->blocks, true);
	    $blocks[$request->block_index]['image_url'] = $imageUrl;
	    $website->update(['blocks' => json_encode($blocks)]);

	    // 3. Return URL
	    return response()->json(['url' => $imageUrl]);
	}

	public function uploadBlockImage(Request $request)
	{
	    $request->validate([
	        'website_id' => 'required',
	        'image' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048'
	    ]);

	    // Upload ra gyud ni siya
	    $path = $request->file('image')->store("websites/{$request->website_id}", 'public');
	    $imageUrl = asset('storage/' . $path);

	    // I-return lang ang URL
	    return response()->json(['url' => $imageUrl]);
	}
}