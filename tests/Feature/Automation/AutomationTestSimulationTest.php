<?php

namespace Tests\Feature\Automation;

use App\Modules\Automation\Models\Automation;
use App\Modules\Automation\Services\AutomationEngine;
use App\Modules\Automation\Services\WorkflowGenerator;
use App\Modules\Shared\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers the builder's "Test" simulation (AutomationEngine::testRun) and the AI
 * "Generate" pipeline's validation/normalisation (WorkflowGenerator::normalise).
 * Neither touches the network; testRun must also never persist or send anything.
 */
class AutomationTestSimulationTest extends TestCase
{
    use RefreshDatabase;

    private function engine(): AutomationEngine
    {
        return app(AutomationEngine::class);
    }

    private function normalise(array $spec): array
    {
        $gen = app(WorkflowGenerator::class);
        $ref = new \ReflectionMethod($gen, 'normalise');
        $ref->setAccessible(true);

        return $ref->invoke($gen, $spec);
    }

    public function test_test_run_simulates_branch_without_sending(): void
    {
        $auto = new Automation(['name' => 'Sim', 'trigger_type' => 'contact.created']);
        $auto->workspace_id = 1;

        $nodes = [
            ['id' => 'trigger-1', 'type' => 'trigger', 'data' => ['triggerType' => 'contact.created']],
            ['id' => 'n1', 'type' => 'send_whatsapp', 'data' => ['nodeType' => 'send_whatsapp', 'body' => 'Hi {{contact.first_name}} ({{contact.email}})!']],
            ['id' => 'n2', 'type' => 'condition', 'data' => ['nodeType' => 'condition', 'field' => 'contact.email', 'operator' => 'exists']],
            ['id' => 'yes', 'type' => 'add_tag', 'data' => ['nodeType' => 'add_tag', 'tag' => 'has-email']],
            ['id' => 'no', 'type' => 'send_email', 'data' => ['nodeType' => 'send_email', 'subject' => 'Hello']],
        ];
        $edges = [
            ['source' => 'trigger-1', 'target' => 'n1'],
            ['source' => 'n1', 'target' => 'n2'],
            ['source' => 'n2', 'target' => 'yes', 'sourceHandle' => 'true'],
            ['source' => 'n2', 'target' => 'no', 'sourceHandle' => 'false'],
        ];

        $res = $this->engine()->testRun($auto, $nodes, $edges);

        $this->assertTrue($res['ok']);
        // trigger → whatsapp → condition (email exists ⇒ true) → add_tag
        $this->assertCount(3, $res['steps']);
        $this->assertSame('send_whatsapp', $res['steps'][0]['node_type']);
        $this->assertStringContainsString('Test', $res['steps'][0]['message']); // {{contact.first_name}} rendered
        $this->assertStringContainsString('test.contact@example.com', $res['steps'][0]['message']); // {{contact.email}} rendered
        $this->assertSame('condition', $res['steps'][1]['node_type']);
        $this->assertSame('true', $res['steps'][1]['branch']);
        $this->assertSame('add_tag', $res['steps'][2]['node_type']);

        // A dry run never writes outbound messages.
        $this->assertSame(0, Message::count());
    }

    public function test_test_run_reports_missing_connection(): void
    {
        $auto = new Automation(['name' => 'Empty', 'trigger_type' => 'contact.created']);
        $auto->workspace_id = 1;

        $res = $this->engine()->testRun($auto, [
            ['id' => 'trigger-1', 'type' => 'trigger', 'data' => ['triggerType' => 'contact.created']],
        ], []);

        $this->assertFalse($res['ok']);
        $this->assertArrayHasKey('error', $res);
    }

    public function test_generator_drops_invalid_nodes_and_edges(): void
    {
        $graph = $this->normalise([
            'name' => 'Welcome flow',
            'trigger_type' => 'contact.created',
            'trigger_config' => [],
            'nodes' => [
                ['id' => 'a', 'type' => 'send_whatsapp', 'data' => ['body' => 'Welcome {{contact.first_name}}']],
                ['id' => 'b', 'type' => 'wait', 'data' => ['amount' => 1, 'unit' => 'days']],
                ['id' => 'c', 'type' => 'bogus_type', 'data' => []],   // unknown → dropped
                ['id' => 'd', 'type' => 'add_tag', 'data' => ['tag' => 'onboarded']],
            ],
            'edges' => [
                ['source' => 'trigger-1', 'target' => 'a'],
                ['source' => 'a', 'target' => 'b'],
                ['source' => 'b', 'target' => 'd'],
                ['source' => 'd', 'target' => 'ghost'],   // dangling → dropped
            ],
        ]);

        // trigger + 3 valid action nodes (bogus dropped)
        $this->assertCount(4, $graph['nodes']);
        $this->assertSame('trigger', $graph['nodes'][0]['type']);
        $this->assertSame('contact.created', $graph['nodes'][0]['data']['triggerType']);
        $this->assertCount(3, $graph['edges']); // ghost edge removed

        foreach (array_slice($graph['nodes'], 1) as $n) {
            $this->assertTrue($n['data']['configured']);
            $this->assertArrayHasKey('x', $n['position']);
            $this->assertArrayHasKey('y', $n['position']);
        }
    }

    public function test_generator_defaults_invalid_trigger_and_builds_chain(): void
    {
        $graph = $this->normalise([
            'trigger_type' => 'not.a.real.trigger',
            'nodes' => [
                ['type' => 'send_whatsapp', 'data' => ['body' => 'Hi']],
                ['type' => 'add_tag', 'data' => ['tag' => 'x']],
            ],
            'edges' => [],
        ]);

        $this->assertSame('contact.created', $graph['trigger_type']); // invalid → safe default
        $this->assertCount(2, $graph['edges']);                       // linear chain synthesised
        $this->assertSame('trigger-1', $graph['edges'][0]['source']);
        $this->assertSame('AI Automation', $graph['name']);            // missing name → default
    }
}
