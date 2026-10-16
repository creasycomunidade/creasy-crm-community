import WhatsappGatewaySection from './WhatsappGatewaySection';
import { Head, router, usePage, Link } from '@inertiajs/react';
import ClientLayout from '@/Layouts/ClientLayout';
import {
    Check, Copy, Link2, AlertTriangle,
    Phone, Inbox, Webhook, FileText,
    Trash2, RefreshCw, Bot, ChevronDown, ExternalLink,
    Edit3, Clock, ShieldCheck, ShieldAlert, Wifi, WifiOff, X, Network, MessageCircle,
} from 'lucide-react';
import { useState, useCallback, useEffect } from 'react';
import { useTranslation } from 'react-i18next';
import axios from 'axios';

/* brand logos (accurate official paths) */

function WhatsAppLogo({ className = 'h-5 w-5' }) {
    return (
        <svg className={className} viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            <rect width="24" height="24" rx="5.5" fill="#25D366"/>
            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z" fill="white"/>
        </svg>
    );
}

function InstagramLogo({ className = 'h-5 w-5' }) {
    return (
        <svg className={className} viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            <defs>
                <radialGradient id="ig1" cx="30%" cy="107%" r="130%">
                    <stop offset="0%" stopColor="#fdf497"/>
                    <stop offset="5%" stopColor="#fdf497"/>
                    <stop offset="45%" stopColor="#fd5949"/>
                    <stop offset="60%" stopColor="#d6249f"/>
                    <stop offset="90%" stopColor="#285AEB"/>
                </radialGradient>
            </defs>
            <rect width="24" height="24" rx="6" fill="url(#ig1)"/>
            <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z" fill="white"/>
        </svg>
    );
}

function MessengerLogo({ className = 'h-5 w-5' }) {
    return (
        <svg className={className} viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            <defs>
                <linearGradient id="msg1" x1="0%" y1="100%" x2="100%" y2="0%">
                    <stop offset="0%" stopColor="#0099FF"/>
                    <stop offset="100%" stopColor="#A033FF"/>
                </linearGradient>
            </defs>
            <rect width="24" height="24" rx="6" fill="url(#msg1)"/>
            <path d="M12 2C6.477 2 2 6.145 2 11.259c0 2.928 1.453 5.544 3.73 7.258V22l3.405-1.869c.91.252 1.872.388 2.865.388 5.523 0 10-4.145 10-9.259C22 6.145 17.523 2 12 2zm.992 12.479l-2.549-2.72-4.976 2.72 5.474-5.809 2.612 2.721 4.911-2.721-5.472 5.809z" fill="white"/>
        </svg>
    );
}

function TelegramLogo({ className = 'h-5 w-5' }) {
    return (
        <svg className={className} viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            <rect width="24" height="24" rx="6" fill="#229ED9"/>
            <path d="M19.62 5.43 16.9 18.24c-.2.9-.74 1.12-1.5.7l-4.14-3.05-2 1.93c-.22.22-.41.41-.84.41l.3-4.22 7.68-6.94c.34-.3-.07-.47-.52-.17l-9.5 5.98-4.09-1.28c-.89-.28-.9-.89.19-1.32l15.97-6.16c.74-.27 1.39.18 1.17 1.31Z" fill="white"/>
        </svg>
    );
}

function TelegramSection({ accounts = [], chatbots = [] }) {
    const { t } = useTranslation();
    const [token, setToken] = useState('');
    const [saving, setSaving] = useState(false);

    const connect = (e) => {
        e.preventDefault();
        if (!token.trim()) return;
        setSaving(true);
        router.post(route('client.telegram.store'), { bot_token: token.trim() }, {
            preserveScroll: true,
            onSuccess: () => setToken(''),
            onFinish: () => setSaving(false),
        });
    };

    return (
        <div className="space-y-4">
            {accounts.length > 0 && (
                <div className="space-y-2">
                    {accounts.map((account) => (
                        <div key={account.id} className="rounded-xl border border-neutral-200 p-3 dark:border-neutral-700">
                            <div className="flex items-center gap-2">
                                <TelegramLogo className="h-7 w-7" />
                                <div className="min-w-0 flex-1">
                                    <p className="truncate text-sm font-medium">{account.display_name}</p>
                                    <StatusBadge status={account.status} />
                                </div>
                                <button
                                    type="button"
                                    onClick={() => router.delete(route('client.telegram.destroy', { channelAccount: account.id }), { preserveScroll: true })}
                                    className="rounded-lg p-2 text-neutral-400 hover:bg-red-50 hover:text-red-600"
                                    title={t('inbox.telegram_disconnect')}
                                >
                                    <Trash2 className="h-4 w-4" />
                                </button>
                            </div>
                            <ChatbotSelector account={account} channelAccountId={account.id} currentChatbotId={account.ai_chatbot_id} chatbots={chatbots} />
                        </div>
                    ))}
                </div>
            )}
            <form onSubmit={connect} className="space-y-3">
                <p className="text-xs leading-relaxed text-neutral-500 dark:text-neutral-400">
                    {t('inbox.telegram_help')}
                </p>
                <div>
                    <label className="mb-1 block text-xs font-medium text-neutral-700 dark:text-neutral-300">{t('inbox.telegram_token')}</label>
                    <input
                        type="password"
                        value={token}
                        onChange={(e) => setToken(e.target.value)}
                        placeholder="123456789:AA..."
                        autoComplete="off"
                        className="w-full rounded-lg border border-neutral-200 bg-white px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-800"
                    />
                </div>
                <button type="submit" disabled={saving || !token.trim()} className="w-full rounded-lg bg-[#229ED9] px-4 py-2 text-sm font-semibold text-white disabled:opacity-50">
                    {saving ? t('inbox.telegram_connecting') : t('inbox.telegram_connect')}
                </button>
            </form>
        </div>
    );
}

const evolutionWebhookEvents = [
    ['MESSAGE', 'Mensagens recebidas'],
    ['SEND_MESSAGE', 'Mensagens enviadas'],
    ['READ_RECEIPT', 'Confirmações de leitura'],
    ['PRESENCE', 'Presença'],
    ['CHAT_PRESENCE', 'Digitando/gravando'],
    ['HISTORY_SYNC', 'Sincronização do histórico'],
    ['CALL', 'Chamadas'],
    ['CONNECTION', 'Estado da conexão'],
    ['QRCODE', 'QR Code'],
];

const evolutionAdvancedOptions = [
    ['always_online', 'Sempre online', 'Mantém a conta aparecendo como conectada.'],
    ['reject_call', 'Rejeitar chamadas', 'Recusa automaticamente chamadas recebidas.'],
    ['read_messages', 'Marcar mensagens como lidas', 'Confirma a leitura automaticamente no WhatsApp.'],
    ['ignore_status', 'Ignorar status', 'Não processa atualizações de status.'],
];

function EvolutionGoSettings({ account }) {
    const initialConnection = account.connection_options || {};
    const initialAdvanced = account.advanced_settings || {};
    const [open, setOpen] = useState(false);
    const [saving, setSaving] = useState(false);
    const [settings, setSettings] = useState({
        events: initialConnection.subscribe || ['MESSAGE', 'READ_RECEIPT', 'CONNECTION', 'QRCODE'],
        rabbitmq: initialConnection.rabbitmq || 'disabled',
        websocket: initialConnection.websocket || 'disabled',
        nats: initialConnection.nats || 'disabled',
        always_online: Boolean(initialAdvanced.always_online),
        reject_call: Boolean(initialAdvanced.reject_call),
        reject_call_message: initialAdvanced.reject_call_message || '',
        read_messages: Boolean(initialAdvanced.read_messages),
        ignore_status: Boolean(initialAdvanced.ignore_status),
    });

    const toggleEvent = eventName => {
        setSettings(current => ({
            ...current,
            events: current.events.includes(eventName)
                ? current.events.filter(item => item !== eventName)
                : [...current.events, eventName],
        }));
    };

    const save = event => {
        event.preventDefault();
        if (settings.events.length === 0) return;
        setSaving(true);
        router.put(route('client.whatsapp.evolution-go.settings', { channelAccount: account.id }), settings, {
            preserveScroll: true,
            onFinish: () => setSaving(false),
        });
    };

    return (
        <div className="mt-3 rounded-xl border border-neutral-200 bg-white dark:border-neutral-700 dark:bg-neutral-900">
            <button type="button" onClick={() => setOpen(value => !value)}
                className="flex w-full items-center justify-between gap-3 px-3 py-2.5 text-left text-xs font-semibold">
                Opções da conexão
                <ChevronDown className={`h-4 w-4 transition-transform ${open ? 'rotate-180' : ''}`} />
            </button>
            {open && (
                <form onSubmit={save} className="space-y-4 border-t border-neutral-200 p-3 dark:border-neutral-700">
                    <div>
                        <p className="text-xs font-semibold">Eventos enviados ao CREASY CRM</p>
                        <p className="mt-1 text-[11px] text-neutral-500">Mantenha “Mensagens recebidas” e “Estado da conexão” ativos para o funcionamento da caixa de entrada.</p>
                        <div className="mt-2 grid gap-2 sm:grid-cols-2">
                            {evolutionWebhookEvents.map(([value, label]) => (
                                <label key={value} className="flex items-center gap-2 rounded-lg border border-neutral-200 px-2.5 py-2 text-xs dark:border-neutral-700">
                                    <input type="checkbox" checked={settings.events.includes(value)} onChange={() => toggleEvent(value)}
                                        className="rounded border-neutral-300 text-brand-600" />
                                    <span>{label}</span>
                                </label>
                            ))}
                        </div>
                        {settings.events.length === 0 && <p className="mt-1 text-[11px] text-red-600">Selecione pelo menos um evento.</p>}
                    </div>

                    <div className="grid gap-3 sm:grid-cols-3">
                        {[
                            ['rabbitmq', 'RabbitMQ'],
                            ['websocket', 'WebSocket'],
                            ['nats', 'NATS'],
                        ].map(([field, label]) => (
                            <label key={field} className="text-xs font-medium">{label}
                                <select value={settings[field]} onChange={event => setSettings(current => ({ ...current, [field]: event.target.value }))}
                                    className="mt-1 w-full rounded-lg border-neutral-200 bg-white text-xs dark:border-neutral-700 dark:bg-neutral-800">
                                    <option value="disabled">Desativado</option>
                                    <option value="enabled">Ativado</option>
                                </select>
                            </label>
                        ))}
                    </div>
                    <p className="text-[11px] text-amber-700 dark:text-amber-400">RabbitMQ, WebSocket e NATS só devem ser ativados quando esses serviços também estiverem configurados no servidor do gateway.</p>

                    <div>
                        <p className="text-xs font-semibold">Configurações avançadas</p>
                        <div className="mt-2 space-y-2">
                            {evolutionAdvancedOptions.map(([field, label, description]) => (
                                <label key={field} className="flex items-start justify-between gap-4 rounded-lg border border-neutral-200 px-3 py-2 dark:border-neutral-700">
                                    <span><span className="block text-xs font-medium">{label}</span><span className="block text-[11px] text-neutral-500">{description}</span></span>
                                    <input type="checkbox" checked={settings[field]}
                                        onChange={event => setSettings(current => ({ ...current, [field]: event.target.checked }))}
                                        className="mt-1 rounded border-neutral-300 text-brand-600" />
                                </label>
                            ))}
                        </div>
                    </div>

                    {settings.reject_call && (
                        <label className="block text-xs font-medium">Mensagem ao rejeitar chamada
                            <input value={settings.reject_call_message}
                                onChange={event => setSettings(current => ({ ...current, reject_call_message: event.target.value }))}
                                maxLength={250} placeholder="Não atendemos chamadas. Envie uma mensagem, por favor."
                                className="mt-1 w-full rounded-lg border-neutral-200 bg-white text-xs dark:border-neutral-700 dark:bg-neutral-800" />
                        </label>
                    )}

                    <button type="submit" disabled={saving || settings.events.length === 0}
                        className="w-full rounded-lg bg-brand-600 px-4 py-2 text-xs font-semibold text-white disabled:opacity-50">
                        {saving ? 'Salvando opções...' : 'Salvar opções da conexão'}
                    </button>
                </form>
            )}
        </div>
    );
}

