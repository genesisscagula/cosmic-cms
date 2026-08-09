<?php

namespace App\Http\Controllers;

use App\Models\CosmicChatConversation;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CosmicChatInboxController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['all', 'new', 'qualified', 'follow_up', 'closed'])],
            'view' => ['nullable', Rule::in(['active', 'archived', 'all'])],
        ]);

        $query = CosmicChatConversation::query()
            ->withCount([
                'messages as visitor_message_count' => fn ($q) => $q->where('role', 'user'),
            ])
            ->orderByDesc('last_message_at')
            ->orderByDesc('id');

        $view = $filters['view'] ?? 'active';
        if ($view === 'active') {
            $query->whereNull('archived_at');
        } elseif ($view === 'archived') {
            $query->whereNotNull('archived_at');
        }

        $status = $filters['status'] ?? 'all';
        if ($status !== 'all') {
            $query->where('lead_status', $status);
        }

        if ($search = trim((string) ($filters['q'] ?? ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('visitor_name', 'like', "%{$search}%")
                    ->orWhere('visitor_email', 'like', "%{$search}%")
                    ->orWhere('started_page', 'like', "%{$search}%")
                    ->orWhere('last_page', 'like', "%{$search}%")
                    ->orWhereHas('messages', fn ($messageQuery) =>
                        $messageQuery->where('content', 'like', "%{$search}%")
                    );
            });
        }

        $conversations = $query->paginate(25)->withQueryString()->through(fn ($conversation) => [
            'id' => $conversation->id,
            'public_id' => $conversation->public_id,
            'status' => $conversation->status,
            'lead_status' => $conversation->lead_status,
            'visitor_name' => $conversation->visitor_name,
            'visitor_email' => $conversation->visitor_email,
            'started_page' => $conversation->started_page,
            'last_page' => $conversation->last_page,
            'message_count' => $conversation->message_count,
            'visitor_message_count' => $conversation->visitor_message_count,
            'last_message_at' => optional($conversation->last_message_at)?->toIso8601String(),
            'admin_read_at' => optional($conversation->admin_read_at)?->toIso8601String(),
            'archived_at' => optional($conversation->archived_at)?->toIso8601String(),
            'unread' => !$conversation->admin_read_at
                || ($conversation->last_message_at && $conversation->last_message_at->gt($conversation->admin_read_at)),
        ]);

        $unreadCount = CosmicChatConversation::query()
            ->whereNull('archived_at')
            ->where(function ($q) {
                $q->whereNull('admin_read_at')
                    ->orWhereColumn('last_message_at', '>', 'admin_read_at');
            })
            ->count();

        return Inertia::render('Admin/ChatInbox', [
            'conversations' => $conversations,
            'filters' => [
                'q' => $filters['q'] ?? '',
                'status' => $status,
                'view' => $view,
            ],
            'unreadCount' => $unreadCount,
        ]);
    }

    public function show(CosmicChatConversation $conversation): Response
    {
        $conversation->forceFill(['admin_read_at' => now()])->save();

        $conversation->load(['messages' => fn ($q) => $q->orderBy('id')]);

        return Inertia::render('Admin/ChatConversation', [
            'conversation' => [
                'id' => $conversation->id,
                'public_id' => $conversation->public_id,
                'status' => $conversation->status,
                'lead_status' => $conversation->lead_status,
                'visitor_name' => $conversation->visitor_name,
                'visitor_email' => $conversation->visitor_email,
                'started_page' => $conversation->started_page,
                'last_page' => $conversation->last_page,
                'message_count' => $conversation->message_count,
                'last_message_at' => optional($conversation->last_message_at)?->toIso8601String(),
                'created_at' => optional($conversation->created_at)?->toIso8601String(),
                'archived_at' => optional($conversation->archived_at)?->toIso8601String(),
                'ai_paused' => (bool) $conversation->ai_paused,
                'taken_over_at' => optional($conversation->taken_over_at)?->toIso8601String(),
                'lead_captured_at' => optional($conversation->lead_captured_at)?->toIso8601String(),
                'messages' => $conversation->messages->map(fn ($message) => [
                    'id' => $message->id,
                    'role' => $message->role,
                    'content' => $message->content,
                    'model' => $message->model,
                    'created_at' => optional($message->created_at)?->toIso8601String(),
                ])->values(),
            ],
        ]);
    }

    public function update(Request $request, CosmicChatConversation $conversation)
    {
        $data = $request->validate([
            'lead_status' => ['sometimes', Rule::in(['new', 'qualified', 'follow_up', 'closed'])],
            'archived' => ['sometimes', 'boolean'],
            'read' => ['sometimes', 'boolean'],
        ]);

        if (array_key_exists('lead_status', $data)) {
            $conversation->lead_status = $data['lead_status'];
        }

        if (array_key_exists('archived', $data)) {
            $conversation->archived_at = $data['archived'] ? now() : null;
        }

        if (!empty($data['read'])) {
            $conversation->admin_read_at = now();
        }

        $conversation->save();

        return back()->with('success', 'Conversation updated.');
    }
    public function reply(Request $request, CosmicChatConversation $conversation)
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
        ]);

        $clean = trim(strip_tags($data['message']));
        abort_if($clean === '', 422, 'Reply is required.');

        $message = $conversation->messages()->create([
            'role' => 'admin',
            'content' => $clean,
            'metadata' => ['admin_user_id' => $request->user()->id],
        ]);

        $conversation->forceFill([
            'ai_paused' => true,
            'taken_over_at' => $conversation->taken_over_at ?: now(),
            'admin_read_at' => now(),
            'message_count' => $conversation->message_count + 1,
            'last_message_at' => now(),
            'lead_status' => $conversation->lead_status === 'new' ? 'follow_up' : $conversation->lead_status,
        ])->save();

        return back()->with('success', 'Reply sent.');
    }

    public function takeover(Request $request, CosmicChatConversation $conversation)
    {
        $data = $request->validate([
            'ai_paused' => ['required', 'boolean'],
        ]);

        $paused = (bool) $data['ai_paused'];
        $conversation->forceFill([
            'ai_paused' => $paused,
            'taken_over_at' => $paused ? ($conversation->taken_over_at ?: now()) : null,
            'admin_read_at' => now(),
        ])->save();

        return back()->with('success', $paused ? 'Human takeover enabled.' : 'AI assistance resumed.');
    }

}
