# Como remover a telemetria do CREASY CRM Community

Guia para a versão 1.0.0-beta.1. Este documento explica uma alteração opcional no código; sua existência não desativa a telemetria da distribuição oficial.

O registro original envia identificador da instalação, segredo técnico de deduplicação, domínio, versão e nome/e-mail do administrador. Não envia a senha do administrador. O envio ocorre após instalar; se falhar, visitas posteriores tentam novamente. A versão com status também envia sinais técnicos a cada 5 minutos, via cron ou visitas. Não é uma medição de usuários ativos. Os métodos vazios abaixo interrompem tanto o registro quanto esses sinais.

## 1. Antes de começar

Faça backup dos arquivos que vai editar. Se o CRM já estiver funcionando, faça a alteração em uma janela de manutenção e aguarde requisições em andamento terminarem. Uma requisição iniciada antes da alteração ainda pode executar o código antigo.

Os caminhos abaixo partem da pasta principal do projeto, onde ficam `app`, `bootstrap`, `public` e `storage`. Não altere `.env`, `APP_KEY`, banco de dados ou a chave de instalação para remover a telemetria.

## 2. Desativar o registro e os envios sem terminal

No gerenciador de arquivos da hospedagem, abra:

```text
app/Services/CommunityRegistration.php
```

Substitua **todo o conteúdo** desse arquivo pelo código abaixo e salve:

```php
<?php

namespace App\Services;

class CommunityRegistration
{
    public function queue(string $name, string $email, string $url): void
    {
        // Registro de instalação removido nesta distribuição.
    }

    public function deliver(): void
    {
        // Nenhum dado é enviado ao receptor da CREASY.
    }
}
```

Essa substituição mantém os métodos usados pelo instalador e pelo middleware, mas elimina a gravação do registro e as chamadas HTTP. Não é necessário recompilar JavaScript ou executar Artisan para essa mudança no serviço PHP. Não apague simplesmente a classe: os outros arquivos ainda a utilizam.

Se a hospedagem mantiver o PHP antigo em OPcache, use a opção de reiniciar PHP/limpar OPcache do painel ou peça ao suporte para invalidar o cache desse arquivo. Reinicie também processos PHP persistentes, caso existam. Não crie um script público para limpar caches.

## 3. Apagar os dados locais da telemetria

Depois de substituir o serviço, encerrar requisições antigas e garantir que a nova versão está sendo executada, exclua apenas estes arquivos, se existirem:

```text
storage/app/private/community-registration.json
storage/app/private/community-registration.json.lock
```

O primeiro contém um registro pendente criptografado ou um comprovante de envio. O segundo é um arquivo de bloqueio. Preserve os demais arquivos de `storage`.

Apagar apenas esses arquivos, sem alterar o serviço, não é uma forma completa de remover a funcionalidade. Alterar apenas o endereço do receptor também não remove a geração dos dados e as tentativas de envio. Esta versão não possui uma variável `.env` de desativação.

## 4. Ajustar o aviso no instalador e a documentação

A coleta já fica desativada pelo passo 2. Para que a interface também descreva corretamente sua edição modificada, edite:

```text
resources/js/Components/Install/AdminStep.jsx
```

Substitua o aviso de registro automático por um texto como:

> Esta edição não envia o registro de instalação à CREASY. Nome e e-mail informados aqui são usados para criar a conta administrativa no CRM.

Atualize também `PRIVACIDADE.md` e a seção “Registro de instalações” do `README.md`. Descreva somente a alteração no registro da CREASY: integrações configuradas pelo usuário, como WhatsApp e provedores de IA, continuam realizando suas próprias conexões.

Alterações em JSX precisam ser compiladas **no seu computador**. Com Node.js/npm instalados, dentro da pasta do projeto, execute:

```powershell
npm ci
$env:RESPONDESK_RELEASE_BUILD='1'
npm run build
```

Em Linux/macOS, o último passo pode ser executado assim:

```bash
npm ci
RESPONDESK_RELEASE_BUILD=1 npm run build
```

Envie a pasta `public/build` resultante, incluindo o manifesto e os assets, à hospedagem junto com as alterações PHP. Preserve o `.env` e os dados da instalação. A variável de build acima é o nome técnico existente nesta versão e evita carregar o `.env` local na compilação pública.

## 5. Verificar o resultado

Confirme que o serviço publicado contém somente os dois métodos vazios do passo 2. Abra o CRM e confira que o acesso continua funcionando. Após remover os arquivos locais, confirme que eles não voltam a ser criados. Essa checagem de arquivos, sozinha, não comprova ausência de tráfego; o envio original acontece no servidor PHP, portanto a aba de rede do navegador não o mostra.

Para quem mantém testes automatizados, em `tests/Feature/Install/CommunityDistributionTest.php`, substitua os dois testes de envio da telemetria por um teste que use `Http::fake()`, chame `queue()` e `deliver()`, execute `Http::assertNothingSent()` e confira que `community-registration.json` não foi criado. Faça a mesma chamada a `deliver()` com um arquivo pendente de teste já existente para verificar que ele também não é transmitido. Preserve o teste de geração de chaves de instalação.

Os testes antigos de telemetria esperam envios e falharão após a desativação; isso exige atualizar essas expectativas. Não use dados reais para testar.

## 6. Dados que já foram enviados

A mudança impede novos envios pelo serviço alterado, mas **não apaga registros já recebidos pela CREASY**. Para removê-los, solicite a exclusão pelos canais oficiais em https://creasy.com.br/. O responsável pelo receptor pode excluir o registro no painel privado. Logs e backups do servidor receptor precisam ser tratados separadamente pelo operador.

## 7. Remoção completa dos pontos de integração — opcional

O serviço vazio é suficiente para interromper a coleta. Se você também quiser retirar as referências do código, faça isso em desenvolvimento e publique o conjunto de alterações:

1. Em `bootstrap/app.php`, remova a entrada `\App\Http\Middleware\DeliverCommunityRegistration::class` da lista de middleware.
2. Em `app/Http/Controllers/Install/InstallController.php`, remova apenas o bloco `try/catch` que chama `CommunityRegistration::queue()` logo após `markInstalled()` e `clearCaches()`. Preserve essas duas chamadas e o tratamento externo de erros da instalação.
3. Em `routes/console.php`, remova o agendamento com nome `community-installation-status`. Preserve os outros agendamentos. Remova `app/Http/Middleware/DeliverCommunityRegistration.php` e `app/Services/CommunityRegistration.php` depois de eliminar todas as referências a essas classes.
4. Em `config/community.php`, remova `registration_url` e `heartbeat_url`. Preserve `version`.
5. Em `tests/Feature/Install/InstallerTest.php`, remova o mock de `CommunityRegistration` e suas expectativas `queue`/`deliver`. Ajuste os testes de distribuição conforme a implementação final, preservando os de instalação e de chaves.
6. Atualize a interface e a documentação como no passo 4. Se sua instalação usa cache de configuração/rotas, limpe-o no processo de publicação; em ambiente com terminal, use `php artisan optimize:clear`. Regenere o autoload com `composer dump-autoload --no-dev --optimize` no ambiente de preparação e publique as dependências atualizadas se necessário.

Para hospedagem sem terminal, prefira o procedimento do passo 2, que mantém a compatibilidade com as referências existentes.

## 8. Atualizações futuras

Guarde sua alteração em uma branch ou commit próprio. Uma atualização oficial que substitua o serviço pode reativar o registro; revise esse ponto antes de publicar cada atualização. Preserve a licença e os avisos de terceiros ao redistribuir sua edição.
