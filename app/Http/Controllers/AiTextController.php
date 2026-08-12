<?php

namespace App\Http\Controllers;

use App\AI\Clients\OpenAIClient;
use App\Models\Page;
use App\Models\TrialGeneration;
use App\Services\CreditWalletService;
use App\Services\TrialCreditService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

class AiTextController extends Controller
{
    public const COST = 10;

    public function generate(
        Request $request,
        Page $page,
        OpenAIClient $openAI,
        CreditWalletService $wallet,
        TrialCreditService $trialCredits,
    ) {
        $page->loadMissing('website');
        $website = $page->website;
        abort_unless($website, 404, 'Website not found.');

        $trial = null;
        $user = $request->user();

        if ($user) {
            $this->authorize('update', $website);
            if (! $wallet->canAfford($user, self::COST)) {
                return response()->json([
                    'message' => 'Not enough Cosmic Credits. Cosmic AI text generation costs '.self::COST.' credits.',
                    'required_credits' => self::COST,
                    'available_credits' => $wallet->balance($user),
                ], 422);
            }
        } else {
            $token = trim((string) ($request->query('token') ?: $request->input('token')));
            abort_if($token === '', 401, 'A valid trial token is required.');
            $trial = TrialGeneration::query()
                ->where('token', $token)
                ->where('page_id', $page->id)
                ->whereNull('claimed_at')
                ->firstOrFail();
            $trialCredits->ensureCanSpend($trial, self::COST, 'Cosmic AI text generation');
        }

        $validated = $request->validate([
            'mode' => ['required', 'string', 'in:generate,rewrite'],
            'current_value' => ['nullable', 'string', 'max:8000'],
            'instruction' => ['nullable', 'string', 'max:1200'],
            'field_kind' => ['required', 'string', 'in:text,textarea'],
            'field_role' => ['nullable', 'string', 'max:80'],
            'presentation_hint' => ['nullable', 'string', 'max:500'],
        ]);

        $mode = $validated['mode'];
        $current = trim((string) ($validated['current_value'] ?? ''));
        $instruction = trim((string) ($validated['instruction'] ?? ''));
        $fieldKind = $validated['field_kind'];
        $fieldRole = trim((string) ($validated['field_role'] ?? ($fieldKind === 'textarea' ? 'paragraph' : 'short text')));

        $system = <<<'PROMPT'
You are Cosmic AI, the inline website copy assistant inside Cosmic CMS.
Return ONLY the replacement copy as plain text. Do not add quotation marks, markdown, labels, explanations, bullets unless the requested field clearly needs them, or surrounding commentary.

Rules:
- Preserve the website's business context and intent.
- Match the requested field role and keep the result immediately usable in a website builder.
- For short text fields/headings/buttons: be concise and avoid unnecessary punctuation.
- For textarea/body copy: write polished natural marketing/editorial copy, normally 1-4 sentences unless the instruction requests otherwise.
- If rewriting existing copy, preserve its meaning unless the user explicitly asks to change it.
- Never output HTML, scripts, markdown fences, or placeholders such as [Company Name].
- Do not invent precise claims, statistics, awards, prices, locations, certifications, or guarantees unless present in the supplied context/current copy.
PROMPT;

        $userPrompt = implode("\n", array_filter([
            'Website name: '.($website->name ?: 'Website'),
            'Industry: '.($website->industry ?: 'General'),
            'Field role: '.$fieldRole,
            'Field type: '.$fieldKind,
            'Mode: '.$mode,
            filled($validated['presentation_hint'] ?? null) ? 'Presentation hint: '.Str::limit((string) $validated['presentation_hint'], 450, '') : null,
            $current !== '' ? 'Current copy: '.$current : 'Current copy: empty',
            $instruction !== '' ? 'User instruction: '.$instruction : ($mode === 'rewrite' ? 'User instruction: Improve clarity, polish, and conversion while preserving the meaning.' : 'User instruction: Write suitable production-ready copy for this field.'),
        ]));

        try {
            $generated = trim((string) $openAI->chat($system, $userPrompt));
            $generated = preg_replace('/^```(?:text)?\s*|\s*```$/i', '', $generated);
            $generated = trim($generated, " \t\n\r\0\x0B\"");
            abort_if($generated === '', 502, 'Cosmic AI returned empty text. No credits were charged.');

            // Clamp runaway responses while preserving normal copy.
            $max = $fieldKind === 'textarea' ? 5000 : 500;
            $generated = Str::limit($generated, $max, '');

            if ($trial) {
                $balance = $trialCredits->consume($trial, self::COST, 'generate_text', [
                    'website_id' => $website->id,
                    'page_id' => $page->id,
                    'mode' => $mode,
                    'field_role' => $fieldRole,
                ]);
            } else {
                $wallet->debit(
                    $user,
                    self::COST,
                    'Cosmic AI inline text '.$mode,
                    'ai_generation',
                    $website,
                    'cosmic-inline-text:'.Str::uuid(),
                    [
                        'page_id' => $page->id,
                        'mode' => $mode,
                        'field_role' => $fieldRole,
                    ],
                );
                $balance = $wallet->balance($user);
            }

            return response()->json([
                'status' => 'success',
                'text' => $generated,
                'cost' => self::COST,
                'credit_balance' => $balance,
                'balance' => $balance,
            ]);
        } catch (Throwable $exception) {
            report($exception);
            $message = $exception instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface
                ? $exception->getMessage()
                : 'Cosmic AI could not generate text right now. No credits were charged.';

            return response()->json(['message' => $message], 502);
        }
    }
}