function EvolutionGoAccount({ account, chatbots = [] }) {
    const { t } = useTranslation();
    const [qr, setQr] = useState(null);
    const [qrError, setQrError] = useState(null);
    const [checking, setChecking] = useState(false);
    const [reconnecting, setReconnecting] = useState(false);
    const qrImage = [qr?.qrcode, qr?.code].find((value) => typeof value === 'string' && value.startsWith('data:image/'));
    const pairingCode = !qrImage
        ? [qr?.passkey_code, qr?.code].find((value) => typeof value === 'string' && /^[A-Z0-9-]{6,16}$/i.test(value))
        : null;

    const loadQr = useCallback(async () => {
        try {
            const response = await fetch(route('client.whatsapp.evolution-go.qr', { channelAccount: account.id }), {
                headers: { Accept: 'application/json' },
            });
            const data = await response.json();
            if (!response.ok) {
                setQrError(data.message || t('inbox.evolution_qr_error'));
                return false;
            }
            setQr(data);
            setQrError(null);
            return Boolean(data.qrcode || data.code || data.passkey_code);
        } catch (error) {
            setQrError(error?.message || t('inbox.evolution_qr_error'));
            return false;
        }
    }, [account.id, t]);

    const reconnect = async () => {
        setReconnecting(true);
        setQr(null);
        setQrError(null);
        try {
            await axios.post(
                route('client.whatsapp.evolution-go.reconnect', { channelAccount: account.id }),
                {},
                { headers: { Accept: 'application/json' } },
            );
            await new Promise(resolve => window.setTimeout(resolve, 1000));
            await loadQr();
        } catch (error) {
            setQrError(error?.response?.data?.message || t('inbox.evolution_qr_error'));
        } finally {
            setReconnecting(false);
        }
    };

    const checkStatus = useCallback(async () => {
        setChecking(true);
        try {
            const response = await fetch(route('client.whatsapp.evolution-go.status', { channelAccount: account.id }), {
                headers: { Accept: 'application/json' },
            });
            const data = await response.json();
            if (data.connected) {
                router.reload({ only: ['evolutionGoAccounts'], preserveScroll: true });
            } else if (!qr) {
                await loadQr();
            }
        } finally {
            setChecking(false);
        }
    }, [account.id, loadQr, qr]);

    useEffect(() => {
        if (account.status === 'active') return undefined;
        loadQr();
        const timer = window.setInterval(checkStatus, 6000);
        return () => window.clearInterval(timer);
    }, [account.status, checkStatus, loadQr]);

    return (
        <div className="rounded-xl border border-amber-200 bg-amber-50/40 p-3 dark:border-amber-900 dark:bg-amber-950/10">
            <div className="flex items-center gap-2">
                <WhatsAppLogo className="h-7 w-7" />
                <div className="min-w-0 flex-1">
                    <p className="truncate text-sm font-medium">{account.display_name}</p>
                    <p className="text-[11px] text-neutral-500">
                        WhatsApp não oficial · {account.status === 'active' ? t('inbox.evolution_connected') : t('inbox.evolution_waiting_qr')}
                    </p>
                </div>
                <button type="button" onClick={checkStatus} disabled={checking}
                    aria-label={t('inbox.evolution_check_status')} aria-busy={checking}
                    className="flex min-h-11 min-w-11 items-center justify-center rounded-lg text-neutral-500 hover:bg-white disabled:opacity-50 dark:hover:bg-neutral-800"
                    title={t('inbox.evolution_check_status')}>
                    <RefreshCw className={`h-4 w-4 ${checking ? 'motion-safe:animate-spin' : ''}`} />
                </button>
                <button type="button"
                    onClick={() => router.delete(route('client.whatsapp.evolution-go.destroy', { channelAccount: account.id }), {
                        preserveScroll: true,
                        onBefore: () => window.confirm(t('inbox.evolution_disconnect_confirm')),
                    })}
                    aria-label={t('inbox.evolution_disconnect')}
                    className="flex min-h-11 min-w-11 items-center justify-center rounded-lg text-neutral-400 hover:bg-red-50 hover:text-red-600"
                    title={t('inbox.evolution_disconnect')}>
                    <Trash2 className="h-4 w-4" />
                </button>
            </div>

            {account.status !== 'active' && (
                <div className="mt-3 rounded-lg border border-neutral-200 bg-white p-3 text-center dark:border-neutral-700 dark:bg-neutral-900">
                    {qrImage ? (
                        <img src={qrImage} alt={t('inbox.evolution_qr_alt')} className="mx-auto h-52 w-52 rounded-lg object-contain" />
                    ) : (
                        <div className="flex h-40 flex-col items-center justify-center gap-2 px-4 text-xs text-neutral-400"
                            role={qrError ? 'alert' : 'status'} aria-live="polite">
                            <RefreshCw className={`h-5 w-5 ${!qrError ? 'motion-safe:animate-spin' : ''}`} />
                            <span>{qrError || t('inbox.evolution_loading_qr')}</span>
                        </div>
                    )}
                    {pairingCode && <CodeField label={t('inbox.evolution_pairing_code')} value={pairingCode} icon={Link2} />}
                    {qr?.passkey_open_url && (
                        <a href={qr.passkey_open_url} target="_blank" rel="noopener noreferrer"
                            className="mt-2 inline-flex items-center gap-1 text-xs font-medium text-brand-600 hover:underline">
                            <ExternalLink className="h-3 w-3" /> {t('inbox.evolution_open_passkey')}
                        </a>
                    )}
                    <button type="button"
                        onClick={reconnect} disabled={reconnecting}
                        aria-busy={reconnecting}
                        className="mt-3 inline-flex min-h-11 items-center px-3 text-xs font-medium text-amber-700 hover:underline disabled:opacity-50 dark:text-amber-400">
                        {reconnecting ? t('inbox.evolution_loading_qr') : t('inbox.evolution_new_qr')}
                    </button>
                </div>
            )}

            <EvolutionGoSettings account={account} />
            <ChatbotSelector account={account} channelAccountId={account.id} currentChatbotId={account.ai_chatbot_id} chatbots={chatbots} />
        </div>
    );
}

function EvolutionGoSection({ accounts = [], configured = false, chatbots = [] }) {
    const { t } = useTranslation();
    const [name, setName] = useState('');
    const [riskAcknowledged, setRiskAcknowledged] = useState(false);
    const [saving, setSaving] = useState(false);

    const connect = (event) => {
        event.preventDefault();
        if (!configured || !name.trim() || !riskAcknowledged) return;
        setSaving(true);
        router.post(route('client.whatsapp.evolution-go.store'), {
            name: name.trim(),
            risk_acknowledged: riskAcknowledged,
        }, {
            preserveScroll: true,
            onSuccess: () => {
                setName('');
                setRiskAcknowledged(false);
            },
            onFinish: () => setSaving(false),
        });
    };

    return (
        <div className="space-y-4">
            <div className="rounded-xl border border-amber-200 bg-amber-50 px-3 py-2.5 text-xs leading-relaxed text-amber-900 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-200">
                <div className="flex gap-2">
                    <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0" />
                    <span>{t('inbox.evolution_risk_warning')}</span>
                </div>
            </div>

            {accounts.map((account) => (
                <EvolutionGoAccount key={account.id} account={account} chatbots={chatbots} />
            ))}

            {!configured ? (
                <div className="rounded-xl border border-neutral-200 p-4 text-sm text-neutral-500 dark:border-neutral-700">
                    {t('inbox.evolution_admin_required')}
                </div>
            ) : (
                <form onSubmit={connect} className="space-y-3">
                    <div>
                        <label className="mb-1 block text-xs font-medium">{t('inbox.evolution_instance_name')}</label>
                        <input value={name} onChange={(event) => setName(event.target.value)}
                            placeholder={t('inbox.evolution_instance_placeholder')}
                            className="w-full rounded-lg border border-neutral-200 bg-white px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-800" />
                    </div>
                    <label className="flex items-start gap-2 text-xs leading-relaxed text-neutral-600 dark:text-neutral-300">
                        <input type="checkbox" checked={riskAcknowledged}
                            onChange={(event) => setRiskAcknowledged(event.target.checked)}
                            className="mt-0.5 rounded border-neutral-300 text-brand-600" />
                        <span>{t('inbox.evolution_risk_ack')}</span>
                    </label>
                    <button type="submit" disabled={saving || !name.trim() || !riskAcknowledged}
                        className="w-full rounded-lg bg-amber-600 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50">
                        {saving ? t('inbox.evolution_creating') : t('inbox.evolution_create')}
                    </button>
                </form>
            )}
        </div>
    );
}

/* â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€ shared helpers â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€ */

function CopyButton({ text }) {
    const { t } = useTranslation();
    const [copied, setCopied] = useState(false);
    const copy = () => navigator.clipboard.writeText(text).then(() => {
        setCopied(true);
        setTimeout(() => setCopied(false), 2000);
    });
    return (
        <button type="button" onClick={copy} title={t('inbox.copy')}
            className="shrink-0 rounded-md p-1.5 text-neutral-400 hover:text-brand-600 hover:bg-brand-50 dark:hover:bg-brand-950/30 dark:hover:text-brand-400 transition-all">
            {copied ? <Check className="h-3.5 w-3.5 text-green-500" /> : <Copy className="h-3.5 w-3.5" />}
        </button>
    );
}

function StatusBadge({ status }) {
    const { t } = useTranslation();
    const map = {
        active:   'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200 dark:bg-emerald-900/30 dark:text-emerald-300 dark:ring-emerald-800',
        inactive: 'bg-neutral-100 text-neutral-500 ring-1 ring-neutral-200 dark:bg-neutral-800 dark:text-neutral-400 dark:ring-neutral-700',
        error:    'bg-red-50 text-red-700 ring-1 ring-red-200 dark:bg-red-900/30 dark:text-red-300 dark:ring-red-800',
    };
    const dot = {
        active: 'bg-emerald-500',
        inactive: 'bg-neutral-400',
        error: 'bg-red-500',
    };
    const labelMap = {
        active:   t('common.active'),
        inactive: t('common.inactive'),
        error:    t('inbox.status_error'),
    };
    return (
        <span className={`inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium ${map[status] ?? map.inactive}`}>
            <span className={`h-1.5 w-1.5 rounded-full ${dot[status] ?? dot.inactive}`} />
            {labelMap[status] ?? status}
        </span>
    );
}

function ChannelCard({ icon: Icon, iconBg, title, count, children, emptyText = '' }) {
    const { t } = useTranslation();
    return (
        <div className="rounded-2xl border bg-white dark:bg-neutral-900 shadow-sm overflow-hidden border-neutral-200 dark:border-neutral-700">
            <div className="flex items-center gap-3 px-5 py-4 border-b border-neutral-100 dark:border-neutral-800">
                <div className={`rounded-xl p-2 ${iconBg}`}>
                    <Icon className="h-4 w-4" />
                </div>
                <div>
                    <h3 className="font-semibold text-neutral-900 dark:text-neutral-100 text-sm">{title}</h3>
                </div>
                {count != null && (
                    <span className={`ml-auto text-xs font-medium px-2 py-0.5 rounded-full ${count > 0 ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300' : 'bg-neutral-100 text-neutral-500 dark:bg-neutral-800 dark:text-neutral-400'}`}>
                        {t('inbox.count_connected', { count })}
                    </span>
                )}
            </div>
            <div className="p-5">
                {children ?? (
                    <div className="text-center py-8">
                        <div className="mx-auto mb-3 rounded-2xl w-12 h-12 flex items-center justify-center bg-white dark:bg-neutral-800 shadow-sm border border-neutral-100 dark:border-neutral-700">
                            <Icon className="h-6 w-6" />
                        </div>
                        <p className="text-sm text-neutral-400 dark:text-neutral-500">{emptyText}</p>
                    </div>
                )}
            </div>
        </div>
    );
}

const zernioChannelPlatforms = [
    ['whatsapp', 'WhatsApp', WhatsAppLogo],
    ['instagram', 'Instagram', InstagramLogo],
    ['facebook', 'Messenger', MessengerLogo],
    ['telegram', 'Telegram', TelegramLogo],
];

