<?php

namespace App\Http\Controllers;

use App\Models\SavedSpark;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SavedSparkController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $sparks = SavedSpark::query()
            ->where('user_id', $request->user()->id)
            ->latest('updated_at')
            ->get()
            ->map(fn (SavedSpark $spark) => $this->payload($spark))
            ->values();

        return response()->json(['saved_sparks' => $sparks]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:140'],
            'spark_type' => ['required', 'string', 'max:140'],
            'payload' => ['required', 'array'],
        ]);

        $payload = $data['payload'];
        unset($payload['_renderKey']);
        $payload['type'] = $data['spark_type'];

        $spark = SavedSpark::create([
            'user_id' => $request->user()->id,
            'name' => trim($data['name']),
            'spark_type' => $data['spark_type'],
            'payload' => $payload,
        ]);

        return response()->json([
            'message' => $spark->name.' saved to Saved Sparks.',
            'saved_spark' => $this->payload($spark),
        ], 201);
    }

    public function update(Request $request, SavedSpark $savedSpark): JsonResponse
    {
        abort_unless($savedSpark->user_id === $request->user()->id, 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:140'],
        ]);

        $savedSpark->update(['name' => trim($data['name'])]);

        return response()->json([
            'message' => 'Saved Spark renamed.',
            'saved_spark' => $this->payload($savedSpark->fresh()),
        ]);
    }

    public function destroy(Request $request, SavedSpark $savedSpark): JsonResponse
    {
        abort_unless($savedSpark->user_id === $request->user()->id, 404);
        $savedSpark->delete();

        return response()->json(['message' => 'Saved Spark deleted.']);
    }

    private function payload(SavedSpark $spark): array
    {
        return [
            'id' => $spark->id,
            'name' => $spark->name,
            'spark_type' => $spark->spark_type,
            'payload' => $spark->payload ?: [],
            'updated_at' => optional($spark->updated_at)->toIso8601String(),
        ];
    }
}
