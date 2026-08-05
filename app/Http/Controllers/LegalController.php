<?php

namespace App\Http\Controllers;

use App\Models\ConsentRecord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LegalController extends Controller
{
    public function terms(): Response { return $this->page('Terms of Service', 'terms', config('cosmic-legal.terms_version')); }
    public function privacy(): Response { return $this->page('Privacy Policy', 'privacy', config('cosmic-legal.privacy_version')); }
    public function cookies(): Response { return $this->page('Cookie Policy', 'cookies', config('cosmic-legal.cookie_version')); }

    public function consent(Request $request): JsonResponse
    {
        $data = $request->validate([
            'analytics' => ['required','boolean'],
            'marketing' => ['required','boolean'],
            'necessary' => ['accepted'],
        ]);

        $subjectKey = hash('sha256', implode('|', [$request->ip(), (string) $request->userAgent()]));
        foreach (['necessary' => true, 'analytics' => $data['analytics'], 'marketing' => $data['marketing']] as $type => $granted) {
            ConsentRecord::create([
                'user_id' => $request->user()?->id,
                'subject_type' => $request->user() ? 'user' : 'visitor',
                'subject_key' => $subjectKey,
                'consent_type' => 'cookie_'.$type,
                'document_version' => config('cosmic-legal.cookie_version'),
                'granted' => $granted,
                'metadata' => ['source' => 'cookie-banner'],
                'ip_hash' => hash('sha256', (string) $request->ip()),
                'user_agent_hash' => hash('sha256', (string) $request->userAgent()),
                'recorded_at' => now(),
            ]);
        }

        return response()->json(['saved' => true, 'version' => config('cosmic-legal.cookie_version')]);
    }

    private function page(string $title, string $type, string $version): Response
    {
        return Inertia::render('Legal/Document', [
            'title' => $title,
            'type' => $type,
            'version' => $version,
            'effectiveDate' => config('cosmic-legal.effective_date'),
            'companyName' => config('cosmic-legal.company_name'),
            'contactEmail' => config('cosmic-legal.contact_email'),
        ]);
    }
}