function ZernioSection({ zernio = {} }) {
    const [processing, setProcessing] = useState(null);
    const connectionError = zernio.last_error?.replace(/zernio/gi, 'serviço de conexão');
    const submit = (routeName) => {
        setProcessing(routeName);
        router.post(route(routeName), {}, {
            preserveScroll: true,
            onFinish: () => setProcessing(null),
        });
    };

    if (!zernio.available) {
        return <div className="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm leading-6 text-amber-800 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-300">
            A conexão central ainda não está ativa. O administrador precisa salvar e ativar a chave da API em Integrações.
        </div>;
    }

    if (!zernio.connected) {
        return <div className="space-y-4">
            <div className="rounded-xl border border-violet-200 bg-violet-50 p-4 text-sm leading-6 text-violet-900 dark:border-violet-900 dark:bg-violet-950/30 dark:text-violet-200">
                O CREASY CRM criará uma conexão exclusiva para esta empresa usando a chave central do SaaS. A chave não será exibida nem compartilhada.
            </div>
            <button type="button" onClick={() => submit('client.integrations.zernio.provision')}
                disabled={processing !== null} aria-busy={processing !== null}
                className="flex min-h-11 w-full items-center justify-center gap-2 rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-brand-700 disabled:cursor-not-allowed disabled:opacity-50">
                <ShieldCheck className={`h-4 w-4 ${processing ? 'motion-safe:animate-pulse' : ''}`} />
                {processing ? 'Preparando empresa...' : 'Preparar canais'}
            </button>
        </div>;
    }

    return <div className="space-y-4">
        <div className="rounded-xl border border-emerald-200 bg-emerald-50 p-3 dark:border-emerald-900 dark:bg-emerald-950/20">
            <div className="flex items-center gap-2 text-sm font-semibold text-emerald-800 dark:text-emerald-300"><ShieldCheck className="h-4 w-4" /> Perfil isolado ativo</div>
            <p className="mt-1 text-xs text-emerald-700 dark:text-emerald-400">{zernio.profile_name}</p>
        </div>

        <div>
            <p className="mb-2 text-xs font-semibold text-neutral-700 dark:text-neutral-300">Adicionar canal</p>
            <div className="grid gap-2 sm:grid-cols-2">
                {zernioChannelPlatforms.map(([platform, label, Logo]) => (
                    <a key={platform} href={route('client.integrations.zernio.connect', { platform, return_to: 'channels' })}
                        className="flex min-h-11 items-center gap-2 rounded-lg border border-neutral-200 px-3 py-2 text-sm font-medium text-neutral-700 transition hover:border-brand-300 hover:bg-brand-50 hover:text-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500 dark:border-neutral-700 dark:text-neutral-200 dark:hover:bg-brand-950/30">
                        <Logo className="h-5 w-5" /> {label}
                    </a>
                ))}
            </div>
        </div>

        <div>
            <div className="mb-2 flex items-center justify-between gap-2">
                <p className="text-xs font-semibold text-neutral-700 dark:text-neutral-300">Canais conectados</p>
                <button type="button" onClick={() => submit('client.integrations.zernio.sync')}
                    disabled={processing !== null} aria-busy={processing !== null}
                    className="flex min-h-11 items-center gap-1.5 rounded-lg px-2 text-xs font-medium text-brand-600 hover:bg-brand-50 disabled:opacity-50 dark:hover:bg-brand-950/30">
                    <RefreshCw className={`h-3.5 w-3.5 ${processing ? 'motion-safe:animate-spin' : ''}`} /> Sincronizar
                </button>
            </div>
            {zernio.accounts?.length ? <div className="space-y-2">{zernio.accounts.map(account => <div key={account.id} className="flex items-center gap-3 rounded-lg border border-neutral-200 p-3 dark:border-neutral-700">
                <Network className="h-4 w-4 text-brand-600" />
                <div className="min-w-0 flex-1"><p className="truncate text-sm font-medium">{account.display_name || account.username || account.platform}</p><p className="text-xs capitalize text-neutral-500">{account.platform}</p></div>
                <span className="rounded-full bg-emerald-50 px-2 py-1 text-[10px] font-medium text-emerald-700 dark:bg-emerald-950/30 dark:text-emerald-300">Ativo</span>
            </div>)}</div> : <div className="rounded-lg border border-dashed border-neutral-300 p-4 text-center text-sm text-neutral-500 dark:border-neutral-700">Nenhum canal conectado por este serviço ainda.</div>}
        </div>

        {connectionError && <p role="alert" className="rounded-lg bg-red-50 p-3 text-xs text-red-700 dark:bg-red-950/30 dark:text-red-300">{connectionError}</p>}
    </div>;
}

/* â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€ chatbot selector â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€ */

const weekDays = [
    ['monday', 'Segunda-feira'], ['tuesday', 'Terça-feira'], ['wednesday', 'Quarta-feira'],
    ['thursday', 'Quinta-feira'], ['friday', 'Sexta-feira'], ['saturday', 'Sábado'], ['sunday', 'Domingo'],
];

function ChannelOperationsSettings({ account }) {
    const { props } = usePage();
    const users = props.humanAgents ?? [];
    const initial = account?.meta_json?.operations ?? {};
    const defaultHours = Object.fromEntries(weekDays.map(([day], index) => [day, {
        enabled: index < 5,
        start: '08:00',
        end: '18:00',
    }]));
    const [open, setOpen] = useState(false);
    const [saving, setSaving] = useState(false);
    const [timezone, setTimezone] = useState(initial.timezone ?? 'America/Sao_Paulo');
    const [hours, setHours] = useState({ ...defaultHours, ...(initial.business_hours ?? {}) });
    const [allowedIds, setAllowedIds] = useState((initial.allowed_user_ids ?? []).map(Number));
    const [outsideHoursEnabled, setOutsideHoursEnabled] = useState(Boolean(initial.outside_hours_enabled));
    const [outsideHoursMessage, setOutsideHoursMessage] = useState(initial.outside_hours_message ?? 'Olá! Recebemos sua mensagem fora do nosso horário de atendimento. Retornaremos assim que estivermos disponíveis.');

    const save = () => {
        setSaving(true);
        router.patch(route('client.inbox.setup.operations.update', { channelAccount: account.id }), {
            timezone,
            business_hours: hours,
            human_access_mode: 'selected',
            allowed_user_ids: allowedIds,
            outside_hours_enabled: outsideHoursEnabled,
            outside_hours_message: outsideHoursMessage,
            outside_hours_cooldown_hours: 12,
        }, { preserveScroll: true, onFinish: () => setSaving(false) });
    };

    return (
        <div className="mt-2.5 border-t border-neutral-100 pt-2.5 dark:border-neutral-700/50">
            <button type="button" onClick={() => setOpen(value => !value)} className="flex w-full items-center justify-between text-xs font-semibold text-neutral-700 dark:text-neutral-200">
                <span className="flex items-center gap-1.5"><Clock className="h-3.5 w-3.5 text-brand-500" /> Horário e agentes do canal</span>
                <ChevronDown className={`h-3.5 w-3.5 transition ${open ? 'rotate-180' : ''}`} />
            </button>
            {open && (
                <div className="mt-3 space-y-4 rounded-lg border border-neutral-200 bg-white p-3 dark:border-neutral-700 dark:bg-neutral-900">
                    <div>
                        <label className="text-xs font-medium">Fuso horário</label>
                        <select value={timezone} onChange={event => setTimezone(event.target.value)} className="mt-1 w-full rounded-lg border-neutral-200 bg-white text-xs dark:border-neutral-700 dark:bg-neutral-800">
                            <option value="America/Sao_Paulo">América/São Paulo</option>
                            <option value="America/Manaus">América/Manaus</option>
                            <option value="America/Rio_Branco">América/Rio Branco</option>
                            <option value="America/Fortaleza">América/Fortaleza</option>
                        </select>
                    </div>
                    <div className="space-y-2">
                        <p className="text-xs font-semibold">Horário de funcionamento</p>
                        {weekDays.map(([day, label]) => {
                            const value = hours[day] ?? defaultHours[day];
                            return <div key={day} className="grid grid-cols-[1fr_70px_70px] items-center gap-2 text-[11px]">
                                <label className="flex items-center gap-1.5"><input type="checkbox" checked={Boolean(value.enabled)} onChange={event => setHours(current => ({ ...current, [day]: { ...value, enabled: event.target.checked } }))} /> {label}</label>
                                <input type="time" disabled={!value.enabled} value={value.start ?? '08:00'} onChange={event => setHours(current => ({ ...current, [day]: { ...value, start: event.target.value } }))} className="rounded border-neutral-200 px-1 py-1 text-[11px] disabled:opacity-40 dark:border-neutral-700 dark:bg-neutral-800" />
                                <input type="time" disabled={!value.enabled} value={value.end ?? '18:00'} onChange={event => setHours(current => ({ ...current, [day]: { ...value, end: event.target.value } }))} className="rounded border-neutral-200 px-1 py-1 text-[11px] disabled:opacity-40 dark:border-neutral-700 dark:bg-neutral-800" />
                            </div>;
                        })}
                    </div>
                    <div>
                        <label className="text-xs font-semibold">Resposta fora do horário</label>
                        <label className="mt-2 flex items-start gap-2 text-xs">
                            <input type="checkbox" checked={outsideHoursEnabled} onChange={event => setOutsideHoursEnabled(event.target.checked)} className="mt-0.5" />
                            <span>Responder automaticamente quando a empresa estiver fechada</span>
                        </label>
                        {outsideHoursEnabled && <>
                            <textarea value={outsideHoursMessage} onChange={event => setOutsideHoursMessage(event.target.value)} rows={4} maxLength={2000} placeholder="Mensagem enviada fora do horário" className="mt-2 w-full rounded-lg border-neutral-200 bg-white text-xs dark:border-neutral-700 dark:bg-neutral-800" />
                            <p className="mt-1 text-[10px] leading-relaxed text-neutral-500">Esta mensagem pertence ao canal e funciona mesmo sem chatbot. Para evitar repetições, cada conversa recebe no máximo uma resposta a cada 12 horas.</p>
                        </>}
                    </div>
                    <div>
                        <label className="text-xs font-semibold">Usuários com acesso a este canal</label>
                        <p className="mt-1 text-[10px] leading-relaxed text-neutral-500">Administradores sempre veem todos os canais. Marque manualmente os demais usuários que podem visualizar e responder estas conversas.</p>
                        <div className="mt-2 max-h-52 space-y-1.5 overflow-y-auto rounded-lg border border-neutral-200 p-2 dark:border-neutral-700">
                            {users.length > 0 ? users.map(user => <label key={user.id} className="flex min-h-11 cursor-pointer items-center gap-2 rounded-md px-2 text-xs transition hover:bg-neutral-50 dark:hover:bg-neutral-800">
                                <input type="checkbox" checked={allowedIds.includes(Number(user.id))} onChange={event => setAllowedIds(current => event.target.checked ? [...new Set([...current, Number(user.id)])] : current.filter(id => id !== Number(user.id)))} />
                                <span className="min-w-0"><span className="block truncate font-medium">{user.name}</span><span className="block truncate text-[10px] text-neutral-500">{user.email}</span></span>
                            </label>) : <p className="px-2 py-3 text-center text-[11px] text-neutral-500">Nenhum usuário comum ativo. Crie usuários na área Equipe.</p>}
                        </div>
                        {allowedIds.length === 0 && <p className="mt-2 rounded-md bg-amber-50 px-2 py-1.5 text-[10px] leading-relaxed text-amber-800 dark:bg-amber-950/30 dark:text-amber-200">Somente administradores terão acesso a este canal.</p>}
                    </div>
                    <button type="button" onClick={save} disabled={saving || (outsideHoursEnabled && !outsideHoursMessage.trim())} className="min-h-11 w-full rounded-lg bg-brand-600 px-3 py-2 text-xs font-semibold text-white disabled:opacity-50">
                        {saving ? 'Salvando...' : 'Salvar horário, resposta e acesso'}
                    </button>
                </div>
            )}
        </div>
    );
}

