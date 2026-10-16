import { useState } from 'react';
import AdminLayout from '@/Layouts/AdminLayout';
import { Head } from '@inertiajs/react';
import { Card } from '@/Components/ui';
import { Clock, Terminal, Server, CheckCircle, AlertTriangle, XCircle, Copy, Check } from 'lucide-react';
import { useTranslation } from 'react-i18next';

/** Copyable command/config block. Forced LTR so cron lines stay readable in RTL locales. */
function CodeBlock({ code, label }) {
    const { t } = useTranslation();
    const [copied, setCopied] = useState(false);

    const handleCopy = async () => {
        try {
            await navigator.clipboard.writeText(code);
            setCopied(true);
            setTimeout(() => setCopied(false), 1500);
        } catch {
            // Clipboard unavailable (e.g. insecure context) — silently ignore.
        }
    };

    return (
        <div className="relative" dir="ltr">
            <pre className="overflow-x-auto rounded-soft bg-neutral-900 dark:bg-neutral-950 border border-neutral-800 px-4 py-3 pr-12 text-left text-xs leading-relaxed text-neutral-100 font-mono">
                <code>{code}</code>
            </pre>
            <button
                type="button"
                onClick={handleCopy}
                aria-label={copied ? t('cron.copied') : t('cron.copy')}
                title={copied ? t('cron.copied') : t('cron.copy')}
                className="absolute top-2.5 right-2.5 inline-flex items-center gap-1 rounded-soft px-2 py-1 text-xs text-neutral-300 hover:bg-neutral-700/60 hover:text-white transition"
            >
                {copied ? <Check className="h-3.5 w-3.5" /> : <Copy className="h-3.5 w-3.5" />}
                {label}
            </button>
        </div>
    );
}

/** Map common cron expressions to a readable label; fall back to the raw expression. */
function humanizeExpression(expr, t) {
    const map = {
        '* * * * *': t('cron.freq.every_minute'),
        '0 * * * *': t('cron.freq.hourly'),
        '0 0 * * *': t('cron.freq.daily'),
        '0 0 * * 0': t('cron.freq.weekly'),
    };
    return map[expr] || null;
}

function relativeTime(iso, t) {
    if (!iso) return null;
    const then = new Date(iso).getTime();
    if (Number.isNaN(then)) return null;
    const seconds = Math.max(0, Math.round((Date.now() - then) / 1000));
    if (seconds < 60) return t('cron.time.just_now');
    const minutes = Math.round(seconds / 60);
    if (minutes < 60) return t('cron.time.minutes_ago', { count: minutes });
    const hours = Math.round(minutes / 60);
    if (hours < 24) return t('cron.time.hours_ago', { count: hours });
    const days = Math.round(hours / 24);
    return t('cron.time.days_ago', { count: days });
}

