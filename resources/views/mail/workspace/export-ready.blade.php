@component('mail::message')
# Sua exportação de dados está pronta

Olá, {{ $name }}!

A exportação de dados do seu espaço foi gerada e está pronta para download.

@component('mail::button', ['url' => $downloadUrl])
Baixar exportação (ZIP)
@endcomponent

**Este link expira em 72 horas.** Depois desse prazo, você poderá solicitar uma nova exportação nas configurações.

Se você não solicitou esta exportação, entre em contato imediatamente com nossa equipe de suporte.

Atenciosamente,  
Equipe {{ config('app.name') }}
@endcomponent
