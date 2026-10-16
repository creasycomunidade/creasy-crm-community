<?php

namespace Database\Seeders;

use App\Models\Template;
use Illuminate\Database\Seeder;

class EmailTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            // ── Auth ────────────────────────────────────────────────────────
            [
                'name'    => 'Boas-vindas',
                'slug'    => 'welcome',
                'subject' => 'Boas-vindas ao {{app_name}}!',
                'type'    => 'email',
                'content' => "<p>Olá, {{user_name}}!</p><p>Boas-vindas ao {{app_name}}. Sua conta está pronta.</p><p><a href=\"{{login_url}}\">Acessar minha conta</a></p><p>Atenciosamente,<br>Equipe {{app_name}}</p>",
                'enabled' => true,
                'meta'    => [
                    'description' => 'Enviado quando um novo usuário se cadastra.',
                    'placeholders' => ['app_name', 'user_name', 'login_url'],
                ],
            ],
            [
                'name'    => 'Redefinição de senha',
                'slug'    => 'reset_password',
                'subject' => 'Redefina sua senha do {{app_name}}',
                'type'    => 'email',
                'content' => "<p>Olá!</p><p>Recebemos uma solicitação para redefinir sua senha.</p><p><a href=\"{{reset_url}}\">Redefinir minha senha</a></p><p>Este link expira em 60 minutos. Se você não fez essa solicitação, ignore este e-mail.</p><p>Atenciosamente,<br>Equipe {{app_name}}</p>",
                'enabled' => true,
                'meta'    => [
                    'description' => 'Enviado quando um usuário solicita a redefinição de senha.',
                    'placeholders' => ['app_name', 'reset_url'],
                ],
            ],
            [
                'name'    => 'Confirmação de e-mail',
                'slug'    => 'email_verification',
                'subject' => 'Confirme seu e-mail no {{app_name}}',
                'type'    => 'email',
                'content' => "<p>Olá!</p><p>Confirme seu endereço de e-mail clicando no botão abaixo:</p><p><a href=\"{{verification_url}}\">Confirmar meu e-mail</a></p><p>Este link expira em 60 minutos. Se você não criou esta conta, ignore este e-mail.</p><p>Atenciosamente,<br>Equipe {{app_name}}</p>",
                'enabled' => true,
                'meta'    => [
                    'description' => 'Enviado para confirmar o endereço de e-mail do usuário.',
                    'placeholders' => ['app_name', 'verification_url'],
                ],
            ],
            [
                'name'    => 'Acesso por link mágico',
                'slug'    => 'magic_link',
                'subject' => 'Seu link de acesso ao {{app_name}}',
                'type'    => 'email',
                'content' => "<p>Olá!</p><p>Use o link abaixo para acessar o {{app_name}}. Ele expira em {{expires_minutes}} minutos.</p><p><a href=\"{{magic_link_url}}\">Acessar o {{app_name}}</a></p><p>Se você não fez essa solicitação, ignore este e-mail.</p><p>Atenciosamente,<br>Equipe {{app_name}}</p>",
                'enabled' => true,
                'meta'    => [
                    'description' => 'Enviado quando um usuário solicita acesso por link mágico.',
                    'placeholders' => ['app_name', 'magic_link_url', 'expires_minutes'],
                ],
            ],
            [
                'name'    => 'Convite para a equipe',
                'slug'    => 'team_invitation',
                'subject' => 'Você foi convidado para participar de {{organization_name}}',
                'type'    => 'email',
                'content' => "<p>Olá!</p><p>{{inviter_name}} convidou você para participar de {{organization_name}} no {{app_name}}.</p><p><a href=\"{{invitation_url}}\">Aceitar convite</a></p><p>Este convite expira em {{expires_days}} dias.</p><p>Atenciosamente,<br>Equipe {{app_name}}</p>",
                'enabled' => true,
                'meta'    => [
                    'description' => 'Enviado quando um usuário é convidado para uma organização.',
                    'placeholders' => ['app_name', 'inviter_name', 'organization_name', 'invitation_url', 'expires_days'],
                ],
            ],

            // ── Billing ─────────────────────────────────────────────────────









            // ── Support / Tickets ───────────────────────────────────────────
            [
                'name'    => 'Chamado de suporte criado',
                'slug'    => 'support_ticket_created',
                'subject' => 'Recebemos seu chamado de suporte #{{ticket_id}}',
                'type'    => 'email',
                'content' => "<p>Olá, {{user_name}}!</p><p>Recebemos seu chamado e nossa equipe responderá o mais breve possível.</p><p><strong>Detalhes do chamado</strong><br>Número: <strong>#{{ticket_id}}</strong><br>Assunto: <strong>{{ticket_subject}}</strong><br>Prioridade: <strong>{{ticket_priority}}</strong><br>Status: <strong>Aberto</strong></p><p><a href=\"{{ticket_url}}\">Ver chamado</a></p><p>Atenciosamente,<br>Equipe de suporte {{app_name}}</p>",
                'enabled' => true,
                'meta'    => [
                    'description'  => 'Enviado ao usuário quando um novo chamado é criado.',
                    'placeholders' => ['app_name', 'user_name', 'ticket_id', 'ticket_subject', 'ticket_priority', 'ticket_url'],
                ],
            ],
            [
                'name'    => 'Novo chamado de suporte (administrador)',
                'slug'    => 'support_ticket_admin_new',
                'subject' => 'Novo chamado #{{ticket_id}} de {{user_name}}',
                'type'    => 'email',
                'content' => "<p>Um novo chamado de suporte foi enviado.</p><p><strong>Detalhes do chamado</strong><br>Número: <strong>#{{ticket_id}}</strong><br>De: <strong>{{user_name}}</strong> ({{user_email}})<br>Assunto: <strong>{{ticket_subject}}</strong><br>Prioridade: <strong>{{ticket_priority}}</strong></p><p><strong>Mensagem:</strong><br>{{ticket_message}}</p><p><a href=\"{{ticket_url}}\">Ver e responder</a></p>",
                'enabled' => true,
                'meta'    => [
                    'description'  => 'Enviado aos administradores quando um novo chamado é criado.',
                    'placeholders' => ['ticket_id', 'user_name', 'user_email', 'ticket_subject', 'ticket_priority', 'ticket_message', 'ticket_url'],
                ],
            ],
            [
                'name'    => 'Resposta do suporte (para o cliente)',
                'slug'    => 'support_ticket_reply_client',
                'subject' => 'Nova resposta no chamado #{{ticket_id}}: {{ticket_subject}}',
                'type'    => 'email',
                'content' => "<p>Olá, {{user_name}}!</p><p>Nossa equipe respondeu ao chamado <strong>#{{ticket_id}}</strong>.</p><p><strong>Resposta de {{staff_name}}:</strong><br>{{reply_message}}</p><p><a href=\"{{ticket_url}}\">Ver e responder</a></p><p>Atenciosamente,<br>Equipe de suporte {{app_name}}</p>",
                'enabled' => true,
                'meta'    => [
                    'description'  => 'Enviado ao cliente quando a equipe responde ao chamado.',
                    'placeholders' => ['app_name', 'user_name', 'ticket_id', 'ticket_subject', 'staff_name', 'reply_message', 'ticket_url'],
                ],
            ],
            [
                'name'    => 'Resposta do chamado (para o administrador)',
                'slug'    => 'support_ticket_reply_admin',
                'subject' => 'Cliente respondeu ao chamado #{{ticket_id}}: {{ticket_subject}}',
                'type'    => 'email',
                'content' => "<p>Um cliente respondeu ao chamado <strong>#{{ticket_id}}</strong>.</p><p><strong>De:</strong> {{user_name}} ({{user_email}})<br><strong>Assunto:</strong> {{ticket_subject}}</p><p><strong>Resposta:</strong><br>{{reply_message}}</p><p><a href=\"{{ticket_url}}\">Ver e responder</a></p>",
                'enabled' => true,
                'meta'    => [
                    'description'  => 'Enviado aos administradores quando o cliente responde ao chamado.',
                    'placeholders' => ['ticket_id', 'user_name', 'user_email', 'ticket_subject', 'reply_message', 'ticket_url'],
                ],
            ],
            [
                'name'    => 'Status do chamado alterado',
                'slug'    => 'support_ticket_status_changed',
                'subject' => 'O status do chamado #{{ticket_id}} mudou para {{new_status}}',
                'type'    => 'email',
                'content' => "<p>Olá, {{user_name}}!</p><p>O status do seu chamado foi atualizado.</p><p><strong>Chamado:</strong> #{{ticket_id}} — {{ticket_subject}}<br><strong>Novo status:</strong> <strong>{{new_status}}</strong></p><p><a href=\"{{ticket_url}}\">Ver chamado</a></p><p>Atenciosamente,<br>Equipe de suporte {{app_name}}</p>",
                'enabled' => true,
                'meta'    => [
                    'description'  => 'Enviado ao cliente quando o status do chamado é alterado.',
                    'placeholders' => ['app_name', 'user_name', 'ticket_id', 'ticket_subject', 'new_status', 'ticket_url'],
                ],
            ],
        ];

        foreach ($templates as $data) {
            $existing = Template::where('slug', $data['slug'])->where('type', 'email')->first();
            if ($existing) {
                // System emails must remain consistent with the platform locale.
                $existing->update($data);
            } else {
                Template::create($data);
            }
        }
    }
}
