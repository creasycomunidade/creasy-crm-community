<?php

namespace App\Modules\Integrations\Services;

use App\Modules\FazOS\Services\FazOSClient;
use App\Modules\Integrations\Models\WorkspaceIntegration;
use App\Support\PublicHttpUrl;
use Illuminate\Support\Facades\Http;

class WorkspaceConnectionTester
{
    public function test(WorkspaceIntegration $connection): array
    {
        try {
            return match ($connection->provider) {
                'stripe', 'todoist', 'discord', 'telegram', 'fathom' => $this->catalogConnection($connection),
                'asaas' => $this->asaas($connection->credentials),
                'bling' => $this->bling($connection->credentials),
                'omie' => $this->omie($connection->credentials),
                'wordpress' => $this->wordpress($connection->credentials),
                'fazos' => $this->fazos($connection),
                default => ['ok' => false, 'message' => 'Provedor ainda não possui teste de conexão.'],
            };
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    private function catalogConnection(WorkspaceIntegration $connection): array
    {
        $token = $connection->credentials['api_key'] ?? '';
        try {
            $request = Http::timeout(15)->connectTimeout(5)->withoutRedirecting()->acceptJson();
            $response = match ($connection->provider) {
                'fathom' => $request->withHeaders(['X-Api-Key'=>$token])->get('https://api.fathom.ai/external/v1/meetings'),
                'stripe' => $request->withToken($token)->get('https://api.stripe.com/v1/account'),
                'todoist' => $request->withToken($token)->get('https://api.todoist.com/api/v1/tasks', ['limit'=>1]),
                'discord' => $request->withToken($token, 'Bot')->get('https://discord.com/api/v10/users/@me'),
                'telegram' => $request->get('https://api.telegram.org/bot'.rawurlencode($token).'/getMe'),
            };
            $ok = $response->successful() && ($connection->provider !== 'telegram' || $response->json('ok') === true);
            return ['ok'=>$ok, 'message'=>$ok?'Credenciais verificadas com o provedor.':'O provedor recusou a conexão. Confira a chave e suas permissões.'];
        } catch (\Throwable) {
            return ['ok'=>false, 'message'=>'Não foi possível contatar o provedor. Tente novamente.'];
        }
    }

    private function asaas(array $credentials): array
    {
        $base = ($credentials['environment'] ?? 'production') === 'sandbox'
            ? 'https://api-sandbox.asaas.com/v3' : 'https://api.asaas.com/v3';
        $response = Http::timeout(20)->acceptJson()
            ->withHeaders(['access_token' => $credentials['api_key'] ?? '', 'User-Agent' => 'CREASY CRM'])
            ->get($base.'/myAccount');

        return $this->result($response->successful(), $response->json('name') ? 'Conectado à conta '.$response->json('name').'.' : null, $response->json('errors.0.description'));
    }

    private function bling(array $credentials): array
    {
        $response = Http::timeout(20)->withToken($credentials['access_token'] ?? '')
            ->acceptJson()->get('https://www.bling.com.br/Api/v3/contatos', ['limite' => 1]);

        return $this->result($response->successful(), 'Conectado ao Bling ERP.', $response->json('error.description'));
    }

    private function omie(array $credentials): array
    {
        $response = Http::timeout(20)->post('https://app.omie.com.br/api/v1/geral/clientes/', [
            'call' => 'ListarClientes',
            'app_key' => $credentials['app_key'] ?? '',
            'app_secret' => $credentials['app_secret'] ?? '',
            'param' => [['pagina' => 1, 'registros_por_pagina' => 1, 'apenas_importado_api' => 'N']],
        ]);

        return $this->result($response->successful() && ! $response->json('faultstring'), 'Conectado ao Omie ERP.', $response->json('faultstring'));
    }

    private function wordpress(array $credentials): array
    {
        $base = rtrim((string) ($credentials['url'] ?? ''), '/');
        $response = Http::timeout(20)
            ->withOptions(PublicHttpUrl::requestOptions($base.'/wp-json/wp/v2/users/me'))
            ->withBasicAuth($credentials['username'] ?? '', $credentials['application_password'] ?? '')
            ->acceptJson()->get($base.'/wp-json/wp/v2/users/me', ['context' => 'edit']);

        return $this->result($response->successful(), 'Conectado ao WordPress como '.$response->json('name').'.', $response->json('message'));
    }

    private function result(bool $ok, ?string $success, ?string $error): array
    {
        return ['ok' => $ok, 'message' => $ok ? ($success ?: 'Conexão validada.') : ($error ?: 'O provedor recusou a conexão.')];
    }

    private function fazos(WorkspaceIntegration $connection): array
    {
        $response = (new FazOSClient($connection))->health();
        $company = data_get($response, 'data.tenant.name') ?: data_get($response, 'data.tenant.host');

        return ['ok' => true, 'message' => $company ? 'Conectado ao FazOS de '.$company.'.' : 'Conectado ao FazOS.'];
    }
}
