<?php

namespace App\Services\Mail;

use App\Models\SmtpConfiguration;
use App\Models\Template;
use App\Modules\Broadcasting\Models\WorkspaceSmtpConfig;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class MailService
{
    /**
     * Send an email using the active SMTP configuration and an email template by key.
     *
     * @param  array<string, string>  $replacements  e.g. ['app_name' => 'CloudPOS', 'plan_name' => 'Pro', 'user_name' => 'John']
     */
    public function sendWithTemplate(string $templateKey, string $to, array $replacements = []): bool
    {
        $smtp = SmtpConfiguration::getActive();
        if (! $smtp) {
            Log::warning('MailService: No active SMTP configuration. Email not sent.', ['to' => $to, 'template' => $templateKey]);

            return false;
        }

        $template = Template::where('type', 'email')->where('slug', $templateKey)->where('enabled', true)->first();
        if (! $template) {
            Log::warning('MailService: Email template not found or disabled.', ['key' => $templateKey]);

            return false;
        }

        $subject = $this->replacePlaceholders($template->subject ?? $template->name, $replacements);
        $body = $this->replacePlaceholders($template->content ?? '', $replacements);

        // Transactional mail must never surface an SMTP error to the end user.
        // Swallow send failures here (already logged in sendRaw) and report a
        // simple false so callers fall back gracefully. Campaign delivery and
        // admin test-send use sendRaw directly and keep the thrown exception.
        try {
            return $this->sendRaw($smtp, $to, $subject, $body);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Send a raw email using the given SMTP configuration.
     *
     * @param  SmtpConfiguration|WorkspaceSmtpConfig  $smtp
     * @param  array<string, string>  $extraHeaders  Additional RFC headers (e.g. List-Unsubscribe)
     * @param  string|null  $fromEmail   Override the SMTP default from address
     * @param  string|null  $fromName    Override the SMTP default from name
     * @param  string|null  $replyTo     Optional reply-to address
     */
    public function sendRaw(
        Model $smtp,
        string $to,
        string $subject,
        string $bodyHtml,
        array $extraHeaders = [],
        ?string $fromEmail = null,
        ?string $fromName = null,
        ?string $replyTo = null,
    ): bool {
        try {
            $this->configureMailer($smtp);
            Mail::mailer('dynamic_smtp')->html($bodyHtml, function ($message) use ($to, $subject, $smtp, $extraHeaders, $fromEmail, $fromName, $replyTo) {
                $message->to($to)
                    ->subject($subject)
                    ->from($fromEmail ?: $smtp->from_email, $fromName ?: $smtp->from_name);

                if ($replyTo) {
                    $message->replyTo($replyTo);
                }

                foreach ($extraHeaders as $name => $value) {
                    $message->getHeaders()->addTextHeader($name, $value);
                }
            });
            return true;
        } catch (\Throwable $e) {
            Log::error('MailService: Failed to send email.', [
                'to' => $to,
                'error' => $e->getMessage(),
                'smtp_id' => $smtp->id,
            ]);
            throw $e;
        }
    }

    /**
     * Configure Laravel mail to use the given SMTP configuration for the 'dynamic_smtp' mailer.
     *
     * @param SmtpConfiguration|WorkspaceSmtpConfig $smtp
     */
    public function configureMailer(Model $smtp): void
    {
        $encryption = $smtp->encryption;
        if ($encryption === 'none' || $encryption === 'null' || $encryption === '') {
            $encryption = null;
        }

        $manager = app('mail.manager');
        if (method_exists($manager, 'purge')) {
            $manager->purge('dynamic_smtp');
        }

        Config::set('mail.mailers.dynamic_smtp', [
            'transport' => 'smtp',
            'host' => $smtp->host,
            'port' => $smtp->port,
            'username' => $smtp->username,
            'password' => $smtp->getDecryptedPassword(),
            'encryption' => $encryption,
            'timeout' => null,
            'from' => [
                'address' => $smtp->from_email,
                'name' => $smtp->from_name,
            ],
        ]);
    }

    private function replacePlaceholders(string $text, array $replacements): string
    {
        foreach ($replacements as $key => $value) {
            $text = str_replace('{{' . $key . '}}', (string) $value, $text);
        }
        return $text;
    }
}