function ChatbotSelector({ account, channelAccountId, currentChatbotId, chatbots }) {
    const { t } = useTranslation();
    const [saving, setSaving] = useState(false);
    const [value, setValue] = useState(currentChatbotId ? String(currentChatbotId) : '');

    const handleChange = (e) => {
        const next = e.target.value;
        setValue(next);
        setSaving(true);
        router.patch(
            route('client.inbox.setup.assign-chatbot', { channelAccount: channelAccountId }),
            { chatbot_id: next === '' ? null : Number(next) },
            { preserveScroll: true, onFinish: () => setSaving(false) },
        );
    };

    return (
        <><div className="flex items-center gap-2 mt-2.5 pt-2.5 border-t border-neutral-100 dark:border-neutral-700/50">
            <Bot className="h-3.5 w-3.5 text-brand-500 shrink-0" />
            <div className="relative flex-1">
                <select
                    value={value}
                    onChange={handleChange}
                    disabled={saving}
                    className="w-full appearance-none rounded-lg border border-neutral-200 dark:border-neutral-600 bg-white dark:bg-neutral-800 pl-2.5 pr-7 py-1.5 text-xs disabled:opacity-60 focus:outline-none focus:ring-2 focus:ring-brand-500/30 focus:border-brand-400 transition"
                >
                    <option value="">{t('inbox.no_chatbot')}</option>
                    {chatbots.map(bot => (
                        <option key={bot.id} value={String(bot.id)}>{bot.name}</option>
                    ))}
                </select>
                <ChevronDown className="pointer-events-none absolute right-2 top-1/2 -translate-y-1/2 h-3 w-3 text-neutral-400" />
            </div>
            {saving && <span className="text-xs text-neutral-400 shrink-0">{t('inbox.saving')}</span>}
        </div>{account && <ChannelOperationsSettings account={account} />}</>
    );
}

/* â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€ WhatsApp section â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€ */

/* ─────────────────── Phone number status helpers ─────────────────── */

