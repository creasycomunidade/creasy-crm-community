<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SupportReply;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\Mail\MailService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SupportTicketController extends Controller
{
    public function index(Request $request): Response
    {
        $query = SupportTicket::with('user')->withCount('replies')->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $tickets = $query->paginate(20)->withQueryString();

        $stats = [
            'total'       => SupportTicket::count(),
            'open'        => SupportTicket::where('status', 'open')->count(),
            'in_progress' => SupportTicket::where('status', 'in_progress')->count(),
            'closed'      => SupportTicket::where('status', 'closed')->count(),
        ];

        return Inertia::render('Admin/Support/Index', [
            'tickets' => $tickets,
            'stats'   => $stats,
            'filters' => $request->only('status'),
        ]);
    }

    public function create(): Response
    {
        $customers = User::select('id', 'name', 'email')->orderBy('name')->get();

        return Inertia::render('Admin/Support/Create', [
            'customers' => $customers,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'user_id'  => ['nullable', 'exists:users,id'],
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'max:255'],
            'subject'  => ['required', 'string', 'max:255'],
            'message'  => ['required', 'string', 'max:10000'],
            'priority' => ['nullable', 'in:low,normal,high,urgent'],
        ]);

        // If linked to a customer, trust the DB record for name/email integrity.
        if (! empty($validated['user_id'])) {
            $customer = User::find($validated['user_id']);
            $validated['name']  = $customer->name;
            $validated['email'] = $customer->email;
        }

        $ticket = SupportTicket::create([
            'user_id'  => $validated['user_id'] ?? null,
            'name'     => $validated['name'],
            'email'    => $validated['email'],
            'subject'  => $validated['subject'],
            'message'  => $validated['message'],
            'priority' => $validated['priority'] ?? 'normal',
            'status'   => 'open',
        ]);

        $mailer = app(MailService::class);

        try {
            $mailer->sendWithTemplate('support_ticket_created', $ticket->email, [
                'app_name'        => config('app.name'),
                'user_name'       => $ticket->name,
                'ticket_id'       => $ticket->id,
                'ticket_subject'  => $ticket->subject,
                'ticket_priority' => ucfirst($ticket->priority),
                'ticket_url'      => route('client.support.show', $ticket),
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to send support_ticket_created email', ['error' => $e->getMessage()]);
        }

        return redirect()->route('admin.support.show', $ticket)
            ->with('success', __('Ticket created.'));
    }

    public function show(SupportTicket $supportTicket): Response
    {
        $supportTicket->load(['user', 'replies.user']);

        return Inertia::render('Admin/Support/Show', [
            'ticket' => $supportTicket,
        ]);
    }

    public function reply(Request $request, SupportTicket $supportTicket): RedirectResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:10000'],
        ]);

        $admin = $request->user('admin');

        SupportReply::create([
            'ticket_id' => $supportTicket->id,
            'user_id' => null,
            'author_name' => $admin->name,
            'is_staff' => true,
            'message' => $validated['message'],
        ]);

        $mailer = app(MailService::class);
        $ticketUrl = route('client.support.show', $supportTicket);

        try {
            $mailer->sendWithTemplate('support_ticket_reply_client', $supportTicket->email, [
                'app_name'       => config('app.name'),
                'user_name'      => $supportTicket->name,
                'ticket_id'      => $supportTicket->id,
                'ticket_subject' => $supportTicket->subject,
                'staff_name'     => $admin->name,
                'reply_message'  => $validated['message'],
                'ticket_url'     => $ticketUrl,
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to send support_ticket_reply_client email', ['error' => $e->getMessage()]);
        }

        return back()->with('success', __('Reply sent.'));
    }

    public function updateStatus(Request $request, SupportTicket $supportTicket): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['open', 'in_progress', 'closed'])],
        ]);

        $oldStatus = $supportTicket->status;
        $supportTicket->update($validated);

        if ($oldStatus !== $validated['status']) {
            $mailer = app(MailService::class);
            $ticketUrl = route('client.support.show', $supportTicket);

            try {
                $mailer->sendWithTemplate('support_ticket_status_changed', $supportTicket->email, [
                    'app_name'       => config('app.name'),
                    'user_name'      => $supportTicket->name,
                    'ticket_id'      => $supportTicket->id,
                    'ticket_subject' => $supportTicket->subject,
                    'new_status'     => ucwords(str_replace('_', ' ', $validated['status'])),
                    'ticket_url'     => $ticketUrl,
                ]);
            } catch (\Throwable $e) {
                Log::error('Failed to send support_ticket_status_changed email', ['error' => $e->getMessage()]);
            }
        }

        return back()->with('success', __('Status updated.'));
    }
}
