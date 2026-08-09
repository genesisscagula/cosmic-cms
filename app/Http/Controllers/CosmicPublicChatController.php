<?php

namespace App\Http\Controllers;

use App\Models\CosmicChatConversation;
use App\Services\CosmicChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CosmicPublicChatController extends Controller
{
    public function start(Request $request): JsonResponse
    {
        abort_unless(config('cosmic-chat.enabled'), 404);

        $data = $request->validate([
            'page' => ['nullable', 'string', 'max:500'],
        ]);

        $token = Str::random(64);
        $conversation = CosmicChatConversation::create([
            'public_id' => (string) Str::uuid(),
            'access_token_hash' => hash('sha256', $token),
            'started_page' => $data['page'] ?? null,
            'last_page' => $data['page'] ?? null,
            'ip_hash' => $request->ip() ? hash('sha256', $request->ip() . '|' . config('app.key')) : null,
            'user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
            'metadata' => ['source' => 'public_widget'],
        ]);

        return response()->json([
            'conversation_id' => $conversation->public_id,
            'access_token' => $token,
            'welcome' => config('cosmic-chat.welcome'),
            'ai_paused' => false,
        ], 201);
    }

    public function history(Request $request): JsonResponse
    {
        abort_unless(config('cosmic-chat.enabled'), 404);

        $data = $request->validate([
            'conversation_id' => ['required', 'uuid'],
            'access_token' => ['required', 'string', 'size:64'],
            'after_id' => ['nullable', 'integer', 'min:0'],
        ]);

        $conversation = $this->resolveConversation($data['conversation_id'], $data['access_token']);
        $afterId = (int) ($data['after_id'] ?? 0);

        $messages = $conversation->messages()
            ->where('id', '>', $afterId)
            ->whereIn('role', ['user', 'assistant', 'admin'])
            ->orderBy('id')
            ->limit(100)
            ->get()
            ->map(fn ($message) => [
                'id' => $message->id,
                'role' => $message->role,
                'content' => $message->content,
                'created_at' => $message->created_at?->toIso8601String(),
            ])
            ->values();

        return response()->json([
            'messages' => $messages,
            'ai_paused' => (bool) $conversation->ai_paused,
            'lead_captured' => filled($conversation->visitor_email),
            'status' => $conversation->status,
        ]);
    }

    public function captureLead(Request $request): JsonResponse
    {
        abort_unless(config('cosmic-chat.enabled'), 404);

        $data = $request->validate([
            'conversation_id' => ['required', 'uuid'],
            'access_token' => ['required', 'string', 'size:64'],
            'name' => ['nullable', 'string', 'max:100'],
            'email' => ['required', 'email:rfc', 'max:190'],
            'page' => ['nullable', 'string', 'max:500'],
        ]);

        $conversation = $this->resolveConversation($data['conversation_id'], $data['access_token']);
        abort_if($conversation->status === 'closed', 409, 'This conversation is closed.');

        $conversation->forceFill([
            'visitor_name' => trim((string) ($data['name'] ?? '')) ?: $conversation->visitor_name,
            'visitor_email' => strtolower(trim($data['email'])),
            'lead_status' => $conversation->lead_status === 'closed' ? 'closed' : 'qualified',
            'lead_captured_at' => $conversation->lead_captured_at ?: now(),
            'last_page' => $data['page'] ?? $conversation->last_page,
        ])->save();

        return response()->json([
            'captured' => true,
            'message' => 'Thanks — your details are saved with this conversation. The Cosmic CMS team can follow up with you.',
        ]);
    }

    public function message(Request $request, CosmicChatService $chat): JsonResponse
    {
        abort_unless(config('cosmic-chat.enabled'), 404);

        $data = $request->validate([
            'conversation_id' => ['required', 'uuid'],
            'access_token' => ['required', 'string', 'size:64'],
            'message' => ['required', 'string', 'max:' . (int) config('cosmic-chat.max_user_chars', 1000)],
            'page' => ['nullable', 'string', 'max:500'],
        ]);

        $conversation = $this->resolveConversation($data['conversation_id'], $data['access_token']);
        abort_if($conversation->status === 'closed', 409, 'This conversation is closed.');

        $clean = trim(strip_tags($data['message']));
        abort_if($clean === '', 422, 'Message is required.');

        $userMessage = $conversation->messages()->create(['role' => 'user', 'content' => $clean]);
        $conversation->forceFill([
            'message_count' => $conversation->message_count + 1,
            'last_message_at' => now(),
            'last_page' => $data['page'] ?? $conversation->last_page,
        ])->save();

        // Once the platform owner takes over, preserve the visitor message for the
        // owner inbox and do not let AI race or contradict a human response.
        if ($conversation->fresh()->ai_paused) {
            return response()->json([
                'user_message' => [
                    'id' => $userMessage->id,
                    'role' => 'user',
                    'content' => $userMessage->content,
                    'created_at' => $userMessage->created_at?->toIso8601String(),
                ],
                'message' => null,
                'queued_for_team' => true,
                'ai_paused' => true,
            ], 202);
        }

        $generated = $chat->reply($conversation->fresh());
        $reply = $conversation->messages()->create([
            'role' => 'assistant',
            'content' => $generated['content'],
            'model' => $generated['model'],
        ]);

        $conversation->forceFill([
            'message_count' => $conversation->message_count + 1,
            'last_message_at' => now(),
        ])->save();

        return response()->json([
            'user_message' => [
                'id' => $userMessage->id,
                'role' => 'user',
                'content' => $userMessage->content,
                'created_at' => $userMessage->created_at?->toIso8601String(),
            ],
            'message' => [
                'id' => $reply->id,
                'role' => 'assistant',
                'content' => $reply->content,
                'created_at' => $reply->created_at?->toIso8601String(),
            ],
            'queued_for_team' => false,
            'ai_paused' => false,
        ]);
    }

    private function resolveConversation(string $publicId, string $token): CosmicChatConversation
    {
        $conversation = CosmicChatConversation::where('public_id', $publicId)->firstOrFail();
        abort_unless($conversation->tokenMatches($token), 403);

        return $conversation;
    }
}
