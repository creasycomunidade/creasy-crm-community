import { Head, router, usePage } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { useState } from 'react';
import { CheckCircle, XCircle, Clock, ChevronRight, ToggleLeft, ToggleRight, FlaskConical, Star, BookOpen, ChevronDown, ChevronUp, MessageCircle, Zap } from 'lucide-react';
import axios from 'axios';
import { useTranslation } from 'react-i18next';
import { ptBrGuideText } from './setupGuidePtBr';

const SETUP_GUIDES = {
    evolution_go: {
        title: 'Gateway de WhatsApp não oficial',
        subtitle: 'WhatsApp por QR Code em instâncias isoladas por espaço de trabalho.',
        steps: [
            'Instale e ative o gateway em um servidor próprio com HTTPS, PostgreSQL e uma chave global forte.',
            'Para exibir mídias recebidas, configure WEBHOOK_FILES=true e armazenamento S3/MinIO no gateway.',
            'Informe a URL pública e a GLOBAL_API_KEY no painel do Super Admin, teste e ative a integração.',
            'Cada administrador de espaço poderá criar uma instância e ler o QR Code em Configuração de canais.',
            'A integração não oficial pode causar restrições ou bloqueio do número pelo WhatsApp.',
        ],
        link: 'https://github.com/evolution-foundation/evolution-go',
        linkLabel: 'Abrir documentação técnica do gateway',
    },
    storage_local: {
        title: 'Local Storage Setup',
        steps: [
            'No credentials needed — files are stored on the server disk.',
            'Ensure the storage/app/public directory is writable by the web server.',
            'Run: php artisan storage:link to create the public symlink.',
            'Set this as the default storage to activate it.',
        ],
    },
    storage_s3: {
        title: 'Amazon S3 Setup',
        steps: [
            'Sign in to the AWS Console at aws.amazon.com.',
            'Go to IAM → Users → Create user with programmatic access.',
            'Attach the AmazonS3FullAccess policy (or a scoped bucket policy).',
            'Copy the Access Key ID and Secret Access Key.',
            'Create an S3 bucket and note the bucket name and region.',
            'Paste the credentials and bucket details into the form.',
        ],
        link: 'https://s3.console.aws.amazon.com',
        linkLabel: 'Open AWS Console',
    },
    storage_do: {
        title: 'DigitalOcean Spaces Setup',
        steps: [
            'Log in to cloud.digitalocean.com.',
            'Navigate to Spaces → Create a Space, choose a region.',
            'Go to API → Spaces Access Keys → Generate New Key.',
            'Copy the Access Key and Secret.',
            'Enter the Space name, region endpoint, and credentials below.',
        ],
        link: 'https://cloud.digitalocean.com/spaces',
        linkLabel: 'Open DigitalOcean',
    },
    storage_wasabi: {
        title: 'Wasabi Cloud Storage Setup',
        steps: [
            'Sign up or log in at console.wasabisys.com.',
            'Create a new bucket and note its name and region.',
            'Go to Access Keys → Create New Access Key.',
            'Copy the Access Key ID and Secret Access Key.',
            'Enter the bucket name, region, and credentials in the form.',
        ],
        link: 'https://console.wasabisys.com',
        linkLabel: 'Open Wasabi Console',
    },
    meta_app: {
        title: 'Meta App — Complete Setup Guide',
        subtitle: 'Covers WhatsApp Business API · Instagram DMs · Messenger · Facebook Social Posting · Embedded Signup',
        sections: [
            {
                heading: 'Step 1 — Create & Configure Your Meta App',
                icon: 'create',
                steps: [
                    'Go to developers.facebook.com → "My Apps" → "Create App".',
                    'Select app type: "Business" (required for WhatsApp + Messenger).',
                    'Fill in app name, contact email, and attach your Business Manager account.',
                    'Under Settings → Basic, copy your App ID and App Secret into the fields below.',
                    'Set App Domain to your production domain (e.g. yourdomain.com).',
                    'Under Settings → Advanced, enable "Allow API access to App settings" for system user tokens.',
                ],
            },
            {
                heading: 'Step 2 — WhatsApp Business API',
                icon: 'whatsapp',
                note: 'Required for WhatsApp messaging and Embedded Signup (Connect WhatsApp button).',
                steps: [
                    'In your app dashboard, click "Add Product" → add WhatsApp.',
                    'Go to WhatsApp → Getting Started to see your test WABA and phone number.',
                    'In Meta Business Suite → System Users, create a System User with ADMIN role.',
                    'Click "Add Assets" → assign your WhatsApp Business Account to the system user.',
                    'Generate a Permanent Token for the system user with these permissions:',
                ],
                permissions: [
                    'whatsapp_business_management',
                    'whatsapp_business_messaging',
                    'business_management',
                ],
                steps2: [
                    'Paste the generated token into the "System User Access Token" field below.',
                    'For the Webhook: go to WhatsApp → Configuration, set Callback URL to: {APP_URL}/webhooks/whatsapp/{VERIFY_TOKEN} (shown in Channel Setup after adding a WABA).',
                ],
            },
            {
                heading: 'Step 3 — Embedded Signup (One-Click Connect)',
                icon: 'signup',
                note: 'Lets your clients connect WhatsApp / Instagram / Messenger with a single button click instead of manual token entry.',
                steps: [
                    'Add "Facebook Login for Business" product to your app.',
                    'Go to Facebook Login for Business → Configurations → Create Configuration.',
                    'WhatsApp config: set Permissions to whatsapp_business_management + whatsapp_business_messaging. Copy the config_id → paste into "Embedded Signup Config ID (WhatsApp)" below.',
                    'Social config (Instagram + Messenger): set the permissions listed below. Copy the config_id → paste into "Embedded Signup Config ID (Instagram / Messenger)" below.',
                ],
                permissions: [
                    'instagram_basic',
                    'instagram_manage_messages',
                    'pages_messaging',
                    'pages_manage_metadata',
                    'pages_read_engagement',
                    'pages_show_list',
                ],
                steps2: [
                    'Under your app Settings → Basic → App Domains, add your domain.',
                    'Under Facebook Login → Settings, add Valid OAuth Redirect URIs: {APP_URL}/painel/inbox/setup',
                ],
            },
            {
                heading: 'Step 4 — Instagram Business DMs',
                icon: 'instagram',
                note: 'Allows receiving and sending Instagram Direct Messages in the Inbox.',
                steps: [
                    'Add "Messenger" product to your app (Instagram DMs use the Messenger API).',
                    'Go to Messenger → Instagram Settings → enable "Connected Tools".',
                    'Set the Webhook callback URL to: {APP_URL}/webhooks/meta/{VERIFY_TOKEN} (shown in Channel Setup).',
                    'Subscribe to these webhook fields:',
                ],
                permissions: [
                    'instagram_basic',
                    'instagram_manage_messages',
                    'pages_manage_metadata',
                ],
                webhookFields: [
                    'messages',
                    'messaging_postbacks',
                    'messaging_optins',
                    'message_deliveries',
                    'message_reads',
                ],
            },
            {
                heading: 'Step 5 — Facebook Messenger DMs',
                icon: 'messenger',
                note: 'Allows receiving and sending Facebook Page messages in the Inbox.',
                steps: [
                    'Go to Messenger → Settings in your app dashboard.',
                    'Set Webhook callback URL to: {APP_URL}/webhooks/meta/{VERIFY_TOKEN}.',
                    'Subscribe the webhook to these fields:',
                ],
                permissions: [
                    'pages_messaging',
                    'pages_manage_metadata',
                    'pages_read_engagement',
                ],
                webhookFields: [
                    'messages',
                    'messaging_postbacks',
                    'messaging_optins',
                    'message_deliveries',
                    'message_reads',
                ],
                steps2: [
                    'Pages are subscribed automatically when a client connects via Embedded Signup.',
                ],
            },
            {
                heading: 'Step 6 — Facebook & Instagram Social Posting',
                icon: 'social',
                note: 'Required only if you use the Social Posting feature to publish posts to Facebook Pages or Instagram.',
                steps: [
                    'Add "Facebook Login" product to your app.',
                    'Go to Facebook Login → Settings and add Valid OAuth Redirect URI: {APP_URL}/auth/facebook/callback',
                    'Request the following permissions (some require App Review for Advanced Access):',
                ],
                permissions: [
                    'pages_manage_posts',
                    'pages_read_engagement',
                    'pages_show_list',
                    'instagram_basic',
                    'instagram_content_publish',
                    'public_profile',
                    'email',
                ],
                steps2: [
                    'Submit your app for App Review for any permissions marked as "Advanced Access" (pages_manage_posts, instagram_content_publish).',
                    'Add a Privacy Policy URL and Terms of Service URL under Settings → Basic before submitting for review.',
                ],
            },
            {
                heading: 'Step 7 — Go Live',
                icon: 'live',
                steps: [
                    'Switch your app from Development to Live mode (top toggle in Meta App Dashboard).',
                    'In App Review → Permissions, request Advanced Access for any restricted permissions.',
                    'Add Privacy Policy URL and Terms of Service URL under Settings → Basic.',
                    'Enable the integration using the toggle at the top of this admin page.',
                ],
            },
        ],
        links: [
            { href: 'https://developers.facebook.com/apps', label: 'Meta App Dashboard' },
            { href: 'https://business.facebook.com/settings/system-users', label: 'System Users' },
            { href: 'https://developers.facebook.com/docs/whatsapp/embedded-signup', label: 'Embedded Signup Docs' },
            { href: 'https://developers.facebook.com/docs/messenger-platform', label: 'Messenger Platform Docs' },
        ],
    },
    oauth_linkedin: {
        title: 'LinkedIn OAuth Setup',
        steps: [
            'Go to linkedin.com/developers and sign in.',
            'Click "Create App", fill in your company and app details.',
            'Under "Auth" tab, add your redirect URL: {your-domain}/auth/linkedin/callback',
            'Copy the Client ID and Client Secret.',
            'Request the r_liteprofile and r_emailaddress permissions.',
        ],
        link: 'https://www.linkedin.com/developers/apps',
        linkLabel: 'Open LinkedIn Developers',
    },
    oauth_twitter: {
        title: 'Twitter / X OAuth Setup',
        steps: [
            'Go to developer.twitter.com and apply for a developer account.',
            'Create a new Project and App.',
            'Under "User authentication settings", enable OAuth 2.0.',
            'Set the callback URL to: {your-domain}/auth/twitter/callback',
            'Copy the Client ID and Client Secret from the "Keys and tokens" tab.',
        ],
        link: 'https://developer.twitter.com/en/portal/apps/new',
        linkLabel: 'Open Twitter Developer Portal',
    },
    oauth_youtube: {
        title: 'YouTube / Google OAuth Setup',
        steps: [
            'Go to console.cloud.google.com and create or select a project.',
            'Navigate to APIs & Services → Credentials → Create Credentials → OAuth 2.0 Client ID.',
            'Set the authorized redirect URI to: {your-domain}/auth/google/callback',
            'Enable the YouTube Data API v3 in APIs & Services → Library.',
            'Copy the Client ID and Client Secret.',
        ],
        link: 'https://console.cloud.google.com/apis/credentials',
        linkLabel: 'Open Google Cloud Console',
    },
    oauth_tiktok: {
        title: 'TikTok OAuth Setup',
        steps: [
            'Go to developers.tiktok.com and sign in.',
            'Create an app under "Manage apps".',
            'Add the callback URL: {your-domain}/auth/tiktok/callback',
            'Request the user.info.basic scope.',
            'Copy the Client Key and Client Secret.',
        ],
        link: 'https://developers.tiktok.com',
        linkLabel: 'Open TikTok Developers',
    },
    llm_openai_default: {
        title: 'OpenAI API Setup',
        steps: [
            'Sign up or log in at platform.openai.com.',
            'Go to API Keys → Create new secret key.',
            'Copy the key immediately — it won\'t be shown again.',
            'Add billing details under Settings → Billing to enable API usage.',
            'Paste the key into the API Key field and select a default model.',
        ],
        link: 'https://platform.openai.com/api-keys',
        linkLabel: 'Open OpenAI Platform',
    },
    llm_anthropic_default: {
        title: 'Anthropic Claude API Setup',
        steps: [
            'Sign up or log in at console.anthropic.com.',
            'Go to Account Settings → API Keys → Create Key.',
            'Copy the key — it\'s shown only once.',
            'Add a credit card under Billing to activate the API.',
            'Paste the key and select claude-3-sonnet or another model.',
        ],
        link: 'https://console.anthropic.com/keys',
        linkLabel: 'Open Anthropic Console',
    },
    llm_gemini_default: {
        title: 'Google Gemini API Setup',
        steps: [
            'Go to aistudio.google.com and sign in with a Google account.',
            'Click "Get API key" → "Create API key in new project".',
            'Copy the generated API key.',
            'Optionally, enable billing in Google Cloud Console for higher quotas.',
            'Paste the key and select a Gemini model.',
        ],
        link: 'https://aistudio.google.com/app/apikey',
        linkLabel: 'Open Google AI Studio',
    },
    google_places: {
        title: 'Configuração da API Google Places',
        steps: [
            'Acesse console.cloud.google.com e crie ou selecione um projeto com faturamento ativo.',
            'Em APIs e serviços → Biblioteca, procure por “Places API” (legada) e clique em Ativar.',
            'Em APIs e serviços → Credenciais, crie uma Chave de API.',
            'Em Restrições do aplicativo, selecione “Endereços IP” e informe o IP público de saída do servidor. Não use a opção “Sites”.',
            'Em Restrições de API, selecione “Restringir chave” e deixe somente “Places API”.',
            'Salve, aguarde alguns minutos e teste a conexão novamente.',
        ],
        link: 'https://console.cloud.google.com/apis/library/places-backend.googleapis.com',
        linkLabel: 'Abrir Google Cloud Console',
    },
    firecrawl: {
        title: 'Configuração do Firecrawl',
        steps: [
            'Acesse firecrawl.dev, crie sua conta e abra a área de chaves da API.',
            'Crie ou copie uma chave com prefixo “fc-”.',
            'Informe a chave abaixo. A URL oficial da API é https://api.firecrawl.dev.',
            'Salve, ative a integração e execute o teste de conexão.',
            'Depois, no Extrator de leads, selecione Firecrawl como fonte da pesquisa.',
        ],
        notes: ['As pesquisas consomem créditos do plano Firecrawl. A chave fica criptografada e não é exposta aos usuários dos espaços.'],
        link: 'https://www.firecrawl.dev/app/api-keys',
        linkLabel: 'Abrir painel do Firecrawl',
    },
    qdrant: {
        title: 'Qdrant Vector Store Setup',
        steps: [
            'Option A — Qdrant Cloud: Sign up at cloud.qdrant.io, create a cluster, copy the cluster URL and API key.',
            'Option B — Self-hosted: Run with Docker: docker run -p 6333:6333 qdrant/qdrant',
            'For self-hosted, the URL is typically http://localhost:6333 and no API key is needed.',
            'Paste the URL and (if applicable) the API key into the form.',
        ],
        link: 'https://cloud.qdrant.io',
        linkLabel: 'Open Qdrant Cloud',
    },
};


