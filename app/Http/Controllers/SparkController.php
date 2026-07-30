<?php
namespace App\Http\Controllers;

use App\Models\Spark;
use App\Services\CreditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SparkController extends Controller
{
    public function index(Request $request): Response
    {
        $ownedIds = $request->user()->sparks()->pluck('sparks.id');
        $sparks = Spark::query()->with('category:id,name,slug')->where('is_published', true)->orderByDesc('is_featured')->orderBy('sort_order')->get()->map(fn (Spark $spark) => [
            'id' => $spark->id, 'name' => $spark->name, 'slug' => $spark->slug,
            'description' => $spark->description, 'thumbnail' => $spark->thumbnail,
            'credits' => $spark->credits, 'is_featured' => $spark->is_featured,
            'category' => $spark->category?->only(['id','name','slug']),
            'owned' => $ownedIds->contains($spark->id),
        ]);

        return Inertia::render('Sparks/Index', [
            'sparks' => $sparks,
            'categories' => $sparks->pluck('category')->filter()->unique('id')->values(),
            'ownedCount' => $ownedIds->count(),
        ]);
    }

    public function unlock(Request $request, Spark $spark, CreditService $credits): JsonResponse
    {
        abort_unless($spark->is_published, 404);
        $user = $request->user();
        if ($user->sparks()->whereKey($spark->id)->exists()) {
            return response()->json(['message' => 'Spark already unlocked.', 'owned' => true, 'credit_balance' => (int) $user->fresh()->credits]);
        }

        return DB::transaction(function () use ($user, $spark, $credits) {
            $transaction = $credits->consume($user, $spark->credits, 'Unlocked Spark: '.$spark->name, null, 'spark-unlock-'.$spark->id.'-'.uniqid(), ['spark_id' => $spark->id]);
            $user->sparks()->attach($spark->id, ['credits_paid' => $spark->credits, 'unlocked_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
            return response()->json(['message' => $spark->name.' added to My Sparks.', 'owned' => true, 'credit_balance' => (int) $transaction->balance_after]);
        });
    }
}
