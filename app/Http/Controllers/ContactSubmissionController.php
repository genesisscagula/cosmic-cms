<?php

namespace App\Http\Controllers;

use App\Models\ContactSubmission;
use App\Models\Website;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
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


    public function storeFromPreview(Request $request, string $slug)
    {
        $website = Website::query()->where('preview_slug', $slug)->firstOrFail();

        // Honeypot submissions receive a normal success response without being stored.
        if (trim((string) $request->input('company', '')) !== '') {
            return response()->json(['status' => 'success', 'message' => 'Thanks — your inquiry has been received.']);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:254'],
            'phone' => ['nullable', 'string', 'max:80'],
            'message' => ['required', 'string', 'max:4000'],
        ]);

        $reserved = ['company', 'name', 'email', 'phone', 'message'];
        $fields = collect($request->except($reserved))
            ->filter(fn ($value, $key) => is_string($key) && preg_match('/^[a-z][a-z0-9_]{0,63}$/', $key))
            ->mapWithKeys(function ($value, $key) {
                $safeKey = mb_substr(strip_tags((string) $key), 0, 80);
                if (is_array($value)) {
                    $safeValue = collect($value)->map(fn ($item) => mb_substr(strip_tags((string) $item), 0, 1000))->filter()->values()->implode(', ');
                } else {
                    $safeValue = mb_substr(strip_tags((string) $value), 0, 1000);
                }
                return $safeKey === '' ? [] : [$safeKey => $safeValue];
            })->all();

        $submission = $website->contactSubmissions()->create([
            ...$validated,
            'fields' => $fields,
            'received_at' => now(),
        ]);

        $recipient = trim((string) ($website->contact_email ?: $website->user?->email));
        if (filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            try {
                Mail::send('emails.contact-inquiry-owner', [
                    'website' => $website,
                    'submission' => $submission,
                    'contact' => $validated,
                    'fields' => $fields,
                ], function ($mail) use ($recipient, $validated, $website) {
                    $mail->to($recipient)
                        ->replyTo($validated['email'], $validated['name'])
                        ->subject('New inquiry from '.$website->name);
                });
            } catch (\Throwable $exception) {
                // Keep the inquiry safely in the Cosmic inbox even if the mail transport is temporarily unavailable.
                Log::warning('Preview inquiry owner email could not be delivered.', [
                    'website_id' => $website->id,
                    'submission_id' => $submission->id,
                    'recipient' => $recipient,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        // Always attempt a visitor acknowledgement separately. A receiver-delivery issue must
        // never prevent the person who submitted the form from receiving their confirmation.
        try {
            Mail::send('emails.contact-inquiry-thank-you', [
                'website' => $website,
                'submission' => $submission,
                'contact' => $validated,
                'fields' => $fields,
            ], function ($mail) use ($validated, $website, $recipient) {
                $mail->to($validated['email'], $validated['name'])
                    ->subject('Thanks for contacting '.$website->name);

                if (filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
                    $mail->replyTo($recipient, $website->name);
                }
            });
        } catch (\Throwable $exception) {
            Log::warning('Preview inquiry thank-you email could not be delivered.', [
                'website_id' => $website->id,
                'submission_id' => $submission->id,
                'recipient' => $validated['email'],
                'error' => $exception->getMessage(),
            ]);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Thank you! Your inquiry has been sent successfully. We’ll be in touch soon.',
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
