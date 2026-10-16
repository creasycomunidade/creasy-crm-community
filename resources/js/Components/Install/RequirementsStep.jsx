import { Check, X } from 'lucide-react';

function Row({ label, detail, passed }) {
    return (
        <div className="flex items-center justify-between py-1.5 text-sm">
            <span className="text-neutral-700 dark:text-neutral-300">
                {label}
                {detail && <span className="ml-2 text-neutral-400 dark:text-neutral-500">{detail}</span>}
            </span>
            {passed ? (
                <span className="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400">
                    <Check className="h-3.5 w-3.5" />
                </span>
            ) : (
                <span className="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-red-100 text-red-600 dark:bg-red-900/30 dark:text-red-400">
                    <X className="h-3.5 w-3.5" />
                </span>
            )}
        </div>
    );
}

function Group({ title, children }) {
    return (
        <section>
            <h3 className="mb-1 text-xs font-semibold uppercase tracking-wide text-neutral-400 dark:text-neutral-500">
                {title}
            </h3>
            <div className="divide-y divide-neutral-100 dark:divide-neutral-800">{children}</div>
        </section>
    );
}

export default function RequirementsStep({ requirements }) {
    const { php, extensions, writable, ok } = requirements;

    return (
        <div className="space-y-6">
            <Group title="PHP">
                <Row label={php.name} detail={php.current} passed={php.passed} />
            </Group>

            <Group title="Extensões">
                {extensions.map((ext) => (
                    <Row key={ext.name} label={ext.name} passed={ext.passed} />
                ))}
            </Group>

            <Group title="Permissões de escrita">
                {writable.map((path) => (
                    <Row key={path.name} label={path.name} passed={path.passed} />
                ))}
            </Group>

            {!ok && (
                <div className="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-800 dark:bg-amber-900/20 dark:text-amber-300">
                    Corrija os itens marcados em vermelho no servidor e recarregue esta página.
                </div>
            )}
        </div>
    );
}
