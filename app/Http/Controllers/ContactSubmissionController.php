<?php

namespace App\Http\Controllers;

use App\Models\ContactSubmission;
use App\Models\Website;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ContactSubmissionController extends Controller
{
    public function index(Website $website)
    {
        $this->authorize('view', $website);

        return Inertia::render('Websites/Inquiries', [
            'website' => $website,
            'submissions' => $website->contactSubmissions()
                ->latest('received_at')
                ->limit(100)
                ->get(),
        ]);
    }

    public function storeFromConnector(Request $request, Website $website)
    {
        $providedSecret = (string) $request->header('X-Cosmic-Sync-Secret');
        $expectedSecret = (string) $website->deployment_secret;

        if ($expectedSecret === '' || ! hash_equals($expectedSecret, $providedSecret)) {
            return response()->json(['message' => 'Unauthorized connector request.'], 401);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:254'],
            'phone' => ['nullable', 'string', 'max:80'],
            'message' => ['required', 'string', 'max:4000'],
            'fields' => ['nullable', 'array', 'max:30'],
            'fields.*' => ['nullable'],
            'received_at' => ['nullable', 'date'],
        ]);

        $fields = collect($validated['fields'] ?? [])
            ->mapWithKeys(function ($value, $key) {
                $safeKey = mb_substr(strip_tags((string) $key), 0, 80);
                $safeValue = is_scalar($value) || $value === null
                    ? mb_substr(strip_tags((string) $value), 0, 1000)
                    : '';

                return $safeKey === '' ? [] : [$safeKey => $safeValue];
            })
            ->all();

        $submission = $website->contactSubmissions()->create([
            ...$validated,
            'fields' => $fields,
            'received_at' => $validated['received_at'] ?? now(),
        ]);

        return response()->json([
            'status' => 'success',
            'submission_id' => $submission->id,
        ], 201);
    }

    public function update(Request $request, Website $website, ContactSubmission $submission)
    {
        $this->authorize('update', $website);

        abort_unless($submission->website_id === $website->id, 404);

        $validated = $request->validate([
            'status' => ['required', 'in:unread,read,archived'],
        ]);

        $status = $validated['status'];
        $submission->update([
            'status' => $status,
            'read_at' => $status === 'unread' ? null : ($submission->read_at ?? now()),
            'archived_at' => $status === 'archived' ? now() : null,
        ]);

        return response()->json(['submission' => $submission->fresh()]);
    }
}