function PermBadge({ p }) {
    return (
        <span className="inline-flex items-center rounded px-1.5 py-0.5 text-[10px] font-mono font-medium bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300 border border-blue-200 dark:border-blue-800">
            {p}
        </span>
    );
}

function SetupGuide({ provider }) {
    const { t } = useTranslation();
    const [open, setOpen] = useState(false);
    const guide = SETUP_GUIDES[provider];
    if (!guide) return null;
    const hasSections = Array.isArray(guide.sections);
    return (
        <div className="border-t border-neutral-100 dark:border-neutral-800 pt-2 mt-1">
            <button type="button" onClick={() => setOpen(v => !v)}
                className="flex items-center gap-1.5 text-xs text-brand-600 dark:text-brand-400 hover:text-brand-700 dark:hover:text-brand-300 font-medium transition w-full text-left">
                <BookOpen className="h-3.5 w-3.5 shrink-0" />
                {t('integrations.setup_guide')}
                {open ? <ChevronUp className="h-3 w-3 ml-auto" /> : <ChevronDown className="h-3 w-3 ml-auto" />}
            </button>
            {open && (
                <div className="mt-2 rounded-lg bg-blue-50 dark:bg-blue-900/20 border border-blue-100 dark:border-blue-900/40 px-3 py-2.5 space-y-3">
                    <div>
                        <p className="text-xs font-semibold text-blue-800 dark:text-blue-300">{ptBrGuideText(guide.title)}</p>
                        {guide.subtitle && <p className="text-[10px] text-blue-600 dark:text-blue-400 mt-0.5">{ptBrGuideText(guide.subtitle)}</p>}
                    </div>
                    {hasSections ? (
                        <div className="space-y-3">
                            {guide.sections.map((sec, si) => (
                                <div key={si} className="space-y-1.5">
                                    <p className="text-[10px] font-bold uppercase tracking-wider text-blue-700 dark:text-blue-400 border-b border-blue-200 dark:border-blue-800 pb-0.5">{ptBrGuideText(sec.heading)}</p>
                                    {sec.note && <p className="text-[10px] italic text-blue-600 dark:text-blue-400">{ptBrGuideText(sec.note)}</p>}
                                    {sec.steps && (
                                        <ol className="space-y-1">
                                            {sec.steps.map((s, i) => (
                                                <li key={i} className="flex gap-1.5 text-xs text-blue-700 dark:text-blue-400">
                                                    <span className="shrink-0 font-medium text-blue-500">{i + 1}.</span>
                                                    <span>{ptBrGuideText(s)}</span>
                                                </li>
                                            ))}
                                        </ol>
                                    )}
                                    {sec.permissions && (
                                        <div className="flex flex-wrap gap-1 pt-0.5">
                                            {sec.permissions.map(p => <PermBadge key={p} p={p} />)}
                                        </div>
                                    )}
                                    {sec.webhookFields && (
                                        <div className="flex flex-wrap gap-1 pt-0.5">
                                            {sec.webhookFields.map(f => (
                                                <span key={f} className="inline-flex items-center rounded px-1.5 py-0.5 text-[10px] font-mono font-medium bg-purple-100 dark:bg-purple-900/40 text-purple-700 dark:text-purple-300 border border-purple-200 dark:border-purple-800">{f}</span>
                                            ))}
                                        </div>
                                    )}
                                    {sec.steps2 && (
                                        <ol className="space-y-1">
                                            {sec.steps2.map((s, i) => (
                                                <li key={i} className="flex gap-1.5 text-xs text-blue-700 dark:text-blue-400">
                                                    <span className="shrink-0 font-medium text-blue-500">{(sec.steps ? sec.steps.length : 0) + i + 1}.</span>
                                                    <span>{ptBrGuideText(s)}</span>
                                                </li>
                                            ))}
                                        </ol>
                                    )}
                                </div>
                            ))}
                        </div>
                    ) : (
                        <ol className="space-y-1">
                            {guide.steps.map((step, i) => (
                                <li key={i} className="flex gap-2 text-xs text-blue-700 dark:text-blue-400">
                                    <span className="shrink-0 font-medium text-blue-500">{i + 1}.</span>
                                    <span>{ptBrGuideText(step)}</span>
                                </li>
                            ))}
                        </ol>
                    )}
                    {guide.links ? (
                        <div className="flex flex-wrap gap-3 pt-1 border-t border-blue-200 dark:border-blue-800">
                            {guide.links.map(l => (
                                <a key={l.href} href={l.href} target="_blank" rel="noopener noreferrer"
                                    className="text-[10px] font-medium text-blue-600 dark:text-blue-400 hover:underline">
                                    {ptBrGuideText(l.label)} →
                                </a>
                            ))}
                        </div>
                    ) : guide.link ? (
                        <a href={guide.link} target="_blank" rel="noopener noreferrer"
                            className="inline-flex items-center gap-1 text-xs font-medium text-blue-600 dark:text-blue-400 hover:underline mt-1">
                            {ptBrGuideText(guide.linkLabel)} →
                        </a>
                    ) : null}
                </div>
            )}
        </div>
    );
}

