import { Link } from '@inertiajs/react';

export default function AgendaPageHeader({ title, description, action, actionHref, actionIcon: ActionIcon }) {
    return (
        <header className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p className="text-xs font-semibold uppercase tracking-wider text-brand-600">Agenda</p>
                <h1 className="mt-1 text-2xl font-semibold text-neutral-950 dark:text-white">{title}</h1>
                {description && <p className="mt-1 max-w-3xl text-sm text-neutral-500 dark:text-neutral-400">{description}</p>}
            </div>
            {action && (actionHref ? <Link href={actionHref} className="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-brand-600 px-4 text-sm font-semibold text-white transition hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2">{ActionIcon && <ActionIcon className="h-4 w-4" />}{action}</Link> : action)}
        </header>
    );
}
