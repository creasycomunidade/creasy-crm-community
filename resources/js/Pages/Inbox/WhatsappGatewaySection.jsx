import { useEffect, useState } from 'react';
import { Link, router, useForm } from '@inertiajs/react';
import axios from 'axios';

const labels = { waha: 'WAHA', evolution_api: 'Evolution API' };
const buttonClass = 'min-h-11 rounded-lg border border-neutral-300 px-3 py-2 text-sm hover:bg-neutral-100 disabled:opacity-50 dark:border-neutral-600 dark:hover:bg-neutral-800';

function GatewayAccount({ account, renderAccountSettings }) {
    const [busy, setBusy] = useState(false);
    const [qr, setQr] = useState(null);
    const [status, setStatus] = useState(account.status);
    const [feedback, setFeedback] = useState('');
    const [confirmDelete, setConfirmDelete] = useState(false);
    useEffect(() => setStatus(account.status), [account.status]);
    const check = async (action) => {
        setBusy(true);
        setFeedback('');
        if (action === 'qr') setQr(null);
        try {
            const { data } = await axios.get(route(`client.whatsapp.gateway.${action}`, account.id));
            if (action === 'qr') {
                setQr(data.qr);
                setFeedback(data.message || 'Leia o QR Code em WhatsApp → Aparelhos conectados. Depois, atualize o status.');
            } else {
                setStatus(data.status);
                if (data.status === 'active') setQr(null);
                setFeedback(data.status === 'active' ? 'WhatsApp conectado e disponível para atendimento na caixa de entrada.' : `Conexão: ${data.connection_status}`);
            }
        } catch (error) {
            setFeedback(error.response?.data?.message || 'Não foi possível acessar o gateway. Tente novamente.');
        } finally {
            setBusy(false);
        }
    };
    const mutate = (action) => {
        setBusy(true);
        setQr(null);
        const options = { preserveScroll: true, onFinish: () => { setBusy(false); setConfirmDelete(false); } };
        if (action === 'destroy') router.delete(route('client.whatsapp.gateway.destroy', account.id), options);
        else router.post(route('client.whatsapp.gateway.reconnect', account.id), {}, options);
    };
    return (
        <div className="space-y-3 rounded-xl border border-neutral-200 p-4 dark:border-neutral-700">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <span className="font-medium">{account.display_name}</span>
                <span className="text-sm">{status === 'active' ? 'Conectado' : status === 'error' ? 'Erro de conexão' : 'Desconectado'}</span>
            </div>
            <div className="flex flex-wrap gap-2">
                <button type="button" disabled={busy} className={buttonClass} onClick={() => check('qr')}>Abrir QR Code</button>
                <button type="button" disabled={busy} className={buttonClass} onClick={() => check('status')}>Atualizar status</button>
                <button type="button" disabled={busy} className={buttonClass} onClick={() => mutate('reconnect')}>Reconectar</button>
                <button type="button" disabled={busy} className={buttonClass} onClick={() => setConfirmDelete(true)}>Remover</button>
            </div>
            <Link className={buttonClass + ' inline-flex items-center'} href={route('client.inbox.index', { account_id: account.id })}>Abrir caixa de entrada</Link>
            {renderAccountSettings?.(account)}
            {busy && <p role="status" className="text-sm">Aguarde…</p>}
            {feedback && <p role="status" className="text-sm">{feedback}</p>}
            {qr && <img src={qr} alt={`QR Code para conectar ${account.display_name}`} className="h-64 w-64 max-w-full rounded-lg bg-white p-3" />}
            {confirmDelete && <div className="space-y-2 text-sm">
                <p>Remover esta conexão e a sessão no gateway?</p>
                <div className="flex gap-2">
                    <button type="button" className={buttonClass} disabled={busy} onClick={() => mutate('destroy')}>Confirmar remoção</button>
                    <button type="button" className={buttonClass} disabled={busy} onClick={() => setConfirmDelete(false)}>Cancelar</button>
                </div>
            </div>}
        </div>
    );
}

export default function WhatsappGatewaySection({ provider, accounts = [], configured = false, renderAccountSettings }) {
    const form = useForm({ provider, name: '' });
    return (
        <div className="space-y-4 text-neutral-800 dark:text-neutral-200">
            <p className="text-sm text-neutral-600 dark:text-neutral-400">Canal de atendimento pelo WhatsApp: receba e responda conversas na caixa de entrada, configure horários, equipe e chatbot. Também disponível para campanhas.</p>
            {accounts.map(account => <GatewayAccount key={account.id} account={account} renderAccountSettings={renderAccountSettings} />)}
            {configured ? <form className="space-y-3" onSubmit={event => {
                event.preventDefault();
                form.post(route('client.whatsapp.gateway.store'), { preserveScroll: true, onSuccess: () => form.reset('name') });
            }}>
                <label htmlFor={`${provider}-name`} className="block text-sm font-medium">Nome da conexão {labels[provider]}</label>
                <input id={`${provider}-name`} value={form.data.name} onChange={event => form.setData('name', event.target.value)} required maxLength={80}
                    className="w-full rounded-lg border-neutral-300 bg-white text-sm dark:border-neutral-600 dark:bg-neutral-900" placeholder="WhatsApp Comercial" />
                {Object.values(form.errors).map((error, index) => <p role="alert" key={index} className="text-sm text-red-600 dark:text-red-400">{error}</p>)}
                <button disabled={form.processing} className={buttonClass}>{form.processing ? 'Criando conexão…' : `Conectar ${labels[provider]}`}</button>
            </form> : <p className="text-sm">O administrador precisa configurar a URL e a chave do {labels[provider]} em Integrações.</p>}
        </div>
    );
}
