<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Invitation;
use App\Services\Mail\MailService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class InvitationController extends Controller
{
    public function __construct(private MailService $mail) {}

    /**
     * Send an email invite to a team member.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user->isClientAdministrator()) {
            abort(403);
        }

        $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'client_role' => ['required', 'in:administrator,staff'],
        ]);

        // Check if there's already a pending invite
        $existing = Invitation::where('email', $request->email)
            ->where('client_id', $user->client_id)
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->first();

        if ($existing) {
            return back()->withErrors(['email' => 'An invitation has already been sent to this email.']);
        }

        $invitation = Invitation::create([
            'client_id' => $user->client_id,
            'email' => $request->email,
            'client_role' => $request->client_role,
            'token' => Str::random(64),
            'invited_by' => $user->id,
            'expires_at' => now()->addDays(7),
        ]);

        $inviteUrl = route('auth.invitations.show', ['token' => $invitation->token]);

        $this->mail->sendWithTemplate('team_invitation', $request->email, [
            'app_name' => config('app.name'),
            'inviter_name' => $user->name,
            'organization_name' => $user->client?->name ?? config('app.name'),
            'invitation_url' => $inviteUrl,
            'expires_days' => 7,
        ]);

        return back()->with('success', 'Invitation sent to '.$request->email);
    }

    /**
     * Revoke a pending invitation.
     */
    public function destroy(Request $request, Invitation $invitation): RedirectResponse
    {
        if ($invitation->client_id !== $request->user()->client_id) {
            abort(403);
        }

        $invitation->delete();

        return back()->with('success', 'Invitation revoked.');
    }
}
