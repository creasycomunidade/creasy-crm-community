// ─── Built-in email templates ─────────────────────────────────────────────────

export const EMAIL_TEMPLATES = [
    {
        id: 'welcome',
        name: 'E-mail de boas-vindas',
        category: 'Integração',
        subject: 'Boas-vindas à {{company_name}}, {{contact.first_name}}!',
        body: `<h2 style="margin:0 0 12px;font-family:sans-serif;color:#111;">Boas-vindas, {{contact.first_name}}! 🎉</h2>
<p style="margin:0 0 12px;font-family:sans-serif;font-size:15px;line-height:1.6;color:#333;">É muito bom ter você conosco. Sua conta está pronta para uso.</p>
<p style="margin:0 0 12px;font-family:sans-serif;font-size:15px;line-height:1.6;color:#333;">Veja os próximos passos:</p>
<p style="margin:0 0 8px;font-family:sans-serif;font-size:15px;line-height:1.6;color:#333;">✅ Complete seu perfil</p>
<p style="margin:0 0 8px;font-family:sans-serif;font-size:15px;line-height:1.6;color:#333;">✅ Conheça nossos recursos</p>
<p style="margin:0 0 16px;font-family:sans-serif;font-size:15px;line-height:1.6;color:#333;">✅ Fale conosco se precisar de ajuda</p>
<div style="text-align:center;margin:16px 0;"><a href="[LINK DE ACESSO]" style="display:inline-block;padding:12px 24px;background:#2563eb;color:#fff;text-decoration:none;border-radius:6px;font-family:sans-serif;font-size:15px;font-weight:600;">Começar agora</a></div>`,
    },
    {
        id: 'promo',
        name: 'Oferta promocional',
        category: 'Marketing',
        subject: 'Uma oferta especial para você, {{contact.first_name}}',
        body: `<h2 style="margin:0 0 12px;font-family:sans-serif;color:#111;">Uma oferta especial para você</h2>
<p style="margin:0 0 12px;font-family:sans-serif;font-size:15px;line-height:1.6;color:#333;">Olá, {{contact.first_name}}! Preparamos uma condição especial para você.</p>
<p style="margin:0 0 12px;font-family:sans-serif;font-size:15px;line-height:1.6;color:#333;">Use o cupom <strong>[CÓDIGO DO CUPOM]</strong> para receber [DESCONTO] em sua próxima compra.</p>
<p style="margin:0 0 12px;font-family:sans-serif;font-size:15px;line-height:1.6;color:#333;">Oferta válida até [DATA DE VALIDADE].</p>
<div style="text-align:center;margin:16px 0;"><a href="[LINK DA OFERTA]" style="display:inline-block;padding:12px 24px;background:#f59e0b;color:#fff;text-decoration:none;border-radius:6px;font-family:sans-serif;font-size:15px;font-weight:600;">Ver oferta</a></div>`,
    },
    {
        id: 'newsletter',
        name: 'Informativo',
        category: 'Conteúdo',
        subject: 'Novidades do mês — edição de {{month}}',
        body: `<h2 style="margin:0 0 4px;font-family:sans-serif;color:#111;">Informativo mensal</h2>
<p style="margin:0 0 20px;font-family:sans-serif;font-size:13px;color:#6b7280;">Novidades, dicas e informações importantes</p>
<hr style="border:none;border-top:1px solid #e5e7eb;margin:20px 0;" />
<h3 style="margin:0 0 8px;font-family:sans-serif;color:#111;">Novidades deste mês</h3>
<p style="margin:0 0 12px;font-family:sans-serif;font-size:15px;line-height:1.6;color:#333;">Use este espaço para apresentar os principais lançamentos e melhorias.</p>
<h3 style="margin:0 0 8px;font-family:sans-serif;color:#111;">Conteúdo em destaque</h3>
<p style="margin:0 0 12px;font-family:sans-serif;font-size:15px;line-height:1.6;color:#333;">Adicione aqui uma breve descrição do conteúdo recomendado.</p>
<div style="text-align:center;margin:16px 0;"><a href="[LINK DO CONTEÚDO]" style="display:inline-block;padding:12px 24px;background:#2563eb;color:#fff;text-decoration:none;border-radius:6px;font-family:sans-serif;font-size:15px;font-weight:600;">Ler conteúdo</a></div>
<hr style="border:none;border-top:1px solid #e5e7eb;margin:20px 0;" />
<p style="margin:0;font-family:sans-serif;font-size:13px;color:#9ca3af;text-align:center;">Você recebeu este e-mail porque se inscreveu em nossa lista.</p>`,
    },
    {
        id: 'announcement',
        name: 'Lançamento de produto',
        category: 'Marketing',
        subject: 'Conheça [NOME DO RECURSO] — disponível agora',
        body: `<h2 style="margin:0 0 12px;font-family:sans-serif;color:#111;">Temos uma novidade 🚀</h2>
<p style="margin:0 0 12px;font-family:sans-serif;font-size:15px;line-height:1.6;color:#333;">Olá, {{contact.first_name}}! Preparamos algo novo para você.</p>
<h3 style="margin:0 0 8px;font-family:sans-serif;color:#111;">Conheça [NOME DO RECURSO]</h3>
<p style="margin:0 0 12px;font-family:sans-serif;font-size:15px;line-height:1.6;color:#333;">[DESCREVA O RECURSO E SEU PRINCIPAL BENEFÍCIO]</p>
<p style="margin:0 0 8px;font-family:sans-serif;font-size:15px;line-height:1.6;color:#333;">⚡ [BENEFÍCIO 1]</p>
<p style="margin:0 0 8px;font-family:sans-serif;font-size:15px;line-height:1.6;color:#333;">🎯 [BENEFÍCIO 2]</p>
<p style="margin:0 0 16px;font-family:sans-serif;font-size:15px;line-height:1.6;color:#333;">🔒 [BENEFÍCIO 3]</p>
<div style="text-align:center;margin:16px 0;"><a href="[LINK DO RECURSO]" style="display:inline-block;padding:12px 24px;background:#2563eb;color:#fff;text-decoration:none;border-radius:6px;font-family:sans-serif;font-size:15px;font-weight:600;">Conhecer agora</a></div>`,
    },
    {
        id: 'reengagement',
        name: 'Reengajamento',
        category: 'Retenção',
        subject: 'Sentimos sua falta, {{contact.first_name}} 👋',
        body: `<h2 style="margin:0 0 12px;font-family:sans-serif;color:#111;">Já faz algum tempo, {{contact.first_name}}</h2>
<p style="margin:0 0 12px;font-family:sans-serif;font-size:15px;line-height:1.6;color:#333;">Percebemos que você não aparece há algum tempo e queremos saber se está tudo bem.</p>
<p style="margin:0 0 12px;font-family:sans-serif;font-size:15px;line-height:1.6;color:#333;">Veja algumas novidades desde sua última visita:</p>
<p style="margin:0 0 8px;font-family:sans-serif;font-size:15px;line-height:1.6;color:#333;">✨ [NOVIDADE 1]</p>
<p style="margin:0 0 8px;font-family:sans-serif;font-size:15px;line-height:1.6;color:#333;">✨ [NOVIDADE 2]</p>
<p style="margin:0 0 16px;font-family:sans-serif;font-size:15px;line-height:1.6;color:#333;">✨ [NOVIDADE 3]</p>
<div style="text-align:center;margin:16px 0;"><a href="[LINK DE ACESSO]" style="display:inline-block;padding:12px 24px;background:#10b981;color:#fff;text-decoration:none;border-radius:6px;font-family:sans-serif;font-size:15px;font-weight:600;">Voltar e conferir</a></div>
<p style="margin:0 0 12px;font-family:sans-serif;font-size:13px;color:#6b7280;text-align:center;">Não deseja receber novidades? <a href="[LINK DE DESCADASTRO]" style="color:#6b7280;">Cancelar inscrição</a></p>`,
    },
    {
        id: 'event',
        name: 'Convite para evento',
        category: 'Eventos',
        subject: 'Você está convidado: [NOME DO EVENTO] em [DATA]',
        body: `<h2 style="margin:0 0 4px;font-family:sans-serif;color:#111;">Você está convidado!</h2>
<p style="margin:0 0 20px;font-family:sans-serif;font-size:13px;color:#6b7280;">Um evento especial para você, {{contact.first_name}}</p>
<h3 style="margin:0 0 8px;font-family:sans-serif;color:#111;">[NOME DO EVENTO]</h3>
<p style="margin:0 0 4px;font-family:sans-serif;font-size:15px;line-height:1.6;color:#333;">📅 [DATA], às [HORÁRIO]</p>
<p style="margin:0 0 16px;font-family:sans-serif;font-size:15px;line-height:1.6;color:#333;">📍 [LOCAL OU LINK]</p>
<p style="margin:0 0 12px;font-family:sans-serif;font-size:15px;line-height:1.6;color:#333;">Será um prazer receber você. [MOTIVO PARA PARTICIPAR]</p>
<div style="text-align:center;margin:16px 0;"><a href="[LINK DE CONFIRMAÇÃO]" style="display:inline-block;padding:12px 24px;background:#7c3aed;color:#fff;text-decoration:none;border-radius:6px;font-family:sans-serif;font-size:15px;font-weight:600;">Confirmar presença</a></div>
<p style="margin:0;font-family:sans-serif;font-size:13px;color:#6b7280;">As vagas são limitadas.</p>`,
    },
];