const STATUS = {
    active:   { icon: CheckCircle,   badge: 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300',     note: 'bg-green-50 dark:bg-green-900/20 text-green-800 dark:text-green-200' },
    stale:    { icon: AlertTriangle, badge: 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300',     note: 'bg-amber-50 dark:bg-amber-900/20 text-amber-800 dark:text-amber-200' },
    inactive: { icon: XCircle,       badge: 'bg-neutral-100 text-neutral-600 dark:bg-neutral-700 dark:text-neutral-300', note: 'bg-neutral-50 dark:bg-neutral-800/60 text-neutral-600 dark:text-neutral-300' },
};

export default function CronSetupIndex({
    basePath = '',
    phpBinary = 'php',
    queueConnection = 'redis',
    tasks = [],
    schedulerLastRun = null,
    schedulerStatus = 'inactive',
}) {
    const { t } = useTranslation();

    const cronCommand = `* * * * * php ${basePath}/artisan schedule:run >> /dev/null 2>&1`;

    const queueCommand = `php ${basePath}/artisan queue:work ${queueConnection} --queue=default --tries=3 --max-time=3600`;

    const supervisorConfig = `[program:creasy-crm-worker]
command=${phpBinary} ${basePath}/artisan queue:work ${queueConnection} --queue=default --tries=3 --max-time=3600
directory=${basePath}
user=www-data
numprocs=2
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
redirect_stderr=true
stdout_logfile=${basePath}/storage/logs/worker.log`;

    const status = STATUS[schedulerStatus] || STATUS.inactive;
    const StatusIcon = status.icon;
    const lastRunRel = relativeTime(schedulerLastRun, t);
    const statusDesc = t(`cron.status_${schedulerStatus}_desc`, { time: lastRunRel || '' });

    return (
        <AdminLayout title={t('cron.title')}>
            <Head title={t('cron.title')} />

            <div className="max-w-3xl space-y-6">
                <div className="flex items-start justify-between gap-4">
                    <div>
                        <h2 className="flex items-center gap-2 text-xl font-semibold text-neutral-900 dark:text-neutral-100">
                            <Clock className="h-5 w-5 text-primary-500" />
                            {t('cron.heading')}
                        </h2>
                        <p className="mt-1 text-sm text-neutral-500 dark:text-neutral-400">{t('cron.subheading')}</p>
                    </div>
                    <span className={`inline-flex flex-shrink-0 items-center gap-1.5 rounded-full px-3 py-1 text-xs font-medium ${status.badge}`}>
                        <StatusIcon className="h-3.5 w-3.5" />
                        {t(`cron.status_${schedulerStatus}`)}
                    </span>
                </div>

                {/* Live status / verification */}
                <Card>
                    <Card.Body className="space-y-2">
                        <div className={`flex items-start gap-2 rounded-soft-lg px-4 py-3 text-sm ${status.note}`}>
                            <StatusIcon className="mt-0.5 h-4 w-4 flex-shrink-0" />
                            <div>
                                <p>{statusDesc}</p>
                                <p className="mt-1 text-xs opacity-80">
                                    {t('cron.last_run')}: {lastRunRel || t('cron.never')}
                                </p>
                            </div>
                        </div>
                    </Card.Body>
                </Card>

                {/* Step 1: cron entry */}
                <Card>
                    <Card.Body className="space-y-3">
                        <div>
                            <h3 className="flex items-center gap-2 text-sm font-semibold text-neutral-900 dark:text-neutral-100">
                                <Terminal className="h-4 w-4 text-neutral-400" />
                                {t('cron.step1_title')}
                            </h3>
                            <p className="mt-1 text-sm text-neutral-500 dark:text-neutral-400">{t('cron.step1_desc')}</p>
                        </div>
                        <CodeBlock code={cronCommand} label={t('cron.copy')} />
                    </Card.Body>
                </Card>

                {/* Step 2: queue workers */}
                <Card>
                    <Card.Body className="space-y-3">
                        <div>
                            <h3 className="flex items-center gap-2 text-sm font-semibold text-neutral-900 dark:text-neutral-100">
                                <Server className="h-4 w-4 text-neutral-400" />
                                {t('cron.step2_title')}
                            </h3>
                            <p className="mt-1 text-sm text-neutral-500 dark:text-neutral-400">{t('cron.step2_desc')}</p>
                        </div>
                        <CodeBlock code={queueCommand} label={t('cron.copy')} />

                        <p className="pt-1 text-xs font-medium text-neutral-700 dark:text-neutral-300">{t('cron.supervisor_title')}</p>
                        <p className="text-xs text-neutral-500 dark:text-neutral-400">{t('cron.supervisor_desc')}</p>
                        <CodeBlock code={supervisorConfig} label={t('cron.copy')} />
                    </Card.Body>
                </Card>

                {/* Step 3: verify */}
                <Card>
                    <Card.Body className="space-y-3">
                        <div>
                            <h3 className="flex items-center gap-2 text-sm font-semibold text-neutral-900 dark:text-neutral-100">
                                <CheckCircle className="h-4 w-4 text-neutral-400" />
                                {t('cron.step3_title')}
                            </h3>
                            <p className="mt-1 text-sm text-neutral-500 dark:text-neutral-400">{t('cron.step3_desc')}</p>
                        </div>
                        <div className="space-y-1">
                            <p className="text-xs text-neutral-500 dark:text-neutral-400">{t('cron.verify_run')}</p>
                            <CodeBlock code="php artisan schedule:run" label={t('cron.copy')} />
                        </div>
                        <div className="space-y-1">
                            <p className="text-xs text-neutral-500 dark:text-neutral-400">{t('cron.verify_list')}</p>
                            <CodeBlock code="php artisan schedule:list" label={t('cron.copy')} />
                        </div>
                    </Card.Body>
                </Card>

                {/* Registered scheduled tasks */}
                <Card>
                    <Card.Body className="space-y-3">
                        <div>
                            <h3 className="text-sm font-semibold text-neutral-900 dark:text-neutral-100">{t('cron.tasks_title')}</h3>
                            <p className="mt-1 text-sm text-neutral-500 dark:text-neutral-400">{t('cron.tasks_desc')}</p>
                        </div>

                        {tasks.length === 0 ? (
                            <p className="text-sm text-neutral-400">{t('cron.tasks_empty')}</p>
                        ) : (
                            <div className="overflow-hidden rounded-soft border border-neutral-200 dark:border-neutral-800">
                                <table className="min-w-full divide-y divide-neutral-200 dark:divide-neutral-800 text-sm">
                                    <thead className="bg-neutral-50 dark:bg-neutral-800/50">
                                        <tr>
                                            <th className="px-4 py-2 text-left rtl:text-right font-medium text-neutral-500 dark:text-neutral-400">{t('cron.col_task')}</th>
                                            <th className="px-4 py-2 text-left rtl:text-right font-medium text-neutral-500 dark:text-neutral-400">{t('cron.col_schedule')}</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-neutral-100 dark:divide-neutral-800">
                                        {tasks.map((task, i) => {
                                            const human = humanizeExpression(task.expression, t);
                                            return (
                                                <tr key={i}>
                                                    <td className="px-4 py-2 text-neutral-800 dark:text-neutral-200">{task.description}</td>
                                                    <td className="px-4 py-2 text-neutral-500 dark:text-neutral-400">
                                                        {human && <span className="block">{human}</span>}
                                                        <span dir="ltr" className="block font-mono text-xs text-neutral-400">{task.expression}</span>
                                                    </td>
                                                </tr>
                                            );
                                        })}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </Card.Body>
                </Card>
            </div>
        </AdminLayout>
    );
}
