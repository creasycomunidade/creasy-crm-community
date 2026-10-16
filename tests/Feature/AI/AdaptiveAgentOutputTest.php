<?php

namespace Tests\Feature\AI;

use App\Modules\AI\Services\AgentRunner;
use App\Modules\AI\Services\Llm\GeminiProvider;
use App\Modules\AI\Services\Llm\LlmResponse;
use Illuminate\Support\Facades\Http;
use ReflectionMethod;
use Tests\TestCase;

class AdaptiveAgentOutputTest extends TestCase
{
    public function test_agent_uses_adaptive_output_budgets(): void
    {
        $method = new ReflectionMethod(AgentRunner::class, 'outputTokenBudget');
        $method->setAccessible(true);
        $runner = app(AgentRunner::class);

        $this->assertSame(16384, $method->invoke($runner, 'Crie uma landing page HTML completa', collect()));
        $this->assertSame(8192, $method->invoke($runner, 'Prepare um relatório detalhado', collect()));
        $this->assertSame(2048, $method->invoke($runner, 'Qual é o status?', collect()));
        $this->assertSame(16384, $method->invoke(
            $runner,
            'Pode continuar',
            collect([['input' => 'Crie o arquivo pagina.html']]),
        ));
    }

    public function test_agent_continues_a_response_stopped_by_the_token_limit(): void
    {
        $method = new ReflectionMethod(AgentRunner::class, 'completeTruncatedResponse');
        $method->setAccessible(true);
        $runner = app(AgentRunner::class);
        $responses = [
            new LlmResponse('```respondesk-file:html'."\n".'<html><body>Olá', 100, 16, 'gemini-test', 50, 'MAX_TOKENS'),
            new LlmResponse('</body></html>'."\n".'```', 140, 8, 'gemini-test', 40, 'STOP'),
        ];
        $calls = 0;
        $request = function (array $messages) use (&$responses, &$calls): LlmResponse {
            $calls++;
            return array_shift($responses);
        };

        $result = $method->invoke($runner, $request, [['role' => 'user', 'content' => 'Crie o HTML']]);

        $this->assertSame(2, $calls);
        $this->assertSame(240, $result->promptTokens);
        $this->assertSame(24, $result->completionTokens);
        $this->assertSame('STOP', $result->finishReason);
        $this->assertStringContainsString('<html><body>Olá</body></html>', $result->content);
    }

    public function test_gemini_reports_truncation_and_receives_the_requested_budget(): void
    {
        Http::fake(['*' => Http::response([
            'candidates' => [[
                'content' => ['parts' => [['text' => '<html>']]],
                'finishReason' => 'MAX_TOKENS',
            ]],
            'usageMetadata' => ['promptTokenCount' => 10, 'candidatesTokenCount' => 16384],
        ])]);

        $response = (new GeminiProvider('test-key', 'gemini-test'))->chat(
            [['role' => 'user', 'content' => 'Crie uma página']],
            ['max_tokens' => 16384],
        );

        $this->assertTrue($response->wasTruncated());
        $this->assertSame('MAX_TOKENS', $response->finishReason);
        Http::assertSent(fn ($request) => $request['generationConfig']['maxOutputTokens'] === 16384);
    }
}