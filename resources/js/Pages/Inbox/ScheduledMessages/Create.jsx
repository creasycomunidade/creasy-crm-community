import { Head, Link, useForm } from '@inertiajs/react';
import ClientLayout from '@/Layouts/ClientLayout';
import { ScheduledImageField, ScheduledTimingFields } from '@/Components/Inbox/ScheduledMessageFields';
import { ArrowLeft, CalendarClock, MessageSquareText, Search } from 'lucide-react';
import { useMemo, useState } from 'react';

const inFiveMinutes = () => {
    const date = new Date(Date.now() + 5 * 60 * 1000);
    date.setMinutes(Math.ceil(date.getMinutes() / 5) * 5, 0, 0);
    return new Date(date.getTime() - date.getTimezoneOffset() * 60000).toISOString().slice(0, 16);
};

export default function ScheduledMessageCreate({ conversations = [], selectedConversation = '' }) {
    const [search, setSearch] = useState('');
    const [minimumSendAt] = useState(inFiveMinutes);
    const { data, setData, post, processing, errors } = useForm({
        conversation_uuid: selectedConversation,
        body: '',
        image: null,
        send_at: minimumSendAt,
        recurrence_unit: 'once',
        recurrence_interval: 1,
        max_occurrences: '',
        ends_at: '',
    });
    const visibleConversations = useMemo(() => {
        const term = search.trim().toLowerCase();
        if (!term) return conversations;
        return conversations.filter((item) => {
            const contact = item.contact ?? {};
            return `${contact.first_name ?? ''} ${contact.last_name ?? ''} ${contact.phone_e164 ?? ''} ${contact.email ?? ''} ${item.channel_account?.display_name ?? ''}`.toLowerCase().includes(term);
        });
    }, [conversations, search]);

    const submit = (event) => {
        event.preventDefault();
        post(route('client.inbox.scheduled-messages.store'), { forceFormData: true });
    };
    const input = 'mt-1 min-h-11 w-full rounded-xl border border-neutral-300 bg-white px-3 py-2 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-neutral-700 dark:bg-neutral-900';

    return <ClientLayout title="Nova mensagem agendada">
        <Head title="Nova mensagem agendada" />
        <div className="mx-auto max-w-3xl space-y-5">
            <header className="flex items-center gap-3">
                <Link href={route('client.inbox.scheduled-messages.index')} aria-label="Voltar" className="flex h-11 w-11 items-center justify-center rounded-xl border border-neutral-200 transition hover:bg-neutral-50 dark:border-neutral-700 dark:hover:bg-neutral-800"><ArrowLeft className="h-5 w-5" /></Link>
                <div><h1 className="text-xl font-semibold text-neutral-900 dark:text-white">Nova mensagem agendada</h1><p className="text-sm text-neutral-500">Escolha a conversa, o conteúdo e quando o envio deve acontecer.</p></div>
            </header>

            <form onSubmit={submit} className="space-y-5">
                <section className="rounded-xl border border-neutral-200 bg-white p-5 dark:border-neutral-700 dark:bg-neutral-900">
                    <h2 className="flex items-center gap-2 font-semibold"><MessageSquareText className="h-4 w-4 text-brand-600" /> Conversa e mensagem</h2>
                    <label className="mt-4 block text-sm font-medium">Pesquisar conversa
                        <span className="relative block"><Search className="pointer-events-none absolute left-3 top-4 h-4 w-4 text-neutral-400" /><input value={search} onChange={(event) => setSearch(event.target.value)} className={`${input} pl-10`} placeholder="Nome ou telefone do contato" /></span>
                    </label>
                    <label className="mt-4 block text-sm font-medium">Conversa
                        <select required value={data.conversation_uuid} onChange={(event) => setData('conversation_uuid', event.target.value)} className={input}>
                            <option value="">Selecione uma conversa</option>
                            {visibleConversations.map((item) => { const contact = item.contact ?? {}; const name = `${contact.first_name ?? ''} ${contact.last_name ?? ''}`.trim() || contact.phone_e164 || contact.email || 'Contato'; return <option key={item.uuid} value={item.uuid}>{name} · {item.channel_account?.display_name || item.channel_account?.channel}</option>; })}
                        </select>{errors.conversation_uuid && <span className="mt-1 block text-xs text-red-600">{errors.conversation_uuid}</span>}
                    </label>
                    <label className="mt-4 block text-sm font-medium">Mensagem
                        <textarea maxLength={4096} rows={6} value={data.body} onChange={(event) => setData('body', event.target.value)} className={`${input} resize-y`} placeholder="Digite a mensagem que será enviada…" />
                        <span className="mt-1 flex justify-between text-xs text-neutral-500"><span>{errors.body}</span><span>{data.body.length}/4096</span></span>
                    </label>
                    <ScheduledImageField image={data.image} onChange={(image) => setData('image', image)} error={errors.image} />
                </section>

                <section className="rounded-xl border border-neutral-200 bg-white p-5 dark:border-neutral-700 dark:bg-neutral-900">
                    <h2 className="flex items-center gap-2 font-semibold"><CalendarClock className="h-4 w-4 text-brand-600" /> Data e repetição</h2>
                    <ScheduledTimingFields form={data} update={setData} errors={errors} minimumSendAt={minimumSendAt} />
                </section>

                <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end"><Link href={route('client.inbox.scheduled-messages.index')} className="inline-flex min-h-11 items-center justify-center rounded-xl border border-neutral-300 px-5 text-sm font-medium dark:border-neutral-700">Cancelar</Link><button disabled={processing || (!data.body.trim() && !data.image)} className="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-brand-600 px-5 text-sm font-semibold text-white transition hover:bg-brand-700 disabled:cursor-not-allowed disabled:opacity-50"><CalendarClock className="h-4 w-4" />{processing ? 'Agendando…' : 'Agendar mensagem'}</button></div>
            </form>
        </div>
    </ClientLayout>;
}
