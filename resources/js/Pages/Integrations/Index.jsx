import { Head, Link, router, useForm } from '@inertiajs/react';
import ClientLayout from '@/Layouts/ClientLayout';
import { BarChart3, CheckCircle2, HardDrive, MessageCircle, Plug, RefreshCw, Send, ShieldCheck, ShoppingBag, Trash2 } from 'lucide-react';

const providers = {
    fathom: {name:'Fathom',description:'Conecte a API de reuniões do Fathom.',fields:[['api_key','Chave de API','password']]},
    stripe: {name:'Stripe',description:'Conexão com a conta Stripe e verificação das credenciais.',fields:[['api_key','Chave secreta ou restrita','password']]},
    todoist: {name:'Todoist',description:'Conexão com a API de tarefas do Todoist.',fields:[['api_key','Token de API','password']]},
    discord: {name:'Discord',description:'Verifique o acesso do bot à API do Discord.',fields:[['api_key','Token do bot','password']]},
    telegram: {name:'Telegram Bot',description:'Conecte e verifique seu bot. Para atendimento, configure também o canal Telegram.',fields:[['api_key','Token do BotFather','password']]},

    asaas: {
        name: 'Asaas',
        description: 'Cobranças, clientes, assinaturas e conciliação.',
        fields: [['api_key', 'Chave de API', 'password'], ['environment', 'Ambiente', 'environment']],
    },
    bling: {
        name: 'Bling ERP',
        description: 'Pedidos, produtos, estoque e dados do ERP.',
        fields: [['access_token', 'Token OAuth de acesso', 'password']],
    },
    omie: {
        name: 'Omie ERP',
        description: 'Clientes, finanças, produtos e documentos do ERP.',
        fields: [['app_key', 'App Key', 'password'], ['app_secret', 'App Secret', 'password']],
    },
    wordpress: {
        name: 'WordPress',
        description: 'Gerencie posts e páginas pela API REST oficial.',
        fields: [
            ['url', 'URL do site', 'url'],
            ['username', 'Usuário do WordPress', 'text'],
            ['application_password', 'Senha de aplicativo da REST API', 'password'],
        ],
    },
    fazos: {
        name: 'FazOS',
        description: 'Clientes, equipamentos e ordens de serviço vinculados às conversas.',
        fields: [
            ['url', 'URL da empresa no FazOS', 'url'],
            ['token', 'Token nativo da Integração CREASY CRM no FazOS', 'password'],
        ],
    },
};

function ConnectionRow({ item }) {
    return <div className="rounded-lg border p-3 text-sm">
        <div className="flex items-start justify-between gap-3">
            <div>
                <div className="flex items-center gap-2"><b>{item.name}</b>{item.status === 'connected' && <CheckCircle2 className="h-4 w-4 text-green-600" />}</div>
                <div className="mt-1 text-xs text-gray-500">{item.status === 'connected' ? 'Conectado' : item.status === 'error' ? 'Erro no teste' : 'Aguardando teste'}</div>
            </div>
            <button aria-label="Remover integração" className="p-1 text-red-600" onClick={() => confirm('Remover esta integração?') && router.delete(route('client.integrations.destroy', item.uuid), { preserveScroll: true })}><Trash2 className="h-4 w-4" /></button>
        </div>
        {item.last_error && <div className="mt-2 text-xs text-red-600">{item.last_error}</div>}
        <div className="mt-3 flex gap-2">
            {item.provider === 'wordpress' && item.status === 'connected' && <Link className="rounded border px-3 py-1" href={route('client.integrations.wordpress.index', item.uuid)}>Gerenciar</Link>}
            {item.provider === 'fazos' && item.status === 'connected' && <button className="rounded border px-3 py-1" onClick={() => router.post(route('client.integrations.fazos.sync', item.uuid), {}, { preserveScroll: true })}>Sincronizar agora</button>}
            <button className="rounded border px-3 py-1" onClick={() => router.post(route('client.integrations.test', item.uuid), {}, { preserveScroll: true })}>Testar conexão</button>
        </div>
        {item.provider === 'fazos' && item.settings?.last_sync_at && <div className="mt-2 text-xs text-gray-500">Última sincronização: {new Date(item.settings.last_sync_at).toLocaleString('pt-BR')}</div>}
    </div>;
}