const STORAGE_PROVIDERS = ['storage_local', 'storage_s3', 'storage_do', 'storage_wasabi'];

const CATEGORY_LABEL_KEYS = {
    'Storage': 'integrations.cat_storage',
    'Meta': 'integrations.cat_meta',
    'AI / LLM': 'integrations.cat_ai_llm',
    'Social OAuth': 'integrations.cat_social_oauth',
    'Maps': 'integrations.cat_maps',
    'Vector Store': 'integrations.cat_vector_store',
};

const STATUS_ICONS = {
    ok:       <CheckCircle className="h-4 w-4 text-green-500" />,
    fail:     <XCircle className="h-4 w-4 text-red-500" />,
    untested: <Clock className="h-4 w-4 text-neutral-400" />,
};

const BRAND = {
    evolution_go: {
        bg: 'bg-emerald-100 dark:bg-emerald-900/30',
        color: '#16a34a',
        accentBorder: 'border-emerald-300 dark:border-emerald-700',
        accentBg: 'bg-emerald-50 dark:bg-emerald-900/20',
        icon: (
            <svg viewBox="0 0 20 20" fill="currentColor" className="h-5 w-5">
                <path d="M10 2a8 8 0 00-6.86 12.13L2 18l3.99-1.05A8 8 0 1010 2zm0 2a6 6 0 11-3.06 11.16l-.34-.2-1.79.47.48-1.72-.22-.36A6 6 0 0110 4zm-2.1 2.7c-.18 0-.47.07-.72.34-.25.27-.94.92-.94 2.25s.97 2.61 1.1 2.79c.14.18 1.9 2.9 4.61 4.07.64.28 1.15.45 1.54.57.65.2 1.24.17 1.7.1.52-.08 1.6-.66 1.83-1.29.23-.63.23-1.17.16-1.28-.07-.12-.25-.18-.53-.32l-1.86-.87c-.25-.09-.43-.14-.61.14-.18.27-.7.86-.86 1.04-.16.18-.32.2-.59.07-.28-.14-1.16-.43-2.21-1.36-.82-.73-1.37-1.63-1.53-1.9-.16-.28-.02-.43.12-.56.12-.12.28-.32.41-.48.14-.16.18-.27.28-.46.09-.18.04-.34-.03-.48l-.84-2.02c-.2-.49-.42-.5-.59-.5z" />
            </svg>
        ),
    },
    storage_local: {
        bg: 'bg-sky-100 dark:bg-sky-900/40',
        color: '#0ea5e9',
        accentBorder: 'border-sky-300 dark:border-sky-700',
        accentBg: 'bg-sky-50 dark:bg-sky-900/20',
        icon: (
            <svg viewBox="0 0 20 20" fill="currentColor" className="h-5 w-5">
                <path d="M2 7a2 2 0 012-2h12a2 2 0 012 2v1H2V7zm0 3h16v4a2 2 0 01-2 2H4a2 2 0 01-2-2v-4zm3 2a1 1 0 100 2 1 1 0 000-2zm3 0a1 1 0 100 2 1 1 0 000-2z" />
            </svg>
        ),
    },
    storage_s3:      { bg: null, color: '#FF9900', logo: 'amazons3' },
    storage_do:      { bg: null, color: '#0080FF', logo: 'digitalocean' },
    storage_wasabi:  { bg: null, color: '#3CBA54', logo: 'wasabi' },
    waha: { bg: 'bg-green-100 dark:bg-green-900/30', color: '#15803d', icon: <MessageCircle className="h-5 w-5" aria-hidden="true" /> },
    evolution_api: { bg: 'bg-orange-100 dark:bg-orange-900/30', color: '#c45100', icon: <Zap className="h-5 w-5" aria-hidden="true" /> },
    meta_app:        { bg: null, color: '#0866FF', logo: 'meta' },
    oauth_linkedin:  { bg: null, color: '#0A66C2', logo: 'linkedin' },
    oauth_twitter:   { bg: null, color: '#000000', logo: 'x' },
    oauth_youtube:   { bg: null, color: '#FF0000', logo: 'youtube' },
    oauth_tiktok:    { bg: null, color: '#000000', logo: 'tiktok' },
    llm_openai_default:     { bg: null, color: '#10a37f', logo: 'openai' },
    llm_anthropic_default:  { bg: null, color: '#d4793b', logo: 'anthropic' },
    llm_gemini_default:     { bg: null, color: '#4285F4', logo: 'googlegemini' },
    google_places:   { bg: null, color: '#34A853', logo: 'googlemaps' },
    firecrawl:       { bg: 'bg-orange-100 dark:bg-orange-900/30', color: '#F97316', icon: <svg viewBox="0 0 20 20" fill="currentColor" className="h-5 w-5"><path d="M10 2a8 8 0 100 16 8 8 0 000-16zm4.6 5H12.7a13.7 13.7 0 00-.8-2.7A6.1 6.1 0 0114.6 7zM10 4c.5.7 1 1.7 1.2 3H8.8C9 5.7 9.5 4.7 10 4zM4.7 12A6 6 0 014 10c0-.7.1-1.4.4-2h2.3a15 15 0 000 4h-2zm.7 1h1.9c.2 1 .5 1.9.8 2.7A6.1 6.1 0 015.4 13zm1.9-6H5.4a6.1 6.1 0 012.7-2.7A13.7 13.7 0 007.3 7zM10 16c-.5-.7-1-1.7-1.2-3h2.4c-.2 1.3-.7 2.3-1.2 3zm1.4-4H8.6a12.7 12.7 0 010-4h2.8a12.7 12.7 0 010 4zm.5 3.7c.3-.8.6-1.7.8-2.7h1.9a6.1 6.1 0 01-2.7 2.7zM13.3 12a15 15 0 000-4h2.3c.3.6.4 1.3.4 2s-.1 1.4-.4 2h-2.3z" /></svg> },
    qdrant:          { bg: null, color: '#DC143C', logo: 'qdrant' },
};

