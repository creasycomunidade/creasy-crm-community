# Receptor privado da CREASY

Este pacote é separado do CRM e deve ser instalado somente pela CREASY.

1. No painel da hospedagem de creasy.com.br, crie o subdomínio `telemetria.creasy.com.br`.
2. Aponte o DNS para a hospedagem desse subdomínio e ative HTTPS.
3. Extraia o pacote `CREASY-TELEMETRIA-SERVIDOR.zip`. A raiz do subdomínio deve apontar para a pasta `public` deste pacote.
4. Habilite PHP 8.2+ com `pdo_sqlite`. Permita escrita apenas na pasta `private` e no diretório padrão de sessões do PHP.
5. Abra `https://telemetria.creasy.com.br/`. A primeira abertura cria o banco SQLite privado e a chave administrativa.
6. Pelo gerenciador de arquivos, leia `private/admin-key.txt`. Use essa chave para entrar no painel. Não a publique nem a envie junto com o código.

Não precisa executar comandos no terminal. O endpoint de registro será `/api/installations.php`.

O painel mostra registros recebidos, domínios distintos e nome/e-mail dos responsáveis, em páginas de 100 registros. Permite excluir registros. O endpoint é público para novas instalações: há limitação de requisições, validação estrita e idempotência por identificador/segredo; isso reduz abuso, mas não comprova que todos os registros são instalações reais. Não use a contagem como verificação de licença.

Não há coleta de senhas do CRM. O receptor armazena somente o hash de um segredo técnico gerado para deduplicação, não a senha do administrador. A chave de acesso ao painel é gerada neste servidor, nunca compartilhada no pacote público.

Configure retenção, acesso e backups do banco privado e dos logs da hospedagem. Atenda pedidos de exclusão pelo painel. Nunca exponha `private` no servidor web; a raiz em `public` é obrigatória especialmente em Nginx, que não lê `.htaccess`.

Este pacote não configura DNS automaticamente. Os registros só começarão a chegar depois de o subdomínio estar publicado e com HTTPS válido.

## Atualizar para status online/offline

Faça backup de `private/registrations.sqlite` e preserve esse banco e `private/admin-key.txt`. Substitua apenas os arquivos de código do receptor pelo novo ZIP. A coluna de último contato é criada automaticamente ao abrir o painel ou receber uma chamada; não precisa de terminal.

Atualize também o CRM: serviço `app/Services/CommunityRegistration.php`, `config/community.php`, `routes/console.php` e demais arquivos do novo pacote, preservando `.env` e `storage`. Limpe o cache de configuração se estiver ativo (com terminal: `php artisan config:clear`; sem terminal: remova somente `bootstrap/cache/config.php` pelo gerenciador de arquivos). Reinicie PHP/OPcache e processos persistentes conforme o painel da hospedagem.

Configure o cron do CRM para executar `php /CAMINHO/DO/PROJETO/artisan schedule:run` a cada minuto. O sinal ocorre a cada 5 minutos; visitas são uma alternativa quando não há cron. Online: sinal recebido há até 15 minutos. Offline: sinal mais antigo. Sem sinal: nunca recebeu sinal autenticado. Atualize o painel para recalcular; último contato é exibido em UTC.

Registros da versão anterior que já concluíram o envio perderam seu segredo técnico por projeto. Eles continuam no painel como Sem sinal e não podem começar a autenticar sinais só com a atualização. Para registrar novamente sem reinstalar o CRM, faça isso em ambiente de manutenção: após atualizar ambos os lados, guarde o ID antigo, remova apenas `storage/app/private/community-registration.json` e seu `.lock`, e execute pelo Tinker, se disponível, `app(\App\Services\CommunityRegistration::class)->queue('NOME DO RESPONSÁVEL', 'EMAIL DO RESPONSÁVEL', config('app.url'));` seguido de `app(\App\Services\CommunityRegistration::class)->deliver();`. Use dados do responsável e não senhas. Isso gera outro ID; confira o novo registro e exclua o antigo no painel para não duplicar a contagem. Sem terminal, mantenha o registro antigo como Sem sinal até disponibilizar uma rotina administrativa de re-registro. Não reabra o instalador nem apague o banco para esse fim.

O receptor não consulta URLs arbitrárias nem faz varredura dos servidores: o status depende de sinais autenticados recebidos. Excluir um registro rejeita futuros sinais daquele ID.
