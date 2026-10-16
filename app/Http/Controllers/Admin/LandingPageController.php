<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LandingPageController extends Controller
{
    /**
     * Single source of truth for every editable marketing-site setting.
     * Public pages, the admin "Site Content" manager, and the LandingPageSeeder
     * all derive their keys/defaults from here so the three never drift.
     */
    public static function defaults(): array
    {
        return [
            // Master switch — when '0', public marketing pages redirect to login
            'landing.page_enabled' => '1',

            // ── Navbar ──────────────────────────────────────────────
            'landing.signin_label'         => 'Sign In',
            'landing.signin_link_type'     => 'dynamic',
            'landing.signin_link_url'      => '',
            'landing.getstarted_label'     => 'Start Free Trial',
            'landing.getstarted_link_type' => 'dynamic',
            'landing.getstarted_link_url'  => '',

            // ── SEO ─────────────────────────────────────────────────
            'landing.seo_title'       => 'CREASY CRM — One Inbox for WhatsApp, Messenger & Instagram',
            'landing.seo_description' => 'CREASY CRM unifies WhatsApp, Messenger and Instagram in one inbox with AI chatbots, no-code automation, bulk broadcasting and a built-in CRM. Start free — no credit card required.',
            'landing.seo_keywords'    => 'WhatsApp Business API, team inbox, WhatsApp marketing, AI chatbot, Messenger, Instagram DM, bulk messaging, marketing automation, CRM',
            'landing.seo_og_image'    => '',

            // ── Hero ────────────────────────────────────────────────
            'landing.hero_enabled'       => '1',
            'landing.hero_badge'         => 'Now with AI chatbots & multi-channel automation',
            'landing.hero_title'         => 'Every Customer Conversation, One Smart Inbox',
            'landing.hero_subtitle'      => 'Unify WhatsApp, Messenger and Instagram, automate replies with AI chatbots, run bulk broadcasts, and turn conversations into revenue — all from one platform.',
            'landing.hero_cta_primary'   => 'Start Free Trial',
            'landing.hero_cta_secondary' => 'View Pricing',
            'landing.hero_trust_1'       => 'No credit card required',
            'landing.hero_trust_2'       => '14-day free trial',
            'landing.hero_trust_3'       => 'Official Meta Business APIs',

            // ── Metrics (numbers band) ──────────────────────────────
            'landing.metrics_enabled' => '0',
            'landing.metric_1_value'  => '50M+',
            'landing.metric_1_label'  => 'Messages delivered',
            'landing.metric_2_value'  => '12,000+',
            'landing.metric_2_label'  => 'Businesses',
            'landing.metric_3_value'  => '99.9%',
            'landing.metric_3_label'  => 'Uptime SLA',
            'landing.metric_4_value'  => '5x',
            'landing.metric_4_label'  => 'Higher open rates',

            // ── Trusted By (brand logos) ────────────────────────────
            'landing.stats_enabled'  => '0',
            'landing.stats_heading'  => 'Trusted by 12,000+ businesses worldwide',
            'landing.stats_1_label'  => 'Acme',
            'landing.stats_2_label'  => 'TechStart',
            'landing.stats_3_label'  => 'GrowthLab',
            'landing.stats_4_label'  => 'Marketly',
            'landing.stats_5_label'  => 'SalesHQ',
            'landing.stats_6_label'  => 'ReachMore',

            // ── Channels showcase ───────────────────────────────────
            'landing.channels_enabled'  => '1',
            'landing.channels_badge'    => 'Omnichannel',
            'landing.channels_title'    => 'Meet customers where they already are',
            'landing.channels_subtitle' => 'Connect every messaging channel to a single shared inbox — no more switching tabs.',
            'landing.channel_1_key'   => 'whatsapp',
            'landing.channel_1_title' => 'WhatsApp Business',
            'landing.channel_1_desc'  => 'Official WhatsApp Cloud API. Send templates, broadcasts and 24/7 automated replies.',
            'landing.channel_2_key'   => 'messenger',
            'landing.channel_2_title' => 'Facebook Messenger',
            'landing.channel_2_desc'  => 'Reply to Page messages, comments and story mentions from the same inbox.',
            'landing.channel_3_key'   => 'instagram',
            'landing.channel_3_title' => 'Instagram DMs',
            'landing.channel_3_desc'  => 'Manage Instagram direct messages, comments and mentions in real time.',
            'landing.channel_4_key'   => 'sms',
            'landing.channel_4_title' => 'SMS Campaigns',
            'landing.channel_4_desc'  => 'Reach customers instantly with high-deliverability SMS broadcasts and alerts.',
            'landing.channel_5_key'   => 'email',
            'landing.channel_5_title' => 'Email',
            'landing.channel_5_desc'  => 'Send transactional and marketing email from the same contact timeline.',

            // ── Problem / Solution ──────────────────────────────────
            'landing.problems_enabled' => '1',
            'landing.problems_title'   => 'Sound familiar?',
            'landing.problem_1'        => 'Conversations scattered across WhatsApp, Messenger, Instagram and email',
            'landing.problem_2'        => 'Leads go cold while messages sit unanswered for hours',
            'landing.problem_3'        => 'No way to broadcast offers or automate follow-ups at scale',
            'landing.problem_4'        => 'Zero visibility into team performance or what is actually converting',
            'landing.solution_title'   => 'CREASY CRM fixes all of it',
            'landing.solution_desc'    => 'One platform to capture, automate and close every conversation.',
            'landing.solution_1'       => 'Unified inbox for every channel, shared across your team',
            'landing.solution_2'       => 'AI chatbots and automations that reply 24/7',
            'landing.solution_3'       => 'Bulk broadcasts with smart scheduling and segmentation',
            'landing.solution_4'       => 'Real-time analytics on delivery, response time and revenue',

            // ── Features ────────────────────────────────────────────
            'landing.features_enabled'   => '1',
            'landing.features_badge'     => 'Features',
            'landing.features_title'     => 'Everything you need to win on every channel',
            'landing.features_subtitle'  => 'From a unified inbox to AI chatbots and broadcasts — one platform for the whole customer journey.',
            'landing.feature_1_icon'     => 'message-square',
            'landing.feature_1_title'    => 'Unified Team Inbox',
            'landing.feature_1_desc'     => 'Every WhatsApp, Messenger and Instagram conversation in one shared inbox with assignments, notes and canned replies.',
            'landing.feature_2_icon'     => 'cpu',
            'landing.feature_2_title'    => 'AI Chatbots',
            'landing.feature_2_desc'     => 'Train chatbots on your own docs and FAQs with RAG, and let AI answer instantly in any language.',
            'landing.feature_3_icon'     => 'zap',
            'landing.feature_3_title'    => 'No-Code Automation',
            'landing.feature_3_desc'     => 'Build visual workflows that route, tag and reply to messages automatically — no developer needed.',
            'landing.feature_4_icon'     => 'share-2',
            'landing.feature_4_title'    => 'Bulk Broadcasting',
            'landing.feature_4_desc'     => 'Send personalized WhatsApp and SMS campaigns to thousands of contacts with smart throttling.',
            'landing.feature_5_icon'     => 'users',
            'landing.feature_5_title'    => 'Contact CRM',
            'landing.feature_5_desc'     => 'Centralize every contact with full conversation history, custom fields, tags and segments.',
            'landing.feature_6_icon'     => 'trending-up',
            'landing.feature_6_title'    => 'Lead Generation',
            'landing.feature_6_desc'     => 'Capture and qualify leads automatically, score them, and never let a hot lead slip away.',
            'landing.feature_7_icon'     => 'layout',
            'landing.feature_7_title'    => 'E-commerce Sync',
            'landing.feature_7_desc'     => 'Connect your store to sync orders, send cart reminders and confirm deliveries over chat.',
            'landing.feature_8_icon'     => 'globe',
            'landing.feature_8_title'    => 'Social Scheduling',
            'landing.feature_8_desc'     => 'Plan and publish posts across your social accounts from one content calendar.',
            'landing.feature_9_icon'     => 'bar-chart-2',
            'landing.feature_9_title'    => 'Analytics & Reports',
            'landing.feature_9_desc'     => 'Track delivery, open and response rates, agent performance and campaign ROI in real time.',

            // ── How it works ────────────────────────────────────────
            'landing.howitworks_enabled'  => '1',
            'landing.howitworks_badge'    => 'Get started',
            'landing.howitworks_title'    => 'Live in minutes, not weeks',
            'landing.howitworks_subtitle' => 'No code, no complex setup — connect a channel and go.',
            'landing.step_1_title'        => 'Connect Your Channels',
            'landing.step_1_desc'         => 'Link WhatsApp, Messenger and Instagram via official Meta APIs in a few clicks.',
            'landing.step_2_title'        => 'Import & Organize Contacts',
            'landing.step_2_desc'         => 'Upload a CSV or sync your CRM — we deduplicate and segment automatically.',
            'landing.step_3_title'        => 'Automate & Grow',
            'landing.step_3_desc'         => 'Launch broadcasts, set up AI chatbots, and watch conversations turn into customers.',

            // ── Integrations strip ──────────────────────────────────
            'landing.integrations_strip_enabled'  => '1',
            'landing.integrations_strip_title'    => 'Works with the tools you already use',
            'landing.integrations_strip_subtitle' => 'Connect CREASY CRM to 100+ apps via native integrations, webhooks and our REST API.',

            // ── Why us ──────────────────────────────────────────────
            'landing.why_enabled'   => '1',
            'landing.why_badge'     => 'Why CREASY CRM',
            'landing.why_title'     => 'Built for teams who need results',
            'landing.why_subtitle'  => 'Powerful enough for enterprises, simple enough for everyone.',
            'landing.why_1_icon'    => 'shield-check',
            'landing.why_1_title'   => 'Official & Compliant',
            'landing.why_1_desc'    => 'Built on official Meta Business APIs — no bans, no grey-area hacks.',
            'landing.why_2_icon'    => 'zap',
            'landing.why_2_title'   => 'Lightning Fast',
            'landing.why_2_desc'    => 'Messages delivered in seconds on enterprise-grade infrastructure.',
            'landing.why_3_icon'    => 'trending-up',
            'landing.why_3_title'   => 'Higher Engagement',
            'landing.why_3_desc'    => 'Chat gets up to 5x the open rate of email — meet customers where they reply.',
            'landing.why_4_icon'    => 'globe',
            'landing.why_4_title'   => 'Multi-Language',
            'landing.why_4_desc'    => 'Serve customers in any language with built-in localization and AI translation.',
            'landing.why_5_icon'    => 'users',
            'landing.why_5_title'   => 'Team Collaboration',
            'landing.why_5_desc'    => 'Assign chats, leave internal notes, and never send a double reply.',
            'landing.why_6_icon'    => 'server',
            'landing.why_6_title'   => '99.9% Uptime',
            'landing.why_6_desc'    => 'Reliable, secure and ready to scale with your business.',

            // ── Security & Compliance ───────────────────────────────
            'landing.security_enabled'  => '1',
            'landing.security_badge'    => 'Security & Compliance',
            'landing.security_title'    => 'Enterprise-grade security by default',
            'landing.security_subtitle' => 'Your data and your customers\' trust are protected at every layer.',
            'landing.security_1_icon'  => 'shield-check',
            'landing.security_1_title' => 'End-to-End Encryption',
            'landing.security_1_desc'  => 'Data encrypted in transit and at rest with industry-standard protocols.',
            'landing.security_2_icon'  => 'check-circle',
            'landing.security_2_title' => 'GDPR Compliant',
            'landing.security_2_desc'  => 'Data-processing controls, consent tracking and the right to be forgotten.',
            'landing.security_3_icon'  => 'users',
            'landing.security_3_title' => 'Role-Based Access',
            'landing.security_3_desc'  => 'Granular permissions, 2FA and audit logs keep your workspace secure.',
            'landing.security_4_icon'  => 'server',
            'landing.security_4_title' => 'Reliable Infrastructure',
            'landing.security_4_desc'  => 'Redundant, monitored systems with 99.9% uptime and automated backups.',

            // ── Testimonials ────────────────────────────────────────
            'landing.testimonials_enabled'   => '0',
            'landing.testimonials_badge'     => 'Testimonials',
            'landing.testimonials_title'     => 'Loved by modern teams',
            'landing.testimonials_subtitle'  => 'See what businesses are saying about CREASY CRM.',
            'landing.testimonial_1_name'     => 'Sarah Johnson',
            'landing.testimonial_1_role'     => 'Marketing Manager, Acme Corp',
            'landing.testimonial_1_text'     => 'We tripled our lead response rate in the first week. The AI chatbot handles the repetitive questions so my team can focus on closing.',
            'landing.testimonial_1_avatar'   => '',
            'landing.testimonial_2_name'     => 'James Lee',
            'landing.testimonial_2_role'     => 'Founder, TechStart',
            'landing.testimonial_2_text'     => 'Setup took 10 minutes. By the end of the day we were running our first WhatsApp broadcast to 8,000 contacts.',
            'landing.testimonial_2_avatar'   => '',
            'landing.testimonial_3_name'     => 'Maria Santos',
            'landing.testimonial_3_role'     => 'Head of Growth, GrowthLab',
            'landing.testimonial_3_text'     => 'The unified inbox changed how our team handles support across WhatsApp and Instagram. Night and day difference.',
            'landing.testimonial_3_avatar'   => '',
            'landing.testimonial_4_name'     => 'David Okafor',
            'landing.testimonial_4_role'     => 'Operations Lead, ReachMore',
            'landing.testimonial_4_text'     => 'Automations save us 30+ hours a week. It is like adding three team members who never sleep.',
            'landing.testimonial_4_avatar'   => '',
            'landing.testimonial_5_name'     => 'Aisha Rahman',
            'landing.testimonial_5_role'     => 'E-commerce Owner, Marketly',
            'landing.testimonial_5_text'     => 'Cart reminders over WhatsApp recovered 22% of abandoned checkouts. It paid for itself in a week.',
            'landing.testimonial_5_avatar'   => '',
            'landing.testimonial_6_name'     => 'Tom Becker',
            'landing.testimonial_6_role'     => 'Customer Success, SalesHQ',
            'landing.testimonial_6_text'     => 'Finally one place for every conversation. Our average first response time dropped from hours to minutes.',
            'landing.testimonial_6_avatar'   => '',

            // ── FAQ ─────────────────────────────────────────────────
            'landing.faq_enabled'   => '1',
            'landing.faq_badge'     => 'FAQ',
            'landing.faq_title'     => 'Frequently Asked Questions',
            'landing.faq_subtitle'  => 'Everything you need to know about CREASY CRM.',
            'landing.faq_1_q'       => 'Which channels does CREASY CRM support?',
            'landing.faq_1_a'       => 'Connect WhatsApp Business, Facebook Messenger and Instagram DMs into one inbox, plus SMS and email broadcasting — all from a single dashboard.',
            'landing.faq_2_q'       => 'Do I need a WhatsApp Business API account?',
            'landing.faq_2_a'       => 'Yes — CREASY CRM connects through the official Meta WhatsApp Cloud API. Our guided setup walks you through it in minutes.',
            'landing.faq_3_q'       => 'Can I try CREASY CRM before paying?',
            'landing.faq_3_a'       => 'Absolutely. Every plan includes a 14-day free trial with no credit card required.',
            'landing.faq_4_q'       => 'Do the AI chatbots understand my business?',
            'landing.faq_4_a'       => 'Yes. Train chatbots on your own documents, FAQs and product catalog using RAG so answers are accurate and on-brand.',
            'landing.faq_5_q'       => 'Is my data secure and compliant?',
            'landing.faq_5_a'       => 'We use end-to-end encryption, role-based access and are fully GDPR compliant. We never sell or share your data.',

            // ── CTA ─────────────────────────────────────────────────
            'landing.cta_enabled'   => '1',
            'landing.cta_title'     => 'Ready to win every conversation?',
            'landing.cta_subtitle'  => 'Join 12,000+ businesses growing faster with CREASY CRM. Start free — no credit card required.',
            'landing.cta_primary'   => 'Start Free Trial',
            'landing.cta_secondary' => 'Talk to Sales',

            // ── About page ──────────────────────────────────────────
            'landing.about_badge'       => 'About CREASY CRM',
            'landing.about_title'       => 'We are on a mission to make business conversations effortless',
            'landing.about_subtitle'    => 'CREASY CRM helps thousands of businesses turn everyday messages into lasting customer relationships.',
            'landing.about_story_title' => 'Our story',
            'landing.about_story_body'  => "CREASY CRM started with a simple frustration: customer conversations were scattered across too many apps, and great leads were slipping through the cracks.\n\nWe set out to build one platform where every WhatsApp, Messenger and Instagram conversation lives together — supercharged with AI and automation. Today, teams in over 60 countries use CREASY CRM to reply faster, sell more and build relationships that last.",
            'landing.about_value_1_icon'  => 'zap',
            'landing.about_value_1_title' => 'Move Fast',
            'landing.about_value_1_desc'  => 'We ship quickly and obsess over making complex things feel simple.',
            'landing.about_value_2_icon'  => 'users',
            'landing.about_value_2_title' => 'Customer First',
            'landing.about_value_2_desc'  => 'Every decision starts with the people who use our product every day.',
            'landing.about_value_3_icon'  => 'shield-check',
            'landing.about_value_3_title' => 'Trust & Privacy',
            'landing.about_value_3_desc'  => 'We protect customer data like it is our own — because it matters.',
            'landing.about_value_4_icon'  => 'globe',
            'landing.about_value_4_title' => 'Built for Everyone',
            'landing.about_value_4_desc'  => 'Accessible, multi-language and designed for teams of every size.',
            'landing.about_stat_1_value' => '12,000+',
            'landing.about_stat_1_label' => 'Businesses served',
            'landing.about_stat_2_value' => '60+',
            'landing.about_stat_2_label' => 'Countries',
            'landing.about_stat_3_value' => '50M+',
            'landing.about_stat_3_label' => 'Messages delivered',
            'landing.about_stat_4_value' => '99.9%',
            'landing.about_stat_4_label' => 'Uptime',
            'landing.about_cta_title'    => 'Want to join our journey?',
            'landing.about_cta_subtitle' => 'Start free today or get in touch — we would love to hear from you.',

            // ── Integrations page ───────────────────────────────────
            'landing.integrations_page_badge'    => 'Integrations',
            'landing.integrations_page_title'    => 'Connect CREASY CRM to your entire stack',
            'landing.integrations_page_subtitle' => 'Native integrations, webhooks and a full REST API — bring CREASY CRM into the tools your team already loves.',
            'landing.intcat_1_title' => 'Messaging Channels',
            'landing.intcat_1_items' => "WhatsApp Business\nFacebook Messenger\nInstagram Direct\nSMS\nEmail",
            'landing.intcat_2_title' => 'AI Providers',
            'landing.intcat_2_items' => "OpenAI\nAnthropic Claude\nGoogle Gemini\nQdrant",
            'landing.intcat_3_title' => 'E-commerce',
            'landing.intcat_3_items' => "Shopify\nWooCommerce\nBigCommerce",
            'landing.intcat_4_title' => 'Payments & Billing',
            'landing.intcat_4_items' => "Stripe\nPayPal\nPaddle",
            'landing.intcat_5_title' => 'CRM & Automation',
            'landing.intcat_5_items' => "Google Sheets\nWebhooks",
            'landing.intcat_6_title' => 'Developer Tools',
            'landing.intcat_6_items' => "REST API\nWebhooks\nOAuth 2.0\nFirebase",
            'landing.intcat_7_title' => 'Social Media',
            'landing.intcat_7_items' => "Facebook\nInstagram\nLinkedIn\nX (Twitter)\nYouTube\nTikTok",
        ];
    }

    public function index(): Response
    {
        $settings = [];
        foreach (self::defaults() as $key => $default) {
            $settings[$key] = SystemSetting::get($key, $default);
        }

        return Inertia::render('Admin/LandingPage/Index', [
            'settings' => $settings,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'settings'   => ['required', 'array'],
            'settings.*' => ['nullable', 'string', 'max:5000'],
        ]);

        foreach ($data['settings'] as $key => $value) {
            if (str_starts_with($key, 'landing.')) {
                SystemSetting::set($key, $value ?? '', false, 'landing');
            }
        }

        return back()->with('success', 'Site content saved.');
    }

    /**
     * Resolve every public marketing setting (DB value, falling back to defaults).
     * Keys are derived from defaults() so they can never drift.
     */
    public static function getPublicSettings(): array
    {
        $defaults = self::defaults();
        $localizedDefaults = app()->getLocale() === 'pt'
            ? config('landing_pt_br', [])
            : [];
        $result   = [];
        foreach ($defaults as $key => $default) {
            // The master toggle is read separately by LandingController; skip it here.
            if ($key === 'landing.page_enabled') {
                continue;
            }
            $value = SystemSetting::get($key, $default);

            // Translate untouched factory copy while preserving anything the
            // administrator customized in the Site Content panel.
            if (array_key_exists($key, $localizedDefaults) && $value === $default) {
                $value = $localizedDefaults[$key];
            }

            $result[$key] = $value;
        }

        return $result;
    }
}