const DEFAULT_BRAND = {
    bg: 'bg-neutral-100 dark:bg-neutral-800',
    color: '#6b7280',
    icon: (
        <svg viewBox="0 0 20 20" fill="currentColor" className="h-5 w-5">
            <path fillRule="evenodd" d="M11.3 1.1a1 1 0 00-1.36.36L2.5 14a1 1 0 00.87 1.5h13.26a1 1 0 00.87-1.5L10.06 2.46a1 1 0 00-.76-.36zM10 6a1 1 0 011 1v3a1 1 0 11-2 0V7a1 1 0 011-1zm0 7a1 1 0 110-2 1 1 0 010 2z" clipRule="evenodd" />
        </svg>
    ),
};

function BrandBadge({ provider }) {
    const brand = BRAND[provider] ?? DEFAULT_BRAND;
    if (brand.logo) {
        return (
            <div
                className="flex items-center justify-center w-9 h-9 rounded-xl shrink-0"
                style={{ backgroundColor: brand.color }}
            >
                <img
                    src={`/images/integrations/${brand.logo}.svg`}
                    alt={provider}
                    className="h-5 w-5"
                    style={{ filter: 'brightness(0) invert(1)' }}
                />
            </div>
        );
    }
    return (
        <div
            className={`flex items-center justify-center w-9 h-9 rounded-xl ${brand.bg} shrink-0`}
            style={{ color: brand.color }}
        >
            {brand.icon}
        </div>
    );
}