function IntegrationCard({ providerKey, connections }) {
    const definition = providers[providerKey];
    const form = useForm({
        provider: providerKey,
        name: definition.name,
        credentials: providerKey === 'asaas' ? { environment: 'production' } : {},
    });
    const setCredential = (key, value) => form.setData('credentials', { ...form.data.credentials, [key]: value });
    const submit = event => {
        event.preventDefault();
        form.post(route('client.integrations.store'), {
            preserveScroll: true,
            onSuccess: () => form.setData({
                provider: providerKey,
                name: definition.name,
                credentials: providerKey === 'asaas' ? { environment: 'production' } : {},
            }),
        });
    };

    return <section className="flex h-full flex-col rounded-xl border bg-white p-5">
        <div className="mb-4">
            <div className="flex items-center gap-2"><Plug className="h-4 w-4 text-violet-600" /><h2 className="font-semibold">{definition.name}</h2></div>
            <p className="mt-1 text-sm text-gray-500">{definition.description}</p>
        </div>
        <form onSubmit={submit} className="grid gap-3">
            <input className="rounded-lg border p-2" placeholder="Nome para identificar a conexão" value={form.data.name} onChange={event => form.setData('name', event.target.value)} required />
            {definition.fields.map(([key, label, type]) => type === 'environment'
                ? <label key={key} className="text-xs text-gray-500">{label}<select className="mt-1 w-full rounded-lg border p-2 text-sm" value={form.data.credentials[key] || 'production'} onChange={event => setCredential(key, event.target.value)}><option value="production">Produção</option><option value="sandbox">Sandbox</option></select></label>
                : <label key={key} className="text-xs text-gray-500">{label}<input type={type} className="mt-1 w-full rounded-lg border p-2 text-sm" value={form.data.credentials[key] || ''} onChange={event => setCredential(key, event.target.value)} required /></label>)}
            {providerKey === 'wordpress' && <div className="rounded-lg bg-blue-50 p-3 text-xs text-blue-800">Use uma <b>Senha de aplicativo</b> criada em Usuários → Perfil no WordPress. A senha normal da conta não é solicitada nem deve ser informada.</div>}
            {providerKey === 'fazos' && <div className="rounded-lg bg-emerald-50 p-3 text-xs leading-5 text-emerald-900"><b>API própria e isolada:</b> no FazOS dessa empresa, acesse Configuração → Integração CREASY CRM, gere um token e informe-o aqui. Não depende de módulos ou serviços de terceiros. O token fica criptografado e nunca é enviado ao navegador novamente.</div>}
            <button className="rounded-lg bg-violet-600 p-2 text-white disabled:opacity-60" disabled={form.processing}>{form.processing ? 'Salvando…' : `Conectar ${definition.name}`}</button>
        </form>
        {connections.length > 0 && <div className="mt-5 space-y-2 border-t pt-4">{connections.map(item => <ConnectionRow key={item.uuid} item={item} />)}</div>}
    </section>;
}

function HubCard({ title, description, href, connected = 0, icon: Icon = Plug }) {
    return <Link href={href} className="rounded-xl border bg-white p-4 transition hover:border-violet-300 hover:shadow-sm">
        <div className="flex items-start justify-between gap-3">
            <span className="rounded-lg bg-violet-50 p-2 text-violet-600"><Icon className="h-4 w-4" /></span>
            <span className={`rounded-full px-2 py-1 text-[10px] font-medium ${connected > 0 ? 'bg-green-50 text-green-700' : 'bg-gray-100 text-gray-500'}`}>
                {connected > 0 ? `${connected} conectado${connected > 1 ? 's' : ''}` : 'Não configurado'}
            </span>
        </div>
        <b className="mt-3 block">{title}</b>
        <p className="mt-1 text-sm text-gray-500">{description}</p>
    </Link>;
}

