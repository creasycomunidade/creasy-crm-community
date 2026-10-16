import ClientLayout from '@/Layouts/ClientLayout';
import { Button, Card, Input, Badge } from '@/Components/ui';
import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { Pencil, Trash2, X } from 'lucide-react';
import { useTranslation } from 'react-i18next';

function WorkspaceAvatar({ name }) {
    const initials = name
        .split(' ')
        .slice(0, 2)
        .map((w) => w[0]?.toUpperCase() ?? '')
        .join('');

    return (
        <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-brand-100 text-brand-700 font-semibold text-sm dark:bg-brand-900/40 dark:text-brand-300">
            {initials}
        </div>
    );
}

export default function WorkspacesIndex({
    workspaces = [],
    availableUsers = [],
    canCreate = false,
    workspaceLimit = null,
    workspaceCount = 0,
}) {
    const { t } = useTranslation();
    const [name, setName] = useState('');
    const [creating, setCreating] = useState(false);
    const [switching, setSwitching] = useState(null);
    const [memberSelection, setMemberSelection] = useState({});
    const [editing, setEditing] = useState(null);
    const [editName, setEditName] = useState('');
    const currentWorkspace = usePage().props.currentWorkspace;

    const handleSwitch = (workspaceId) => {
        setSwitching(workspaceId);
        router.post(route('client.workspaces.switch'), { workspace_id: workspaceId }, {
            preserveScroll: true,
            onFinish: () => setSwitching(null),
        });
    };

    const handleCreate = (e) => {
        e.preventDefault();
        if (!name.trim()) return;
        setCreating(true);
        router.post(route('client.workspaces.store'), { name: name.trim() }, {
            preserveScroll: true,
            onFinish: () => {
                setCreating(false);
                setName('');
            },
        });
    };

    const addMember = (workspaceId) => {
        const selected = memberSelection[workspaceId];
        if (!selected?.user_id) return;
        router.post(route('client.workspaces.members.store', { workspace: workspaceId }), selected, {
            preserveScroll: true,
        });
    };

    const removeMember = (workspaceId, userId) => {
        router.delete(route('client.workspaces.members.destroy', { workspace: workspaceId, member: userId }), {
            preserveScroll: true,
        });
    };

    const saveName = (event) => {
        event.preventDefault();
        if (!editName.trim()) return;
        router.patch(route('client.workspaces.update', editing.id), { name: editName.trim() }, {
            preserveScroll: true,
            onSuccess: () => setEditing(null),
        });
    };

    const deleteWorkspace = (workspace) => {
        if (!window.confirm(`Excluir o espaço “${workspace.name}”? Ele será retirado do acesso dos usuários.`)) return;
        router.delete(route('client.workspaces.destroy', workspace.id), { preserveScroll: true });
    };

    return (
        <ClientLayout title={t('workspaces.title')}>
            <Head title={t('workspaces.title')} />

            <div className="max-w-2xl space-y-8">
                {/* Header */}
                <div>
                    <h2 className="text-xl font-semibold text-neutral-900 dark:text-neutral-100">{t('workspaces.title')}</h2>
                    <p className="mt-1 text-sm text-neutral-500 dark:text-neutral-400">
                        {t('workspaces.subtitle')}
                    </p>
                </div>

                {/* Workspace list */}
                <div className="space-y-3">
                    <h3 className="text-xs font-semibold uppercase tracking-wider text-neutral-400 dark:text-neutral-500">
                        {t('workspaces.your_workspaces', { count: workspaces.length })}
                    </h3>

                    {workspaces.length === 0 ? (
                        <Card>
                            <Card.Body className="py-10 text-center">
                                <div className="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-xl bg-neutral-100 dark:bg-neutral-800">
                                    <svg className="h-6 w-6 text-neutral-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
                                        <path strokeLinecap="round" strokeLinejoin="round" d="M2.25 7.125C2.25 6.504 2.754 6 3.375 6h6c.621 0 1.125.504 1.125 1.125v3.75c0 .621-.504 1.125-1.125 1.125h-6a1.125 1.125 0 01-1.125-1.125v-3.75zM14.25 8.625c0-.621.504-1.125 1.125-1.125h5.25c.621 0 1.125.504 1.125 1.125v8.25c0 .621-.504 1.125-1.125 1.125h-5.25a1.125 1.125 0 01-1.125-1.125v-8.25zM3.75 16.125c0-.621.504-1.125 1.125-1.125h5.25c.621 0 1.125.504 1.125 1.125v2.25c0 .621-.504 1.125-1.125 1.125h-5.25a1.125 1.125 0 01-1.125-1.125v-2.25z" />
                                    </svg>
                                </div>
                                <p className="text-sm font-medium text-neutral-700 dark:text-neutral-300">{t('workspaces.none_yet')}</p>
                                <p className="mt-1 text-sm text-neutral-400">{t('workspaces.create_first')}</p>
                            </Card.Body>
                        </Card>
                    ) : (
                        <ul className="space-y-2">
                            {workspaces.map((w) => {
                                const isCurrent = currentWorkspace?.id === w.id;
                                const isSwitching = switching === w.id;
                                return (
                                    <li key={w.id}>
                                        <div className={[
                                            'flex items-center justify-between rounded-xl border px-4 py-3 transition-colors duration-150',
                                            isCurrent
                                                ? 'border-brand-300 bg-brand-50 dark:border-brand-700 dark:bg-brand-900/20'
                                                : 'border-neutral-200 bg-white hover:border-neutral-300 hover:bg-neutral-50 dark:border-neutral-700 dark:bg-neutral-900 dark:hover:border-neutral-600 dark:hover:bg-neutral-800',
                                        ].join(' ')}>
                                            <div className="flex items-center gap-3">
                                                <WorkspaceAvatar name={w.name} />
                                                <div>
                                                    <p className="text-sm font-medium text-neutral-900 dark:text-neutral-100">{w.name}</p>
                                                    <p className="text-xs text-neutral-400 dark:text-neutral-500">
                                                        {w.is_owner ? t('workspaces.owner') : t('workspaces.member')}
                                                    </p>
                                                </div>
                                            </div>

                                            <div className="flex items-center gap-2">
                                                {w.can_edit && (
                                                    <button type="button" onClick={() => { setEditing(w); setEditName(w.name); }} className="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-neutral-200 text-neutral-600 hover:bg-neutral-50 dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-800" aria-label={`Editar ${w.name}`}><Pencil className="h-4 w-4" /></button>
                                                )}
                                                {w.can_delete && (
                                                    <button type="button" onClick={() => deleteWorkspace(w)} className="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-red-200 text-red-600 hover:bg-red-50 dark:border-red-900 dark:hover:bg-red-950/30" aria-label={`Excluir ${w.name}`}><Trash2 className="h-4 w-4" /></button>
                                                )}
                                                {isCurrent && (
                                                    <Badge variant="brand" size="sm">{t('common.active')}</Badge>
                                                )}
                                                <Button
                                                    variant={isCurrent ? 'outline' : 'primary'}
                                                    size="sm"
                                                    onClick={() => !isCurrent && handleSwitch(w.id)}
                                                    disabled={isCurrent || isSwitching}
                                                >
                                                    {isSwitching ? t('workspaces.switching') : isCurrent ? t('workspaces.current') : t('workspaces.switch')}
                                                </Button>
                                            </div>
                                        </div>
                                        {w.can_manage_members && (
                                            <div className="border-t border-neutral-100 px-4 py-3 dark:border-neutral-800">
                                                <p className="mb-2 text-xs font-semibold text-neutral-600 dark:text-neutral-300">
                                                    {t('workspaces.members_title')}
                                                </p>
                                                <div className="mb-3 flex flex-wrap gap-2">
                                                    {w.members.map((member) => (
                                                        <span key={member.id} className="inline-flex items-center gap-1 rounded-full bg-neutral-100 px-2.5 py-1 text-xs text-neutral-700 dark:bg-neutral-800 dark:text-neutral-300">
                                                            {member.name} · {member.role === 'administrator' || member.role === 'owner' ? t('workspaces.workspace_admin') : t('workspaces.member')}
                                                            {!member.is_owner && (
                                                                <button type="button" onClick={() => removeMember(w.id, member.id)} className="ml-1 text-red-500" aria-label={t('workspaces.remove_member')}>×</button>
                                                            )}
                                                        </span>
                                                    ))}
                                                </div>
                                                <div className="grid gap-2 sm:grid-cols-[1fr_160px_auto]">
                                                    <select
                                                        className="rounded-lg border border-neutral-200 bg-white px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-900"
                                                        value={memberSelection[w.id]?.user_id ?? ''}
                                                        onChange={(e) => setMemberSelection((current) => ({
                                                            ...current,
                                                            [w.id]: { ...(current[w.id] ?? { role: 'member' }), user_id: Number(e.target.value) },
                                                        }))}
                                                    >
                                                        <option value="">{t('workspaces.select_user')}</option>
                                                        {availableUsers.filter((user) => !w.members.some((member) => member.id === user.id)).map((user) => (
                                                            <option key={user.id} value={user.id}>{user.name} ({user.email})</option>
                                                        ))}
                                                    </select>
                                                    <select
                                                        className="rounded-lg border border-neutral-200 bg-white px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-900"
                                                        value={memberSelection[w.id]?.role ?? 'member'}
                                                        onChange={(e) => setMemberSelection((current) => ({
                                                            ...current,
                                                            [w.id]: { ...(current[w.id] ?? {}), role: e.target.value },
                                                        }))}
                                                    >
                                                        <option value="member">{t('workspaces.member')}</option>
                                                        <option value="administrator">{t('workspaces.workspace_admin')}</option>
                                                    </select>
                                                    <Button type="button" variant="outline" onClick={() => addMember(w.id)}>
                                                        {t('workspaces.add_member')}
                                                    </Button>
                                                </div>
                                            </div>
                                        )}
                                    </li>
                                );
                            })}
                        </ul>
                    )}
                </div>

                {/* Create workspace */}
                {canCreate ? (
                <Card className="border-dashed border-neutral-300 dark:border-neutral-700">
                    <Card.Body className="space-y-4">
                        <div className="flex items-start gap-3">
                            <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-brand-50 dark:bg-brand-900/30">
                                <svg className="h-4.5 w-4.5 text-brand-600 dark:text-brand-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.75}>
                                    <path strokeLinecap="round" strokeLinejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                </svg>
                            </div>
                            <div>
                                <h3 className="text-sm font-semibold text-neutral-800 dark:text-neutral-200">{t('workspaces.create_new')}</h3>
                                <p className="mt-0.5 text-xs text-neutral-400 dark:text-neutral-500">{t('workspaces.create_new_desc')}</p>
                            </div>
                        </div>

                        <form onSubmit={handleCreate} className="flex items-end gap-3">
                            <Input
                                label={t('workspaces.name_label')}
                                value={name}
                                onChange={(e) => setName(e.target.value)}
                                placeholder={t('workspaces.name_placeholder')}
                                className="flex-1"
                            />
                            <Button
                                type="submit"
                                variant="primary"
                                disabled={creating || !name.trim()}
                                className="shrink-0"
                            >
                                {creating ? (
                                    <span className="flex items-center gap-1.5">
                                        <svg className="h-3.5 w-3.5 animate-spin" viewBox="0 0 24 24" fill="none">
                                            <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                            <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z" />
                                        </svg>
                                        {t('workspaces.creating')}
                                    </span>
                                ) : (
                                    <span className="flex items-center gap-1.5">
                                        <svg className="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
                                            <path strokeLinecap="round" strokeLinejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                        </svg>
                                        {t('workspaces.create_workspace')}
                                    </span>
                                )}
                            </Button>
                        </form>
                    </Card.Body>
                </Card>
                ) : (
                    <Card>
                        <Card.Body>
                            <p className="text-sm text-neutral-600 dark:text-neutral-300">
                                {workspaceLimit !== null && workspaceCount >= workspaceLimit
                                    ? t('workspaces.limit_reached', { count: workspaceLimit })
                                    : t('workspaces.admin_only')}
                            </p>
                        </Card.Body>
                    </Card>
                )}
            </div>

            {editing && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4" role="dialog" aria-modal="true" aria-labelledby="workspace-edit-title">
                    <form onSubmit={saveName} className="w-full max-w-md rounded-2xl bg-white p-5 shadow-2xl dark:bg-neutral-900">
                        <div className="flex items-center justify-between"><h2 id="workspace-edit-title" className="text-lg font-semibold">Editar espaço de trabalho</h2><button type="button" onClick={() => setEditing(null)} className="flex h-10 w-10 items-center justify-center rounded-lg hover:bg-neutral-100 dark:hover:bg-neutral-800" aria-label="Fechar"><X className="h-5 w-5" /></button></div>
                        <label className="mt-4 block text-sm font-medium">Nome<input autoFocus value={editName} onChange={event => setEditName(event.target.value)} maxLength={255} className="mt-1 min-h-11 w-full rounded-lg border px-3 dark:border-neutral-700 dark:bg-neutral-950" /></label>
                        <div className="mt-5 flex justify-end gap-2"><button type="button" onClick={() => setEditing(null)} className="min-h-11 rounded-lg border px-4 dark:border-neutral-700">Cancelar</button><button disabled={!editName.trim()} className="min-h-11 rounded-lg bg-brand-600 px-4 font-semibold text-white disabled:opacity-50">Salvar</button></div>
                    </form>
                </div>
            )}
        </ClientLayout>
    );
}