function ProviderCard({ item, onTest, onSetDefault, testing, settingDefault }) {
    const { t } = useTranslation();
    const isStorage = STORAGE_PROVIDERS.includes(item.provider);
    const brand = BRAND[item.provider] ?? DEFAULT_BRAND;
    const isDefault = isStorage && item.is_default;

    return (
        <div className={`rounded-xl border p-5 flex flex-col gap-3 transition ${
            isDefault
                ? `${brand.accentBorder ?? 'border-sky-300 dark:border-sky-700'} ${brand.accentBg ?? 'bg-sky-50 dark:bg-sky-900/20'}`
                : 'border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900 shadow-sm hover:shadow-md'
        }`}>
            <div className="flex items-start justify-between gap-2">
                <div className="flex items-center gap-2.5">
                    <BrandBadge provider={item.provider} />
                    <div className="flex items-center gap-1.5 flex-wrap">
                        <span className="font-semibold text-sm text-neutral-900 dark:text-neutral-100 leading-tight">
                            {item.label}
                        </span>
                        {isDefault && (
                            <span className="text-xs px-1.5 py-0.5 rounded bg-sky-500 text-white font-medium flex items-center gap-0.5">
                                <Star className="h-3 w-3 fill-white" /> {t('integrations.default_badge')}
                            </span>
                        )}
                        {isStorage && item.enabled && !isDefault && (
                            <span className="text-xs px-1.5 py-0.5 rounded bg-neutral-200 dark:bg-neutral-700 text-neutral-600 dark:text-neutral-300 font-medium">{t('integrations.active_badge')}</span>
                        )}
                    </div>
                </div>
                <span className={`shrink-0 text-xs px-2 py-0.5 rounded-full font-medium ${item.configured ? 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300' : 'bg-neutral-100 text-neutral-500 dark:bg-neutral-800 dark:text-neutral-400'}`}>
                    {item.configured ? t('integrations.configured') : t('integrations.not_set')}
                </span>
            </div>

            <div className="flex items-center gap-2 text-xs text-neutral-500 dark:text-neutral-400">
                {STATUS_ICONS[item.last_test_status]}
                <span>{item.last_test_message || t('integrations.not_tested')}</span>
            </div>

            <div className="flex items-center gap-2 text-xs">
                {item.enabled
                    ? <span className="flex items-center gap-1 text-green-600 dark:text-green-400"><ToggleRight className="h-4 w-4" /> {t('integrations.enabled')}</span>
                    : <span className="flex items-center gap-1 text-neutral-400"><ToggleLeft className="h-4 w-4" /> {t('integrations.disabled')}</span>
                }
                <span className="text-neutral-300 dark:text-neutral-600">·</span>
                <span className="text-neutral-400">{item.mode === 'test' ? t('integrations.test_mode') : t('integrations.live_mode')}</span>
            </div>

            <SetupGuide provider={item.provider} />

            <div className="flex gap-2 mt-auto pt-2 border-t border-neutral-100 dark:border-neutral-800 flex-wrap">
                <a
                    href={route('admin.integrations.edit', item.provider)}
                    className="flex-1 text-center rounded-lg border border-neutral-200 dark:border-neutral-700 px-3 py-1.5 text-xs font-medium text-neutral-700 dark:text-neutral-300 hover:bg-neutral-50 dark:hover:bg-neutral-800 transition flex items-center justify-center gap-1"
                >
                    {t('integrations.configure')} <ChevronRight className="h-3 w-3" />
                </a>
                {item.configured && (
                    <button
                        type="button"
                        disabled={testing === item.provider}
                        onClick={() => onTest(item.provider)}
                        className="flex items-center gap-1 rounded-lg border border-neutral-200 dark:border-neutral-700 px-3 py-1.5 text-xs font-medium text-neutral-700 dark:text-neutral-300 hover:bg-neutral-50 dark:hover:bg-neutral-800 transition disabled:opacity-50"
                    >
                        <FlaskConical className="h-3 w-3" />
                        {testing === item.provider ? t('integrations.testing') : t('integrations.test')}
                    </button>
                )}
                {isStorage && item.enabled && !isDefault && (
                    <button
                        type="button"
                        disabled={settingDefault === item.provider}
                        onClick={() => onSetDefault(item.provider)}
                        className="flex items-center gap-1 rounded-lg border border-sky-300 dark:border-sky-700 px-3 py-1.5 text-xs font-medium text-sky-700 dark:text-sky-300 hover:bg-sky-50 dark:hover:bg-sky-900/20 transition disabled:opacity-50"
                    >
                        <Star className="h-3 w-3" />
                        {settingDefault === item.provider ? t('integrations.setting') : t('integrations.set_default')}
                    </button>
                )}
            </div>
        </div>
    );
}

