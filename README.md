# CREASY CRM Community

**1.0.0-beta.1** — CRM de código aberto para centralizar atendimentos, gerenciar contatos e automatizar conversas em servidor próprio.

## Recursos

- Caixa de entrada, canais WhatsApp, contatos, CRM, tarefas e relatórios de atendimento.
- Chatbots de IA, transcrição, automações e fluxos.
- Agenda, lembretes, mensagens programadas e webhooks.
- Personalização de nome, logo e cores.

Integrações exigem credenciais e serviços externos próprios. Esta é uma versão beta; valide seu cenário antes de usar com dados reais.

## Instalar sem terminal

Esta distribuição inclui **dependências PHP em `vendor` e interface compilada em `public/build`**. Não é necessário executar Composer, Node ou Artisan para abrir e concluir o instalador.

1. Extraia os arquivos na hospedagem com PHP 8.2+ e MySQL/MariaDB ou PostgreSQL compatível.
2. Configure HTTPS e a raiz do domínio apontando para `public`.
3. Abra `/install`. O sistema gera chaves exclusivas no servidor, sem usar chaves compartilhadas pelo repositório.
4. Leia `storage/app/private/installation-key.txt` no gerenciador de arquivos e informe a chave no assistente.
5. Informe um banco vazio, configure a marca e crie o administrador.

Veja [as instruções completas](docs/INSTALACAO-SEM-TERMINAL.md). Filas e tarefas programadas precisam de cron configurado no painel da hospedagem após instalar.

## Registro de instalações

Ao concluir a instalação, a edição oficial registra na CREASY um identificador aleatório, domínio, versão e **nome/e-mail do administrador**. Não envia senha, contatos, conversas ou credenciais. A coleta é informada no instalador e não possui botão de desativação na interface. Leia [PRIVACIDADE.md](PRIVACIDADE.md).

## Desenvolvimento

Fontes incluídos: Laravel, React e Inertia.js. Para desenvolvimento local, instale dependências com Composer e npm. A interface pode ser recompilada com `npm run build`; builds públicos devem usar `RESPONDESK_RELEASE_BUILD=1 npm run build` para não incorporar o `.env` local. Preserve as licenças das dependências.

`vendor` e `public/build` estão incluídos intencionalmente nesta distribuição para hospedagens sem terminal. Nunca adicione `.env`, bancos, logs, uploads, arquivos de instalação gerados ou chaves ao repositório.

## Licença

AGPL-3.0-only para o código próprio abrangido; dependências mantêm suas licenças. Consulte [LICENSE](LICENSE), [LICENSING.md](LICENSING.md) e [THIRD_PARTY_NOTICES.md](THIRD_PARTY_NOTICES.md).
