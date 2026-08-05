<?php

namespace App\Http\Controllers;

use App\Models\Website;
use App\Models\WebsiteAnalyticsDaily;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AnalyticsEventController extends Controller
{
    public function store(Request $request, Website $website): JsonResponse
    {
        abort_unless(hash_equals((string) $website->api_token, (string) $request->header('X-Cosmic-Token')), 401);
        $data = $request->validate([
            'page_views' => ['nullable','integer','min:0','max:10000'],
            'visitors' => ['nullable','integer','min:0','max:10000'],
            'sessions' => ['nullable','integer','min:0','max:10000'],
            'conversions' => ['nullable','integer','min:0','max:10000'],
            'engaged_sessions' => ['nullable','integer','min:0','max:10000'],
            'duration_seconds' => ['nullable','integer','min:0','max:864000'],
            'date' => ['nullable','date','before_or_equal:today'],
        ]);
        $date = $data['date'] ?? now($website->timezone ?: config('app.timezone'))->toDateString();
        DB::transaction(function () use ($website, $data, $date) {
            $row = WebsiteAnalyticsDaily::query()->lockForUpdate()->firstOrCreate(['website_id'=>$website->id,'date'=>$date]);
            foreach (['page_views','visitors','sessions','conversions','engaged_sessions','duration_seconds'] as $metric) {
                $row->{$metric} = (int) $row->{$metric} + (int) ($data[$metric] ?? 0);
            }
            $row->save();
        }, 3);
        return response()->json(['status'=>'accepted'], 202);
    }
}
