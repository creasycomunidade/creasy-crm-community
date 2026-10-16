@component('mail::message')
# Relatório semanal — {{ $workspace->name }}

Confira o resumo de desempenho de **{{ $period }}**.

---

## 📬 Mensagens

@component('mail::table')
| Métrica | Valor |
|--------|-------|
| Mensagens enviadas | {{ number_format($stats['messages_sent']) }} |
| Taxa de entrega | {{ $stats['delivery_rate'] }}% |
| Conversas iniciadas | {{ number_format($stats['conversations_opened']) }} |
| Conversas resolvidas | {{ number_format($stats['conversations_resolved']) }} |
@endcomponent

---

## 🤖 Uso de IA

@component('mail::table')
| Métrica | Valor |
|--------|-------|
| Total de tokens | {{ number_format($stats['ai_tokens']) }} |
| Custo estimado | US$ {{ number_format($stats['ai_cost_usd'], 2, ',', '.') }} |
| Execuções de IA | {{ number_format($stats['ai_runs']) }} |
@endcomponent

---

@if($stats['top_campaign'])
## 📣 Campanha em destaque

**{{ $stats['top_campaign']['name'] }}** — {{ $stats['top_campaign']['delivered_pct'] }}% entregue

@endif

@if($stats['top_chatbot'])
## 🧠 Chatbot em destaque

**{{ $stats['top_chatbot']['name'] }}** — {{ number_format($stats['top_chatbot']['runs']) }} execuções

@endif

---

@component('mail::button', ['url' => $dashboardUrl, 'color' => 'indigo'])
Abrir painel
@endcomponent

Você recebeu este e-mail porque o resumo semanal está ativo em seu espaço de trabalho.
Para desativá-lo, acesse as [configurações de relatórios]({{ $settingsUrl }}).

Atenciosamente,
{{ config('app.name') }}
@endcomponent
