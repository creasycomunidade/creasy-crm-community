<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use App\Models\SupportReply;
use App\Models\SupportTicket;
use App\Services\Mail\MailService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class SupportTicketController extends Controller
{
    public function index(Request $request): Response
    {
        $userId = $request->user()->id;

        $tickets = SupportTicket::where('user_id', $userId)
            ->withCount('replies')
            ->latest()
            ->paginate(15);

        $stats = [
            'total'       => SupportTicket::where('user_id', $userId)->count(),
            'open'        => SupportTicket::where('user_id', $userId)->where('status', 'open')->count(),
            'in_progress' => SupportTicket::where('user_id', $userId)->where('status', 'in_progress')->count(),
            'closed'      => SupportTicket::where('user_id', $userId)->where('status', 'closed')->count(),
        ];

        return Inertia::render('client/Support/Index', [
            'tickets' => $tickets,
            'stats'   => $stats,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('client/Support/Create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:10000'],
            'priority' => ['nullable', 'in:low,normal,high,urgent'],
        ]);

        $user = $request->user();

        $ticket = SupportTicket::create([
            'user_id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'subject' => $validated['subject'],
            'message' => $validated['message'],
            'priority' => $validated['priority'] ?? 'normal',
            'status' => 'open',
        ]);

        $mailer = app(MailService::class);
        $appName = config('app.name');
        $ticketUrl = route('client.support.show', $ticket);
        $adminTicketUrl = route('admin.support.show', $ticket);

        try {
            $mailer->sendWithTemplate('support_ticket_created', $user->email, [
                'app_name'        => $appName,
                'user_name'       => $user->name,
                'ticket_id'       => $ticket->id,
                'ticket_subject'  => $ticket->subject,
                'ticket_priority' => ucfirst($ticket->priority),
                'ticket_url'      => $ticketUrl,
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to send support_ticket_created email', ['error' => $e->getMessage()]);
        }

        try {
            $admins = AdminUser::where('status', AdminUser::STATUS_ACTIVE)->get();
            foreach ($admins as $admin) {
                $mailer->sendWithTemplate('support_ticket_admin_new', $admin->email, [
                    'ticket_id'       => $ticket->id,
                    'user_name'       => $user->name,
                    'user_email'      => $user->email,
                    'ticket_subject'  => $ticket->subject,
                    'ticket_priority' => ucfirst($ticket->priority),
                    'ticket_message'  => $ticket->message,
                    'ticket_url'      => $adminTicketUrl,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('Failed to send support_ticket_admin_new email', ['error' => $e->getMessage()]);
        }

        return redirect()->route('client.support.index')
            ->with('success', __('Support ticket submitted.'));
    }

    public function show(Request $request, SupportTicket $supportTicket): Response
    {
        abort_unless($supportTicket->user_id === $request->user()->id, 403);

        $supportTicket->load('replies');

        return Inertia::render('client/Support/Show', [
            'ticket' => $supportTicket,
        ]);
    }

    public function reply(Request $request, SupportTicket $supportTicket): RedirectResponse
    {
        abort_unless($supportTicket->user_id === $request->user()->id, 403);
        abort_unless($supportTicket->isOpen(), 403, 'Ticket is closed.');

        $validated = $request->validate([
            'message' => ['required', 'string', 'max:10000'],
        ]);

        SupportReply::create([
            'ticket_id' => $supportTicket->id,
            'user_id' => $request->user()->id,
            'author_name' => $request->user()->name,
            'is_staff' => false,
            'message' => $validated['message'],
        ]);

        $mailer = app(MailService::class);
        $adminTicketUrl = route('admin.support.show', $supportTicket);
        $user = $request->user();

        try {
            $admins = AdminUser::where('status', AdminUser::STATUS_ACTIVE)->get();
            foreach ($admins as $admin) {
                $mailer->sendWithTemplate('support_ticket_reply_admin', $admin->email, [
                    'ticket_id'      => $supportTicket->id,
                    'user_name'      => $user->name,
                    'user_email'     => $user->email,
                    'ticket_subject' => $supportTicket->subject,
                    'reply_message'  => $validated['message'],
                    'ticket_url'     => $adminTicketUrl,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('Failed to send support_ticket_reply_admin email', ['error' => $e->getMessage()]);
        }

        return back()->with('success', __('Reply sent.'));
    }
}