const zernioPlatforms = [
    ['whatsapp', 'WhatsApp'], ['instagram', 'Instagram'], ['facebook', 'Facebook'],
    ['telegram', 'Telegram'], ['linkedin', 'LinkedIn'], ['twitter', 'X / Twitter'],
    ['tiktok', 'TikTok'], ['youtube', 'YouTube'], ['threads', 'Threads'],
    ['reddit', 'Reddit'], ['pinterest', 'Pinterest'], ['bluesky', 'Bluesky'],
    ['googlebusiness', 'Google Business'], ['discord', 'Discord'], ['slack', 'Slack'],
];

const zernioAdPlatforms = [
    ['facebook', 'Meta Ads'], ['googleads', 'Google Ads'], ['tiktok', 'TikTok Ads'],
    ['linkedin', 'LinkedIn Ads'], ['pinterest', 'Pinterest Ads'], ['twitter', 'X Ads'],
];

function ZernioCard({ zernio }) {
    const connectionError = zernio.last_error?.replace(/zernio/gi, 'serviço de conexão');
    const capabilities = account => Object.entries(account.capabilities || {}).filter(([, enabled]) => enabled).map(([name]) => ({
        messages: 'Conversas', publishing: 'Postagens', ads: 'Anúncios', analytics: 'Métricas',
    }[name] || name));

    return <section className="rounded-xl border border-violet-200 bg-white p-5 shadow-sm">
        <div className="flex flex-col justify-between gap-4 lg:flex-row lg:items-start">
            <div className="max-w-3xl">
                <div className="flex items-center gap-2">
                    <span className="rounded-lg bg-violet-100 p-2 text-violet-700"><ShieldCheck className="h-5 w-5" /></span>
                    <div><h2 className="font-semibold">Conexão central multiempresa</h2><p className="text-xs text-gray-500">Uma conexão do CREASY CRM, com perfil e contas isolados para esta empresa.</p></div>
                </div>
                <p className="mt-3 text-sm leading-6 text-gray-600">Conecte canais de conversa, publicação social, métricas e anúncios sem entregar a chave mestra ao usuário. Toda consulta é limitada ao perfil exclusivo desta empresa.</p>
            </div>
            {!zernio.available ? <div className="rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-800">O administrador precisa configurar e ativar a conexão central.</div>
                : !zernio.connected ? <button onClick={() => router.post(route('client.integrations.zernio.provision'), {}, { preserveScroll: true })} className="rounded-lg bg-violet-600 px-4 py-2 text-sm font-medium text-white">Preparar integração</button>
                    : <button onClick={() => router.post(route('client.integrations.zernio.sync'), {}, { preserveScroll: true })} className="inline-flex items-center justify-center gap-2 rounded-lg border px-4 py-2 text-sm font-medium hover:bg-gray-50"><RefreshCw className="h-4 w-4" /> Sincronizar contas</button>}
        </div>

        {zernio.connected && <>
            <div className="mt-5 grid gap-3 sm:grid-cols-3">
                <div className="rounded-lg bg-violet-50 p-3"><div className="text-xs text-violet-700">Perfil da empresa</div><div className="mt-1 font-medium">{zernio.profile_name}</div></div>
                <div className="rounded-lg bg-gray-50 p-3"><div className="text-xs text-gray-500">Contas vinculadas</div><div className="mt-1 font-medium">{zernio.accounts?.length || 0}</div></div>
                <div className="rounded-lg bg-gray-50 p-3"><div className="text-xs text-gray-500">Última sincronização</div><div className="mt-1 font-medium">{zernio.last_synced_at ? new Date(zernio.last_synced_at).toLocaleString('pt-BR') : 'Ainda não realizada'}</div></div>
            </div>
            {connectionError && <div className="mt-3 rounded-lg bg-red-50 p-3 text-sm text-red-700">{connectionError}</div>}

            <div className="mt-5 grid gap-5 xl:grid-cols-2">
                <div>
                    <div className="mb-2 flex items-center gap-2 text-sm font-semibold"><MessageCircle className="h-4 w-4 text-violet-600" /> Canais e postagens</div>
                    <div className="flex flex-wrap gap-2">{zernioPlatforms.map(([key, label]) => <a key={key} href={route('client.integrations.zernio.connect', key)} className="rounded-full border px-3 py-1.5 text-xs hover:border-violet-400 hover:text-violet-700">+ {label}</a>)}</div>
                </div>
                <div>
                    <div className="mb-2 flex items-center gap-2 text-sm font-semibold"><BarChart3 className="h-4 w-4 text-violet-600" /> Plataformas de anúncios</div>
                    <div className="flex flex-wrap gap-2">{zernioAdPlatforms.map(([key, label]) => <a key={key} href={route('client.integrations.zernio.connect-ads', key)} className="rounded-full border px-3 py-1.5 text-xs hover:border-violet-400 hover:text-violet-700">+ {label}</a>)}</div>
                </div>
            </div>

            <div className="mt-5 border-t pt-4">
                <div className="mb-3 flex items-center gap-2 text-sm font-semibold"><Send className="h-4 w-4 text-violet-600" /> Contas desta empresa</div>
                {zernio.accounts?.length ? <div className="grid gap-2 md:grid-cols-2 xl:grid-cols-3">{zernio.accounts.map(account => <div key={account.id} className="rounded-lg border p-3 text-sm">
                    <div className="flex items-start justify-between gap-2"><div><b>{account.display_name || account.username || account.platform}</b><div className="text-xs capitalize text-gray-500">{account.platform}{account.username ? ` · ${account.username}` : ''}</div></div><span className={`rounded-full px-2 py-1 text-[10px] ${account.status === 'connected' ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700'}`}>{account.status === 'connected' ? 'Conectada' : 'Desconectada'}</span></div>
                    <div className="mt-2 flex flex-wrap gap-1">{capabilities(account).map(item => <span key={item} className="rounded bg-gray-100 px-2 py-1 text-[10px] text-gray-600">{item}</span>)}</div>
                </div>)}</div> : <div className="rounded-lg border border-dashed p-5 text-center text-sm text-gray-500">Nenhuma conta conectada neste perfil. Use um dos botões acima.</div>}
            </div>
        </>}
    </section>;
}

