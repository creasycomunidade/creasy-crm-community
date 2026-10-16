import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowRight, BarChart3, CheckCircle2, CircleDollarSign, ExternalLink,
    Megaphone, Plug, Settings2, Target, TrendingUp, Users,
} from 'lucide-react';
import {
    Bar, BarChart, CartesianGrid, ResponsiveContainer, Tooltip, XAxis, YAxis,
} from 'recharts';
import ClientLayout from '@/Layouts/ClientLayout';

const statusLabels = {
    draft: 'Rascunho',
    scheduled: 'Agendada',
    running: 'Em andamento',
    paused: 'Pausada',
    completed: 'Concluída',
    connected: 'Conectada',
    active: 'Ativo',
    pending: 'Pendente',
    error: 'Com erro',
    failed: 'Com falha',
};

function money(cents, currency = 'BRL') {
    return new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: currency || 'BRL',
        minimumFractionDigits: 2,
    }).format((Number(cents) || 0) / 100);
}

function MetricCard({ label, value, helper, icon: Icon, accent = 'violet' }) {
    const colors = {
        violet: 'bg-violet-50 text-violet-600',
        blue: 'bg-blue-50 text-blue-600',
        green: 'bg-emerald-50 text-emerald-600',
        amber: 'bg-amber-50 text-amber-600',
    };

    return <div className="rounded-2xl border border-neutral-200 bg-white p-4 shadow-sm dark:border-neutral-800 dark:bg-neutral-900">
        <div className="flex items-start justify-between gap-3">
            <div>
                <p className="text-xs font-medium uppercase tracking-wide text-neutral-500">{label}</p>
                <p className="mt-2 text-2xl font-semibold text-neutral-950 dark:text-white">{value}</p>
                {helper && <p className="mt-1 text-xs text-neutral-500">{helper}</p>}
            </div>
            <span className={`rounded-xl p-2.5 ${colors[accent]}`}><Icon className="h-5 w-5" /></span>
        </div>
    </div>;
}

function EmptyPipeline({ setupRequired = false }) {
    if (setupRequired) {
        return <div className="rounded-2xl border border-amber-200 bg-amber-50 p-10 text-center text-amber-950">
            <Settings2 className="mx-auto h-10 w-10 text-amber-600" />
            <h2 className="mt-4 text-lg font-semibold">Atualização do funil pendente</h2>
            <p className="mx-auto mt-2 max-w-xl text-sm text-amber-800">
                Os arquivos já estão disponíveis, mas o banco de dados ainda precisa receber as migrations desta versão. Avise o administrador do sistema.
            </p>
        </div>;
    }

    return <div className="rounded-2xl border border-dashed border-neutral-300 bg-white p-10 text-center dark:border-neutral-700 dark:bg-neutral-900">
        <Target className="mx-auto h-10 w-10 text-violet-500" />
        <h2 className="mt-4 text-lg font-semibold">Crie seu primeiro pipeline</h2>
        <p className="mx-auto mt-2 max-w-xl text-sm text-neutral-500">
            O funil usa as etapas do Kanban para medir leads, oportunidades, vendas e receita.
        </p>
        <Link href={route('client.crm.pipelines.index')} className="mt-5 inline-flex items-center gap-2 rounded-xl bg-violet-600 px-4 py-2 text-sm font-medium text-white">
            Configurar Kanban <ArrowRight className="h-4 w-4" />
        </Link>
    </div>;
}

