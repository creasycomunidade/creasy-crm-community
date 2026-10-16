import { useState } from 'react';
import { Input, Button } from '@/Components/ui';
import { Database, Loader2, CheckCircle2, XCircle } from 'lucide-react';

export default function DatabaseStep({ data, setData, errors, onValidityChange }) {
    const [testing, setTesting] = useState(false);
    const [result, setResult] = useState(null);

    // Editing any field invalidates a previous successful test.
    const change = (field) => (e) => {
        setData(field, e.target.value);
        setResult(null);
        onValidityChange(false);
    };

    const canTest = data.db_host && data.db_database && data.db_username;

    const test = async () => {
        setTesting(true);
        setResult(null);
        try {
            const res = await window.axios.post(route('install.test-database'), {
                install_token: data.install_token,
                db_connection: data.db_connection,
                db_host: data.db_host,
                db_port: data.db_port,
                db_database: data.db_database,
                db_username: data.db_username,
                db_password: data.db_password,
            });
            setResult(res.data);
            onValidityChange(Boolean(res.data?.ok));
        } catch (err) {
            const message =
                err?.response?.data?.message ||
                Object.values(err?.response?.data?.errors || {})[0]?.[0] ||
                'Confira os campos e tente novamente.';
            setResult({ ok: false, message });
            onValidityChange(false);
        } finally {
            setTesting(false);
        }
    };

    return (
        <div className="space-y-4">
            <label className="block text-sm">
                Banco de dados
                <select className="mt-1 block w-full rounded border p-2 dark:bg-neutral-900" value={data.db_connection} onChange={(e) => {
                    setData((current) => ({ ...current, db_connection: e.target.value, db_port: '' }));
                    setResult(null);
                    onValidityChange(false);
                }}>
                    <option value="pgsql">PostgreSQL</option>
                    <option value="mysql">MySQL</option>
                </select>
            </label>
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div className="sm:col-span-2">
                    <Input
                        label="Host"
                        name="db_host"
                        value={data.db_host}
                        onChange={change('db_host')}
                        error={errors.db_host}
                    />
                </div>
                <Input
                    label="Porta (opcional)"
                    placeholder={data.db_connection === 'pgsql' ? '5432' : '3306'}
                    hint="Deixe em branco para usar a porta padrão."
                    name="db_port"
                    value={data.db_port}
                    onChange={change('db_port')}
                    error={errors.db_port}
                />
            </div>

            <Input
                label="Nome do banco"
                name="db_database"
                value={data.db_database}
                onChange={change('db_database')}
                error={errors.db_database}
            />

            <Input
                label="Usuário"
                name="db_username"
                value={data.db_username}
                onChange={change('db_username')}
                error={errors.db_username}
            />

            <Input
                type="password"
                label="Senha"
                name="db_password"
                value={data.db_password}
                onChange={change('db_password')}
                error={errors.db_password}
                hint="Informe a senha do usuário do banco."
            />

            <div className="flex items-center gap-3 pt-1">
                <Button variant="outline" onClick={test} disabled={testing || !canTest}>
                    {testing ? (
                        <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                    ) : (
                        <Database className="mr-2 h-4 w-4" />
                    )}
                    {testing ? 'Testando…' : 'Testar conexão'}
                </Button>
                {!testing && !result && (
                    <span className="text-xs text-neutral-400 dark:text-neutral-500">
                        Teste a conexão para continuar.
                    </span>
                )}
            </div>

            {result && (
                <div
                    className={[
                        'flex items-start gap-2 rounded-lg border px-4 py-3 text-sm',
                        result.ok
                            ? 'border-green-200 bg-green-50 text-green-800 dark:border-green-800 dark:bg-green-900/20 dark:text-green-300'
                            : 'border-red-200 bg-red-50 text-red-800 dark:border-red-800 dark:bg-red-900/20 dark:text-red-300',
                    ].join(' ')}
                >
                    {result.ok ? (
                        <CheckCircle2 className="mt-0.5 h-4 w-4 shrink-0" />
                    ) : (
                        <XCircle className="mt-0.5 h-4 w-4 shrink-0" />
                    )}
                    <span>{result.message}</span>
                </div>
            )}

            <p className="text-xs text-neutral-400 dark:text-neutral-500">
                Crie um banco vazio no servidor antes de continuar. O instalador criará as tabelas.
            </p>
        </div>
    );
}