export default function IntegrationsIndex({ grouped }) {
    const { t } = useTranslation();
    const { props } = usePage();
    const flash = props.flash ?? {};
    const [testing, setTesting] = useState(null);
    const [testResults, setTestResults] = useState({});
    const [settingDefault, setSettingDefault] = useState(null);

    const handleTest = async (provider) => {
        setTesting(provider);
        try {
            const { data } = await axios.post(route('admin.integrations.test', provider));
            setTestResults(r => ({ ...r, [provider]: data }));
            router.reload({ only: ['grouped'] });
        } catch (e) {
            setTestResults(r => ({ ...r, [provider]: { ok: false, message: e?.response?.data?.message || t('integrations.request_failed') } }));
        } finally {
            setTesting(null);
        }
    };

    const handleSetDefault = (provider) => {
        setSettingDefault(provider);
        router.post(route('admin.integrations.set-default', provider), {}, {
            onFinish: () => setSettingDefault(null),
        });
    };

    const categoryOrder = ['Storage', 'Meta', 'AI / LLM', 'Social OAuth', 'Maps', 'Vector Store'];
    const sortedCategories = Object.keys(grouped).sort((a, b) => {
        const ai = categoryOrder.indexOf(a);
        const bi = categoryOrder.indexOf(b);
        return (ai === -1 ? 99 : ai) - (bi === -1 ? 99 : bi);
    });

    return (
        <AdminLayout title={t('integrations.title')}>
            <Head title={`${t('integrations.title')} · Admin`} />
            <div className="space-y-8">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h2 className="text-xl font-semibold text-neutral-900 dark:text-neutral-100">{t('integrations.title')}</h2>
                        <p className="mt-1 text-sm text-neutral-500 dark:text-neutral-400">
                            {t('integrations.subtitle')}
                        </p>
                    </div>
                    <a
                        href={route('admin.integrations.audit-log')}
                        className="text-sm font-medium text-brand-600 hover:text-brand-700 dark:text-brand-400"
                    >
                        {t('integrations.audit_log_link')}
                    </a>
                </div>

                {flash.success && (
                    <div className="rounded-lg bg-green-50 dark:bg-green-900/30 text-green-800 dark:text-green-200 px-4 py-2 text-sm">
                        {flash.success}
                    </div>
                )}
                {flash.error && (
                    <div className="rounded-lg bg-red-50 dark:bg-red-900/30 text-red-800 dark:text-red-200 px-4 py-2 text-sm">
                        {flash.error}
                    </div>
                )}

                {sortedCategories.map(category => (
                    <div key={category}>
                        <h3 className="mb-3 text-xs font-semibold uppercase tracking-wider text-neutral-400 dark:text-neutral-500">
                            {CATEGORY_LABEL_KEYS[category] ? t(CATEGORY_LABEL_KEYS[category]) : category}
                        </h3>
                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            {grouped[category].map(item => (
                                <ProviderCard
                                    key={item.provider}
                                    item={testResults[item.provider] ? { ...item, ...testResults[item.provider] } : item}
                                    onTest={handleTest}
                                    onSetDefault={handleSetDefault}
                                    testing={testing}
                                    settingDefault={settingDefault}
                                />
                            ))}
                        </div>
                    </div>
                ))}
            </div>
        </AdminLayout>
    );
}
