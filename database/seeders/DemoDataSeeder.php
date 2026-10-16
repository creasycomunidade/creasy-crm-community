<?php

namespace Database\Seeders;

use App\Modules\AI\Models\AiChatbot;
use App\Modules\AI\Models\AiKbChunk;
use App\Modules\AI\Models\AiKbDocument;
use App\Modules\AI\Models\AiKnowledgeBase;
use App\Modules\AI\Models\AiProviderConfig;
use App\Modules\Automation\Models\Automation;
use App\Modules\Whatsapp\Models\WhatsappAutoReply;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $workspaceId = 1;

        $this->seedAiProvider($workspaceId);
        $kb      = $this->seedKnowledgeBase($workspaceId);
        $chatbot = $this->seedChatbot($workspaceId, $kb->id);
        $this->seedAutoReplies($workspaceId);
        $this->seedAutomations($workspaceId, $chatbot->id);
    }

    /* ── AI Provider ─────────────────────────────────────────────── */

    private function seedAiProvider(int $workspaceId): void
    {
        AiProviderConfig::firstOrCreate(
            ['workspace_id' => $workspaceId, 'provider' => 'openai'],
            [
                'credentials'        => ['api_key' => env('OPENAI_API_KEY', 'sk-demo-placeholder')],
                'default_model_chat' => 'gpt-4o-mini',
                'default_model_embed' => 'text-embedding-3-small',
                'enabled'            => true,
            ]
        );
    }

    /* ── Knowledge Base ──────────────────────────────────────────── */

    private function seedKnowledgeBase(int $workspaceId): AiKnowledgeBase
    {
        $kb = AiKnowledgeBase::firstOrCreate(
            ['workspace_id' => $workspaceId, 'name' => 'Acme Support FAQ'],
            [
                'uuid'            => Str::uuid(),
                'embedding_model' => 'text-embedding-3-small',
                'dimensions'      => 1536,
                'status'          => 'active',
            ]
        );

        $documents = [
            [
                'title' => 'Company Overview',
                'content' => "Acme Corp is a leading provider of AI-powered business communication solutions. Founded in 2020, we help companies of all sizes automate customer support, streamline workflows, and grow their business.\n\nOur flagship product, WebWithAI, integrates seamlessly with WhatsApp, Instagram, and Messenger to give businesses a single inbox for all customer conversations. With built-in AI chatbots, automated workflows, and smart analytics, WebWithAI helps support teams respond faster and smarter.\n\nKey features include:\n- Unified Inbox: All channels in one place\n- AI Chatbots: Trained on your own knowledge base\n- Automation Builder: Visual workflow designer with no-code drag-and-drop\n- WhatsApp Templates: Send approved templates for transactional messages\n- Auto-Replies: Instant responses for common questions\n- Team Collaboration: Assign, snooze, and resolve conversations as a team\n- Analytics: Track response times, CSAT, and team performance\n\nOur customers range from small e-commerce stores to large enterprise call centers. We are headquartered in Dhaka, Bangladesh with team members across the globe.",
            ],
            [
                'title' => 'Pricing & Plans FAQ',
                'content' => "Q: What plans does Acme offer?\nA: We offer four plans: Free, Starter ($19/mo), Pro ($49/mo), and Business ($99/mo).\n\nQ: What is included in the Free plan?\nA: The Free plan includes 1 team member, 500MB storage, and 500 API calls per month. It's great for testing the platform.\n\nQ: What is in the Starter plan?\nA: Starter ($19/month or $190/year) includes 5 team members, 5GB storage, and 5,000 API calls. Ideal for small businesses.\n\nQ: What is in the Pro plan?\nA: Pro ($49/month or $490/year) includes 25 team members, 20GB storage, and 50,000 API calls. Perfect for growing teams.\n\nQ: What is in the Business plan?\nA: Business ($99/month or $990/year) includes 100 team members, 100GB storage, and 500,000 API calls. Built for large enterprises.\n\nQ: Is there a yearly discount?\nA: Yes! Paying yearly saves you 2 months compared to monthly billing.\n\nQ: Can I upgrade or downgrade my plan?\nA: Yes, you can change your plan at any time from the billing settings. Upgrades take effect immediately.\n\nQ: Do you offer a free trial?\nA: Yes, all paid plans come with a 14-day free trial. No credit card required.\n\nQ: What payment methods do you accept?\nA: We accept all major credit cards (Visa, Mastercard, Amex) and PayPal.\n\nQ: Can I cancel anytime?\nA: Yes, you can cancel your subscription at any time. Your account will remain active until the end of the billing period.",
            ],
            [
                'title' => 'Troubleshooting Guide',
                'content' => "Q: My WhatsApp messages are not being received. What should I check?\nA: First, verify your WABA (WhatsApp Business Account) is connected in Channel Setup. Check that your webhook URL is correctly configured in Meta Business Manager. Ensure your phone number is verified and the channel account status shows 'active'.\n\nQ: Why are my auto-replies not triggering?\nA: Make sure auto-reply rules are enabled. Check the trigger type and keywords match what customers are sending. Ensure the conversation has not been handed over to a human agent.\n\nQ: The AI chatbot is not responding. What do I do?\nA: Verify that an AI provider (OpenAI, Anthropic, or Gemini) is configured with a valid API key in AI Settings. Check that the chatbot is enabled and linked to the channel account in Channel Setup.\n\nQ: How do I add a new team member?\nA: Go to Settings > Team Members and click 'Invite'. Enter the email address and select the role. The user will receive an invitation email.\n\nQ: My automation is not running. Why?\nA: Check that the automation status is set to 'Active' (not Draft or Paused). Verify the trigger type matches the event you expect. Check the Automation Runs page for any error messages.\n\nQ: How do I connect Instagram?\nA: Go to Channel Setup, click 'Connect Instagram', and enter your Facebook Page ID and Page Access Token. Make sure your Instagram account is linked to a Facebook Page.\n\nQ: Can I import contacts?\nA: Yes, go to Contacts and click 'Bulk Import'. Upload a CSV file with columns: first_name, last_name, phone, email.\n\nQ: How do I create a WhatsApp template?\nA: Go to WhatsApp > Templates and click 'Create Template'. Templates must be approved by Meta before use. Approval typically takes 24-48 hours.\n\nQ: My messages show as 'queued' but never send.\nA: This usually means the channel driver is unable to reach the provider API. Check your API credentials and network connectivity. Check the application logs for specific error messages.\n\nQ: How do I reset my password?\nA: Click 'Forgot Password' on the login page and enter your email address. You will receive a password reset link within a few minutes.",
            ],
        ];

        foreach ($documents as $docData) {
            $doc = AiKbDocument::firstOrCreate(
                ['kb_id' => $kb->id, 'title' => $docData['title']],
                [
                    'source_type'    => 'text',
                    'source_ref'     => 'demo:' . Str::slug($docData['title']),
                    'status'         => 'indexed',
                    'tokens'         => str_word_count($docData['content']),
                    'last_indexed_at' => now(),
                ]
            );

            // Create chunks (split by paragraphs for demo, no real embeddings)
            if (AiKbChunk::where('document_id', $doc->id)->doesntExist()) {
                $paragraphs = array_filter(array_map('trim', explode("\n\n", $docData['content'])));
                foreach (array_values($paragraphs) as $ord => $para) {
                    AiKbChunk::create([
                        'document_id' => $doc->id,
                        'ord'         => $ord,
                        'content'     => $para,
                        'tokens'      => str_word_count($para),
                        'embedding'   => null,
                    ]);
                }
            }
        }

        return $kb;
    }

    /* ── AI Chatbot ──────────────────────────────────────────────── */

    private function seedChatbot(int $workspaceId, int $kbId): AiChatbot
    {
        return AiChatbot::firstOrCreate(
            ['workspace_id' => $workspaceId, 'name' => 'Acme Support Bot'],
            [
                'uuid'               => Str::uuid(),
                'ai_kb_id'           => $kbId,
                'system_prompt'      => "You are a helpful and friendly support agent for Acme Corp. Your role is to answer customer questions accurately using the provided knowledge base context. Be concise, professional, and empathetic. If you cannot find a relevant answer in the context, let the customer know politely and suggest they contact a human agent.",
                'tone'               => 'professional',
                'max_context_chunks' => 5,
                'fallback_reply'     => "I'm sorry, I don't have enough information to answer that. Let me connect you with a human agent who can help. Type 'human' at any time to be transferred.",
                'channels'           => ['whatsapp', 'instagram', 'playground'],
                'enabled'            => true,
            ]
        );
    }

    /* ── Auto-Replies ────────────────────────────────────────────── */

    private function seedAutoReplies(int $workspaceId): void
    {
        $rules = [
            [
                'trigger_type'  => 'welcome',
                'match_mode'    => 'contains',
                'keywords'      => [],
                'response_kind' => 'text',
                'payload_json'  => ['text' => "👋 Welcome to Acme Corp support! How can we help you today?\n\nYou can ask us about:\n• Pricing and plans\n• Technical support\n• Account management\n\nOr type *human* at any time to speak with a live agent."],
                'priority'      => 1,
                'enabled'       => true,
            ],
            [
                'trigger_type'  => 'keyword',
                'match_mode'    => 'exact',
                'keywords'      => ['pricing', 'price', 'plans', 'cost', 'how much'],
                'response_kind' => 'text',
                'payload_json'  => ['text' => "💰 *Acme Pricing Plans*\n\n• *Free* — $0/mo (1 member, 500 API calls)\n• *Starter* — $19/mo (5 members, 5,000 API calls)\n• *Pro* — $49/mo (25 members, 50,000 API calls)\n• *Business* — $99/mo (100 members, 500,000 API calls)\n\nAll paid plans include a 14-day free trial. Reply *TRIAL* to start yours!"],
                'priority'      => 2,
                'enabled'       => true,
            ],
            [
                'trigger_type'  => 'keyword',
                'match_mode'    => 'contains',
                'keywords'      => ['hours', 'working hours', 'office hours', 'open', 'available'],
                'response_kind' => 'text',
                'payload_json'  => ['text' => "🕐 *Our Support Hours*\n\nMonday – Friday: 9:00 AM – 6:00 PM EST\nSaturday: 10:00 AM – 2:00 PM EST\nSunday: Closed\n\nFor urgent issues outside business hours, our AI assistant is available 24/7. For critical production issues, email support@acme.com."],
                'priority'      => 3,
                'enabled'       => true,
            ],
            [
                'trigger_type'  => 'keyword',
                'match_mode'    => 'contains',
                'keywords'      => ['human', 'agent', 'real person', 'live support', 'speak to someone'],
                'response_kind' => 'text',
                'payload_json'  => ['text' => "👤 Connecting you to a human agent now...\n\nPlease hold for a moment. Our next available agent will be with you shortly.\n\nExpected wait time: 2-5 minutes during business hours."],
                'priority'      => 4,
                'enabled'       => true,
            ],
            [
                'trigger_type'  => 'out_of_hours',
                'match_mode'    => 'contains',
                'keywords'      => [],
                'schedule_json' => [
                    'days'     => [1, 2, 3, 4, 5],
                    'start'    => '09:00',
                    'end'      => '18:00',
                    'timezone' => 'America/New_York',
                ],
                'response_kind' => 'text',
                'payload_json'  => ['text' => "🌙 We're currently outside business hours.\n\nOur team is available Monday–Friday, 9 AM–6 PM EST.\n\nYour message has been received and we'll respond as soon as we're back. In the meantime, our AI assistant can help with common questions!"],
                'priority'      => 10,
                'enabled'       => true,
            ],
        ];

        foreach ($rules as $rule) {
            $existing = WhatsappAutoReply::where('workspace_id', $workspaceId)
                ->where('trigger_type', $rule['trigger_type'])
                ->where('priority', $rule['priority'])
                ->first();

            if (! $existing) {
                WhatsappAutoReply::create(array_merge($rule, ['workspace_id' => $workspaceId]));
            }
        }
    }

    /* ── Automations ─────────────────────────────────────────────── */

    private function seedAutomations(int $workspaceId, int $chatbotId): void
    {
        $automations = [
            $this->welcomeFlowData($workspaceId),
            $this->supportTriageData($workspaceId, $chatbotId),
            $this->leadNurtureData($workspaceId),
        ];

        foreach ($automations as $data) {
            Automation::firstOrCreate(
                ['workspace_id' => $workspaceId, 'name' => $data['name']],
                $data
            );
        }
    }

    private function welcomeFlowData(int $workspaceId): array
    {
        return [
            'workspace_id'   => $workspaceId,
            'uuid'           => Str::uuid(),
            'name'           => 'New Contact Welcome Flow',
            'status'         => 'active',
            'trigger_type'   => 'contact.created',
            'trigger_config' => [],
            'run_count'      => 0,
            'nodes' => [
                [
                    'id'       => 'trigger-1',
                    'type'     => 'trigger',
                    'position' => ['x' => 250, 'y' => 50],
                    'data'     => ['label' => 'Trigger', 'triggerType' => 'contact.created'],
                ],
                [
                    'id'       => 'wait-1',
                    'type'     => 'wait',
                    'position' => ['x' => 250, 'y' => 180],
                    'data'     => ['nodeType' => 'wait', 'label' => 'Wait / Delay', 'configured' => true, 'amount' => 1, 'unit' => 'hours'],
                ],
                [
                    'id'       => 'send_whatsapp-1',
                    'type'     => 'send_whatsapp',
                    'position' => ['x' => 250, 'y' => 310],
                    'data'     => [
                        'nodeType'   => 'send_whatsapp',
                        'label'      => 'Send WhatsApp',
                        'configured' => true,
                        'message'    => "👋 Hi {{contact.first_name}}!\n\nWelcome to Acme Corp. We're thrilled to have you on board!\n\nOur AI assistant is here to help 24/7. You can ask about pricing, features, or get technical support anytime.\n\nType *help* to see what we can do for you.",
                    ],
                ],
                [
                    'id'       => 'add_tag-1',
                    'type'     => 'add_tag',
                    'position' => ['x' => 250, 'y' => 440],
                    'data'     => ['nodeType' => 'add_tag', 'label' => 'Add Tag', 'configured' => true, 'tag' => 'welcome-sent'],
                ],
            ],
            'edges' => [
                ['id' => 'e1', 'source' => 'trigger-1',      'target' => 'wait-1'],
                ['id' => 'e2', 'source' => 'wait-1',         'target' => 'send_whatsapp-1'],
                ['id' => 'e3', 'source' => 'send_whatsapp-1','target' => 'add_tag-1'],
            ],
        ];
    }

    private function supportTriageData(int $workspaceId, int $chatbotId): array
    {
        return [
            'workspace_id'   => $workspaceId,
            'uuid'           => Str::uuid(),
            'name'           => 'AI Support Triage',
            'status'         => 'active',
            'trigger_type'   => 'message.received',
            'trigger_config' => ['keywords' => ['help', 'support', 'problem', 'issue', 'error', 'broken', 'not working']],
            'run_count'      => 0,
            'nodes' => [
                [
                    'id'       => 'trigger-1',
                    'type'     => 'trigger',
                    'position' => ['x' => 250, 'y' => 50],
                    'data'     => ['label' => 'Trigger', 'triggerType' => 'message.received'],
                ],
                [
                    'id'       => 'condition-1',
                    'type'     => 'condition',
                    'position' => ['x' => 250, 'y' => 180],
                    'data'     => [
                        'nodeType'   => 'condition',
                        'label'      => 'Condition (If/Else)',
                        'configured' => true,
                        'field'      => 'contact.tag',
                        'operator'   => 'contains',
                        'value'      => 'vip',
                    ],
                ],
                [
                    'id'       => 'send_whatsapp-vip',
                    'type'     => 'send_whatsapp',
                    'position' => ['x' => 50, 'y' => 340],
                    'data'     => [
                        'nodeType'   => 'send_whatsapp',
                        'label'      => 'Send WhatsApp',
                        'configured' => true,
                        'message'    => "⭐ Hi {{contact.name}}, as a VIP member you get priority support!\n\nA dedicated agent will contact you within 1 hour. Your reference number is #{{contact.id}}.",
                    ],
                ],
                [
                    'id'       => 'ai_reply-1',
                    'type'     => 'ai_reply',
                    'position' => ['x' => 450, 'y' => 340],
                    'data'     => [
                        'nodeType'   => 'ai_reply',
                        'label'      => 'AI Reply',
                        'configured' => true,
                        'chatbot_id' => $chatbotId,
                        'prompt'     => 'Answer the customer\'s support question: {{context.message_body}}',
                    ],
                ],
                [
                    'id'       => 'add_tag-1',
                    'type'     => 'add_tag',
                    'position' => ['x' => 450, 'y' => 470],
                    'data'     => ['nodeType' => 'add_tag', 'label' => 'Add Tag', 'configured' => true, 'tag' => 'ai-handled'],
                ],
            ],
            'edges' => [
                ['id' => 'e1', 'source' => 'trigger-1',     'target' => 'condition-1'],
                ['id' => 'e2', 'source' => 'condition-1',   'target' => 'send_whatsapp-vip', 'sourceHandle' => 'true'],
                ['id' => 'e3', 'source' => 'condition-1',   'target' => 'ai_reply-1',        'sourceHandle' => 'false'],
                ['id' => 'e4', 'source' => 'ai_reply-1',    'target' => 'add_tag-1'],
            ],
        ];
    }

    private function leadNurtureData(int $workspaceId): array
    {
        return [
            'workspace_id'   => $workspaceId,
            'uuid'           => Str::uuid(),
            'name'           => 'Lead Nurture Drip',
            'status'         => 'paused',
            'trigger_type'   => 'contact.created',
            'trigger_config' => [],
            'run_count'      => 0,
            'nodes' => [
                [
                    'id'       => 'trigger-1',
                    'type'     => 'trigger',
                    'position' => ['x' => 250, 'y' => 50],
                    'data'     => ['label' => 'Trigger', 'triggerType' => 'contact.created'],
                ],
                [
                    'id'       => 'wait-1',
                    'type'     => 'wait',
                    'position' => ['x' => 250, 'y' => 180],
                    'data'     => ['nodeType' => 'wait', 'label' => 'Wait / Delay', 'configured' => true, 'amount' => 1, 'unit' => 'days'],
                ],
                [
                    'id'       => 'send_whatsapp-1',
                    'type'     => 'send_whatsapp',
                    'position' => ['x' => 250, 'y' => 310],
                    'data'     => [
                        'nodeType'   => 'send_whatsapp',
                        'label'      => 'Send WhatsApp',
                        'configured' => true,
                        'message'    => "Hey {{contact.first_name}}! 👋\n\nDid you get a chance to explore Acme? Our AI-powered inbox can save your team hours every week.\n\nReply *DEMO* to schedule a free 30-minute walkthrough with our team!",
                    ],
                ],
                [
                    'id'       => 'wait-2',
                    'type'     => 'wait',
                    'position' => ['x' => 250, 'y' => 440],
                    'data'     => ['nodeType' => 'wait', 'label' => 'Wait / Delay', 'configured' => true, 'amount' => 3, 'unit' => 'days'],
                ],
                [
                    'id'       => 'send_whatsapp-2',
                    'type'     => 'send_whatsapp',
                    'position' => ['x' => 250, 'y' => 570],
                    'data'     => [
                        'nodeType'   => 'send_whatsapp',
                        'label'      => 'Send WhatsApp',
                        'configured' => true,
                        'message'    => "Hi {{contact.first_name}}, last chance! 🚀\n\nOur 14-day free trial includes everything — AI chatbots, automation workflows, and WhatsApp integration.\n\nStart free today: https://acme.com/signup\n\nNo credit card required.",
                    ],
                ],
                [
                    'id'       => 'add_tag-1',
                    'type'     => 'add_tag',
                    'position' => ['x' => 250, 'y' => 700],
                    'data'     => ['nodeType' => 'add_tag', 'label' => 'Add Tag', 'configured' => true, 'tag' => 'nurture-sequence-sent'],
                ],
            ],
            'edges' => [
                ['id' => 'e1', 'source' => 'trigger-1',      'target' => 'wait-1'],
                ['id' => 'e2', 'source' => 'wait-1',          'target' => 'send_whatsapp-1'],
                ['id' => 'e3', 'source' => 'send_whatsapp-1', 'target' => 'wait-2'],
                ['id' => 'e4', 'source' => 'wait-2',          'target' => 'send_whatsapp-2'],
                ['id' => 'e5', 'source' => 'send_whatsapp-2', 'target' => 'add_tag-1'],
            ],
        ];
    }
}
