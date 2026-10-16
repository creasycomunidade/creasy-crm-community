import ClientLayout from '@/Layouts/ClientLayout';
import { Head, useForm, usePage } from '@inertiajs/react';
import { Button, Card, Input } from '@/Components/ui';
import { Monitor, Globe } from 'lucide-react';
import { useTranslation } from 'react-i18next';

export default function Sessions({ sessions = [] }) {
    const { t } = useTranslation();
    const { flash } = usePage().props;
    const form = useForm({ password: '' });

    const handleRevoke = (e) => {
        e.preventDefault();
        form.delete(route('client.profile.sessions.destroy'), { preserveScroll: true });
    };

    return (
        <ClientLayout title={t('profile.active_sessions')}>
            <Head title={t('profile.active_sessions')} />
            <div className="space-y-6 max-w-2xl">
                {flash?.success && (
                    <div className="rounded-lg bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 px-4 py-3 text-sm text-green-800 dark:text-green-300">
                        {flash.success}
                    </div>
                )}

                <Card>
                    <Card.Body>
                        <h2 className="text-base font-semibold text-neutral-900 dark:text-neutral-100 mb-4">
                            {t('profile.active_sessions')}
                        </h2>
                        <div className="space-y-3">
                            {sessions.map((session) => (
                                <div
                                    key={session.id}
                                    className={`flex items-start gap-3 rounded-lg px-4 py-3 border ${
                                        session.is_current
                                            ? 'border-blue-200 dark:border-blue-700 bg-blue-50 dark:bg-blue-900/20'
                                            : 'border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800/50'
                                    }`}
                                >
                                    <Monitor className="h-5 w-5 mt-0.5 text-neutral-400 shrink-0" />
                                    <div className="flex-1 min-w-0">
                                        <p className="text-sm font-medium text-neutral-800 dark:text-neutral-200 truncate">
                                            {session.user_agent || t('profile.unknown_device')}
                                        </p>
                                        <div className="flex items-center gap-2 mt-0.5 text-xs text-neutral-500 dark:text-neutral-400">
                                            <Globe className="h-3 w-3" />
                                            <span>{session.ip_address || t('profile.unknown_ip')}</span>
                                            <span>·</span>
                                            <span>{session.last_active_at}</span>
                                            {session.is_current && (
                                                <span className="text-blue-600 dark:text-blue-400 font-medium">{t('profile.this_device')}</span>
                                            )}
                                        </div>
                                    </div>
                                </div>
                            ))}
                        </div>

                        {sessions.length > 1 && (
                            <form onSubmit={handleRevoke} className="mt-6 flex gap-3 items-end pt-4 border-t border-neutral-200 dark:border-neutral-700">
                                <Input
                                    label={t('profile.revoke_password_label')}
                                    type="password"
                                    value={form.data.password}
                                    onChange={(e) => form.setData('password', e.target.value)}
                                    className="flex-1 max-w-xs"
                                    error={form.errors.password}
                                />
                                <Button type="submit" variant="destructive" disabled={form.processing}>
                                    {t('profile.revoke_other_sessions')}
                                </Button>
                            </form>
                        )}
                    </Card.Body>
                </Card>
            </div>
        </ClientLayout>
    );
}
