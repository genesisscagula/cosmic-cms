<?php

namespace App\Http\Controllers;

use App\Services\QueueDashboardService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class QueueDashboardController extends Controller
{
    private function guard(Request $request): void
    {
        abort_unless($request->user()?->isPlatformOwner(), 403);
    }

    public function index(Request $request, QueueDashboardService $service)
    {
        $this->guard($request);
        return Inertia::render('Admin/QueueDashboard', $service->snapshot());
    }

    public function status(Request $request, QueueDashboardService $service)
    {
        $this->guard($request);
        return response()->json($service->snapshot());
    }

    public function retryFailed(Request $request)
    {
        $this->guard($request);
        $validated = $request->validate(['uuid' => ['nullable','string']]);
        Artisan::call('queue:retry', [$validated['uuid'] ?? 'all']);
        return back()->with('status', 'Failed queue jobs were requeued.');
    }

    public function forgetFailed(Request $request)
    {
        $this->guard($request);
        DB::table('failed_jobs')->delete();
        return back()->with('status', 'Failed queue history cleared.');
    }
}
