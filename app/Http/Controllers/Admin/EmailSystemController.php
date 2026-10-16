<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SmtpConfiguration;
use App\Models\Template;
use App\Services\Mail\MailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class EmailSystemController extends Controller
{
    public function __construct(
        protected MailService $mailService
    ) {}

    public function index(): Response
    {
        $smtpConfigurations = SmtpConfiguration::orderBy('is_active', 'desc')->orderBy('id')->get()->map(fn (SmtpConfiguration $c) => [
            'id' => $c->id,
            'host' => $c->host,
            'port' => $c->port,
            'username' => $c->username,
            'password' => '', // never send to frontend
            'encryption' => $c->encryption,
            'from_email' => $c->from_email,
            'from_name' => $c->from_name,
            'is_active' => $c->is_active,
            'summary' => $c->summary,
        ]);

        $emailTemplates = Template::where('type', 'email')->orderBy('name')->get()->map(fn (Template $t) => [
            'id'           => $t->id,
            'name'         => $t->name,
            'slug'         => $t->slug,
            'subject'      => $t->subject,
            'content'      => $t->content,
            'enabled'      => $t->enabled,
            'description'  => $t->meta['description'] ?? null,
            'placeholders' => $t->meta['placeholders'] ?? [],
        ]);

        return Inertia::render('Admin/EmailSystem/Index', [
            'smtpConfigurations' => $smtpConfigurations,
            'emailTemplates' => $emailTemplates,
        ]);
    }

    public function storeSmtp(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'host' => ['required', 'string', 'max:255'],
            'port' => ['required', 'integer', 'min:1', 'max:65535'],
            'username' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
            'encryption' => ['required', 'string', 'in:tls,ssl,none'],
            'from_email' => ['required', 'email'],
            'from_name' => ['required', 'string', 'max:255'],
            'activate' => ['boolean'],
        ]);

        if (($validated['activate'] ?? false) === true) {
            SmtpConfiguration::query()->update(['is_active' => false]);
        }

        SmtpConfiguration::create([
            'host' => $validated['host'],
            'port' => $validated['port'],
            'username' => $validated['username'],
            'password' => $validated['password'],
            'encryption' => $validated['encryption'],
            'from_email' => $validated['from_email'],
            'from_name' => $validated['from_name'],
            'is_active' => $validated['activate'] ?? false,
        ]);

        return redirect()->route('admin.email-system.index')->with('success', __('SMTP configuration added.'));
    }

    public function updateSmtp(Request $request, SmtpConfiguration $smtpConfiguration): RedirectResponse
    {
        $validated = $request->validate([
            'host' => ['required', 'string', 'max:255'],
            'port' => ['required', 'integer', 'min:1', 'max:65535'],
            'username' => ['required', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:255'],
            'encryption' => ['required', 'string', 'in:tls,ssl,none'],
            'from_email' => ['required', 'email'],
            'from_name' => ['required', 'string', 'max:255'],
            'activate' => ['boolean'],
        ]);

        if (($validated['activate'] ?? false) === true) {
            SmtpConfiguration::where('id', '!=', $smtpConfiguration->id)->update(['is_active' => false]);
        }

        $data = [
            'host' => $validated['host'],
            'port' => $validated['port'],
            'username' => $validated['username'],
            'encryption' => $validated['encryption'],
            'from_email' => $validated['from_email'],
            'from_name' => $validated['from_name'],
            'is_active' => $validated['activate'] ?? $smtpConfiguration->is_active,
        ];
        if (! empty($validated['password'])) {
            $data['password'] = $validated['password'];
        }
        $smtpConfiguration->update($data);

        return redirect()->route('admin.email-system.index')->with('success', __('SMTP configuration updated.'));
    }

    public function updateTemplate(Request $request, Template $template): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'subject' => ['nullable', 'string', 'max:500'],
            'content' => ['nullable', 'string'],
            'enabled' => ['boolean'],
        ]);

        $template->update($validated);

        return redirect()->route('admin.email-system.index')->with('success', __('Email template updated.'));
    }

    public function destroySmtp(SmtpConfiguration $smtpConfiguration): RedirectResponse
    {
        $smtpConfiguration->delete();
        return redirect()->route('admin.email-system.index')->with('success', __('SMTP configuration removed.'));
    }

    public function activateSmtp(SmtpConfiguration $smtpConfiguration): RedirectResponse
    {
        SmtpConfiguration::query()->update(['is_active' => false]);
        $smtpConfiguration->update(['is_active' => true]);
        return redirect()->route('admin.email-system.index')->with('success', __('SMTP configuration activated.'));
    }

    /**
     * Send a test email using a specific template's content.
     */
    public function testTemplate(Request $request, Template $template): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $smtp = SmtpConfiguration::getActive();
        if (! $smtp) {
            return response()->json(['message' => __('No active SMTP configuration. Please activate one first.')], 422);
        }

        try {
            $subject = '[TESTE] '.($template->subject ?? $template->name);
            $content = $template->content ?? '<p>Sem conteúdo.</p>';

            $this->mailService->sendRaw($smtp, $validated['email'], $subject, $content);

            return response()->json(['message' => __('Test email sent successfully.')]);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Send a test email using the active SMTP configuration.
     */
    public function testEmail(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $smtp = SmtpConfiguration::getActive();
        if (! $smtp) {
            if ($request->wantsJson()) {
                return response()->json(['message' => __('No active SMTP configuration. Please activate one first.')], 422);
            }
            throw ValidationException::withMessages(['email' => [__('No active SMTP configuration.')]]);
        }

        try {
            $appName = config('app.name');
            $this->mailService->sendRaw(
                $smtp,
                $validated['email'],
                __('Test email from :app', ['app' => $appName]),
                '<p>'.__('This is a test email. Your SMTP configuration is working.').'</p>'
            );
            if ($request->wantsJson()) {
                return response()->json(['message' => __('Test email sent successfully.')]);
            }
            return redirect()->route('admin.email-system.index')->with('success', __('Test email sent.'));
        } catch (\Throwable $e) {
            if ($request->wantsJson()) {
                return response()->json(['message' => $e->getMessage()], 500);
            }
            throw ValidationException::withMessages(['email' => [$e->getMessage()]]);
        }
    }
}