export default function IntegrationsIndex({ connections = [], available = {}, channelStatus = {}, adStatus = {}, storeStatus = {}, zernio = {} }) {
    const channelCount = key => channelStatus?.[key]?.connected ?? 0;
    const adCount = key => adStatus?.[key]?.connected ?? 0;
    const storeCount = key => storeStatus?.[key]?.connected ?? 0;

    return <ClientLayout title="Integrações">
        <Head title="Integrações" />
        <div className="space-y-6 p-6">
            <div><h1 className="text-2xl font-semibold">Integrações</h1><p className="text-sm text-gray-500">Cada serviço tem sua própria conexão, credenciais criptografadas e teste isolado.</p></div>
            <ZernioCard zernio={zernio} />
            <section>
                <h2 className="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-500">Canais de atendimento</h2>
                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <HubCard title="WhatsApp oficial e não oficial" description="Conversas, campanhas, templates e QR Code." href={route('client.inbox.setup')} connected={channelCount('whatsapp')} icon={MessageCircle} />
                    <HubCard title="Instagram e Messenger" description="Mensagens, comentários e leads da Meta." href={route('client.inbox.setup')} connected={channelCount('instagram') + channelCount('messenger')} icon={MessageCircle} />
                    <HubCard title="Telegram" description="Mensagens e automações por bot." href={route('client.inbox.setup')} connected={channelCount('telegram')} icon={MessageCircle} />
                    <HubCard title="E-mail e SMS" description="Atendimento, campanhas e notificações." href={route('client.inbox.setup')} connected={channelCount('email') + channelCount('sms')} icon={MessageCircle} />
                </div>
            </section>
            <section>
                <h2 className="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-500">Aquisição, anúncios e comércio</h2>
                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <HubCard title="Meta Ads" description="Campanhas, Pixel e API de Conversões." href={route('client.ads.index')} connected={adCount('meta')} icon={BarChart3} />
                    <HubCard title="Google Ads" description="Cliques, campanhas e conversões offline." href={route('client.ads.index')} connected={adCount('google')} icon={BarChart3} />
                    <HubCard title="WooCommerce" description="Produtos, estoque, clientes e pedidos." href={route('client.ecommerce.stores.index')} connected={storeCount('woocommerce')} icon={ShoppingBag} />
                    <HubCard title="Shopify e BigCommerce" description="Catálogo, estoque e pedidos sincronizados." href={route('client.ecommerce.stores.index')} connected={storeCount('shopify') + storeCount('bigcommerce')} icon={ShoppingBag} />
                </div>
            </section>
            <section>
                <h2 className="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-500">Conteúdo e produtividade</h2>
            <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <Link href={route('client.social.accounts.index')} className="rounded-xl border bg-white p-4"><b>Instagram</b><div className="mt-2 text-sm text-gray-500">Mensagens e publicações</div><div className="mt-3 text-sm text-violet-600">{available.instagram ? 'Conectado' : 'Configurar conta social'}</div></Link>
                <Link href={route('client.social.accounts.index')} className="rounded-xl border bg-white p-4"><b>LinkedIn</b><div className="mt-2 text-sm text-gray-500">Publicações em páginas e perfis autorizados</div><div className="mt-3 text-sm text-violet-600">{available.linkedin ? 'Conectado' : 'Configurar conta social'}</div></Link>
                <div className="rounded-xl border bg-white p-4"><b>Google Workspace</b><div className="mt-2 text-sm text-gray-500">Drive, Docs, Agenda e Meet. Gmail exige escopo OAuth adicional.</div><div className="mt-3 text-sm">{available.google_workspace ? 'Disponível no sistema' : 'Credenciais do sistema pendentes'}</div></div>
                <div className="rounded-xl border bg-white p-4">
                    <div className="flex items-center gap-2"><HardDrive className="h-4 w-4 text-violet-600" /><b>Google Drive</b></div>
                    <div className="mt-2 text-sm text-gray-500">Arquivos sempre atualizados na Base de Conhecimento.</div>
                    {available.google_drive_connected ? (
                        <div className="mt-3"><div className="text-sm text-green-700">Conectado · {available.google_drive_email}</div><Link href={route('client.ai.knowledge-bases.index')} className="mt-2 inline-block text-sm text-violet-600">Escolher uma base →</Link></div>
                    ) : available.google_workspace ? (
                        <a href={route('client.ai.google-drive.connect')} className="mt-3 inline-block text-sm text-violet-600">Conectar conta Google →</a>
                    ) : (
                        <div className="mt-3 text-sm text-amber-700">Aplicativo Google pendente no sistema</div>
                    )}
                </div>
            </div>
            </section>
            <section>
                <h2 className="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-500">Operação, ERP, cobrança e sites</h2>
            <div className="grid gap-5 md:grid-cols-2">{Object.keys(providers).map(providerKey => <IntegrationCard key={providerKey} providerKey={providerKey} connections={connections.filter(item => item.provider === providerKey)} />)}</div>
            </section>
            <div className="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900"><b>Gestão segura de sites:</b> a integração usa a API REST oficial do WordPress. Posts e páginas podem ser gerenciados; mudanças em plugins, tema, arquivos ou código continuam exigindo backup, homologação e aprovação explícita.</div>
        </div>
    </ClientLayout>;
}
