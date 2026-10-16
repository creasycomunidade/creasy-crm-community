import { useState } from 'react';
import { Head, useForm } from '@inertiajs/react';
import InstallLayout from '@/Layouts/InstallLayout';
import { Button } from '@/Components/ui';
import { ListChecks, AppWindow, Database, ShieldCheck, Rocket, ArrowLeft, ArrowRight, Loader2 } from 'lucide-react';
import RequirementsStep from '@/Components/Install/RequirementsStep';
import AppSettingsStep from '@/Components/Install/AppSettingsStep';
import DatabaseStep from '@/Components/Install/DatabaseStep';
import AdminStep from '@/Components/Install/AdminStep';
import SeedStep from '@/Components/Install/SeedStep';

export default function Setup({ requirements, defaults }) {
    const { data, setData, post, processing, errors } = useForm({
        app_name: defaults.app_name || '',
        app_url: defaults.app_url || '',
        app_env: defaults.app_env || 'production',
        db_connection: defaults.db_connection || 'pgsql',
        db_host: defaults.db_host || '127.0.0.1',
        db_port: '',
        db_database: defaults.db_database || '',
        db_username: defaults.db_username || '',
        db_password: '',
        admin_name: '',
        admin_email: '',
        admin_password: '',
        admin_password_confirmation: '',
        import_demo: false,
        install_token: '', app_tagline: '', support_email: '', primary_color: '#c45100', logo: null, favicon: null,
    });

    const [step, setStep] = useState(0);
    const [dbOk, setDbOk] = useState(false);

    const steps = [
        { key: 'requirements', label: 'Requisitos', desc: 'Confira o servidor', icon: ListChecks, title: 'Requisitos do servidor', subtitle: 'Confira os requisitos antes de continuar.' },
        { key: 'application', label: 'White label', desc: 'Identidade da plataforma', icon: AppWindow, title: 'Sua marca e endereço', subtitle: 'Personalize a plataforma com sua marca.' },
        { key: 'database', label: 'Banco de dados', desc: 'Conecte seu banco', icon: Database, title: 'Conexão com o banco', subtitle: 'Informe os dados do banco e teste a conexão.' },
        { key: 'admin', label: 'Admin', desc: 'Crie sua conta', icon: ShieldCheck, title: 'Conta de administrador', subtitle: 'Crie a conta que irá administrar a plataforma.' },
        { key: 'finish', label: 'Concluir', desc: 'Revisar e instalar', icon: Rocket, title: 'Confirmar e instalar', subtitle: 'Escolha o conteúdo inicial e conclua a instalação.' },
    ];

    const current = steps[step];
    const isLast = step === steps.length - 1;

    const canAdvance = () => {
        switch (current.key) {
            case 'requirements':
                return requirements.ok;
            case 'application':
                return data.app_name.trim() && data.app_url.trim() && data.install_token.trim().length >= 32 && data.support_email.trim();
            case 'database':
                return dbOk;
            case 'admin':
                return (
                    data.admin_name.trim() &&
                    data.admin_email.trim() &&
                    data.admin_password.length >= 8 &&
                    data.admin_password === data.admin_password_confirmation
                );
            default:
                return true;
        }
    };

    const back = () => setStep((s) => Math.max(0, s - 1));
    const next = () => setStep((s) => Math.min(steps.length - 1, s + 1));
    const submit = () => post(route('install.run'), { forceFormData: true });

    const errorList = Object.values(errors).filter(Boolean);

    return (
        <InstallLayout
            steps={steps}
            current={step}
            title={current.title}
            subtitle={current.subtitle}
        >
            <Head title="Instalação" />

            {current.key === 'requirements' && <RequirementsStep requirements={requirements} />}
            {current.key === 'application' && <AppSettingsStep data={data} setData={setData} errors={errors} />}
            {current.key === 'database' && (
                <DatabaseStep data={data} setData={setData} errors={errors} onValidityChange={setDbOk} />
            )}
            {current.key === 'admin' && <AdminStep data={data} setData={setData} errors={errors} />}
            {current.key === 'finish' && <SeedStep data={data} setData={setData} />}

            {isLast && errorList.length > 0 && (
                <div className="mt-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-800 dark:bg-red-900/20 dark:text-red-300">
                    <p className="font-medium">Não foi possível concluir:</p>
                    <ul className="mt-1 list-inside list-disc space-y-0.5">
                        {errorList.map((message, i) => (
                            <li key={i}>{message}</li>
                        ))}
                    </ul>
                </div>
            )}

            {isLast && processing && (
                <div className="mt-5 flex items-start gap-2 rounded-lg border border-brand-200 bg-brand-50/60 px-4 py-3 text-sm text-brand-800 dark:border-brand-800 dark:bg-brand-900/10 dark:text-brand-300">
                    <Loader2 className="mt-0.5 h-4 w-4 shrink-0 animate-spin" />
                    <span>
                        Preparando o banco e as configurações. Aguarde sem fechar esta janela.
                    </span>
                </div>
            )}

            <div className="mt-8 flex items-center justify-between border-t border-neutral-100 pt-5 dark:border-neutral-800">
                <Button variant="ghost" onClick={back} disabled={step === 0 || processing}>
                    <ArrowLeft className="mr-2 h-4 w-4" /> Voltar
                </Button>

                {isLast ? (
                    <Button variant="primary" onClick={submit} disabled={processing}>
                        {processing ? (
                            <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                        ) : (
                            <Rocket className="mr-2 h-4 w-4" />
                        )}
                        {processing ? 'Instalando…' : 'Instalar agora'}
                    </Button>
                ) : (
                    <Button variant="primary" onClick={next} disabled={!canAdvance()}>
                        Avançar <ArrowRight className="ml-2 h-4 w-4" />
                    </Button>
                )}
            </div>
        </InstallLayout>
    );
}
