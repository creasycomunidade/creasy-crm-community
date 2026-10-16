<?php

namespace App\Modules\Appointments\Services;

use App\Events\MessageSent;
use App\Modules\Appointments\Models\Appointment;
use App\Modules\Appointments\Models\AppointmentSetting;
use App\Modules\Shared\Models\ChannelAccount;
use App\Modules\Shared\Models\Contact;
use App\Modules\Shared\Models\Conversation;
use App\Modules\Shared\Models\Message;
use App\Modules\Shared\Services\ChannelManager;
use Illuminate\Support\Facades\Log;

class AppointmentChannelNotifier
{
    public function __construct(private readonly ChannelManager $channels) {}

    public function send(Appointment $appointment, string $event): bool
    {
        $appointment->loadMissing(['service', 'resource']);
        $settings = AppointmentSetting::forWorkspace((int) $appointment->workspace_id);
        if (! $this->enabled($settings, $event) || ! $settings->channel_account_id) {
            return false;
        }

        $account = ChannelAccount::query()
            ->where('workspace_id', $appointment->workspace_id)
            ->where('status', 'active')
            ->find($settings->channel_account_id);
        if (! $account) {
            return false;
        }

        $contact = $appointment->contact_id
            ? Contact::where('workspace_id', $appointment->workspace_id)->find($appointment->contact_id)
            : Contact::where('workspace_id', $appointment->workspace_id)->where('phone_e164', $appointment->customer_phone)->first();
        if (! $contact) {
            Log::notice('appointments.channel_notification_skipped', ['appointment_id' => $appointment->id, 'reason' => 'contact_not_found']);

            return false;
        }

        $conversation = Conversation::query()
            ->where('workspace_id', $appointment->workspace_id)
            ->where('channel_account_id', $account->id)
            ->where('contact_id', $contact->id)
            ->latest('id')
            ->first();

        if (! $conversation && $account->channel === 'whatsapp') {
            $conversation = Conversation::create([
                'workspace_id' => $appointment->workspace_id,
                'channel_account_id' => $account->id,
                'contact_id' => $contact->id,
                'external_thread_id' => $contact->phone_e164,
                'status' => 'open',
                'assigned_to' => 'bot',
                'last_message_at' => now(),
            ]);
        }
        if (! $conversation) {
            Log::notice('appointments.channel_notification_skipped', ['appointment_id' => $appointment->id, 'reason' => 'conversation_not_found', 'channel' => $account->channel]);

            return false;
        }

        if ($account->channel === 'whatsapp' && $account->provider !== 'evolution_go' && ! $conversation->isWhatsappWindowOpen()) {
            Log::notice('appointments.channel_notification_skipped', ['appointment_id' => $appointment->id, 'reason' => 'whatsapp_window_closed']);

            return false;
        }

        $body = $this->render($settings, $appointment, $event);
        $message = Message::create([
            'conversation_id' => $conversation->id,
            'direction' => 'out',
            'channel' => $account->channel,
            'type' => 'text',
            'body' => $body,
            'payload' => ['provider_body' => $body, 'appointment_uuid' => $appointment->uuid, 'appointment_event' => $event],
            'status' => 'queued',
            'sent_by' => 'automation',
            'sent_at' => now(),
        ]);

        try {
            $providerId = $this->channels->driver($account->channel)->send($message);
            $message->update(['status' => 'sent', 'provider_message_id' => $providerId]);
            $conversation->update(['last_message_at' => now()]);
            MessageSent::dispatch($message->load('conversation'));
            $appointment->forceFill([$this->timestampColumn($event) => now()])->save();

            return true;
        } catch (\Throwable $error) {
            $message->update(['status' => 'failed', 'error_json' => ['message' => $error->getMessage()]]);
            Log::warning('appointments.channel_notification_failed', [
                'appointment_id' => $appointment->id,
                'channel_account_id' => $account->id,
                'event' => $event,
                'error' => $error->getMessage(),
            ]);

            return false;
        }
    }

    private function enabled(AppointmentSetting $settings, string $event): bool
    {
        return match ($event) {
            'confirmation' => $settings->send_confirmation,
            'reminder' => $settings->send_reminders,
            'cancelled', 'rescheduled' => $settings->send_status_updates,
            default => false,
        };
    }

    private function timestampColumn(string $event): string
    {
        return match ($event) {
            'confirmation' => 'channel_confirmation_sent_at',
            'reminder' => 'channel_reminder_sent_at',
            default => 'channel_status_sent_at',
        };
    }

    private function render(AppointmentSetting $settings, Appointment $appointment, string $event): string
    {
        $templates = [
            'confirmation' => $settings->confirmation_message ?: 'Olá, {cliente}! Seu agendamento {codigo} de {servico} com {profissional} foi confirmado para {data} às {hora}.',
            'reminder' => $settings->reminder_message ?: 'Olá, {cliente}! Lembrete do seu agendamento {codigo}: {servico}, amanhã em {data} às {hora}, com {profissional}.',
            'cancelled' => $settings->cancellation_message ?: 'Olá, {cliente}. O agendamento {codigo} de {servico}, marcado para {data} às {hora}, foi cancelado.',
            'rescheduled' => $settings->reschedule_message ?: 'Olá, {cliente}! Seu agendamento {codigo} foi reagendado para {data} às {hora}, com {profissional}.',
        ];
        $local = $appointment->starts_at->timezone($appointment->timezone ?: $settings->timezone);

        return strtr($templates[$event] ?? $templates['confirmation'], [
            '{cliente}' => $appointment->customer_name,
            '{codigo}' => $appointment->reference,
            '{servico}' => $appointment->service?->name ?? 'atendimento',
            '{profissional}' => $appointment->resource?->name ?? 'equipe',
            '{data}' => $local->format('d/m/Y'),
            '{hora}' => $local->format('H:i'),
            '{motivo}' => $appointment->cancel_reason ?? '',
        ]);
    }
}