const NAME_STATUS = {
    APPROVED:                  { labelKey: 'inbox.name_status_approved',      color: 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200 dark:bg-emerald-900/30 dark:text-emerald-300 dark:ring-emerald-800', icon: ShieldCheck },
    AVAILABLE_WITHOUT_MOCK_UP: { labelKey: 'inbox.name_status_approved',      color: 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200 dark:bg-emerald-900/30 dark:text-emerald-300 dark:ring-emerald-800', icon: ShieldCheck },
    PENDING_REVIEW:            { labelKey: 'inbox.name_status_under_review',  color: 'bg-amber-50 text-amber-700 ring-1 ring-amber-200 dark:bg-amber-900/30 dark:text-amber-300 dark:ring-amber-800',           icon: Clock       },
    DECLINED:                  { labelKey: 'inbox.name_status_declined',      color: 'bg-red-50 text-red-700 ring-1 ring-red-200 dark:bg-red-900/30 dark:text-red-300 dark:ring-red-800',                       icon: ShieldAlert  },
    EXPIRED:                   { labelKey: 'inbox.name_status_expired',       color: 'bg-red-50 text-red-700 ring-1 ring-red-200 dark:bg-red-900/30 dark:text-red-300 dark:ring-red-800',                       icon: ShieldAlert  },
};

const QUALITY_COLOR = {
    GREEN:  'text-emerald-600 dark:text-emerald-400',
    YELLOW: 'text-amber-500 dark:text-amber-400',
    RED:    'text-red-500 dark:text-red-400',
};

function NameStatusBadge({ status }) {
    const { t } = useTranslation();
    const info = NAME_STATUS[status];
    if (!info) return null;
    const Icon = info.icon;
    return (
        <span className={`inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-medium ${info.color}`}>
            {Icon && <Icon className="h-2.5 w-2.5 shrink-0" />}
            {t(info.labelKey)}
        </span>
    );
}

function PhoneStatusCard({ num, wabaId, onRefreshed }) {
    const { t } = useTranslation();
    const phoneId = num.phone_number_id ?? num.id;
    const [refreshing, setRefreshing]     = useState(false);
    const [showNameForm, setShowNameForm] = useState(false);
    const [newName, setNewName]           = useState('');
    const [submitting, setSubmitting]     = useState(false);
    const [nameMsg, setNameMsg]           = useState(null);
    const [liveData, setLiveData]         = useState(null);

    const data          = liveData ?? num;
    const nameStatus    = data.name_status ?? null;
    const requestedName = data.requested_verified_name ?? null;
    const verifiedName  = data.verified_name ?? null;
    const qualityRating = data.quality_rating ?? null;
    const accountMode   = data.account_mode ?? null;
    const codeStatus    = data.code_verification_status ?? null;

    const overallStatus = (() => {
        if (nameStatus === 'PENDING_REVIEW') return { label: t('inbox.overall_pending_review'), color: 'bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300', dot: 'bg-amber-400' };
        if (nameStatus === 'DECLINED')       return { label: t('inbox.overall_name_declined'),  color: 'bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-300',         dot: 'bg-red-500'  };
        if (qualityRating === 'RED')         return { label: t('inbox.overall_quality_issue'),  color: 'bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-300',         dot: 'bg-red-500'  };
        if (codeStatus === 'NOT_VERIFIED')   return { label: t('inbox.overall_not_verified'),   color: 'bg-neutral-100 text-neutral-500 dark:bg-neutral-800 dark:text-neutral-400', dot: 'bg-neutral-400' };
        if (nameStatus === 'APPROVED' || nameStatus === 'AVAILABLE_WITHOUT_MOCK_UP')
            return { label: t('common.active'), color: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300', dot: 'bg-emerald-500' };
        return { label: t('inbox.overall_unknown'), color: 'bg-neutral-100 text-neutral-500 dark:bg-neutral-800 dark:text-neutral-400', dot: 'bg-neutral-400' };
    })();

    async function refresh() {
        setRefreshing(true);
        try {
            const res = await fetch(
                route('client.whatsapp.setup.refresh-phone-status', { waba: wabaId, phoneNumberId: phoneId }),
                { method: 'POST', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' } }
            );
            const json = await res.json();
            if (res.ok && json.data) { setLiveData({ ...num, ...json.data }); onRefreshed?.(); }
        } finally { setRefreshing(false); }
    }

    // Auto-fetch from Meta on first render if name_status is unknown.
    useEffect(() => {
        if (!nameStatus && !refreshing) {
            refresh();
        }
    // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [phoneId]);

    const submitNameChange = async () => {
        if (!newName.trim()) return;
        setSubmitting(true);
        setNameMsg(null);
        try {
            const res = await fetch(
                route('client.whatsapp.setup.change-display-name', { waba: wabaId, phoneNumberId: phoneId }),
                {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' },
                    body: JSON.stringify({ name: newName.trim() }),
                }
            );
            let json = {};
            try { json = await res.json(); } catch { /* non-JSON response */ }
            if (res.ok) {
                setNameMsg({ type: 'success', text: json.message ?? t('inbox.submitted_successfully') });
                setLiveData(prev => ({ ...(prev ?? num), name_status: 'PENDING_REVIEW', requested_verified_name: newName.trim() }));
                setShowNameForm(false);
                setNewName('');
            } else {
                setNameMsg({ type: 'error', text: json.error ?? t('inbox.request_failed_http', { status: res.status }) });
            }
        } catch (err) {
            setNameMsg({ type: 'error', text: t('inbox.network_error_detail', { message: err?.message ?? t('inbox.unknown') }) });
        } finally { setSubmitting(false); }
    };

    return (
        <div className="rounded-xl border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800/60 overflow-hidden">
            <div className="flex items-start gap-3 px-3.5 py-3">
                <div className="mt-0.5 rounded-lg bg-green-100 dark:bg-green-900/30 p-1.5 shrink-0">
                    <Phone className="h-3.5 w-3.5 text-green-600 dark:text-green-400" />
                </div>
                <div className="flex-1 min-w-0">
                    <div className="flex items-center gap-2 flex-wrap">
                        <span className="font-semibold text-sm text-neutral-900 dark:text-neutral-100">
                            {data.display_phone ?? data.display_phone_number ?? '—'}
                        </span>
                        <span className={`inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-medium ${overallStatus.color}`}>
                            <span className={`h-1.5 w-1.5 rounded-full ${overallStatus.dot}`} />
                            {overallStatus.label}
                        </span>
                        {qualityRating && (
                            <span className={`text-xs font-medium ${QUALITY_COLOR[qualityRating] ?? 'text-neutral-400'}`}>{qualityRating}</span>
                        )}
                        {accountMode && (
                            <span className="inline-flex items-center gap-1 text-[10px] text-neutral-400">
                                {accountMode === 'LIVE' ? <Wifi className="h-2.5 w-2.5" /> : <WifiOff className="h-2.5 w-2.5" />}
                                {accountMode}
                            </span>
                        )}
                    </div>
                    <div className="flex items-center gap-2 mt-1 flex-wrap">
                        <span className="text-xs text-neutral-500 dark:text-neutral-400">{verifiedName ?? '—'}</span>
                        {nameStatus && <NameStatusBadge status={nameStatus} />}
                    </div>
                    {nameStatus === 'PENDING_REVIEW' && requestedName && requestedName !== verifiedName && (
                        <div className="mt-1 flex items-center gap-1 text-[10px] text-amber-600 dark:text-amber-400">
                            <Clock className="h-3 w-3 shrink-0" />
                            <span>{t('inbox.requested_label')} <strong>{requestedName}</strong> {t('inbox.under_review_suffix')}</span>
                        </div>
                    )}
                    {nameStatus === 'DECLINED' && (
                        <div className="mt-1 flex items-center gap-1 text-[10px] text-red-600 dark:text-red-400">
                            <ShieldAlert className="h-3 w-3 shrink-0" />
                            <span>{t('inbox.name_declined_submit_new')}</span>
                        </div>
                    )}
                    <div className="font-mono text-[10px] text-neutral-400 mt-0.5">{t('inbox.id_label')} {phoneId}</div>
                </div>
                <div className="flex items-center gap-1 shrink-0">
                    <button type="button" onClick={refresh} disabled={refreshing} title={t('inbox.refresh_status_meta')}
                        className="p-1.5 rounded-lg text-neutral-400 hover:text-brand-600 hover:bg-brand-50 dark:hover:bg-brand-950/30 disabled:opacity-50 transition">
                        <RefreshCw className={`h-3.5 w-3.5 ${refreshing ? 'animate-spin' : ''}`} />
                    </button>
                    <button type="button" onClick={() => { setShowNameForm(v => !v); setNameMsg(null); }} title={t('inbox.change_display_name')}
                        className={`p-1.5 rounded-lg transition ${showNameForm ? 'bg-brand-100 dark:bg-brand-900/30 text-brand-600' : 'text-neutral-400 hover:text-brand-600 hover:bg-brand-50 dark:hover:bg-brand-950/30'}`}>
                        <Edit3 className="h-3.5 w-3.5" />
                    </button>
                </div>
            </div>

            {nameMsg && (
                <div className={`flex items-start gap-2 px-3.5 py-2 text-xs border-t ${nameMsg.type === 'success' ? 'bg-emerald-50 dark:bg-emerald-900/20 text-emerald-700 dark:text-emerald-300 border-emerald-100 dark:border-emerald-900/30' : 'bg-red-50 dark:bg-red-900/20 text-red-600 dark:text-red-400 border-red-100 dark:border-red-900/30'}`}>
                    {nameMsg.type === 'success' ? <Check className="h-3.5 w-3.5 mt-0.5 shrink-0" /> : <AlertTriangle className="h-3.5 w-3.5 mt-0.5 shrink-0" />}
                    <span className="flex-1">{nameMsg.text}</span>
                    <button onClick={() => setNameMsg(null)}><X className="h-3.5 w-3.5 opacity-50 hover:opacity-100" /></button>
                </div>
            )}

            {showNameForm && (
                <div className="px-3.5 pb-3.5 pt-2.5 border-t border-neutral-200 dark:border-neutral-700 space-y-2">
                    <p className="text-[10px] font-semibold text-neutral-500 uppercase tracking-wider flex items-center gap-1">
                        <Edit3 className="h-3 w-3" /> {t('inbox.change_display_name')}
                    </p>
                    <p className="text-[10px] text-neutral-400 leading-relaxed">
                        {t('inbox.change_display_name_help')}
                    </p>
                    <div className="flex gap-2">
                        <input type="text" value={newName} onChange={e => setNewName(e.target.value)}
                            placeholder={verifiedName ?? t('inbox.business_name_placeholder')} maxLength={100}
                            className="flex-1 rounded-lg border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 px-2.5 py-1.5 text-xs focus:outline-none focus:ring-2 focus:ring-brand-500/30 focus:border-brand-400" />
                        <button type="button" onClick={submitNameChange} disabled={submitting || !newName.trim()}
                            className="rounded-lg bg-brand-600 hover:bg-brand-700 disabled:opacity-50 text-white text-xs font-medium px-3 py-1.5 transition whitespace-nowrap">
                            {submitting ? t('inbox.submitting') : t('inbox.submit')}
                        </button>
                        <button type="button" onClick={() => { setShowNameForm(false); setNewName(''); }}
                            className="rounded-lg border border-neutral-300 dark:border-neutral-600 px-2 py-1.5 text-xs text-neutral-500 hover:bg-neutral-50 dark:hover:bg-neutral-800 transition">
                            {t('common.cancel')}
                        </button>
                    </div>
                </div>
            )}
        </div>
    );
}

function CodeField({ label, value, icon: Icon }) {
    return (
        <div className="rounded-xl bg-neutral-50 dark:bg-neutral-800/60 border border-neutral-200 dark:border-neutral-700 px-3.5 py-2.5">
            <p className="text-[10px] font-semibold uppercase tracking-wider text-neutral-400 mb-1.5 flex items-center gap-1.5">
                {Icon && <Icon className="h-3 w-3" />} {label}
            </p>
            <div className="flex items-center gap-2">
                <code className="flex-1 min-w-0 text-xs font-mono text-neutral-600 dark:text-neutral-300 break-all leading-relaxed">{value}</code>
                <CopyButton text={value} />
            </div>
        </div>
    );
}

function WabaCard({ waba, webhookGlobalUrl, channelAccounts, chatbots }) {
    const { t } = useTranslation();
    const [confirmDelete, setConfirmDelete] = useState(false);
    const [deleting, setDeleting]           = useState(false);
    const [syncingPhones, setSyncingPhones] = useState(false);
    const [reregistering, setReregistering] = useState(false);
    const [reregisterMsg, setReregisterMsg] = useState(null);
    const phoneList = waba.phone_numbers ?? waba.phoneNumbers ?? [];

    const caByPhone = {};
    (channelAccounts ?? []).forEach(ca => { caByPhone[String(ca.phone_number_id)] = ca; });

    const handleDelete = () => {
        setDeleting(true);
        router.delete(route('client.whatsapp.setup.destroy', { waba: waba.id }), {
            preserveScroll: true,
            onFinish: () => { setDeleting(false); setConfirmDelete(false); },
        });
    };

    const syncPhones = () => {
        setSyncingPhones(true);
        router.post(route('client.whatsapp.setup.sync-phone-numbers', { waba: waba.id }), {}, {
            preserveScroll: true,
            onFinish: () => setSyncingPhones(false),
        });
    };

    const reregisterWebhook = async () => {
        setReregistering(true);
        setReregisterMsg(null);
        try {
            const res = await fetch(route('client.whatsapp.setup.reregister-webhook', { waba: waba.id }), {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                },
            });
            const json = await res.json();
            setReregisterMsg(res.ok ? (json.message ?? t('inbox.webhook_reregistered')) : (json.message ?? t('inbox.failed')));
        } catch {
            setReregisterMsg(t('inbox.network_error'));
        } finally {
            setReregistering(false);
        }
    };

    return (
        <div className="rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800/60 overflow-hidden">
            {/* Header row */}
            <div className="flex items-center justify-between gap-3 px-4 py-3 bg-neutral-50 dark:bg-neutral-800">
                <div className="flex items-center gap-2.5 min-w-0">
                    <div className="rounded-lg bg-white dark:bg-neutral-700 p-1 shadow-sm border border-neutral-100 dark:border-neutral-600">
                        <WhatsAppLogo className="h-5 w-5" />
                    </div>
                    <div className="min-w-0">
                        <div className="flex items-center gap-2 flex-wrap">
                            <span className="text-xs font-semibold text-neutral-500 dark:text-neutral-400 uppercase tracking-wide">WABA</span>
                            <code className="font-mono text-xs text-neutral-600 dark:text-neutral-300">{waba.waba_id}</code>
                            <StatusBadge status={waba.status} />
                        </div>
                    </div>
                </div>
                <div className="flex items-center gap-2 shrink-0">
                    <Link
                        href={route('client.whatsapp.templates.index')}
                        className="flex items-center gap-1.5 rounded-lg border border-neutral-200 dark:border-neutral-600 bg-white dark:bg-neutral-700 px-2.5 py-1.5 text-xs font-medium text-neutral-600 dark:text-neutral-300 hover:border-brand-300 hover:text-brand-600 dark:hover:text-brand-400 transition"
                    >
                        <FileText className="h-3 w-3" /> {t('inbox.templates')}
                    </Link>
                    {confirmDelete ? (
                        <div className="flex items-center gap-1.5">
                            <button onClick={handleDelete} disabled={deleting}
                                className="rounded-lg bg-red-600 px-2.5 py-1.5 text-xs font-medium text-white hover:bg-red-700 disabled:opacity-60 transition">
                                {deleting ? t('inbox.removing') : t('inbox.confirm')}
                            </button>
                            <button onClick={() => setConfirmDelete(false)}
                                className="rounded-lg border border-neutral-200 dark:border-neutral-600 px-2.5 py-1.5 text-xs text-neutral-600 dark:text-neutral-400 hover:bg-neutral-50 dark:hover:bg-neutral-700 transition">
                                {t('common.cancel')}
                            </button>
                        </div>
                    ) : (
                        <button onClick={() => setConfirmDelete(true)}
                            className="rounded-lg border border-red-200 dark:border-red-800 p-1.5 text-red-400 hover:bg-red-50 dark:hover:bg-red-950/40 hover:text-red-600 transition"
                            title={t('inbox.remove_waba')}>
                            <Trash2 className="h-3.5 w-3.5" />
                        </button>
                    )}
                </div>
            </div>

            {confirmDelete && (
                <div className="px-4 py-2.5 flex items-start gap-2 text-xs text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-950/20 border-b border-red-100 dark:border-red-900/30">
                    <AlertTriangle className="h-3.5 w-3.5 mt-0.5 shrink-0" />
                    <span>{t('inbox.remove_waba_warning')}</span>
                </div>
            )}

            <div className="p-4 space-y-4">
                {/* Webhook — always global for embedded signup */}
                <div className="space-y-2">
                    <CodeField label={t('inbox.webhook_url')} value={webhookGlobalUrl} icon={Webhook} />
                    <p className="text-xs text-neutral-500 dark:text-neutral-400">
                        {t('inbox.registered_via_embedded')}
                    </p>
                    <div className="flex items-center gap-2 flex-wrap">
                        <button type="button" onClick={reregisterWebhook} disabled={reregistering}
                            className="flex items-center gap-1 text-xs text-brand-600 dark:text-brand-400 hover:text-brand-700 disabled:opacity-60 font-medium transition">
                            <RefreshCw className={`h-3 w-3 ${reregistering ? 'animate-spin' : ''}`} />
                            {reregistering ? t('inbox.reregistering') : t('inbox.reregister_webhook')}
                        </button>
                        {reregisterMsg && (
                            <span className="text-xs text-neutral-500 dark:text-neutral-400">{reregisterMsg}</span>
                        )}
                    </div>
                </div>

                {/* Phone numbers */}
                <div>
                    <div className="flex items-center justify-between mb-3">
                        <p className="text-xs font-semibold text-neutral-500 dark:text-neutral-400 uppercase tracking-wider flex items-center gap-1.5">
                            <Phone className="h-3 w-3" /> {t('inbox.phone_numbers')}
                            <span className="ml-1 rounded-full bg-neutral-100 dark:bg-neutral-700 px-1.5 py-0.5 text-[10px] font-semibold text-neutral-600 dark:text-neutral-300">{phoneList.length}</span>
                        </p>
                        <button onClick={syncPhones} disabled={syncingPhones}
                            className="flex items-center gap-1 text-xs text-brand-600 dark:text-brand-400 hover:text-brand-700 disabled:opacity-60 font-medium transition">
                            <RefreshCw className={`h-3 w-3 ${syncingPhones ? 'animate-spin' : ''}`} />
                            {syncingPhones ? t('inbox.syncing') : t('inbox.sync_from_meta')}
                        </button>
                    </div>

                    {phoneList.length > 0 ? (
                        <div className="space-y-2">
                            {phoneList.map((num, i) => {
                                const phoneId = num.phone_number_id ?? num.id;
                                const ca = caByPhone[String(phoneId)];
                                return (
                                    <div key={phoneId ?? i}>
                                        <PhoneStatusCard num={num} wabaId={waba.id} onRefreshed={() => {}} />
                                        {ca && (
                                            <div className="px-3.5 pb-2 -mt-1 rounded-b-xl border-x border-b border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800/60">
                                                <span className="flex items-center gap-1 text-xs font-medium text-brand-600 dark:text-brand-400">
                                                    <Inbox className="h-3 w-3" /> {t('inbox.active_in_inbox')}
                                                </span>
                                                <ChatbotSelector account={ca} channelAccountId={ca.id} currentChatbotId={ca.ai_chatbot_id} chatbots={chatbots} />
                                            </div>
                                        )}
                                    </div>
                                );
                            })}
                        </div>
                    ) : (
                        <div className="rounded-xl border border-dashed border-neutral-200 dark:border-neutral-700 py-6 text-center">
                            <Phone className="h-5 w-5 text-neutral-300 dark:text-neutral-600 mx-auto mb-2" />
                            <p className="text-xs text-neutral-400">{t('inbox.no_phone_numbers')}</p>
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}

function WhatsAppSection({ wabas, webhookGlobalUrl, channelAccountsByWaba, chatbots, showForm, setShowForm, metaConfigIdWhatsapp, metaAppId }) {
    const { t } = useTranslation();
    const [waApiError, setWaApiError] = useState(null);
    const [waSubmitting, setWaSubmitting] = useState(false);

    const handleWaEmbeddedCode = useCallback(async (code, wabaId, phoneNumberId = null) => {
        setWaApiError(null);
        if (!wabaId) {
            setWaApiError(t('inbox.could_not_detect_waba'));
            return;
        }
        setWaSubmitting(true);
        try {
            const res = await fetch(route('client.whatsapp.setup.embedded-signup'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ code, waba_id: wabaId, phone_number_id: phoneNumberId }),
            });
            const json = await res.json();
            if (!res.ok) {
                setWaApiError(json.message ?? t('inbox.connection_failed'));
            } else {
                if (json.webhook_warning) setWaApiError(json.webhook_warning);
                router.reload({ preserveScroll: true });
                setShowForm(false);
            }
        } catch {
            setWaApiError(t('inbox.network_error_retry'));
        } finally {
            setWaSubmitting(false);
        }
    }, [setShowForm, t]);

    return (
        <ChannelCard
            icon={WhatsAppLogo}
            iconBg="bg-white dark:bg-neutral-800 shadow-sm border border-neutral-100 dark:border-neutral-700"
            title={t('inbox.whatsapp_business')}
            count={wabas.length}
        >
            {wabas.length > 0 && (
                <div className="space-y-3">
                    {wabas.map(waba => (
                        <WabaCard
                            key={waba.id}
                            waba={waba}
                            webhookGlobalUrl={webhookGlobalUrl}
                            channelAccounts={channelAccountsByWaba?.[waba.id] ?? []}
                            chatbots={chatbots}
                        />
                    ))}
                </div>
            )}

            {!showForm && wabas.length === 0 && (
                <div className="text-center py-8">
                    <div className="mx-auto mb-3 rounded-2xl w-12 h-12 flex items-center justify-center bg-green-100 dark:bg-green-900/30 opacity-60">
                        <WhatsAppLogo className="h-6 w-6" />
                    </div>
                    <p className="text-sm text-neutral-400 dark:text-neutral-500">{t('inbox.no_whatsapp_accounts')}</p>
                </div>
            )}

            {showForm && (
                <div className={`${wabas.length > 0 ? 'mt-4 pt-4 border-t border-neutral-100 dark:border-neutral-800' : ''} space-y-3`}>
                    {metaConfigIdWhatsapp ? (
                        <>
                            <p className="text-xs text-neutral-500 dark:text-neutral-400 leading-relaxed">
                                {t('inbox.authorize_whatsapp_help')}
                            </p>
                            <EmbeddedSignupButton
                                configId={metaConfigIdWhatsapp}
                                appId={metaAppId}
                                channel="whatsapp"
                                label={t('inbox.continue_meta_whatsapp')}
                                color="green"
                                onCode={handleWaEmbeddedCode}
                            />
                            {waSubmitting && <p className="text-xs text-neutral-400">{t('inbox.connecting_whatsapp')}</p>}
                            {waApiError && (
                                <p className="text-xs text-red-500 flex items-start gap-1.5">
                                    <AlertTriangle className="h-3.5 w-3.5 shrink-0 mt-0.5" /> {waApiError}
                                </p>
                            )}
                        </>
                    ) : (
                        <div className="rounded-lg border border-amber-200 dark:border-amber-700 bg-amber-50 dark:bg-amber-900/20 px-3 py-2.5 text-xs text-amber-700 dark:text-amber-300 flex items-start gap-2">
                            <AlertTriangle className="h-3.5 w-3.5 shrink-0 mt-0.5" />
                            <span>{t('inbox.meta_app_not_configured')}</span>
                        </div>
                    )}
                    <button type="button" onClick={() => setShowForm(false)}
                        className="w-full rounded-lg border border-neutral-300 dark:border-neutral-600 px-4 py-2 text-xs text-neutral-700 dark:text-neutral-300 hover:bg-neutral-50 dark:hover:bg-neutral-800 transition">
                        {t('common.cancel')}
                    </button>
                </div>
            )}
        </ChannelCard>
    );
}

/* â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€ Instagram / Messenger sections â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€ */

function AccountRow({ account, channel, chatbots }) {
    const { t } = useTranslation();
    const [confirmDelete, setConfirmDelete] = useState(false);
    const [deleting, setDeleting] = useState(false);
    const pageId = channel === 'instagram'
        ? account.meta_json?.instagram_page_id
        : account.meta_json?.page_id;

    const handleDelete = () => {
        setDeleting(true);
        router.delete(route('client.inbox.setup.destroy', { channelAccount: account.id }), {
            preserveScroll: true,
            onFinish: () => { setDeleting(false); setConfirmDelete(false); },
        });
    };

    return (
        <div className="rounded-xl border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800/60 overflow-hidden">
            <div className="flex items-start justify-between gap-3 px-3.5 py-3">
                <div className="min-w-0 flex-1">
                    <div className="flex items-center gap-2 flex-wrap">
                        <span className="font-semibold text-sm text-neutral-900 dark:text-neutral-100">{account.display_name}</span>
                        <StatusBadge status={account.status} />
                    </div>
                    {pageId && <div className="font-mono text-xs text-neutral-400 mt-0.5">{t('inbox.page_id_label')} {pageId}</div>}
                    <ChatbotSelector
                            account={account}
                            channelAccountId={account.id}
                            currentChatbotId={account.ai_chatbot_id}
                            chatbots={chatbots}
                        />
                </div>
                <div className="flex items-center gap-1.5 shrink-0">
                    {confirmDelete ? (
                        <>
                            <button onClick={handleDelete} disabled={deleting}
                                className="rounded-lg bg-red-600 px-2.5 py-1.5 text-xs font-medium text-white hover:bg-red-700 disabled:opacity-60 transition">
                                {deleting ? t('inbox.removing') : t('inbox.confirm')}
                            </button>
                            <button onClick={() => setConfirmDelete(false)}
                                className="rounded-lg border border-neutral-200 dark:border-neutral-600 px-2.5 py-1.5 text-xs text-neutral-600 dark:text-neutral-400 transition">
                                {t('common.cancel')}
                            </button>
                        </>
                    ) : (
                        <button onClick={() => setConfirmDelete(true)}
                            className="rounded-lg border border-red-200 dark:border-red-800 p-1.5 text-red-400 hover:bg-red-50 dark:hover:bg-red-950/40 hover:text-red-600 transition">
                            <Trash2 className="h-3.5 w-3.5" />
                        </button>
                    )}
                </div>
            </div>
            {confirmDelete && (
                <div className="flex items-start gap-2 px-3.5 py-2 text-xs text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-950/20 border-t border-red-100 dark:border-red-900/30">
                    <AlertTriangle className="h-3.5 w-3.5 mt-0.5 shrink-0" />
                    <span>{t('inbox.account_remove_warning')}</span>
                </div>
            )}
        </div>
    );
}

/* ─────────────────── Meta Embedded Signup helpers ─────────────────── */

/**
 * Listens for the WA_EMBEDDED_SIGNUP postMessage that Meta sends when
 * sessionInfoVersion:'3' is set. Resolves with { waba_id, phone_number_id }.
 */
function waitForWabaSessionInfo(timeout = 120000) {
    return new Promise((resolve, reject) => {
        const timer = setTimeout(() => {
            window.removeEventListener('message', handler);
            reject(new Error('Timed out waiting for WABA session info'));
        }, timeout);

        function handler(event) {
            if (event.origin !== 'https://www.facebook.com') return;
            try {
                const parsed = typeof event.data === 'string' ? JSON.parse(event.data) : event.data;
                if (parsed?.type === 'WA_EMBEDDED_SIGNUP') {
                    clearTimeout(timer);
                    window.removeEventListener('message', handler);
                    resolve(parsed.data ?? {});
                }
            } catch {
                // Ignore unrelated or malformed cross-window messages.
            }
        }

        window.addEventListener('message', handler);
    });
}

function initFbSdk(appId) {
    if (typeof window.FB === 'undefined' || !appId) return false;
    try {
        window.FB.init({ appId, autoLogAppEvents: true, xfbml: false, version: 'v20.0' });
        window.__fbSdkReady = true;
        return true;
    } catch (_) {
        return false;
    }
}

/**
 * Loads the Facebook JS SDK on demand and resolves when FB.init() is done.
 * Safe to call multiple times — returns immediately if SDK is already ready.
 */
function loadFbSdk(appId) {
    if (!appId) {
        return Promise.reject(new Error(
            'Meta App ID is not configured. Ask your administrator to set App ID in Admin → Integrations → Meta App.'
        ));
    }

    if (window.__fbSdkReady && typeof window.FB !== 'undefined') {
        return Promise.resolve();
    }

    if (typeof window.FB !== 'undefined' && initFbSdk(appId)) {
        return Promise.resolve();
    }

    if (window.__fbSdkPromise) return window.__fbSdkPromise;

    const blockedMsg =
        'Facebook SDK could not load. Disable ad blockers or privacy shields for this site, ' +
        'or ask your administrator to allow https://connect.facebook.net in the Content-Security-Policy.';

    window.__fbSdkPromise = new Promise((resolve, reject) => {
        let settled = false;
        const settle = (fn, val) => {
            if (settled) return;
            settled = true;
            clearInterval(poll);
            clearTimeout(timer);
            fn(val);
        };

        const tryReady = () => {
            if (window.__fbSdkReady && typeof window.FB !== 'undefined') {
                settle(resolve);
                return true;
            }
            if (typeof window.FB !== 'undefined' && initFbSdk(appId)) {
                settle(resolve);
                return true;
            }
            return false;
        };

        const poll = setInterval(() => { tryReady(); }, 100);

        const existingScript = document.querySelector('script[src*="connect.facebook.net"]');
        const alreadyInjected = !!existingScript;

        const onScriptError = () => settle(reject, new Error(blockedMsg));
        if (existingScript) {
            existingScript.addEventListener('error', onScriptError, { once: true });
        }

        if (!alreadyInjected) {
            window.fbAsyncInit = () => {
                try {
                    initFbSdk(appId);
                    settle(resolve);
                } catch (e) {
                    settle(reject, e);
                }
            };

            const script = document.createElement('script');
            script.src = 'https://connect.facebook.net/en_US/sdk.js';
            script.async = true;
            script.defer = true;
            script.crossOrigin = 'anonymous';
            script.onerror = onScriptError;
            document.body.appendChild(script);
        }

        const timer = setTimeout(() => {
            settle(reject, new Error(
                'Facebook SDK did not load within 15 seconds. ' + blockedMsg
            ));
        }, 15000);
    });

    window.__fbSdkPromise.catch(() => { window.__fbSdkPromise = null; });

    return window.__fbSdkPromise;
}

function EmbeddedSignupButton({ configId, appId, channel, label, color, onCode, children }) {
    const { t } = useTranslation();
    const { props } = usePage();
    const resolvedAppId = appId || props.metaAppId;
    const [loading, setLoading] = useState(false);
    const [error, setError]     = useState(null);

    const launch = useCallback(async () => {
        setError(null);

        if (!configId) {
            setError(t('inbox.embedded_signup_not_configured'));
            return;
        }

        setLoading(true);

        try {
            await loadFbSdk(resolvedAppId);
        } catch (e) {
            setLoading(false);
            setError(e?.message ?? t('inbox.could_not_load_fb_sdk'));
            return;
        }

        const isWhatsapp = channel === 'whatsapp';
        const sessionInfoPromise = isWhatsapp ? waitForWabaSessionInfo() : Promise.resolve(null);

        const extrasMap = {
            whatsapp:  { setup: {}, featureType: '', sessionInfoVersion: '3' },
            instagram: { feature_type: 'instagram_management' },
            messenger: { feature_type: 'messenger_chat' },
        };

        window.FB.login(
            (response) => {
                if (response.authResponse && response.authResponse.code) {
                    const code = response.authResponse.code;
                    if (isWhatsapp) {
                        sessionInfoPromise
                            .then((info) => {
                                setLoading(false);
                                onCode(code, info?.waba_id ?? null, info?.phone_number_id ?? null);
                            })
                            .catch(() => { setLoading(false); onCode(code, null, null); });
                    } else {
                        setLoading(false);
                        onCode(code);
                    }
                } else {
                    setLoading(false);
                    if (response.status !== 'connected') {
                        setError(t('inbox.authorization_cancelled'));
                    }
                }
            },
            {
                config_id: configId,
                response_type: 'code',
                override_default_response_type: true,
                extras: extrasMap[channel] ?? {},
            },
        );
    }, [configId, resolvedAppId, channel, onCode, t]);

    const colors = {
        green:  'bg-[#25D366] hover:bg-[#1ebe5d] text-white',
        blue:   'bg-[#0866FF] hover:bg-[#0759e0] text-white',
        purple: 'bg-gradient-to-r from-pink-500 to-purple-600 hover:from-pink-600 hover:to-purple-700 text-white',
    };
    return (
        <div className="space-y-2">
            <button
                type="button"
                onClick={launch}
                disabled={loading}
                className={`w-full flex items-center justify-center gap-2 rounded-lg px-4 py-2.5 text-sm font-semibold shadow-sm transition disabled:opacity-60 ${colors[color] ?? colors.blue}`}
            >
                {loading ? (
                    <svg className="animate-spin h-4 w-4" viewBox="0 0 24 24" fill="none"><circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"/><path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/></svg>
                ) : (
                    <svg viewBox="0 0 24 24" className="h-4 w-4 fill-current"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                )}
                {loading ? t('inbox.opening_meta') : label}
            </button>
            {error && (
                <p className="text-xs text-red-500 flex items-start gap-1.5">
                    <AlertTriangle className="h-3.5 w-3.5 shrink-0 mt-0.5" /> {error}
                </p>
            )}
            {children}
        </div>
    );
}

/* ─────────────────── Instagram connect drawer ─────────────────── */

function AddInstagramForm({ onSuccess, metaConfigIdSocial, metaAppId, metaConfigIdWhatsapp }) {
    const { t } = useTranslation();
    const [apiError, setApiError] = useState(null);
    const [submitting, setSubmitting] = useState(false);
    const configMismatch = metaConfigIdSocial && metaConfigIdWhatsapp && metaConfigIdSocial === metaConfigIdWhatsapp;

    const handleEmbeddedCode = useCallback(async (code) => {
        setApiError(null);
        setSubmitting(true);
        try {
            const res = await fetch(route('client.inbox.setup.embedded-signup.instagram'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ code }),
            });
            const json = await res.json();
            if (!res.ok) {
                setApiError(json.message ?? t('inbox.connection_failed'));
            } else {
                router.reload({ preserveScroll: true });
                onSuccess?.();
            }
        } catch {
            setApiError(t('inbox.network_error_retry'));
        } finally {
            setSubmitting(false);
        }
    }, [onSuccess, t]);

    if (!metaConfigIdSocial) {
        return (
            <div className="rounded-lg border border-amber-200 dark:border-amber-700 bg-amber-50 dark:bg-amber-900/20 px-3 py-2.5 text-xs text-amber-700 dark:text-amber-300 flex items-start gap-2">
                <AlertTriangle className="h-3.5 w-3.5 shrink-0 mt-0.5" />
                <span>{t('inbox.meta_app_social_not_configured')}</span>
            </div>
        );
    }

    return (
        <div className="space-y-3">
            {configMismatch && (
                <div className="rounded-lg border border-amber-200 dark:border-amber-700 bg-amber-50 dark:bg-amber-900/20 px-3 py-2.5 text-xs text-amber-700 dark:text-amber-300 flex items-start gap-2">
                    <AlertTriangle className="h-3.5 w-3.5 shrink-0 mt-0.5" />
                    <span><strong>{t('inbox.misconfiguration_label')}</strong> {t('inbox.config_mismatch_instagram')}</span>
                </div>
            )}
            <p className="text-xs text-neutral-500 dark:text-neutral-400 leading-relaxed">
                {t('inbox.authorize_instagram_help')}
            </p>
            <EmbeddedSignupButton
                configId={metaConfigIdSocial}
                appId={metaAppId}
                channel="instagram"
                label={t('inbox.continue_meta_instagram')}
                color="purple"
                onCode={handleEmbeddedCode}
            />
            {submitting && <p className="text-xs text-neutral-400">{t('inbox.connecting_accounts')}</p>}
            {apiError && (
                <p className="text-xs text-red-500 flex items-start gap-1.5">
                    <AlertTriangle className="h-3.5 w-3.5 shrink-0 mt-0.5" /> {apiError}
                </p>
            )}
        </div>
    );
}

function AddMessengerForm({ onSuccess, metaConfigIdSocial, metaAppId, metaConfigIdWhatsapp }) {
    const { t } = useTranslation();
    const [apiError, setApiError] = useState(null);
    const [submitting, setSubmitting] = useState(false);
    const configMismatch = metaConfigIdSocial && metaConfigIdWhatsapp && metaConfigIdSocial === metaConfigIdWhatsapp;

    const handleEmbeddedCode = useCallback(async (code) => {
        setApiError(null);
        setSubmitting(true);
        try {
            const res = await fetch(route('client.inbox.setup.embedded-signup.messenger'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ code }),
            });
            const json = await res.json();
            if (!res.ok) {
                setApiError(json.message ?? t('inbox.connection_failed'));
            } else {
                router.reload({ preserveScroll: true });
                onSuccess?.();
            }
        } catch {
            setApiError(t('inbox.network_error_retry'));
        } finally {
            setSubmitting(false);
        }
    }, [onSuccess, t]);

    if (!metaConfigIdSocial) {
        return (
            <div className="rounded-lg border border-amber-200 dark:border-amber-700 bg-amber-50 dark:bg-amber-900/20 px-3 py-2.5 text-xs text-amber-700 dark:text-amber-300 flex items-start gap-2">
                <AlertTriangle className="h-3.5 w-3.5 shrink-0 mt-0.5" />
                <span>{t('inbox.meta_app_social_not_configured')}</span>
            </div>
        );
    }

    return (
        <div className="space-y-3">
            {configMismatch && (
                <div className="rounded-lg border border-amber-200 dark:border-amber-700 bg-amber-50 dark:bg-amber-900/20 px-3 py-2.5 text-xs text-amber-700 dark:text-amber-300 flex items-start gap-2">
                    <AlertTriangle className="h-3.5 w-3.5 shrink-0 mt-0.5" />
                    <span><strong>{t('inbox.misconfiguration_label')}</strong> {t('inbox.config_mismatch_messenger')}</span>
                </div>
            )}
            <p className="text-xs text-neutral-500 dark:text-neutral-400 leading-relaxed">
                {t('inbox.authorize_messenger_help')}
            </p>
            <EmbeddedSignupButton
                configId={metaConfigIdSocial}
                appId={metaAppId}
                channel="messenger"
                label={t('inbox.continue_meta_messenger')}
                color="blue"
                onCode={handleEmbeddedCode}
            />
            {submitting && <p className="text-xs text-neutral-400">{t('inbox.connecting_pages')}</p>}
            {apiError && (
                <p className="text-xs text-red-500 flex items-start gap-1.5">
                    <AlertTriangle className="h-3.5 w-3.5 shrink-0 mt-0.5" /> {apiError}
                </p>
            )}
        </div>
    );
}

function ConnectDrawer({ open, onClose, title, icon: Icon, iconBg, children }) {
    if (!open) return null;
    return (
        <>
            <div className="fixed inset-0 z-40 bg-black/50 backdrop-blur-sm" onClick={onClose} />
            <div className="fixed inset-y-0 right-0 z-50 flex w-full max-w-md flex-col bg-white dark:bg-neutral-900 shadow-2xl">
                <div className="flex items-center gap-3 border-b border-neutral-200 dark:border-neutral-700 px-6 py-4">
                    <div className={`rounded-xl p-2 ${iconBg}`}>
                        <Icon className="h-4 w-4" />
                    </div>
                    <h3 className="flex-1 font-semibold text-neutral-900 dark:text-neutral-100">{title}</h3>
                    <button onClick={onClose} className="rounded-lg p-1.5 text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-800 transition">
                        <svg xmlns="http://www.w3.org/2000/svg" className="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M18 6 6 18M6 6l12 12"/></svg>
                    </button>
                </div>
                <div className="flex-1 overflow-y-auto px-6 py-5">
                    {children}
                </div>
            </div>
        </>
    );
}

/* â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€ page root â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€ */

export default function ChannelSetup({
    wabas, whatsappWebhookGlobalUrl,
    channelAccountsByWaba, instagramAccounts, messengerAccounts, telegramAccounts = [],
    evolutionGoAccounts = [], evolutionGoConfigured = false, metaWebhookUrl,
    gatewayAccounts = [], gatewayConfigured = {},
    zernio = {},
    metaAppId = null, metaConfigIdWhatsapp = null, metaConfigIdSocial = null,
    chatbots = [],
}) {
    const { t } = useTranslation();
    const { props } = usePage();
    const flash = props.flash ?? {};

    const [drawer, setDrawer] = useState(null);
    const [showWabaForm, setShowWabaForm] = useState(false);

    const openDrawer = (key) => {
        setDrawer(key);
        if (key === 'whatsapp') setShowWabaForm(true);
    };
    const closeDrawer = () => {
        setDrawer(null);
        setShowWabaForm(false);
    };

    return (
        <ClientLayout title={t('inbox.channel_setup')}>
            <Head title={t('inbox.channel_setup')} />

            {/* Page header */}
            <div className="mb-6">
                <div className="flex items-center justify-between gap-4 flex-wrap">
                    <div>
                        <h2 className="text-xl font-bold text-neutral-900 dark:text-neutral-100">{t('inbox.channel_setup')}</h2>
                        <p className="mt-0.5 text-sm text-neutral-500 dark:text-neutral-400">
                            {t('inbox.channel_setup_subtitle')}
                        </p>
                    </div>
                    <div className="grid w-full grid-cols-1 gap-2 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-6">
                        <button type="button" onClick={() => openDrawer('whatsapp')}
                            className="flex h-11 w-full items-center justify-center gap-1.5 rounded-lg border border-green-200 dark:border-green-800 bg-green-50 dark:bg-green-950/30 px-3 py-2 text-xs font-medium text-green-700 dark:text-green-400 hover:bg-green-100 dark:hover:bg-green-950/50 transition shadow-sm">
                            <WhatsAppLogo className="h-3.5 w-3.5" /> {t('inbox.connect_whatsapp')}
                        </button>
                        <button type="button" onClick={() => openDrawer('messenger')}
                            className="flex h-11 w-full items-center justify-center gap-1.5 rounded-lg border border-blue-200 dark:border-blue-800 bg-blue-50 dark:bg-blue-950/30 px-3 py-2 text-xs font-medium text-blue-700 dark:text-blue-400 hover:bg-blue-100 dark:hover:bg-blue-950/50 transition shadow-sm">
                            <MessengerLogo className="h-3.5 w-3.5" /> {t('inbox.connect_messenger')}
                        </button>
                        <button type="button" onClick={() => openDrawer('instagram')}
                            className="flex h-11 w-full items-center justify-center gap-1.5 rounded-lg border border-pink-200 dark:border-pink-800 bg-pink-50 dark:bg-pink-950/30 px-3 py-2 text-xs font-medium text-pink-700 dark:text-pink-400 hover:bg-pink-100 dark:hover:bg-pink-950/50 transition shadow-sm">
                            <InstagramLogo className="h-3.5 w-3.5" /> {t('inbox.connect_instagram')}
                        </button>
                        <button type="button" onClick={() => openDrawer('telegram')}
                            className="flex h-11 w-full items-center justify-center gap-1.5 rounded-lg border border-sky-200 bg-sky-50 px-3 py-2 text-xs font-medium text-sky-700 hover:bg-sky-100 dark:border-sky-800 dark:bg-sky-950/30 dark:text-sky-400">
                            <TelegramLogo className="h-3.5 w-3.5" /> {t('inbox.connect_telegram')}
                        </button>
                        <button type="button" onClick={() => openDrawer('evolution-go')}
                            className="flex h-11 w-full items-center justify-center gap-1.5 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-medium text-amber-800 hover:bg-amber-100 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-300">
                            <WhatsAppLogo className="h-3.5 w-3.5" /> {t('inbox.connect_evolution_go')}
                        </button>
                        <Link href={route('client.inbox.webchat.index')}
                            className="flex h-11 w-full items-center justify-center gap-1.5 rounded-lg border border-violet-200 bg-violet-50 px-3 py-2 text-xs font-medium text-violet-700 shadow-sm transition hover:bg-violet-100 focus:outline-none focus:ring-2 focus:ring-violet-500 dark:border-violet-800 dark:bg-violet-950/30 dark:text-violet-300">
                            <MessageCircle className="h-3.5 w-3.5" /> Chat do site
                        </Link>
                        
                    </div>
                </div>
            </div>

            {/* Flash message */}
            {flash.success && (
                <div className="mb-4 rounded-xl bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 text-emerald-800 dark:text-emerald-200 px-4 py-3 text-sm flex items-center gap-2">
                    <Check className="h-4 w-4 shrink-0" />
                    {flash.success}
                </div>
            )}
            {flash.error && (
                <div role="alert" className="mb-4 rounded-xl bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-800 dark:text-red-200 px-4 py-3 text-sm flex items-center gap-2">
                    <AlertTriangle className="h-4 w-4 shrink-0" />
                    {flash.error}
                </div>
            )}

            {/* Row 1 — 3-column channel cards */}
            <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">

                {/* WhatsApp */}
                <WhatsAppSection
                    wabas={wabas}
                    webhookGlobalUrl={whatsappWebhookGlobalUrl}
                    channelAccountsByWaba={channelAccountsByWaba ?? {}}
                    chatbots={chatbots}
                    showForm={false}
                    setShowForm={() => {}}
                    metaConfigIdWhatsapp={metaConfigIdWhatsapp}
                    metaAppId={metaAppId}
                />

                {/* Instagram */}
                <ChannelCard
                    icon={InstagramLogo}
                    iconBg="bg-white dark:bg-neutral-800 shadow-sm border border-neutral-100 dark:border-neutral-700"
                    title={t('inbox.instagram_business')}
                    count={instagramAccounts.length}
                >
                    {instagramAccounts.length > 0 ? (
                        <div className="space-y-2">
                            {instagramAccounts.map(a => <AccountRow key={a.id} account={a} channel="instagram" chatbots={chatbots} />)}
                        </div>
                    ) : (
                        <div className="text-center py-8">
                            <div className="mx-auto mb-3 rounded-2xl w-12 h-12 flex items-center justify-center bg-gradient-to-br from-pink-100 to-purple-100 dark:from-pink-900/30 dark:to-purple-900/30 opacity-60">
                                <InstagramLogo className="h-6 w-6" />
                            </div>
                            <p className="text-sm text-neutral-400 dark:text-neutral-500 mb-3">{t('inbox.no_instagram_accounts')}</p>
                            <button onClick={() => openDrawer('instagram')}
                                className="text-xs font-medium text-pink-600 dark:text-pink-400 hover:underline">
                                {t('inbox.plus_connect_instagram')}
                            </button>
                        </div>
                    )}
                </ChannelCard>

                {/* Messenger */}
                <ChannelCard
                    icon={MessengerLogo}
                    iconBg="bg-white dark:bg-neutral-800 shadow-sm border border-neutral-100 dark:border-neutral-700"
                    title={t('inbox.facebook_messenger')}
                    count={messengerAccounts.length}
                >
                    {messengerAccounts.length > 0 ? (
                        <div className="space-y-2">
                            {messengerAccounts.map(a => <AccountRow key={a.id} account={a} channel="messenger" chatbots={chatbots} />)}
                        </div>
                    ) : (
                        <div className="text-center py-8">
                            <div className="mx-auto mb-3 rounded-2xl w-12 h-12 flex items-center justify-center bg-blue-100 dark:bg-blue-900/30 opacity-60">
                                <MessengerLogo className="h-6 w-6" />
                            </div>
                            <p className="text-sm text-neutral-400 dark:text-neutral-500 mb-3">{t('inbox.no_messenger_accounts')}</p>
                            <button onClick={() => openDrawer('messenger')}
                                className="text-xs font-medium text-blue-600 dark:text-blue-400 hover:underline">
                                {t('inbox.plus_connect_messenger')}
                            </button>
                        </div>
                    )}
                </ChannelCard>

                <ChannelCard
                    icon={TelegramLogo}
                    iconBg="bg-white dark:bg-neutral-800 shadow-sm border border-neutral-100 dark:border-neutral-700"
                    title={t('inbox.telegram')}
                    count={telegramAccounts.length}
                >
                    {telegramAccounts.length > 0 ? (
                        <TelegramSection accounts={telegramAccounts} chatbots={chatbots} />
                    ) : (
                        <div className="py-8 text-center">
                            <TelegramLogo className="mx-auto mb-3 h-10 w-10 opacity-60" />
                            <p className="mb-3 text-sm text-neutral-400">{t('inbox.no_telegram_bots')}</p>
                            <button onClick={() => openDrawer('telegram')} className="text-xs font-medium text-sky-600 hover:underline">
                                {t('inbox.connect_telegram')}
                            </button>
                        </div>
                    )}
                </ChannelCard>

                <ChannelCard
                    icon={WhatsAppLogo}
                    iconBg="bg-white dark:bg-neutral-800 shadow-sm border border-amber-200 dark:border-amber-900"
                    title="Evolution API"
                    count={evolutionGoAccounts.filter((account) => account.status === 'active').length + gatewayAccounts.filter(account => account.provider === 'evolution_api' && account.status === 'active').length}
                >
                    {evolutionGoAccounts.length > 0 ? (
                        <EvolutionGoSection accounts={evolutionGoAccounts} configured={evolutionGoConfigured} chatbots={chatbots} />
                    ) : (
                        <div className="py-8 text-center">
                            <WhatsAppLogo className="mx-auto mb-3 h-10 w-10 opacity-60" />
                            <p className="mb-3 text-sm text-neutral-400">{t('inbox.no_evolution_accounts')}</p>
                            <button onClick={() => openDrawer('evolution-go')} className="text-xs font-medium text-amber-700 hover:underline">
                                {t('inbox.connect_evolution_go')}
                            </button>
                        </div>
                    )}
                    {gatewayAccounts.some(account => account.provider === 'evolution_api') && (
                        <WhatsappGatewaySection provider="evolution_api" accounts={gatewayAccounts.filter(account => account.provider === 'evolution_api')} configured={gatewayConfigured.evolution_api} renderAccountSettings={account => <ChatbotSelector account={account} channelAccountId={account.id} currentChatbotId={account.meta_json?.ai_chatbot_id} chatbots={chatbots} />} />
                    )}
                </ChannelCard>

                {['waha'].map(provider => (
                    <ChannelCard key={provider} icon={WhatsAppLogo} iconBg="bg-white dark:bg-neutral-800" title={provider === 'waha' ? 'WAHA' : 'Evolution API'}
                        count={gatewayAccounts.filter(account => account.provider === provider && account.status === 'active').length}>
                        <WhatsappGatewaySection provider={provider} accounts={gatewayAccounts.filter(account => account.provider === provider)} configured={gatewayConfigured[provider]} renderAccountSettings={account => <ChatbotSelector account={account} channelAccountId={account.id} currentChatbotId={account.meta_json?.ai_chatbot_id} chatbots={chatbots} />} />
                    </ChannelCard>
                ))}

                
            </div>

            {/* Row 2 — guide + resources (only shown when no channels connected) */}
            {(wabas.length === 0 && instagramAccounts.length === 0 && messengerAccounts.length === 0 && telegramAccounts.length === 0 && evolutionGoAccounts.length === 0 && gatewayAccounts.length === 0) && (
            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                {/* Setup guide */}
                <div className="md:col-span-2 rounded-2xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900 shadow-sm overflow-hidden">
                    <div className="px-5 py-4 border-b border-neutral-100 dark:border-neutral-800">
                        <h4 className="text-sm font-semibold text-neutral-800 dark:text-neutral-200">{t('inbox.getting_started')}</h4>
                        <p className="text-xs text-neutral-500 dark:text-neutral-400 mt-0.5">{t('inbox.getting_started_subtitle')}</p>
                    </div>
                    <div className="p-5 grid grid-cols-3 gap-4">
                        {[
                            { step: 1, title: t('inbox.step1_title'), desc: t('inbox.step1_desc') },
                            { step: 2, title: t('inbox.step2_title'), desc: t('inbox.step2_desc') },
                            { step: 3, title: t('inbox.step3_title'), desc: t('inbox.step3_desc') },
                        ].map(({ step, title, desc }) => (
                            <div key={step} className="flex flex-col gap-2">
                                <div className="w-7 h-7 rounded-full bg-brand-600 text-white text-xs font-bold flex items-center justify-center shrink-0">{step}</div>
                                <div>
                                    <p className="text-xs font-semibold text-neutral-700 dark:text-neutral-300">{title}</p>
                                    <p className="text-xs text-neutral-500 dark:text-neutral-400 mt-0.5 leading-relaxed">{desc}</p>
                                </div>
                            </div>
                        ))}
                    </div>
                </div>

                {/* Sidebar: webhook + resources */}
                <div className="space-y-4">
                    {metaWebhookUrl && (
                        <div className="rounded-2xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900 shadow-sm p-4 space-y-3">
                            <p className="text-xs font-semibold text-neutral-500 dark:text-neutral-400 uppercase tracking-wider flex items-center gap-1.5">
                                <Webhook className="h-3 w-3" /> {t('inbox.meta_webhook')}
                            </p>
                            <p className="text-xs text-neutral-500 dark:text-neutral-400 leading-relaxed">
                                {t('inbox.meta_webhook_help')}
                            </p>
                            <CodeField label={t('inbox.webhook_url')} value={metaWebhookUrl} icon={Link2} />
                        </div>
                    )}
                    <div className="rounded-2xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900 shadow-sm p-4">
                        <h4 className="text-xs font-semibold text-neutral-500 dark:text-neutral-400 uppercase tracking-wider mb-3">{t('inbox.resources')}</h4>
                        <div className="space-y-2">
                            {[
                                { label: 'WhatsApp Oficial — documentação da Meta', href: 'https://developers.facebook.com/docs/whatsapp' },
                                { label: 'Meta Webhooks guide', href: 'https://developers.facebook.com/docs/graph-api/webhooks' },
                                { label: 'Instagram Messaging API', href: 'https://developers.facebook.com/docs/messenger-platform/instagram' },
                            ].map(({ label, href }) => (
                                <a key={label} href={href} target="_blank" rel="noopener noreferrer"
                                    className="flex items-center gap-2 text-xs text-brand-600 dark:text-brand-400 hover:underline">
                                    <ExternalLink className="h-3 w-3 shrink-0" /> {label}
                                </a>
                            ))}
                        </div>
                    </div>
                </div>
            </div>
            )}

            {/* Connect WhatsApp drawer */}
            <ConnectDrawer open={drawer === 'whatsapp'} onClose={closeDrawer}
                title={t('inbox.connect_whatsapp')}
                icon={WhatsAppLogo}
                iconBg="bg-white dark:bg-neutral-800 shadow-sm border border-neutral-100 dark:border-neutral-700">
                <WhatsAppSection
                    wabas={wabas}
                    webhookGlobalUrl={whatsappWebhookGlobalUrl}
                    channelAccountsByWaba={channelAccountsByWaba ?? {}}
                    chatbots={chatbots}
                    showForm={showWabaForm}
                    setShowForm={setShowWabaForm}
                    metaConfigIdWhatsapp={metaConfigIdWhatsapp}
                    metaAppId={metaAppId}
                />
            </ConnectDrawer>

            {/* Connect Instagram drawer */}
            <ConnectDrawer open={drawer === 'instagram'} onClose={closeDrawer}
                title={t('inbox.connect_instagram')}
                icon={InstagramLogo}
                iconBg="bg-white dark:bg-neutral-800 shadow-sm border border-neutral-100 dark:border-neutral-700">
                <AddInstagramForm onSuccess={closeDrawer} metaConfigIdSocial={metaConfigIdSocial} metaAppId={metaAppId} metaConfigIdWhatsapp={metaConfigIdWhatsapp} />
            </ConnectDrawer>

            {/* Connect Messenger drawer */}
            <ConnectDrawer open={drawer === 'messenger'} onClose={closeDrawer}
                title={t('inbox.connect_messenger')}
                icon={MessengerLogo}
                iconBg="bg-white dark:bg-neutral-800 shadow-sm border border-neutral-100 dark:border-neutral-700">
                <AddMessengerForm onSuccess={closeDrawer} metaConfigIdSocial={metaConfigIdSocial} metaAppId={metaAppId} metaConfigIdWhatsapp={metaConfigIdWhatsapp} />
            </ConnectDrawer>

            <ConnectDrawer open={drawer === 'telegram'} onClose={closeDrawer}
                title={t('inbox.connect_telegram')}
                icon={TelegramLogo}
                iconBg="bg-white dark:bg-neutral-800 shadow-sm border border-neutral-100 dark:border-neutral-700">
                <TelegramSection accounts={telegramAccounts} chatbots={chatbots} />
            </ConnectDrawer>

            <ConnectDrawer open={drawer === 'evolution-go'} onClose={closeDrawer}
                title={t('inbox.connect_evolution_go')}
                icon={WhatsAppLogo}
                iconBg="bg-white dark:bg-neutral-800 shadow-sm border border-amber-200 dark:border-amber-900">
                <EvolutionGoSection accounts={evolutionGoAccounts} configured={evolutionGoConfigured} chatbots={chatbots} />
            </ConnectDrawer>

            
        </ClientLayout>
    );
}
