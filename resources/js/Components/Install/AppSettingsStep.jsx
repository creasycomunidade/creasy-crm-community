import { Input, Select } from '@/Components/ui';

const ENV_OPTIONS = [
    { value: 'production', label: 'Produção' },
    { value: 'local', label: 'Local / desenvolvimento' },
];

export default function AppSettingsStep({ data, setData, errors }) {
    return (
        <div className="space-y-4">
            <Input type="password" label="Chave de instalação" name="install_token" value={data.install_token} onChange={e => setData('install_token', e.target.value)} error={errors.install_token} hint="No gerenciador de arquivos da hospedagem, abra storage/app/private/installation-key.txt e copie a chave. Não é necessário terminal." />
            <Input
                label="Nome da plataforma"
                name="app_name"
                value={data.app_name}
                onChange={(e) => setData('app_name', e.target.value)}
                error={errors.app_name}
            />

            <Input
                type="url"
                label="URL da plataforma"
                name="app_url"
                value={data.app_url}
                onChange={(e) => setData('app_url', e.target.value)}
                error={errors.app_url}
                hint="Endereço público, por exemplo https://atendimento.suaempresa.com.br"
            />

            <Input label="Slogan (opcional)" name="app_tagline" value={data.app_tagline} onChange={e => setData('app_tagline', e.target.value)} error={errors.app_tagline} />
            <Input type="email" label="E-mail de suporte" name="support_email" value={data.support_email} onChange={e => setData('support_email', e.target.value)} error={errors.support_email} />
            <Input type="color" label="Cor principal" name="primary_color" value={data.primary_color} onChange={e => setData('primary_color', e.target.value)} error={errors.primary_color} />
            <Input type="file" label="Logo (opcional)" name="logo" accept="image/png,image/jpeg,image/webp" onChange={e => setData('logo', e.target.files[0] || null)} error={errors.logo} hint="PNG, JPG ou WebP, até 2 MB." />
            <Input type="file" label="Favicon (opcional)" name="favicon" accept="image/png" onChange={e => setData('favicon', e.target.files[0] || null)} error={errors.favicon} hint="PNG quadrado, até 512 KB." />
            <Select
                label="Ambiente"
                name="app_env"
                value={data.app_env}
                onChange={(e) => setData('app_env', e.target.value)}
                error={errors.app_env}
                placeholder={null}
                options={ENV_OPTIONS}
            />
        </div>
    );
}