export default function FunnelIndex({ funnel, currency = 'BRL', needsPipeline = false, setupRequired = false }) {
    if (setupRequired || needsPipeline || !funnel) {
        return <ClientLayout title="Funil de vendas">
            <Head title="Funil de vendas" />
            <div className="p-6"><EmptyPipeline setupRequired={setupRequired} /></div>
        </ClientLayout>;
    }

    const updateFilter = (key, value) => {
        const params = {
            days: key === 'days' ? value : funnel.period.days,
            pipeline: key === 'pipeline' ? value : funnel.selectedPipeline?.uuid,
        };
        router.get(route('client.crm.funnel.index'), params, { preserveState: true, preserveScroll: true });
    };

    const summary = funnel.summary;
    const maxStageCount = Math.max(...funnel.stages.map(stage => stage.deal_count), 1);
    const integrations = funnel.integrations?.items ?? [];

    return <ClientLayout title="Funil de vendas">
        <Head title="Funil de vendas" />
        <div className="space-y-6 p-4 sm:p-6">
            <div className="flex flex-col justify-between gap-4 lg:flex-row lg:items-end">
                <div>
                    <h1 className="text-2xl font-semibold text-neutral-950 dark:text-white">Funil de vendas</h1>
                    <p className="mt-1 text-sm text-neutral-500">
                        Da origem do lead à venda: campanhas, canais, Kanban e conversões em uma única visão.
                    </p>
                </div>
                <div className="flex flex-wrap items-center gap-2">
                    <select
                        aria-label="Pipeline"
                        className="rounded-xl border-neutral-300 text-sm dark:border-neutral-700 dark:bg-neutral-900"
                        value={funnel.selectedPipeline?.uuid ?? ''}
                        onChange={event => updateFilter('pipeline', event.target.value)}
                    >
                        {funnel.pipelines.map(pipeline => <option key={pipeline.uuid} value={pipeline.uuid}>{pipeline.name}</option>)}
                    </select>
                    <div className="flex rounded-xl border border-neutral-200 bg-white p-1 dark:border-neutral-700 dark:bg-neutral-900">
                        {[7, 30, 90, 365].map(days => <button
                            key={days}
                            type="button"
                            onClick={() => updateFilter('days', days)}
                            className={`rounded-lg px-3 py-1.5 text-xs font-medium ${funnel.period.days === days ? 'bg-violet-600 text-white' : 'text-neutral-600 hover:bg-neutral-50 dark:text-neutral-300 dark:hover:bg-neutral-800'}`}
                        >{days === 365 ? '1 ano' : `${days}d`}</button>)}
                    </div>
                    <Link href={route('client.crm.pipelines.index', { pipeline: funnel.selectedPipeline?.uuid })} className="inline-flex items-center gap-2 rounded-xl border border-neutral-300 bg-white px-3 py-2 text-sm font-medium hover:border-violet-300 hover:text-violet-700 dark:border-neutral-700 dark:bg-neutral-900">
                        <Settings2 className="h-4 w-4" /> Abrir Kanban
                    </Link>
                </div>
            </div>

            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-6">
                <MetricCard label="Novas oportunidades" value={summary.new_deals} helper={`Nos últimos ${funnel.period.days} dias`} icon={Users} />
                <MetricCard label="Valor no pipeline" value={money(summary.pipeline_value_cents, currency)} icon={CircleDollarSign} accent="blue" />
                <MetricCard label="Vendas ganhas" value={summary.won_deals} icon={CheckCircle2} accent="green" />
                <MetricCard label="Receita ganha" value={money(summary.won_value_cents, currency)} icon={TrendingUp} accent="green" />
                <MetricCard label="Conversão" value={`${summary.conversion_rate}%`} helper="Oportunidades → vendas" icon={Target} accent="amber" />
                <MetricCard label="Ticket médio" value={money(summary.average_ticket_cents, currency)} icon={BarChart3} accent="blue" />
            </div>

            <section className="rounded-2xl border border-neutral-200 bg-white p-5 shadow-sm dark:border-neutral-800 dark:bg-neutral-900">
                <div className="flex items-center justify-between gap-3">
                    <div>
                        <h2 className="font-semibold">Etapas do funil</h2>
                        <p className="mt-1 text-xs text-neutral-500">Retrato atual das oportunidades criadas no período.</p>
                    </div>
                    <span className="text-xs text-neutral-500">{funnel.selectedPipeline?.name}</span>
                </div>
                <div className="mt-5 grid gap-3" style={{ gridTemplateColumns: `repeat(${Math.min(funnel.stages.length, 6) || 1}, minmax(160px, 1fr))`, overflowX: 'auto' }}>
                    {funnel.stages.map((stage, index) => <div key={stage.uuid} className="min-w-40">
                        <div className="rounded-xl border border-neutral-200 p-3 dark:border-neutral-700">
                            <div className="flex items-center gap-2">
                                <span className="h-2.5 w-2.5 rounded-full" style={{ backgroundColor: stage.color }} />
                                <span className="truncate text-sm font-medium">{stage.name}</span>
                            </div>
                            <div className="mt-3 flex items-end justify-between gap-2">
                                <span className="text-2xl font-semibold">{stage.deal_count}</span>
                                <span className="text-xs text-neutral-500">{money(stage.value_cents, currency)}</span>
                            </div>
                            <div className="mt-3 h-1.5 overflow-hidden rounded-full bg-neutral-100 dark:bg-neutral-800">
                                <div className="h-full rounded-full" style={{ width: `${Math.max(4, (stage.deal_count / maxStageCount) * 100)}%`, backgroundColor: stage.color }} />
                            </div>
                        </div>
                        {index < funnel.stages.length - 1 && <ArrowRight className="mx-auto mt-2 hidden h-4 w-4 text-neutral-300 xl:block" />}
                    </div>)}
                </div>
            </section>

            <div className="grid gap-5 xl:grid-cols-2">
                <section className="rounded-2xl border border-neutral-200 bg-white p-5 shadow-sm dark:border-neutral-800 dark:bg-neutral-900">
                    <h2 className="font-semibold">Origem dos leads</h2>
                    <p className="mt-1 text-xs text-neutral-500">Conversão e receita conforme a origem registrada no contato.</p>
                    {funnel.sources.length === 0 ? <p className="py-12 text-center text-sm text-neutral-400">Ainda não há oportunidades no período.</p> : <div className="mt-4 h-64">
                        <ResponsiveContainer width="100%" height="100%">
                            <BarChart data={funnel.sources} layout="vertical" margin={{ left: 8, right: 12 }}>
                                <CartesianGrid strokeDasharray="3 3" horizontal={false} />
                                <XAxis type="number" allowDecimals={false} />
                                <YAxis type="category" dataKey="source" width={125} tick={{ fontSize: 11 }} />
                                <Tooltip formatter={(value, name) => [value, name === 'leads' ? 'Leads' : 'Vendas']} />
                                <Bar dataKey="leads" name="Leads" fill="#8b5cf6" radius={[0, 4, 4, 0]} />
                                <Bar dataKey="won" name="Vendas" fill="#22c55e" radius={[0, 4, 4, 0]} />
                            </BarChart>
                        </ResponsiveContainer>
                    </div>}
                </section>

                <section className="rounded-2xl border border-neutral-200 bg-white p-5 shadow-sm dark:border-neutral-800 dark:bg-neutral-900">
                    <h2 className="font-semibold">Canais que geram oportunidades</h2>
                    <p className="mt-1 text-xs text-neutral-500">Um contato pode conversar em mais de um canal.</p>
                    <div className="mt-4 space-y-3">
                        {funnel.channels.length === 0 && <p className="py-12 text-center text-sm text-neutral-400">Nenhum canal associado às oportunidades.</p>}
                        {funnel.channels.map(channel => <div key={channel.key} className="rounded-xl border border-neutral-200 p-3 dark:border-neutral-700">
                            <div className="flex items-center justify-between gap-3">
                                <div><p className="text-sm font-medium">{channel.channel}</p><p className="text-xs text-neutral-500">{channel.leads} oportunidades · {channel.won} vendas</p></div>
                                <span className="rounded-full bg-violet-50 px-2.5 py-1 text-xs font-semibold text-violet-700">{channel.conversion_rate}%</span>
                            </div>
                        </div>)}
                    </div>
                </section>
            </div>

            <section className="rounded-2xl border border-neutral-200 bg-white p-5 shadow-sm dark:border-neutral-800 dark:bg-neutral-900">
                <div className="flex items-center justify-between gap-3">
                    <div><h2 className="font-semibold">Campanhas no funil</h2><p className="mt-1 text-xs text-neutral-500">Entrega, leitura e vendas dos contatos alcançados.</p></div>
                    <Link href={route('client.campaigns.index')} className="inline-flex items-center gap-1 text-sm font-medium text-violet-600">Ver campanhas <ExternalLink className="h-3.5 w-3.5" /></Link>
                </div>
                <div className="mt-4 overflow-x-auto">
                    <table className="w-full min-w-[760px] text-left text-sm">
                        <thead className="border-b text-xs uppercase text-neutral-500"><tr><th className="pb-3">Campanha</th><th className="pb-3">Canal</th><th className="pb-3">Status</th><th className="pb-3 text-right">Enviadas</th><th className="pb-3 text-right">Leitura</th><th className="pb-3 text-right">Leads</th><th className="pb-3 text-right">Vendas</th><th className="pb-3 text-right">Receita</th></tr></thead>
                        <tbody className="divide-y divide-neutral-100 dark:divide-neutral-800">
                            {funnel.campaigns.map(campaign => <tr key={campaign.uuid}>
                                <td className="py-3 font-medium"><Link className="hover:text-violet-600" href={route('client.campaigns.show', campaign.uuid)}>{campaign.name}</Link></td>
                                <td className="py-3">{campaign.channel}</td><td className="py-3">{statusLabels[campaign.status] ?? campaign.status}</td>
                                <td className="py-3 text-right">{campaign.sent}</td><td className="py-3 text-right">{campaign.read_rate}%</td>
                                <td className="py-3 text-right">{campaign.leads}</td><td className="py-3 text-right">{campaign.won}</td>
                                <td className="py-3 text-right">{money(campaign.revenue_cents, currency)}</td>
                            </tr>)}
                            {funnel.campaigns.length === 0 && <tr><td colSpan="8" className="py-10 text-center text-neutral-400">Nenhuma campanha criada no período.</td></tr>}
                        </tbody>
                    </table>
                </div>
            </section>

            <div className="grid gap-5 xl:grid-cols-[1.5fr_1fr]">
                <section className="rounded-2xl border border-neutral-200 bg-white p-5 shadow-sm dark:border-neutral-800 dark:bg-neutral-900">
                    <div className="flex items-center justify-between gap-3">
                        <div><h2 className="font-semibold">Integrações que alimentam o funil</h2><p className="mt-1 text-xs text-neutral-500">Canais, anúncios, ERPs e serviços conectados neste espaço.</p></div>
                        <Link href={route('client.integrations.index')} className="inline-flex items-center gap-1 text-sm font-medium text-violet-600">Gerenciar <ExternalLink className="h-3.5 w-3.5" /></Link>
                    </div>
                    <div className="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        {integrations.slice(0, 9).map((item, index) => {
                            const connected = ['connected', 'active'].includes(item.status);
                            return <div key={`${item.type}-${item.provider}-${index}`} className="flex items-start gap-3 rounded-xl border border-neutral-200 p-3 dark:border-neutral-700">
                                <span className={`mt-0.5 rounded-lg p-1.5 ${connected ? 'bg-emerald-50 text-emerald-600' : 'bg-amber-50 text-amber-600'}`}><Plug className="h-4 w-4" /></span>
                                <div className="min-w-0"><p className="truncate text-sm font-medium">{item.name}</p><p className={`text-xs ${connected ? 'text-emerald-600' : 'text-amber-600'}`}>{connected ? 'Conectado' : statusLabels[item.status] ?? 'Requer atenção'}</p></div>
                            </div>;
                        })}
                        {integrations.length === 0 && <p className="col-span-full py-8 text-center text-sm text-neutral-400">Nenhuma integração configurada.</p>}
                    </div>
                </section>

                <section className="rounded-2xl border border-neutral-200 bg-white p-5 shadow-sm dark:border-neutral-800 dark:bg-neutral-900">
                    <h2 className="font-semibold">Conversões para anúncios</h2>
                    <p className="mt-1 text-xs text-neutral-500">Leads qualificados e vendas devolvidos aos algoritmos.</p>
                    <div className="mt-5 grid grid-cols-3 gap-2 text-center">
                        <div className="rounded-xl bg-amber-50 p-3"><div className="text-xl font-semibold text-amber-700">{funnel.conversionDispatch.pending ?? 0}</div><div className="text-[10px] uppercase text-amber-700">Pendentes</div></div>
                        <div className="rounded-xl bg-emerald-50 p-3"><div className="text-xl font-semibold text-emerald-700">{funnel.conversionDispatch.sent ?? 0}</div><div className="text-[10px] uppercase text-emerald-700">Enviadas</div></div>
                        <div className="rounded-xl bg-red-50 p-3"><div className="text-xl font-semibold text-red-700">{funnel.conversionDispatch.failed ?? 0}</div><div className="text-[10px] uppercase text-red-700">Falhas</div></div>
                    </div>
                    <Link href={route('client.ads.index')} className="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-xl border border-neutral-300 px-3 py-2 text-sm font-medium hover:border-violet-300 hover:text-violet-700 dark:border-neutral-700">
                        <Megaphone className="h-4 w-4" /> Configurar Meta e Google Ads
                    </Link>
                </section>
            </div>
        </div>
    </ClientLayout>;
}
