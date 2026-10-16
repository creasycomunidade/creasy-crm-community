import { useState } from 'react';
import AdminLayout from '@/Layouts/AdminLayout';
import { Head, useForm } from '@inertiajs/react';
import { Button, Card } from '@/Components/ui';
import axios from 'axios';
import { Wifi, WifiOff, CheckCircle, XCircle, Eye, EyeOff } from 'lucide-react';
import { useTranslation } from 'react-i18next';

function Field({ label, name, value, onChange, type = 'text', hint }) {
    const [show, setShow] = useState(false);
    const isPassword = type === 'password';
    const inputType = isPassword && show ? 'text' : type;

    return (
        <div>
            <label className="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-1">
                {label}
            </label>
            <div className="relative">
                <input
                    type={inputType}
                    name={name}
                    value={value}
                    onChange={onChange}
                    autoComplete="off"
                    className="block w-full rounded-soft border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 px-3 py-2 text-sm text-neutral-900 dark:text-neutral-100 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 pr-10"
                />
                {isPassword && (
                    <button
                        type="button"
                        onClick={() => setShow((v) => !v)}
                        className="absolute inset-y-0 right-2 flex items-center text-neutral-400 hover:text-neutral-600"
                        tabIndex={-1}
                    >
                        {show ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                    </button>
                )}
            </div>
            {hint && <p className="mt-1 text-xs text-neutral-400">{hint}</p>}
        </div>
    );
}

export default function PusherSettingsIndex({ settings = {}, configured = false, missingFields = [], flash = {} }) {
    const { t } = useTranslation();
    const { data, setData, put, processing, errors } = useForm({
        pusher_app_id:      settings.pusher_app_id      ?? '',
        pusher_app_key:     settings.pusher_app_key     ?? '',
        pusher_app_secret:  settings.pusher_app_secret  ?? '',
        pusher_app_cluster: settings.pusher_app_cluster ?? '',
        pusher_enabled:     settings.pusher_enabled      ?? 'false',
    });

    const [testState, setTestState] = useState(null); // null | 'loading' | 'ok' | 'fail'
    const [testMsg, setTestMsg]     = useState('');

    const handleChange = (e) => setData(e.target.name, e.target.value);

    const handleSubmit = (e) => {
        e.preventDefault();
        put(route('admin.pusher-settings.update'));
    };

    const handleTest = async () => {
        setTestState('loading');
        setTestMsg('');
        try {
            const { data: res } = await axios.post(route('admin.pusher-settings.test'));
            setTestState('ok');
            setTestMsg(res.message);
        } catch (e) {
            setTestState('fail');
            setTestMsg(e?.response?.data?.message || t('pusher.connection_failed'));
        }
    };

    const isEnabled = data.pusher_enabled === 'true';

    return (
        <AdminLayout title={t('pusher.title')}>
            <Head title={t('pusher.title')} />

            <div className="max-w-2xl space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h2 className="text-xl font-semibold text-neutral-900 dark:text-neutral-100">{t('pusher.config_title')}</h2>
                        <p className="mt-1 text-sm text-neutral-500 dark:text-neutral-400">{t('pusher.config_desc')}</p>
                    </div>
                    <span className={`inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-medium ${
                        configured && isEnabled
                            ? 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300'
                            : 'bg-neutral-100 text-neutral-600 dark:bg-neutral-700 dark:text-neutral-300'
                    }`}>
                        {configured && isEnabled
                            ? <><Wifi className="h-3.5 w-3.5" /> {t('pusher.status_active')}</>
                            : <><WifiOff className="h-3.5 w-3.5" /> {configured ? t('pusher.status_disabled') : t('pusher.status_not_configured')}</>
                        }
                    </span>
                </div>

                {flash?.success && (
                    <div className="flex items-center gap-2 rounded-soft-lg bg-green-50 dark:bg-green-900/30 text-green-800 dark:text-green-200 px-4 py-2 text-sm">
                        <CheckCircle className="h-4 w-4 flex-shrink-0" />
                        {flash.success}
                    </div>
                )}

                {flash?.error && (
                    <div className="flex items-center gap-2 rounded-soft-lg bg-red-50 dark:bg-red-900/30 text-red-800 dark:text-red-200 px-4 py-2 text-sm">
                        <XCircle className="h-4 w-4 flex-shrink-0" />
                        {flash.error}
                    </div>
                )}

                {!configured && (
                    <div className="rounded-soft-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-200">
                        <p className="font-medium">Configuração incompleta</p>
                        <p className="mt-1">
                            Preencha {missingFields.length > 0 ? missingFields.join(', ') : 'todas as credenciais'}.
                            O App Secret é obrigatório e fica armazenado de forma criptografada.
                        </p>
                    </div>
                )}

                <Card>
                    <Card.Body>
                        <form onSubmit={handleSubmit} className="space-y-5">
                            <div className="flex items-center justify-between pb-3 border-b border-neutral-200 dark:border-neutral-700">
                                <div>
                                    <p className="text-sm font-medium text-neutral-900 dark:text-neutral-100">{t('pusher.enable_pusher')}</p>
                                    <p className="text-xs text-neutral-400">{t('pusher.enable_pusher_desc')}</p>
                                </div>
                                <button
                                    type="button"
                                    onClick={() => setData('pusher_enabled', isEnabled ? 'false' : 'true')}
                                    className={`relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 focus:outline-none ${
                                        isEnabled ? 'bg-green-500' : 'bg-neutral-300 dark:bg-neutral-600'
                                    }`}
                                >
                                    <span className={`pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ${isEnabled ? 'translate-x-5' : 'translate-x-0'}`} />
                                </button>
                            </div>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <Field
                                    label={t('pusher.app_id')}
                                    name="pusher_app_id"
                                    value={data.pusher_app_id}
                                    onChange={handleChange}
                                    hint={t('pusher.app_id_hint')}
                                />
                                <Field
                                    label={t('pusher.app_key')}
                                    name="pusher_app_key"
                                    value={data.pusher_app_key}
                                    onChange={handleChange}
                                    hint={t('pusher.app_key_hint')}
                                />
                                <Field
                                    label={t('pusher.app_secret')}
                                    name="pusher_app_secret"
                                    value={data.pusher_app_secret}
                                    onChange={handleChange}
                                    type="password"
                                    hint={t('pusher.app_secret_hint')}
                                />
                                <Field
                                    label={t('pusher.cluster')}
                                    name="pusher_app_cluster"
                                    value={data.pusher_app_cluster}
                                    onChange={handleChange}
                                    hint={t('pusher.cluster_hint')}
                                />
                            </div>

                            {Object.keys(errors).length > 0 && (
                                <ul className="text-sm text-red-600 dark:text-red-400 space-y-0.5">
                                    {Object.values(errors).map((e, i) => <li key={i}>{e}</li>)}
                                </ul>
                            )}

                            <div className="flex flex-wrap items-center gap-3 pt-2">
                                <Button type="submit" disabled={processing}>
                                    {processing ? t('pusher.saving') : t('pusher.save_settings')}
                                </Button>

                                {configured && (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={handleTest}
                                        disabled={testState === 'loading'}
                                    >
                                        {testState === 'loading' ? t('pusher.testing') : t('pusher.test_connection')}
                                    </Button>
                                )}
                            </div>

                            {testState && testState !== 'loading' && (
                                <div className={`flex items-center gap-2 text-sm mt-2 ${testState === 'ok' ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'}`}>
                                    {testState === 'ok'
                                        ? <CheckCircle className="h-4 w-4 flex-shrink-0" />
                                        : <XCircle className="h-4 w-4 flex-shrink-0" />
                                    }
                                    {testMsg}
                                </div>
                            )}
                        </form>
                    </Card.Body>
                </Card>

                <Card>
                    <Card.Body className="space-y-3">
                        <h3 className="text-sm font-semibold text-neutral-900 dark:text-neutral-100">{t('pusher.where_to_find')}</h3>
                        <ol className="text-sm text-neutral-500 dark:text-neutral-400 space-y-1 list-decimal list-inside">
                            <li>{t('pusher.step_1')}</li>
                            <li>{t('pusher.step_2')}</li>
                            <li>{t('pusher.step_3')}</li>
                        </ol>
                    </Card.Body>
                </Card>
            </div>
        </AdminLayout>
    );
}
