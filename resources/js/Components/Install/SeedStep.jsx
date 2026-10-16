import { Check } from 'lucide-react';

function Option({ selected, onClick, title, description }) {
    return (
        <button
            type="button"
            onClick={onClick}
            className={[
                'w-full rounded-soft-lg border p-4 text-left transition',
                selected
                    ? 'border-brand-500 bg-brand-50/60 ring-1 ring-brand-500/30 dark:border-brand-500 dark:bg-brand-900/10'
                    : 'border-neutral-200 bg-white hover:border-neutral-300 dark:border-neutral-700 dark:bg-neutral-900 dark:hover:border-neutral-600',
            ].join(' ')}
        >
            <div className="flex items-start justify-between gap-3">
                <div>
                    <p className="font-medium text-neutral-900 dark:text-neutral-100">{title}</p>
                    <p className="mt-1 text-sm text-neutral-500 dark:text-neutral-400">{description}</p>
                </div>
                <span
                    className={[
                        'mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full border',
                        selected
                            ? 'border-brand-600 bg-brand-600 text-white'
                            : 'border-neutral-300 dark:border-neutral-600',
                    ].join(' ')}
                >
                    {selected && <Check className="h-3.5 w-3.5" />}
                </span>
            </div>
        </button>
    );
}

export default function SeedStep({ data, setData }) {
    return (
        <div className="space-y-3">
            <Option
                selected={!data.import_demo}
                onClick={() => setData('import_demo', false)}
                title="Instalação de produção"
                description="Instala permissões, planos, moedas, modelos de e-mail e páginas padrão. Sem clientes ou conversas fictícias."
            />
            <Option
                selected={data.import_demo}
                onClick={() => setData('import_demo', true)}
                title="Demonstração (com exemplos)"
                description="Adiciona clientes, contatos, conversas e automações fictícias. Use apenas em ambientes de demonstração."
            />
        </div>
    );
}
