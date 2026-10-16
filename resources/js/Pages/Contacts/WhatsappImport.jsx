import { Head, Link } from '@inertiajs/react';
import axios from 'axios';
import { useMemo, useState } from 'react';
import {
    ArrowLeft, Check, CheckCircle2, ContactRound, Info, LoaderCircle,
    Search, ShieldCheck, Smartphone, Users, XCircle,
} from 'lucide-react';
import ClientLayout from '@/Layouts/ClientLayout';

const PAGE_SIZE = 100;

export default function WhatsappImport({ accounts = [], tags = [], segments = [] }) {
    const [accountId, setAccountId] = useState(accounts.length === 1 ? accounts[0].id : null);
    const [contacts, setContacts] = useState([]);
    const [summary, setSummary] = useState(null);
    const [selected, setSelected] = useState(new Set());
    const [search, setSearch] = useState('');
    const [status, setStatus] = useState('all');
    const [page, setPage] = useState(1);
    const [loading, setLoading] = useState(false);
    const [importing, setImporting] = useState(false);
    const [error, setError] = useState('');
    const [success, setSuccess] = useState('');
    const [updateNames, setUpdateNames] = useState(false);
    const [hasConsent, setHasConsent] = useState(false);
    const [onlyGroups, setOnlyGroups] = useState(false);
    const [groups, setGroups] = useState([]);
    const [selectedGroupJids, setSelectedGroupJids] = useState(new Set());
    const [groupsLoading, setGroupsLoading] = useState(false);
    const [groupSearch, setGroupSearch] = useState('');
    const [tagId, setTagId] = useState('');
    const [segmentId, setSegmentId] = useState('');

    const filtered = useMemo(() => {
        const needle = search.trim().toLocaleLowerCase('pt-BR');
        return contacts.filter(contact => {
            const matchesStatus = status === 'all' || contact.status === status;
            const matchesSearch = !needle || `${contact.name} ${contact.phone_e164} ${contact.business_name}`.toLocaleLowerCase('pt-BR').includes(needle);
            return matchesStatus && matchesSearch;
        });
    }, [contacts, search, status]);

    const pageCount = Math.max(1, Math.ceil(filtered.length / PAGE_SIZE));
    const visible = filtered.slice((page - 1) * PAGE_SIZE, page * PAGE_SIZE);
    const filteredGroups = useMemo(() => {
        const needle = groupSearch.trim().toLocaleLowerCase('pt-BR');
        return needle
            ? groups.filter(group => group.name.toLocaleLowerCase('pt-BR').includes(needle))
            : groups;
    }, [groups, groupSearch]);
    const allFilteredSelected = filtered.length > 0 && filtered.every(contact => selected.has(contact.jid));
    const allGroupsSelected = groups.length > 0 && groups.every(group => selectedGroupJids.has(group.jid));

    const resetPreview = (nextAccountId) => {
        setAccountId(nextAccountId);
        setContacts([]);
        setSummary(null);
        setSelected(new Set());
        setSuccess('');
        setError('');
        setPage(1);
        setGroups([]);
        setSelectedGroupJids(new Set());
        setGroupSearch('');
    };

    const loadGroups = async (targetAccountId = accountId) => {
        if (!targetAccountId) {
            setError('Escolha uma conexão do WhatsApp.');
            return;
        }
        setGroupsLoading(true);
        setError('');
        try {
            const { data } = await axios.post(route('client.contacts.whatsapp-import.groups'), {
                channel_account_id: targetAccountId,
            });
            setGroups(data.groups ?? []);
            setSelectedGroupJids(new Set());
            setGroupSearch('');
        } catch (requestError) {
            setGroups([]);
            setSelectedGroupJids(new Set());
            setError(requestError.response?.data?.message || 'Não foi possível carregar os grupos. Confira se o WhatsApp está conectado.');
        } finally {
            setGroupsLoading(false);
        }
    };

    const toggleGroup = (jid) => setSelectedGroupJids(current => {
        const next = new Set(current);
        next.has(jid) ? next.delete(jid) : next.add(jid);
        return next;
    });

    const toggleAllGroups = () => setSelectedGroupJids(
        allGroupsSelected ? new Set() : new Set(groups.map(group => group.jid)),
    );

    const loadContacts = async () => {
        if (!accountId) {
            setError('Escolha uma conexão do WhatsApp.');
            return;
        }
        if (onlyGroups && selectedGroupJids.size === 0) {
            setError('Selecione pelo menos um grupo para ler os participantes.');
            return;
        }
        setLoading(true);
        setError('');
        setSuccess('');
        try {
            const { data } = await axios.post(route('client.contacts.whatsapp-import.preview'), {
                channel_account_id: accountId,
                only_groups: onlyGroups,
                group_jids: onlyGroups ? [...selectedGroupJids] : [],
            });
            setContacts(data.contacts ?? []);
            setSummary(data.summary ?? null);
            setSelected(new Set((data.contacts ?? []).filter(contact => contact.status === 'new').map(contact => contact.jid)));
            setPage(1);
        } catch (requestError) {
            setError(requestError.response?.data?.message || 'Não foi possível ler os contatos. Confira se o WhatsApp está conectado.');
        } finally {
            setLoading(false);
        }
    };

    const toggle = (jid) => setSelected(current => {
        const next = new Set(current);
        next.has(jid) ? next.delete(jid) : next.add(jid);
        return next;
    });

    const toggleFiltered = () => setSelected(current => {
        const next = new Set(current);
        if (allFilteredSelected) filtered.forEach(contact => next.delete(contact.jid));
        else filtered.forEach(contact => next.add(contact.jid));
        return next;
    });

    const importSelected = async () => {
        if (selected.size === 0) {
            setError('Selecione pelo menos um contato para importar.');
            return;
        }
        setImporting(true);
        setError('');
        setSuccess('');
        try {
            const { data } = await axios.post(route('client.contacts.whatsapp-import.store'), {
                channel_account_id: accountId,
                selected_jids: [...selected],
                update_existing_names: updateNames,
                marketing_consent_confirmed: hasConsent,
                only_groups: onlyGroups,
                group_jids: onlyGroups ? [...selectedGroupJids] : [],
                tag_id: tagId || null,
                segment_id: segmentId || null,
            });
            await loadContacts();
            setSuccess(data.message || 'Contatos importados com sucesso.');
        } catch (requestError) {
            const validation = requestError.response?.data?.errors;
            setError(validation ? Object.values(validation).flat()[0] : (requestError.response?.data?.message || 'Não foi possível importar os contatos.'));
        } finally {
            setImporting(false);
        }
    };

    return (
        <ClientLayout title="Extrair contatos do WhatsApp">
            <Head title="Extrair contatos do WhatsApp" />
            <main className="mx-auto max-w-7xl space-y-6">
                <header className="flex flex-wrap items-start justify-between gap-4">
                    <div className="flex items-start gap-3">
                        <Link href={route('client.contacts.index')} aria-label="Voltar para contatos" className="flex h-11 w-11 items-center justify-center rounded-xl border border-neutral-300 text-neutral-600 transition hover:bg-neutral-100 focus:outline-none focus:ring-2 focus:ring-brand-500 dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-800">
                            <ArrowLeft className="h-5 w-5" aria-hidden="true" />
                        </Link>
                        <div>
                            <h1 className="text-2xl font-semibold text-neutral-950 dark:text-white">Extrair contatos do WhatsApp</h1>
                            <p className="mt-1 max-w-2xl text-sm leading-6 text-neutral-600 dark:text-neutral-400">Leia nomes e telefones da agenda da conexão por QR Code, revise duplicados e escolha exatamente o que será salvo.</p>
                        </div>
                    </div>
                </header>

                <section className="rounded-2xl border border-blue-200 bg-blue-50 p-4 text-sm text-blue-950 dark:border-blue-900 dark:bg-blue-950/30 dark:text-blue-200" aria-label="Privacidade da importação">
                    <div className="flex gap-3">
                        <ShieldCheck className="mt-0.5 h-5 w-5 shrink-0" aria-hidden="true" />
                        <div><strong>Privacidade preservada.</strong> A leitura usa somente a conta conectada neste espaço de trabalho. Estar na agenda não significa autorização para campanhas; essa permissão é registrada separadamente.</div>
                    </div>
                </section>

                <section className="rounded-2xl border border-neutral-200 bg-white p-5 shadow-sm dark:border-neutral-800 dark:bg-neutral-900">
                    <div>
                        <span className="text-xs font-semibold uppercase tracking-wide text-brand-600 dark:text-brand-400">Etapa 1</span>
                        <h2 className="mt-1 text-lg font-semibold text-neutral-900 dark:text-neutral-100">Escolha a conexão</h2>
                        <p className="mt-1 text-sm leading-6 text-neutral-500 dark:text-neutral-400">Selecione a conta do WhatsApp por QR Code que será usada na leitura.</p>
                    </div>

                    {accounts.length === 0 ? (
                        <div className="mt-5 rounded-xl border border-dashed border-neutral-300 p-8 text-center dark:border-neutral-700">
                            <Smartphone className="mx-auto h-8 w-8 text-neutral-400" aria-hidden="true" />
                            <p className="mt-3 font-medium text-neutral-800 dark:text-neutral-200">Nenhuma conexão por QR Code ativa</p>
                            <p className="mt-1 text-sm text-neutral-500 dark:text-neutral-400">Conecte uma conta do WhatsApp para liberar a extração.</p>
                            <Link href={route('client.inbox.setup')} className="mt-4 inline-flex min-h-11 items-center rounded-xl border border-neutral-300 px-4 text-sm font-medium text-neutral-700 transition hover:bg-neutral-50 focus:outline-none focus:ring-2 focus:ring-brand-500 dark:border-neutral-700 dark:text-neutral-200 dark:hover:bg-neutral-800">Abrir conexões</Link>
                        </div>
                    ) : (
                        <div className="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-3" role="radiogroup" aria-label="Conexão do WhatsApp">
                            {accounts.map(account => {
                                const active = account.id === accountId;
                                return (
                                    <button key={account.id} type="button" role="radio" aria-checked={active} onClick={() => { resetPreview(account.id); if (onlyGroups) void loadGroups(account.id); }} className={`min-h-20 rounded-xl border p-4 text-left transition focus:outline-none focus:ring-2 focus:ring-green-500 ${active ? 'border-green-500 bg-green-50 ring-1 ring-green-500 dark:bg-green-950/30' : 'border-neutral-200 hover:border-green-300 hover:bg-neutral-50 dark:border-neutral-700 dark:hover:bg-neutral-800'}`}>
                                        <span className="flex items-center justify-between gap-3"><span className="font-semibold text-neutral-900 dark:text-neutral-100">{account.name}</span>{active && <CheckCircle2 className="h-5 w-5 text-green-600" aria-hidden="true" />}</span>
                                        <span className="mt-1 block text-sm text-neutral-500 dark:text-neutral-400">{account.phone || 'WhatsApp conectado'}</span>
                                    </button>
                                );
                            })}
                        </div>
                    )}

                    {accounts.length > 0 && (
                        <div className="mt-6 border-t border-neutral-200 pt-5 dark:border-neutral-800">
                            <span className="text-xs font-semibold uppercase tracking-wide text-brand-600 dark:text-brand-400">Etapa 2</span>
                            <h3 className="mt-1 text-lg font-semibold text-neutral-900 dark:text-neutral-100">Escolha a origem dos contatos</h3>
                            <label className={`mt-3 flex min-h-20 cursor-pointer items-center gap-4 rounded-2xl border p-4 transition focus-within:ring-2 focus-within:ring-brand-500 focus-within:ring-offset-2 dark:focus-within:ring-offset-neutral-900 ${onlyGroups ? 'border-brand-400 bg-brand-50 shadow-sm dark:border-brand-600 dark:bg-brand-950/30' : 'border-neutral-200 bg-neutral-50 hover:border-neutral-300 dark:border-neutral-700 dark:bg-neutral-800/50 dark:hover:border-neutral-600'}`}>
                                <span className={`flex h-11 w-11 shrink-0 items-center justify-center rounded-xl ${onlyGroups ? 'bg-brand-600 text-white' : 'bg-white text-neutral-500 shadow-sm dark:bg-neutral-900 dark:text-neutral-300'}`}><Users className="h-5 w-5" aria-hidden="true" /></span>
                                <span className="min-w-0 flex-1">
                                    <strong className="block text-sm text-neutral-900 dark:text-neutral-100">Somente participantes de grupos</strong>
                                    <span className="mt-1 block text-sm leading-5 text-neutral-500 dark:text-neutral-400">Ative para escolher os grupos. Desativado, o extrator lê os contatos individuais da agenda.</span>
                                </span>
                                <input
                                    type="checkbox"
                                    checked={onlyGroups}
                                    onChange={event => {
                                        const checked = event.target.checked;
                                        setOnlyGroups(checked);
                                        setContacts([]);
                                        setSummary(null);
                                        setSelected(new Set());
                                        setGroups([]);
                                        setSelectedGroupJids(new Set());
                                        setGroupSearch('');
                                        setSuccess('');
                                        setError('');
                                        setPage(1);
                                        if (checked && accountId) void loadGroups(accountId);
                                    }}
                                    className="peer sr-only"
                                    aria-label="Extrair somente participantes dos grupos"
                                />
                                <span aria-hidden="true" className="relative h-7 w-12 shrink-0 rounded-full bg-neutral-300 transition-colors after:absolute after:left-1 after:top-1 after:h-5 after:w-5 after:rounded-full after:bg-white after:shadow-sm after:transition-transform peer-checked:bg-brand-600 peer-checked:after:translate-x-5 peer-focus-visible:ring-2 peer-focus-visible:ring-brand-500 peer-focus-visible:ring-offset-2 dark:bg-neutral-600" />
                            </label>
                        </div>
                    )}

                    {onlyGroups && accounts.length > 0 && (
                        <div className="mt-4 overflow-hidden rounded-2xl border border-brand-200 bg-brand-50/60 p-4 dark:border-brand-800 dark:bg-brand-950/20">
                            <div className="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <h3 className="text-base font-semibold text-neutral-900 dark:text-neutral-100">Escolha os grupos</h3>
                                    <p className="mt-1 text-sm text-neutral-500 dark:text-neutral-400">Somente os participantes dos grupos marcados serão exibidos para revisão.</p>
                                </div>
                                <div className="flex flex-wrap gap-2">
                                    <button type="button" onClick={() => loadGroups()} disabled={!accountId || groupsLoading} className="min-h-11 rounded-xl border border-neutral-300 bg-white px-3 text-sm font-medium text-neutral-700 transition hover:bg-neutral-100 focus:outline-none focus:ring-2 focus:ring-brand-500 disabled:cursor-not-allowed disabled:opacity-50 dark:border-neutral-600 dark:bg-neutral-900 dark:text-neutral-200 dark:hover:bg-neutral-800">Recarregar grupos</button>
                                    <button type="button" onClick={toggleAllGroups} disabled={groupsLoading || groups.length === 0} className="min-h-11 rounded-xl border border-brand-300 bg-white px-3 text-sm font-semibold text-brand-700 transition hover:bg-brand-50 focus:outline-none focus:ring-2 focus:ring-brand-500 disabled:cursor-not-allowed disabled:opacity-50 dark:border-brand-700 dark:bg-neutral-900 dark:text-brand-300 dark:hover:bg-brand-950/30">{allGroupsSelected ? 'Desmarcar todos' : 'Selecionar todos'}</button>
                                </div>
                            </div>

                            {groups.length > 6 && !groupsLoading && (
                                <label className="relative mt-4 block">
                                    <span className="sr-only">Buscar grupo</span>
                                    <Search className="pointer-events-none absolute left-3 top-3.5 h-4 w-4 text-neutral-400" aria-hidden="true" />
                                    <input value={groupSearch} onChange={event => setGroupSearch(event.target.value)} placeholder="Buscar grupo pelo nome" className="min-h-11 w-full rounded-xl border border-neutral-300 bg-white pl-10 pr-3 text-base text-neutral-900 outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-neutral-700 dark:bg-neutral-900 dark:text-white sm:text-sm" />
                                </label>
                            )}

                            <div className="mt-4" aria-live="polite">
                                {groupsLoading ? (
                                    <p className="flex min-h-11 items-center gap-2 text-sm text-neutral-600 dark:text-neutral-300"><LoaderCircle className="h-4 w-4 animate-spin motion-reduce:animate-none" aria-hidden="true" />Carregando grupos…</p>
                                ) : groups.length > 0 ? (
                                    <div className="grid max-h-80 gap-2 overflow-y-auto pr-1 sm:grid-cols-2 lg:grid-cols-3" role="group" aria-label="Grupos disponíveis">
                                        {filteredGroups.map(group => (
                                            <label key={group.jid} className={`flex min-h-16 cursor-pointer items-center gap-3 rounded-xl border px-3 py-2.5 transition focus-within:ring-2 focus-within:ring-brand-500/30 ${selectedGroupJids.has(group.jid) ? 'border-brand-400 bg-white shadow-sm dark:border-brand-600 dark:bg-brand-950/40' : 'border-neutral-200 bg-white/80 hover:border-brand-300 dark:border-neutral-700 dark:bg-neutral-900 dark:hover:border-brand-700'}`}>
                                                <input type="checkbox" checked={selectedGroupJids.has(group.jid)} onChange={() => toggleGroup(group.jid)} className="h-5 w-5 rounded border-neutral-300 text-brand-600 focus:ring-brand-500" />
                                                <span className="min-w-0 flex-1"><strong className="block truncate text-sm text-neutral-900 dark:text-neutral-100">{group.name}</strong><span className="mt-1 block text-xs text-neutral-500 dark:text-neutral-400">{group.participant_count} participante(s)</span></span>
                                                {selectedGroupJids.has(group.jid) && <CheckCircle2 className="h-5 w-5 shrink-0 text-brand-600" aria-hidden="true" />}
                                            </label>
                                        ))}
                                        {filteredGroups.length === 0 && <p className="col-span-full rounded-xl border border-dashed border-neutral-300 bg-white/70 p-6 text-center text-sm text-neutral-500 dark:border-neutral-700 dark:bg-neutral-900/70 dark:text-neutral-400">Nenhum grupo encontrado com esse nome.</p>}
                                    </div>
                                ) : (
                                    <p className="rounded-lg border border-dashed border-neutral-300 p-4 text-sm text-neutral-500 dark:border-neutral-700 dark:text-neutral-400">Nenhum grupo disponível nesta conexão.</p>
                                )}
                            </div>
                            <div className="mt-4 flex items-center justify-between gap-3 border-t border-brand-200 pt-4 dark:border-brand-800">
                                <p className="text-sm font-semibold text-neutral-700 dark:text-neutral-300">{selectedGroupJids.size} de {groups.length} grupo(s) selecionado(s)</p>
                                {selectedGroupJids.size > 0 && <span className="rounded-full bg-brand-600 px-2.5 py-1 text-xs font-semibold text-white">Pronto para ler</span>}
                            </div>
                        </div>
                    )}

                    {accounts.length > 0 && (
                        <div className="mt-6 flex flex-col gap-3 border-t border-neutral-200 pt-5 sm:flex-row sm:items-center sm:justify-between dark:border-neutral-800">
                            <p className="text-sm leading-6 text-neutral-500 dark:text-neutral-400">
                                {onlyGroups
                                    ? (selectedGroupJids.size > 0 ? `Serão consultados ${selectedGroupJids.size} grupo(s).` : 'Escolha ao menos um grupo para continuar.')
                                    : 'Serão lidos somente os contatos individuais da agenda.'}
                            </p>
                            <button type="button" onClick={loadContacts} disabled={!accountId || loading || groupsLoading || (onlyGroups && selectedGroupJids.size === 0)} className="inline-flex min-h-12 w-full items-center justify-center gap-2 rounded-xl bg-brand-600 px-5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 disabled:shadow-none sm:w-auto dark:focus:ring-offset-neutral-900">
                                {loading ? <LoaderCircle className="h-4 w-4 animate-spin motion-reduce:animate-none" aria-hidden="true" /> : <ContactRound className="h-4 w-4" aria-hidden="true" />}
                                {loading ? 'Lendo contatos…' : (onlyGroups ? 'Ler participantes selecionados' : 'Ler contatos da agenda')}
                            </button>
                        </div>
                    )}
                </section>

                {error && <div role="alert" className="flex items-start gap-2 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800 dark:border-red-900 dark:bg-red-950/30 dark:text-red-300"><XCircle className="mt-0.5 h-5 w-5 shrink-0" aria-hidden="true" />{error}</div>}
                {success && <div role="status" className="flex items-start gap-2 rounded-xl border border-green-200 bg-green-50 p-4 text-sm text-green-800 dark:border-green-900 dark:bg-green-950/30 dark:text-green-300"><Check className="mt-0.5 h-5 w-5 shrink-0" aria-hidden="true" />{success}</div>}

                {summary && (
                    <>
                        <section className="grid gap-3 sm:grid-cols-3" aria-label="Resumo dos contatos encontrados">
                            {[[ 'Encontrados', summary.total, Users], ['Novos', summary.new, ContactRound], ['Já cadastrados', summary.existing, CheckCircle2]].map(([label, value, Icon]) => (
                                <div key={label} className="rounded-2xl border border-neutral-200 bg-white p-4 shadow-sm dark:border-neutral-800 dark:bg-neutral-900"><div className="flex items-center gap-2 text-sm text-neutral-500 dark:text-neutral-400"><Icon className="h-4 w-4" aria-hidden="true" />{label}</div><strong className="mt-2 block text-2xl text-neutral-950 dark:text-white">{value}</strong></div>
                            ))}
                        </section>

                        <section className="overflow-hidden rounded-2xl border border-neutral-200 bg-white shadow-sm dark:border-neutral-800 dark:bg-neutral-900">
                            <div className="border-b border-neutral-200 p-5 dark:border-neutral-800">
                                <h2 className="text-base font-semibold text-neutral-900 dark:text-neutral-100">2. Revise e selecione</h2>
                                <div className="mt-4 grid gap-3 sm:grid-cols-[1fr_180px_auto]">
                                    <label className="relative"><span className="sr-only">Buscar contato</span><Search className="pointer-events-none absolute left-3 top-3.5 h-4 w-4 text-neutral-400" aria-hidden="true" /><input value={search} onChange={event => { setSearch(event.target.value); setPage(1); }} placeholder="Buscar por nome ou telefone" className="min-h-11 w-full rounded-xl border border-neutral-300 bg-white pl-10 pr-3 text-base text-neutral-900 outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-neutral-700 dark:bg-neutral-800 dark:text-white sm:text-sm" /></label>
                                    <label><span className="sr-only">Filtrar situação</span><select value={status} onChange={event => { setStatus(event.target.value); setPage(1); }} className="min-h-11 w-full rounded-xl border-neutral-300 bg-white text-sm dark:border-neutral-700 dark:bg-neutral-800 dark:text-white"><option value="all">Todos</option><option value="new">Somente novos</option><option value="existing">Já cadastrados</option></select></label>
                                    <button type="button" onClick={toggleFiltered} disabled={filtered.length === 0} className="min-h-11 rounded-xl border border-neutral-300 px-4 text-sm font-medium text-neutral-700 transition hover:bg-neutral-50 focus:outline-none focus:ring-2 focus:ring-brand-500 disabled:opacity-50 dark:border-neutral-700 dark:text-neutral-200 dark:hover:bg-neutral-800">{allFilteredSelected ? 'Desmarcar filtrados' : 'Selecionar filtrados'}</button>
                                </div>
                                <p className="mt-3 text-sm text-neutral-500 dark:text-neutral-400">{selected.size} selecionado(s) de {contacts.length}</p>
                            </div>

                            <div className="divide-y divide-neutral-100 dark:divide-neutral-800">
                                {visible.map(contact => (
                                    <label key={contact.jid} className="flex min-h-16 cursor-pointer items-center gap-3 px-5 py-3 transition hover:bg-neutral-50 focus-within:bg-neutral-50 dark:hover:bg-neutral-800/60 dark:focus-within:bg-neutral-800/60">
                                        <input type="checkbox" checked={selected.has(contact.jid)} onChange={() => toggle(contact.jid)} className="h-5 w-5 rounded border-neutral-300 text-brand-600 focus:ring-brand-500" />
                                        <span className="min-w-0 flex-1"><span className="block truncate font-medium text-neutral-900 dark:text-neutral-100">{contact.name}</span><span className="block text-sm tabular-nums text-neutral-500 dark:text-neutral-400">{contact.phone_e164}</span>{contact.group_names?.length > 0 && <span className="mt-0.5 block truncate text-xs text-neutral-500 dark:text-neutral-400">Grupos: {contact.group_names.join(', ')}</span>}</span>
                                        <span className={`rounded-full px-2.5 py-1 text-xs font-medium ${contact.status === 'new' ? 'bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-300' : 'bg-neutral-100 text-neutral-700 dark:bg-neutral-800 dark:text-neutral-300'}`}>{contact.status === 'new' ? 'Novo' : 'Já cadastrado'}</span>
                                    </label>
                                ))}
                                {visible.length === 0 && <p className="p-10 text-center text-sm text-neutral-500 dark:text-neutral-400">Nenhum contato corresponde aos filtros.</p>}
                            </div>

                            {pageCount > 1 && <nav aria-label="Paginação dos contatos" className="flex items-center justify-between border-t border-neutral-200 px-5 py-4 dark:border-neutral-800"><button type="button" disabled={page === 1} onClick={() => setPage(value => Math.max(1, value - 1))} className="min-h-11 rounded-xl border border-neutral-300 px-4 text-sm disabled:opacity-40 dark:border-neutral-700">Anterior</button><span className="text-sm text-neutral-500">Página {page} de {pageCount}</span><button type="button" disabled={page === pageCount} onClick={() => setPage(value => Math.min(pageCount, value + 1))} className="min-h-11 rounded-xl border border-neutral-300 px-4 text-sm disabled:opacity-40 dark:border-neutral-700">Próxima</button></nav>}
                        </section>

                        <section className="rounded-2xl border border-neutral-200 bg-white p-5 shadow-sm dark:border-neutral-800 dark:bg-neutral-900">
                            <h2 className="text-base font-semibold text-neutral-900 dark:text-neutral-100">3. Defina como salvar</h2>
                            <div className="mt-4 grid gap-4 md:grid-cols-2">
                                <label className="block"><span className="text-sm font-medium text-neutral-700 dark:text-neutral-300">Etiqueta opcional</span><select value={tagId} onChange={event => setTagId(event.target.value)} className="mt-1 min-h-11 w-full rounded-xl border-neutral-300 bg-white text-sm dark:border-neutral-700 dark:bg-neutral-800 dark:text-white"><option value="">Sem etiqueta</option>{tags.map(tag => <option key={tag.id} value={tag.id}>{tag.name}</option>)}</select></label>
                                <label className="block"><span className="text-sm font-medium text-neutral-700 dark:text-neutral-300">Grupo de contatos opcional</span><select value={segmentId} onChange={event => setSegmentId(event.target.value)} className="mt-1 min-h-11 w-full rounded-xl border-neutral-300 bg-white text-sm dark:border-neutral-700 dark:bg-neutral-800 dark:text-white"><option value="">Sem grupo</option>{segments.map(segment => <option key={segment.id} value={segment.id}>{segment.name}</option>)}</select></label>
                            </div>
                            <div className="mt-5 space-y-3">
                                <label className="flex cursor-pointer items-start gap-3 rounded-xl border border-neutral-200 p-4 dark:border-neutral-700"><input type="checkbox" checked={updateNames} onChange={event => setUpdateNames(event.target.checked)} className="mt-0.5 h-5 w-5 rounded text-brand-600 focus:ring-brand-500" /><span><strong className="block text-sm text-neutral-900 dark:text-neutral-100">Atualizar nomes já cadastrados</strong><span className="mt-1 block text-sm text-neutral-500 dark:text-neutral-400">Substitui o nome atual pelo nome exibido no WhatsApp.</span></span></label>
                                <label className="flex cursor-pointer items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4 dark:border-amber-900 dark:bg-amber-950/20"><input type="checkbox" checked={hasConsent} onChange={event => setHasConsent(event.target.checked)} className="mt-0.5 h-5 w-5 rounded text-brand-600 focus:ring-brand-500" /><span><strong className="block text-sm text-amber-950 dark:text-amber-200">Tenho autorização destes contatos para receber mensagens</strong><span className="mt-1 block text-sm text-amber-800 dark:text-amber-300">Marque somente se houve consentimento. Sem isso, os contatos são salvos, mas não entram automaticamente em campanhas.</span></span></label>
                            </div>
                            <div className="mt-5 flex flex-wrap items-center justify-between gap-3 border-t border-neutral-200 pt-5 dark:border-neutral-800"><p className="flex items-center gap-2 text-sm text-neutral-500 dark:text-neutral-400"><Info className="h-4 w-4" aria-hidden="true" />A importação nunca apaga contatos existentes.</p><button type="button" onClick={importSelected} disabled={importing || selected.size === 0} className="inline-flex min-h-11 items-center gap-2 rounded-xl bg-brand-600 px-5 text-sm font-semibold text-white transition hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 dark:focus:ring-offset-neutral-900">{importing && <LoaderCircle className="h-4 w-4 animate-spin motion-reduce:animate-none" aria-hidden="true" />}{importing ? 'Importando…' : `Importar ${selected.size} contato(s)`}</button></div>
                        </section>
                    </>
                )}
            </main>
        </ClientLayout>
    );
}
