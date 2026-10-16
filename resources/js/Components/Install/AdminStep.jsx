import { Input } from '@/Components/ui';

export default function AdminStep({ data, setData, errors }) {
    const mismatch =
        data.admin_password &&
        data.admin_password_confirmation &&
        data.admin_password !== data.admin_password_confirmation;

    return (
        <div className="space-y-4">
            <div className="rounded-lg border border-orange-200 bg-orange-50 p-3 text-sm text-neutral-800">
                Ao concluir, esta edição registra na CREASY o nome e o e-mail deste administrador,
                o domínio, a versão e um identificador aleatório da instalação para contabilizar
                instalações e seus responsáveis. Envia também um sinal técnico a cada 5 minutos para indicar disponibilidade. Não envia senha, contatos ou mensagens.
                O registro é enviado para telemetria.creasy.com.br e não tem opção de desativação
                na interface. Consulte o documento PRIVACIDADE.md incluído no pacote.
            </div>
            <Input
                label="Nome completo"
                name="admin_name"
                value={data.admin_name}
                onChange={(e) => setData('admin_name', e.target.value)}
                error={errors.admin_name}
            />

            <Input
                type="email"
                label="E-mail"
                name="admin_email"
                autoComplete="username"
                value={data.admin_email}
                onChange={(e) => setData('admin_email', e.target.value)}
                error={errors.admin_email}
            />

            <Input
                type="password"
                label="Senha"
                name="admin_password"
                autoComplete="new-password"
                value={data.admin_password}
                onChange={(e) => setData('admin_password', e.target.value)}
                error={errors.admin_password}
                hint="Pelo menos 8 caracteres."
            />

            <Input
                type="password"
                label="Confirmar senha"
                name="admin_password_confirmation"
                autoComplete="new-password"
                value={data.admin_password_confirmation}
                onChange={(e) => setData('admin_password_confirmation', e.target.value)}
                error={mismatch ? 'As senhas não coincidem.' : undefined}
            />
        </div>
    );
}
