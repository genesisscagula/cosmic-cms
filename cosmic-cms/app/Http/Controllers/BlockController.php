<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class BlockController extends Controller
{
    public function update(Request $request)
    {
        // Debug lang usa para makita kung naay nadawat nga data
        \Log::info($request->all());
        
        return response()->json(['message' => 'Controller is working!']);
    }
}