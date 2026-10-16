# CREASY CRM Community 1.0.0-beta.1 — instalação sem terminal

Use o pacote completo compilado. Inclui `vendor` e `public/build`; não exige Composer, Node, SSH ou comandos Artisan para instalar.

1. Crie um banco MySQL vazio e um usuário autorizado no painel da hospedagem.
2. Extraia todos os arquivos, incluindo `.env.example` e `.htaccess`. Não envie um `.env` de outra instalação.
3. Aponte a raiz do domínio para a pasta `public` do projeto e ative HTTPS.
4. O PHP precisa escrever na pasta do projeto para criar `.env`, e em `storage`, `bootstrap/cache` e `public/storage`.
5. Abra `https://SEU-DOMINIO/install`. Na primeira abertura, o sistema gera `.env`, uma APP_KEY exclusiva e uma chave privada de instalação.
6. Pelo gerenciador de arquivos, abra `storage/app/private/installation-key.txt`. Copie a chave para o assistente. Ela não é mostrada publicamente e é removida ao concluir.
7. Informe sua URL, os dados do banco e do administrador. A porta é opcional. Use o host exato informado pela hospedagem. Escolha produção sem exemplos e conclua.

O instalador testa e salva o banco com os valores do formulário. Não apaga um banco existente nem permite reinstalar sobre um banco com administradores. Remover arquivos do site não remove as tabelas do banco.

PHP 8.2 ou superior e extensões PDO MySQL, mbstring, openssl, tokenizer, ctype, JSON, bcmath, fileinfo, curl, DOM, XML, ZIP e GD são necessários; o assistente verifica os requisitos.

Uploads públicos usam `public/storage`, sem precisar executar `storage:link`. Não exponha a raiz do projeto como uma pasta navegável. Apache/LiteSpeed devem respeitar `.htaccess`; para Nginx, configure a raiz em `public`.

## Após instalar

Configure seus próprios canais, SMTP e provedores. Leia `PRIVACIDADE.md`: esta edição registra nome/e-mail do administrador, domínio, versão e identificador de instalação na CREASY.

Filas, lembretes e mensagens programadas precisam de tarefas periódicas. A instalação não exige terminal, mas esses recursos exigem que o painel da hospedagem execute a cada minuto `php /CAMINHO/DO/PROJETO/artisan schedule:run` como tarefa cron. Ajuste o caminho do PHP conforme o provedor. Se a hospedagem não permite cron ou processos longos, parte das automações/IA não funcionará continuamente; use uma hospedagem compatível.

Nunca publique `.env`, `storage` com dados, chaves geradas ou backups. Para atualizar uma instalação existente, preserve `.env` e os arquivos enviados; este pacote inicial não é um mecanismo automático de atualização.

O status de telemetria usa sinais técnicos a cada 5 minutos, sem reenviar nome/e-mail. Configure o cron `php /CAMINHO/DO/PROJETO/artisan schedule:run` a cada minuto para manter os sinais mesmo sem visitas. O painel considera Online até 15 minutos do último sinal e Offline depois; isso não comprova disponibilidade completa do site. Consulte `PRIVACIDADE.md` e [como remover a telemetria](REMOVER-TELEMETRIA.md).
