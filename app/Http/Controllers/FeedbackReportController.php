<?php

namespace App\Http\Controllers;

use App\Models\FeedbackReport;
use App\Models\Page;
use App\Models\TrialGeneration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class FeedbackReportController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $this->validatedSubmission($request);
        $page = Page::query()->with('website')->findOrFail($data['page_id']);
        $this->authorize('editBuilder', $page->website);

        $report = $this->createReport(
            $request,
            $data,
            $page,
            $request->user()?->name,
            $request->user()?->email,
            $request->user()?->id,
        );

        return response()->json([
            'message' => 'Thanks — your feedback was sent to the Cosmic team.',
            'report_id' => $report->id,
        ], 201);
    }

    public function storeTrial(Request $request, TrialGeneration $trial): JsonResponse
    {
        abort_unless($trial->status === 'ready' && ! $trial->claimed_at, 404);
        $data = $this->validatedSubmission($request);
        $page = Page::query()
            ->whereKey($data['page_id'])
            ->where('website_id', $trial->website_id)
            ->firstOrFail();

        $report = $this->createReport(
            $request,
            $data,
            $page,
            $trial->business_name,
            $trial->email,
            null,
            $trial->id,
        );

        return response()->json([
            'message' => 'Thanks — your feedback was sent to the Cosmic team.',
            'report_id' => $report->id,
        ], 201);
    }

    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'category' => ['nullable', Rule::in(array_merge(['all'], FeedbackReport::CATEGORIES))],
            'status' => ['nullable', Rule::in(array_merge(['all'], FeedbackReport::STATUSES))],
        ]);

        $query = FeedbackReport::query()
            ->with(['user:id,name,email', 'website:id,name', 'page:id,title,slug'])
            ->latest();

        $category = $filters['category'] ?? 'all';
        if ($category !== 'all') {
            $query->where('category', $category);
        }

        $status = $filters['status'] ?? 'all';
        if ($status !== 'all') {
            $query->where('status', $status);
        }

        if ($search = trim((string) ($filters['q'] ?? ''))) {
            $query->where(function ($inner) use ($search) {
                $inner->where('description', 'like', "%{$search}%")
                    ->orWhere('reporter_name', 'like', "%{$search}%")
                    ->orWhere('reporter_email', 'like', "%{$search}%")
                    ->orWhereHas('website', fn ($website) => $website->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('page', fn ($page) => $page->where('title', 'like', "%{$search}%"));
            });
        }

        $reports = $query->paginate(25)->withQueryString()->through(fn (FeedbackReport $report) => [
            'id' => $report->id,
            'category' => $report->category,
            'status' => $report->status,
            'description' => Str::limit($report->description, 180),
            'reporter_name' => $report->reporter_name ?: $report->user?->name,
            'reporter_email' => $report->reporter_email ?: $report->user?->email,
            'website_name' => $report->website?->name,
            'page_title' => $report->page?->title,
            'has_screenshot' => filled($report->screenshot_path),
            'unread' => blank($report->viewed_at),
            'created_at' => optional($report->created_at)?->toIso8601String(),
        ]);

        return Inertia::render('Admin/FeedbackInbox', [
            'reports' => $reports,
            'filters' => [
                'q' => $filters['q'] ?? '',
                'category' => $category,
                'status' => $status,
            ],
            'unreadCount' => FeedbackReport::query()->whereNull('viewed_at')->count(),
        ]);
    }

    public function show(FeedbackReport $feedbackReport): Response
    {
        if (! $feedbackReport->viewed_at) {
            $feedbackReport->forceFill(['viewed_at' => now()])->save();
        }

        $feedbackReport->load(['user:id,name,email', 'website:id,name', 'page:id,title,slug', 'trialGeneration:id,token,business_name,email']);

        return Inertia::render('Admin/FeedbackReport', [
            'report' => $this->detailPayload($feedbackReport),
        ]);
    }

    public function update(Request $request, FeedbackReport $feedbackReport)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(FeedbackReport::STATUSES)],
            'admin_notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $feedbackReport->forceFill([
            'status' => $data['status'],
            'admin_notes' => trim((string) ($data['admin_notes'] ?? '')) ?: null,
            'viewed_at' => $feedbackReport->viewed_at ?: now(),
            'resolved_at' => $data['status'] === 'resolved'
                ? ($feedbackReport->resolved_at ?: now())
                : null,
        ])->save();

        return back()->with('success', 'Feedback report updated.');
    }

    public function screenshot(FeedbackReport $feedbackReport): BinaryFileResponse
    {
        abort_unless(filled($feedbackReport->screenshot_path), 404);
        abort_unless(Storage::disk('local')->exists($feedbackReport->screenshot_path), 404);

        $safeName = preg_replace(
            '/[^A-Za-z0-9._-]+/',
            '-',
            basename((string) ($feedbackReport->screenshot_original_name ?: 'feedback-screenshot')),
        ) ?: 'feedback-screenshot';

        return response()->file(
            Storage::disk('local')->path($feedbackReport->screenshot_path),
            [
                'Content-Type' => $feedbackReport->screenshot_mime ?: 'application/octet-stream',
                'Content-Disposition' => 'inline; filename="'.$safeName.'"',
                'Cache-Control' => 'private, no-store',
            ],
        );
    }

    private function validatedSubmission(Request $request): array
    {
        return $request->validate([
            'category' => ['required', Rule::in(FeedbackReport::CATEGORIES)],
            'description' => ['required', 'string', 'min:5', 'max:5000'],
            'screenshot' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp', 'max:8192'],
            'source_url' => ['nullable', 'url:http,https', 'max:2048'],
            'page_id' => ['required', 'integer', 'exists:pages,id'],
            'context' => ['nullable', 'array'],
            'context.page_title' => ['nullable', 'string', 'max:255'],
            'context.page_slug' => ['nullable', 'string', 'max:255'],
            'context.luna_scope' => ['nullable', 'string', 'max:255'],
            'context.builder_mode' => ['nullable', 'string', 'max:50'],
            'context.blocks_count' => ['nullable', 'integer', 'min:0', 'max:2000'],
            'context.viewport_width' => ['nullable', 'integer', 'min:1', 'max:20000'],
            'context.viewport_height' => ['nullable', 'integer', 'min:1', 'max:20000'],
        ]);
    }

    private function createReport(
        Request $request,
        array $data,
        Page $page,
        ?string $reporterName,
        ?string $reporterEmail,
        ?int $userId,
        ?int $trialId = null,
    ): FeedbackReport {
        $description = trim(strip_tags($data['description']));
        abort_if($description === '', 422, 'Feedback description is required.');

        $screenshot = $request->file('screenshot');
        $screenshotPath = null;
        if ($screenshot) {
            $extension = strtolower($screenshot->guessExtension() ?: $screenshot->extension() ?: 'png');
            $filename = Str::uuid().'.'.$extension;
            $screenshotPath = $screenshot->storeAs('feedback-reports/'.now()->format('Y/m'), $filename, 'local');
            abort_unless($screenshotPath, 500, 'The screenshot could not be stored.');
        }

        return FeedbackReport::query()->create([
            'user_id' => $userId,
            'website_id' => $page->website_id,
            'page_id' => $page->id,
            'trial_generation_id' => $trialId,
            'category' => $data['category'],
            'status' => 'new',
            'description' => $description,
            'reporter_name' => Str::limit(trim((string) $reporterName), 255) ?: null,
            'reporter_email' => Str::limit(Str::lower(trim((string) $reporterEmail)), 255) ?: null,
            'source_url' => $data['source_url'] ?? null,
            'context' => Arr::only($data['context'] ?? [], [
                'page_title', 'page_slug', 'luna_scope', 'builder_mode', 'blocks_count',
                'viewport_width', 'viewport_height',
            ]),
            'user_agent' => Str::limit((string) $request->userAgent(), 1000) ?: null,
            'ip_hash' => $request->ip() ? hash('sha256', $request->ip().'|'.config('app.key')) : null,
            'screenshot_path' => $screenshotPath,
            'screenshot_original_name' => $screenshot ? Str::limit($screenshot->getClientOriginalName(), 255) : null,
            'screenshot_mime' => $screenshot?->getMimeType(),
            'screenshot_size' => $screenshot?->getSize(),
        ]);
    }

    private function detailPayload(FeedbackReport $report): array
    {
        return [
            'id' => $report->id,
            'category' => $report->category,
            'status' => $report->status,
            'description' => $report->description,
            'reporter_name' => $report->reporter_name ?: $report->user?->name,
            'reporter_email' => $report->reporter_email ?: $report->user?->email,
            'website' => $report->website ? ['id' => $report->website->id, 'name' => $report->website->name] : null,
            'page' => $report->page ? ['id' => $report->page->id, 'title' => $report->page->title, 'slug' => $report->page->slug] : null,
            'trial' => $report->trialGeneration ? [
                'id' => $report->trialGeneration->id,
                'business_name' => $report->trialGeneration->business_name,
                'email' => $report->trialGeneration->email,
            ] : null,
            'source_url' => $report->source_url,
            'context' => $report->context ?: [],
            'user_agent' => $report->user_agent,
            'has_screenshot' => filled($report->screenshot_path),
            'screenshot_url' => filled($report->screenshot_path)
                ? route('admin.feedback.screenshot', $report)
                : null,
            'screenshot_name' => $report->screenshot_original_name,
            'screenshot_size' => $report->screenshot_size,
            'admin_notes' => $report->admin_notes,
            'viewed_at' => optional($report->viewed_at)?->toIso8601String(),
            'resolved_at' => optional($report->resolved_at)?->toIso8601String(),
            'created_at' => optional($report->created_at)?->toIso8601String(),
        ];
    }
}
