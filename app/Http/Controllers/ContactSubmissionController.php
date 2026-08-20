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
            'name' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:254'],
            'phone' => ['nullable', 'string', 'max:80'],
            'message' => ['nullable', 'string', 'max:4000'],
            'fields' => ['nullable', 'array', 'max:30'],
            'fields.*' => ['nullable'],
            'received_at' => ['nullable', 'date'],
        ]);

        $fields = collect($validated['fields'] ?? [])
            ->mapWithKeys(function ($value, $key) {
                $safeKey = mb_substr(strip_tags((string) $key), 0, 80);
                $safeValue = is_scalar($value) || $value === null
                    ? mb_substr(strip_tags((string) $value), 0, 1000)
                    : (is_array($value) ? collect($value)->map(fn($item)=>mb_substr(strip_tags((string)$item),0,1000))->implode(', ') : '');
                return $safeKey === '' ? [] : [$safeKey => $safeValue];
            })->all();

        $name = trim((string) ($validated['name'] ?? $fields['name'] ?? $fields['full_name'] ?? 'Website visitor'));
        $email = trim((string) ($validated['email'] ?? $fields['email'] ?? ''));
        $phone = trim((string) ($validated['phone'] ?? $fields['phone'] ?? $fields['mobile'] ?? ''));
        $message = trim((string) ($validated['message'] ?? ''));
        if ($message === '') {
            $message = collect($fields)->map(fn($value,$key)=>ucwords(str_replace('_',' ',$key)).': '.$value)->implode("\n");
        }

        $submission = $website->contactSubmissions()->create([
            'name' => mb_substr($name ?: 'Website visitor', 0, 120),
            'email' => filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '',
            'phone' => $phone !== '' ? mb_substr($phone, 0, 80) : null,
            'message' => mb_substr($message ?: 'Website form submission', 0, 4000),
            'fields' => $fields,
            'source' => 'custom_spark_form',
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

        if (trim((string) $request->input('company', '')) !== '') {
            return response()->json(['status' => 'success', 'message' => 'Thanks — your inquiry has been received.']);
        }

        $request->validate([
            'name' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:254'],
            'phone' => ['nullable', 'string', 'max:80'],
            'message' => ['nullable', 'string', 'max:4000'],
            '_cosmic_form_name' => ['nullable', 'string', 'max:120'],
            '_cosmic_success_message' => ['nullable', 'string', 'max:500'],
            '_cosmic_required' => ['nullable', 'string', 'max:700'],
        ]);

        $reserved = ['company', 'name', 'email', 'phone', 'message', '_cosmic_form_name', '_cosmic_success_message', '_cosmic_required'];
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

        $requiredNames = collect(explode(',', (string) $request->input('_cosmic_required', '')))
            ->map(fn($name) => trim($name))
            ->filter(fn($name) => preg_match('/^[a-z][a-z0-9_]{0,63}$/', $name))
            ->unique()
            ->values();
        foreach ($requiredNames as $requiredName) {
            $value = $request->input($requiredName);
            $hasValue = is_array($value) ? collect($value)->filter(fn($item)=>trim((string)$item)!=='')->isNotEmpty() : trim((string)$value) !== '';
            if (!$hasValue) {
                return response()->json(['status'=>'error','message'=>'Please complete all required fields.'], 422);
            }
        }

        $isCustomSparkForm = trim((string) $request->input('_cosmic_form_name', '')) !== '' || $requiredNames->isNotEmpty();
        if (!$isCustomSparkForm) {
            $request->validate([
                'name' => ['required','string','max:120'],
                'email' => ['required','email','max:254'],
                'message' => ['required','string','max:4000'],
            ]);
        }

        $name = trim((string) $request->input('name', ''));
        $email = trim((string) $request->input('email', ''));
        $phone = trim((string) $request->input('phone', ''));
        $message = trim((string) $request->input('message', ''));
        $formName = trim((string) $request->input('_cosmic_form_name', 'Website inquiry'));

        // ContactSubmissions historically requires name/email/message columns.
        // Functional Custom Sparks may intentionally omit any of those fields,
        // so preserve the schema exactly and derive harmless storage fallbacks.
        if ($name === '') {
            $name = trim((string) ($fields['name'] ?? $fields['full_name'] ?? $fields['first_name'] ?? 'Website visitor'));
        }
        if ($email === '') {
            $email = trim((string) ($fields['email'] ?? ''));
        }
        if ($phone === '') {
            $phone = trim((string) ($fields['phone'] ?? $fields['mobile'] ?? ''));
        }
        if ($message === '') {
            $message = collect($fields)
                ->map(fn ($value, $key) => ucwords(str_replace('_', ' ', (string) $key)).': '.$value)
                ->implode("\n");
        }
        if ($message === '') {
            $message = $formName !== '' ? $formName : 'Website form submission';
        }

        $submission = $website->contactSubmissions()->create([
            'name' => mb_substr($name ?: 'Website visitor', 0, 120),
            'email' => filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '',
            'phone' => $phone !== '' ? mb_substr($phone, 0, 80) : null,
            'message' => mb_substr($message, 0, 4000),
            'fields' => $fields,
            'source' => 'custom_spark_form',
            'received_at' => now(),
        ]);

        $recipient = trim((string) ($website->contact_email ?: $website->user?->email));
        $validVisitorEmail = filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;

        if (filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            try {
                Mail::send('emails.contact-inquiry-owner', [
                    'website' => $website,
                    'submission' => $submission,
                    'contact' => [
                        'name' => $name ?: 'Website visitor',
                        'email' => $validVisitorEmail ?: '',
                        'phone' => $phone,
                        'message' => $message,
                    ],
                    'fields' => $fields,
                ], function ($mail) use ($recipient, $validVisitorEmail, $name, $website, $formName) {
                    $mail->to($recipient)
                        ->subject(($formName !== '' ? $formName : 'New inquiry').' — '.$website->name);
                    if ($validVisitorEmail) {
                        $mail->replyTo($validVisitorEmail, $name ?: 'Website visitor');
                    }
                });
            } catch (\Throwable $exception) {
                Log::warning('Preview custom form owner email could not be delivered.', [
                    'website_id' => $website->id,
                    'submission_id' => $submission->id,
                    'recipient' => $recipient,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        if ($validVisitorEmail) {
            try {
                Mail::send('emails.contact-inquiry-thank-you', [
                    'website' => $website,
                    'submission' => $submission,
                    'contact' => [
                        'name' => $name ?: 'Website visitor',
                        'email' => $validVisitorEmail,
                        'phone' => $phone,
                        'message' => $message,
                    ],
                    'fields' => $fields,
                ], function ($mail) use ($validVisitorEmail, $name, $website, $recipient) {
                    $mail->to($validVisitorEmail, $name ?: 'Website visitor')
                        ->subject('Thanks for contacting '.$website->name);
                    if (filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
                        $mail->replyTo($recipient, $website->name);
                    }
                });
            } catch (\Throwable $exception) {
                Log::warning('Preview custom form thank-you email could not be delivered.', [
                    'website_id' => $website->id,
                    'submission_id' => $submission->id,
                    'recipient' => $validVisitorEmail,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        $success = trim((string) $request->input('_cosmic_success_message', ''));
        return response()->json([
            'status' => 'success',
            'message' => $success !== '' ? mb_substr($success, 0, 500) : 'Thank you! Your inquiry has been sent successfully. We’ll be in touch soon.',
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
