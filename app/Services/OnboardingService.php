<?php

namespace App\Services;

use App\Models\OnboardingStep;
use App\Models\User;
use App\Modules\AI\Models\AiChatbot;
use App\Modules\Shared\Models\ChannelAccount;
use App\Modules\Shared\Models\Contact;
use App\Modules\Shared\Models\Message;

class OnboardingService
{
    public const STEPS = [
        'verify_email' => 'Verify your email address',
        'connect_first_channel' => 'Connect your first messaging channel',
        'import_first_contacts' => 'Import or add your first contacts',
        'send_first_message' => 'Send your first message',
    ];

    public function getProgress(User $user): array
    {
        $workspaceId = $user->current_workspace_id ?? $user->workspace_id;

        $completed = OnboardingStep::where('user_id', $user->id)
            ->where('completed', true)
            ->pluck('step')
            ->toArray();

        $steps = [];
        foreach (self::STEPS as $key => $label) {
            $isCompleted = $this->isCompleted($user, $workspaceId, $key, $completed);
            $steps[] = [
                'key' => $key,
                'label' => __($label),
                'completed' => $isCompleted,
            ];

            // Persist auto-detected completions so they survive future checks
            if ($isCompleted && ! in_array($key, $completed, true)) {
                $this->complete($user, $key);
                $completed[] = $key;
            }
        }

        $doneCount = count(array_filter($steps, fn ($s) => $s['completed']));
        $totalCount = count($steps);

        // Next pending step for the dashboard nudge
        $nextStep = null;
        foreach ($steps as $step) {
            if (! $step['completed']) {
                $nextStep = $step;
                break;
            }
        }

        return [
            'steps' => $steps,
            'percent' => $totalCount > 0 ? (int) round(($doneCount / $totalCount) * 100) : 0,
            'done' => $doneCount,
            'total' => $totalCount,
            'is_complete' => $doneCount === $totalCount,
            'next_step' => $nextStep,
        ];
    }

    private function isCompleted(User $user, ?int $workspaceId, string $step, array $manuallyCompleted): bool
    {
        if (in_array($step, $manuallyCompleted, true)) {
            return true;
        }

        return match ($step) {
            'verify_email' => $user->hasVerifiedEmail(),

            'connect_first_channel' => $workspaceId !== null &&
                ChannelAccount::where('workspace_id', $workspaceId)->exists(),

            'import_first_contacts' => $workspaceId !== null &&
                Contact::where('workspace_id', $workspaceId)->exists(),

            'send_first_message' => $workspaceId !== null &&
                Message::whereHas('conversation', function ($q) use ($workspaceId) {
                    $q->where('workspace_id', $workspaceId);
                })->where('direction', 'out')->exists(),

            default => false,
        };
    }

    /**
     * Mark a step as complete.
     *
     * If $verify is true (default), the step is only recorded when the real-world
     * condition confirms it is actually done — preventing a user from spoofing
     * progress by calling this endpoint with an arbitrary step key.
     */
    public function markStep(User $user, string $step, bool $verify = true): bool
    {
        if (! array_key_exists($step, self::STEPS)) {
            return false;
        }

        $workspaceId = $user->current_workspace_id ?? $user->workspace_id;

        if ($verify && ! $this->isCompleted($user, $workspaceId, $step, [])) {
            return false;
        }

        OnboardingStep::updateOrCreate(
            ['user_id' => $user->id, 'step' => $step],
            ['completed' => true, 'completed_at' => now()]
        );

        return true;
    }

    /** @deprecated Use markStep() */
    public function complete(User $user, string $step): void
    {
        $this->markStep($user, $step, false);
    }
}
