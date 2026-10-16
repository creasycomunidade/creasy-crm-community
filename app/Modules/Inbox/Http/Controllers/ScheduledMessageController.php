<?php

namespace App\Modules\Inbox\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Inbox\Models\ScheduledMessage;
use App\Modules\Inbox\Services\ChannelAccountAccess;
use App\Modules\Shared\Models\Conversation;
use App\Services\StorageManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ScheduledMessageController extends Controller
{
    public function index(Request $request, ChannelAccountAccess $access): Response
    {
        $workspaceId = $this->workspaceId($request);
        $allowedIds = $access->allowedAccountIds($request->user(), $workspaceId);

        $query = ScheduledMessage::query()
            ->where('workspace_id', $workspaceId)
            ->whereHas('conversation', fn ($conversation) => $conversation->whereIn('channel_account_id', $allowedIds))
            ->with(['conversation.contact:id,first_name,last_name,phone_e164,email', 'conversation.channelAccount:id,channel,display_name'])
            ->when($request->status, fn ($builder, $status) => $builder->where('status', $status))
            ->when($request->search, function ($builder, $search) {
                $builder->where(function ($nested) use ($search) {
                    $nested->where('body', 'like', "%{$search}%")
                        ->orWhereHas('conversation.contact', fn ($contact) => $contact
                            ->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('phone_e164', 'like', "%{$search}%"));
                });
            });

        $counts = (clone $query)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return Inertia::render('Inbox/ScheduledMessages/Index', [
            'schedules' => $query->orderByRaw("CASE status WHEN 'processing' THEN 1 WHEN 'scheduled' THEN 2 WHEN 'paused' THEN 3 WHEN 'failed' THEN 4 WHEN 'completed' THEN 5 ELSE 6 END")
                ->orderBy('next_send_at')->paginate(20)->withQueryString(),
            'counts' => $counts,
            'filters' => $request->only('status', 'search'),
        ]);
    }

    public function create(Request $request, ChannelAccountAccess $access): Response
    {
        $workspaceId = $this->workspaceId($request);
        $allowedIds = $access->allowedAccountIds($request->user(), $workspaceId);

        $conversations = Conversation::query()
            ->where('workspace_id', $workspaceId)
            ->whereIn('channel_account_id', $allowedIds)
            ->with(['contact:id,first_name,last_name,phone_e164,email', 'channelAccount:id,channel,display_name'])
            ->orderByDesc('last_message_at')
            ->limit(300)
            ->get(['id', 'uuid', 'contact_id', 'channel_account_id', 'last_message_at']);

        return Inertia::render('Inbox/ScheduledMessages/Create', [
            'conversations' => $conversations,
            'selectedConversation' => $request->string('conversation')->toString(),
        ]);
    }

    public function store(Request $request, ChannelAccountAccess $access, StorageManager $storageManager): JsonResponse|RedirectResponse
    {
        $workspaceId = $this->workspaceId($request);
        $validated = $request->validate([
            'conversation_uuid' => ['required', 'uuid'],
            'body' => ['nullable', 'string', 'max:4096', 'required_without:image'],
            'image' => ['nullable', 'file', 'max:10240', 'mimes:jpg,jpeg,png,webp', 'required_without:body'],
            'send_at' => ['required', 'date_format:Y-m-d\\TH:i'],
            'recurrence_unit' => ['required', 'in:once,minute,hour,day,week,month'],
            'recurrence_interval' => ['required', 'integer', 'min:1', 'max:365'],
            'max_occurrences' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'ends_at' => ['nullable', 'date_format:Y-m-d\\TH:i'],
        ]);

        $conversation = Conversation::query()
            ->where('workspace_id', $workspaceId)
            ->where('uuid', $validated['conversation_uuid'])
            ->with('channelAccount')
            ->firstOrFail();
        abort_unless($conversation->channelAccount && $access->allows($request->user(), $conversation->channelAccount), 403);

        $timezone = $request->user()->timezone ?: 'America/Sao_Paulo';
        $sendAt = $this->localDate($validated['send_at'], $timezone);
        abort_if($sendAt->lte(now()), 422, 'Escolha uma data e hora futura.');

        $endsAt = filled($validated['ends_at'] ?? null)
            ? $this->localDate($validated['ends_at'], $timezone)
            : null;
        abort_if($endsAt && $endsAt->lte($sendAt), 422, 'A data final deve ser posterior ao primeiro envio.');

        $media = [];
        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $storedPath = $storageManager->prefixedPath("message-media/{$workspaceId}/scheduled/".$file->hashName());
            $stored = $storageManager->disk()->putFileAs(dirname($storedPath), $file, basename($storedPath));
            abort_unless($stored, 422, 'Não foi possível armazenar a imagem. Tente novamente.');
            $storageUrl = $storageManager->disk()->url($storedPath);

            $media = [
                'media_path' => $storedPath,
                'media_url' => Str::startsWith($storageUrl, ['http://', 'https://']) ? $storageUrl : url($storageUrl),
                'media_mime' => $file->getMimeType() ?: 'image/jpeg',
                'media_name' => $file->getClientOriginalName(),
            ];
        }

        $schedule = ScheduledMessage::create(array_merge([
            'workspace_id' => $workspaceId,
            'conversation_id' => $conversation->id,
            'created_by' => $request->user()->id,
            'body' => trim((string) ($validated['body'] ?? '')),
            'timezone' => $timezone,
            'next_send_at' => $sendAt,
            'recurrence_unit' => $validated['recurrence_unit'],
            'recurrence_interval' => $validated['recurrence_unit'] === 'once' ? 1 : $validated['recurrence_interval'],
            'max_occurrences' => $validated['recurrence_unit'] === 'once' ? 1 : ($validated['max_occurrences'] ?? null),
            'ends_at' => $validated['recurrence_unit'] === 'once' ? null : $endsAt,
            'status' => 'scheduled',
        ], $media));

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Mensagem agendada.',
                'schedule' => $schedule->only('uuid', 'status', 'next_send_at', 'recurrence_unit'),
            ], 201);
        }

        return redirect()->route('client.inbox.scheduled-messages.index')->with('success', 'Mensagem agendada.');
    }

    public function pause(Request $request, ScheduledMessage $scheduledMessage): RedirectResponse
    {
        $this->authorise($request, $scheduledMessage);
        abort_unless(in_array($scheduledMessage->status, ['scheduled', 'processing'], true), 422);
        $scheduledMessage->update(['status' => 'paused', 'processing_at' => null]);

        return back()->with('success', 'Agendamento pausado.');
    }

    public function update(Request $request, ScheduledMessage $scheduledMessage, StorageManager $storageManager): JsonResponse|RedirectResponse
    {
        $this->authorise($request, $scheduledMessage);
        abort_unless(in_array($scheduledMessage->status, ['scheduled', 'paused', 'failed'], true), 422, 'Esta mensagem não pode mais ser editada.');

        $validated = $request->validate([
            'body' => ['nullable', 'string', 'max:4096'],
            'image' => ['nullable', 'file', 'max:10240', 'mimes:jpg,jpeg,png,webp'],
            'remove_image' => ['nullable', 'boolean'],
            'send_at' => ['required', 'date_format:Y-m-d\\TH:i'],
            'recurrence_unit' => ['required', 'in:once,minute,hour,day,week,month'],
            'recurrence_interval' => ['required', 'integer', 'min:1', 'max:365'],
            'max_occurrences' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'ends_at' => ['nullable', 'date_format:Y-m-d\\TH:i'],
        ]);

        $keepsExistingImage = filled($scheduledMessage->media_path) && ! $request->boolean('remove_image');
        abort_if(blank(trim((string) ($validated['body'] ?? ''))) && ! $request->hasFile('image') && ! $keepsExistingImage, 422, 'Informe uma mensagem ou imagem.');

        $timezone = $request->user()->timezone ?: 'America/Sao_Paulo';
        $sendAt = $this->localDate($validated['send_at'], $timezone);
        abort_if($sendAt->lte(now()), 422, 'Escolha uma data e hora futura.');
        $endsAt = filled($validated['ends_at'] ?? null) ? $this->localDate($validated['ends_at'], $timezone) : null;
        abort_if($endsAt && $endsAt->lte($sendAt), 422, 'A data final deve ser posterior ao primeiro envio.');
        abort_if(filled($validated['max_occurrences'] ?? null) && (int) $validated['max_occurrences'] < $scheduledMessage->occurrences_sent, 422, 'A quantidade máxima não pode ser menor que os envios já realizados.');

        $media = [];
        $oldMediaPath = $scheduledMessage->media_path;
        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $storedPath = $storageManager->prefixedPath("message-media/{$scheduledMessage->workspace_id}/scheduled/".$file->hashName());
            $stored = $storageManager->disk()->putFileAs(dirname($storedPath), $file, basename($storedPath));
            abort_unless($stored, 422, 'Não foi possível armazenar a imagem. Tente novamente.');
            $storageUrl = $storageManager->disk()->url($storedPath);
            $media = [
                'media_path' => $storedPath,
                'media_url' => Str::startsWith($storageUrl, ['http://', 'https://']) ? $storageUrl : url($storageUrl),
                'media_mime' => $file->getMimeType() ?: 'image/jpeg',
                'media_name' => $file->getClientOriginalName(),
            ];
        } elseif ($request->boolean('remove_image')) {
            $media = ['media_path' => null, 'media_url' => null, 'media_mime' => null, 'media_name' => null];
        }

        $scheduledMessage->update(array_merge([
            'body' => trim((string) ($validated['body'] ?? '')),
            'timezone' => $timezone,
            'next_send_at' => $sendAt,
            'recurrence_unit' => $validated['recurrence_unit'],
            'recurrence_interval' => $validated['recurrence_unit'] === 'once' ? 1 : $validated['recurrence_interval'],
            'max_occurrences' => $validated['recurrence_unit'] === 'once' ? 1 : ($validated['max_occurrences'] ?? null),
            'ends_at' => $validated['recurrence_unit'] === 'once' ? null : $endsAt,
            'status' => $scheduledMessage->status === 'failed' ? 'scheduled' : $scheduledMessage->status,
            'failure_count' => 0,
            'last_error' => null,
            'processing_at' => null,
        ], $media));

        if ($oldMediaPath && ($request->hasFile('image') || $request->boolean('remove_image')) && $oldMediaPath !== $scheduledMessage->media_path) {
            $storageManager->disk()->delete($oldMediaPath);
        }

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Mensagem agendada atualizada.', 'schedule' => $scheduledMessage->fresh()]);
        }

        return back()->with('success', 'Mensagem agendada atualizada.');
    }

    public function resume(Request $request, ScheduledMessage $scheduledMessage): RedirectResponse
    {
        $this->authorise($request, $scheduledMessage);
        abort_unless(in_array($scheduledMessage->status, ['paused', 'failed'], true), 422);
        $scheduledMessage->update([
            'status' => 'scheduled',
            'failure_count' => 0,
            'last_error' => null,
            'processing_at' => null,
            'next_send_at' => $scheduledMessage->next_send_at->isPast() ? now()->addMinute() : $scheduledMessage->next_send_at,
        ]);

        return back()->with('success', 'Agendamento retomado.');
    }

    public function cancel(Request $request, ScheduledMessage $scheduledMessage): RedirectResponse
    {
        $this->authorise($request, $scheduledMessage);
        abort_if(in_array($scheduledMessage->status, ['completed', 'cancelled'], true), 422);
        $scheduledMessage->update(['status' => 'cancelled', 'processing_at' => null]);

        return back()->with('success', 'Agendamento cancelado.');
    }

    public function destroy(Request $request, ScheduledMessage $scheduledMessage, StorageManager $storageManager): RedirectResponse
    {
        $this->authorise($request, $scheduledMessage);
        abort_if($scheduledMessage->status === 'processing', 422, 'Aguarde o envio em andamento terminar antes de excluir.');

        $mediaPath = $scheduledMessage->media_path;
        $scheduledMessage->delete();
        if ($mediaPath) {
            $storageManager->disk()->delete($mediaPath);
        }

        return back()->with('success', 'Mensagem agendada excluída.');
    }

    private function authorise(Request $request, ScheduledMessage $scheduledMessage): void
    {
        abort_unless($scheduledMessage->workspace_id === $this->workspaceId($request), 403);
        $account = $scheduledMessage->conversation()->with('channelAccount')->first()?->channelAccount;
        abort_unless($account && app(ChannelAccountAccess::class)->allows($request->user(), $account), 403);
    }

    private function workspaceId(Request $request): int
    {
        return (int) ($request->user()->current_workspace_id ?? $request->user()->workspace_id);
    }

    private function localDate(string $value, string $timezone): Carbon
    {
        return Carbon::createFromFormat('Y-m-d\\TH:i', $value, $timezone)
            ->setTimezone((string) config('app.timezone', 'UTC'));
    }
}
