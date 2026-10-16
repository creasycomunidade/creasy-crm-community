import ClientLayout from '@/Layouts/ClientLayout';
import { Button, Modal } from '@/Components/ui';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Users, Pencil, Trash2, UserPlus, Mail, X } from 'lucide-react';

const STATUS_ACTIVE    = 'active';
const CLIENT_ROLE_ADMIN = 'administrator';
const CLIENT_ROLE_STAFF = 'staff';

export default function TeamIndex({ users = [], client = {}, invitations = [] }) {
    const { t } = useTranslation();
    const { flash = {} } = usePage().props;
    const [addOpen, setAddOpen]         = useState(false);
    const [inviteOpen, setInviteOpen]   = useState(false);
    const [editOpen, setEditOpen]       = useState(false);
    const [editUser, setEditUser]       = useState(null);
    const [deleteConfirm, setDeleteConfirm] = useState(null);

    const inviteForm = useForm({ email: '', client_role: CLIENT_ROLE_STAFF });

    const submitInvite = (e) => {
        e.preventDefault();
        inviteForm.post(route('client.invitations.store'), {
            preserveScroll: true,
            onSuccess: () => { setInviteOpen(false); inviteForm.reset(); },
        });
    };

    const revokeInvitation = (id) => {
        router.delete(route('client.invitations.destroy', id), { preserveScroll: true });
    };

    const addForm = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
        client_role: CLIENT_ROLE_STAFF,
        status: STATUS_ACTIVE,
    });

    const editForm = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
        client_role: CLIENT_ROLE_STAFF,
        status: STATUS_ACTIVE,
    });

    const openAdd = () => {
        addForm.reset();
        addForm.setData({ client_role: CLIENT_ROLE_STAFF, status: STATUS_ACTIVE });
        setAddOpen(true);
    };

    const submitAdd = (e) => {
        e.preventDefault();
        addForm.post(route('client.team.store'), {
            preserveScroll: true,
            onSuccess: () => {
                setAddOpen(false);
                addForm.reset();
            },
        });
    };

    const openEdit = (u) => {
        setEditUser(u);
        editForm.setData({
            name: u.name,
            email: u.email,
            password: '',
            password_confirmation: '',
            client_role: u.client_role || CLIENT_ROLE_STAFF,
            status: u.status,
        });
        setEditOpen(true);
    };

    const submitEdit = (e) => {
        e.preventDefault();
        if (!editUser) return;
        editForm.put(route('client.team.update', { member: editUser.id }), {
            preserveScroll: true,
            onSuccess: () => {
                setEditOpen(false);
                setEditUser(null);
            },
        });
    };

    const doDelete = (u) => {
        router.delete(route('client.team.destroy', { member: u.id }), {
            preserveScroll: true,
            onSuccess: () => setDeleteConfirm(null),
        });
    };

    return (
        <ClientLayout title={t('team.page_title') || 'Team'}>
            <Head title={t('team.page_title') || 'Team'} />
            <div className="space-y-6">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-bold text-neutral-900 dark:text-white">
                            {t('team.page_title') || 'Team'}
                        </h1>
                        <p className="mt-1 text-sm text-neutral-500 dark:text-neutral-400">
                            {client?.name && `${t('client.team_for') || 'Team for'} ${client.name}`}
                        </p>
                    </div>
                    {(
                        <div className="flex gap-2">
                            <Button variant="outline" onClick={() => setInviteOpen(true)} className="inline-flex items-center gap-2">
                                <Mail className="h-4 w-4" />
                                {t('client.invite_by_email') || 'Invite by email'}
                            </Button>
                            <Button variant="primary" onClick={openAdd} className="inline-flex items-center gap-2">
                                <UserPlus className="h-4 w-4" />
                                {t('client.add_member') || 'Add member'}
                            </Button>
                        </div>
                    )}
                </div>

                {flash?.success && (
                    <div className="rounded-lg bg-green-50 dark:bg-green-900/20 text-green-800 dark:text-green-200 px-4 py-3 text-sm">
                        {flash.success}
                    </div>
                )}
                {flash?.error && (
                    <div className="rounded-lg bg-coral-50 dark:bg-coral-900/20 text-coral-800 dark:text-coral-200 px-4 py-3 text-sm">
                        {flash.error}
                    </div>
                )}

                <div className="overflow-x-auto rounded-xl border border-neutral-200 bg-white dark:border-neutral-700 dark:bg-neutral-800/50">
                    {users.length > 0 ? (
                        <table className="min-w-full divide-y divide-neutral-200 dark:divide-neutral-700">
                            <thead className="bg-neutral-50 dark:bg-neutral-800">
                                <tr>
                                    <th className="px-4 py-3 text-left text-xs font-medium text-neutral-500 uppercase">
                                        {t('client.name') || 'Name'}
                                    </th>
                                    <th className="px-4 py-3 text-left text-xs font-medium text-neutral-500 uppercase">
                                        {t('client.email') || 'Email'}
                                    </th>
                                    <th className="px-4 py-3 text-left text-xs font-medium text-neutral-500 uppercase">
                                        {t('client.role') || 'Role'}
                                    </th>
                                    <th className="px-4 py-3 text-left text-xs font-medium text-neutral-500 uppercase">
                                        {t('client.status') || 'Status'}
                                    </th>
                                    {(
                                        <th className="px-4 py-3 text-right text-xs font-medium text-neutral-500 uppercase">
                                            {t('client.actions') || 'Actions'}
                                        </th>
                                    )}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-neutral-200 dark:divide-neutral-700">
                                {users.map((u) => (
                                    <tr key={u.id} className="bg-white dark:bg-neutral-800/30">
                                        <td className="px-4 py-3 text-sm text-neutral-900 dark:text-white">
                                            {u.name}
                                        </td>
                                        <td className="px-4 py-3 text-sm text-neutral-600 dark:text-neutral-300">
                                            {u.email}
                                        </td>
                                        <td className="px-4 py-3">
                                            <span className="inline-flex rounded-full px-2 py-0.5 text-xs font-medium bg-brand-50 dark:bg-brand-900/30 text-brand-800 dark:text-brand-300">
                                                {u.client_role === CLIENT_ROLE_ADMIN ? (t('admin.administrator') || 'Administrator') : (t('admin.staff') || 'Staff')}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3">
                                            <span className={`inline-flex rounded-full px-2 py-0.5 text-xs font-medium ${
                                                u.status === STATUS_ACTIVE ? 'bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-200' : 'bg-neutral-100 dark:bg-neutral-700 text-neutral-600 dark:text-neutral-400'
                                            }`}>
                                                {u.status}
                                            </span>
                                        </td>
                                        {(
                                            <td className="px-4 py-3 text-right">
                                                <button
                                                    type="button"
                                                    onClick={() => openEdit(u)}
                                                    className="rounded p-1.5 text-neutral-500 hover:bg-neutral-100 dark:hover:bg-neutral-700 hover:text-neutral-700 dark:hover:text-neutral-200 mr-1"
                                                    aria-label={t('client.edit') || 'Edit'}
                                                >
                                                    <Pencil className="h-4 w-4" />
                                                </button>
                                                <button
                                                    type="button"
                                                    onClick={() => setDeleteConfirm(u)}
                                                    className="rounded p-1.5 text-neutral-500 hover:bg-coral-50 dark:hover:bg-coral-900/20 hover:text-coral-600"
                                                    aria-label={t('client.remove') || 'Remove'}
                                                >
                                                    <Trash2 className="h-4 w-4" />
                                                </button>
                                            </td>
                                        )}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    ) : (
                        <div className="px-6 py-12 text-center text-neutral-500 dark:text-neutral-400">
                            <Users className="mx-auto h-12 w-12 text-neutral-400 mb-3" />
                            <p>{t('client.no_team_members') || 'No team members yet'}</p>
                            {(
                                <Button variant="primary" onClick={openAdd} className="mt-3">
                                    {t('client.add_member') || 'Add member'}
                                </Button>
                            )}
                        </div>
                    )}
                </div>

                {/* Pending invitations */}
                {invitations.length > 0 && (
                    <div className="rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800/50 overflow-hidden">
                        <div className="px-4 py-3 border-b border-neutral-100 dark:border-neutral-700">
                            <h3 className="text-sm font-semibold text-neutral-700 dark:text-neutral-300">{t('team.pending_invitations')}</h3>
                        </div>
                        <ul className="divide-y divide-neutral-100 dark:divide-neutral-700">
                            {invitations.map(inv => (
                                <li key={inv.id} className="flex items-center justify-between px-4 py-3">
                                    <div>
                                        <span className="text-sm font-medium text-neutral-800 dark:text-neutral-200">{inv.email}</span>
                                        <span className="ml-2 text-xs text-neutral-500 dark:text-neutral-400 capitalize">{inv.client_role}</span>
                                    </div>
                                    <button
                                        onClick={() => revokeInvitation(inv.id)}
                                        className="p-1 rounded hover:bg-neutral-100 dark:hover:bg-neutral-700 text-neutral-400 hover:text-coral-500 transition"
                                        title={t('team.revoke_invitation')}
                                    >
                                        <X className="h-4 w-4" />
                                    </button>
                                </li>
                            ))}
                        </ul>
                    </div>
                )}

                {/* Invite by email modal */}
                <Modal show={inviteOpen} onClose={() => setInviteOpen(false)} maxWidth="sm">
                    <Modal.Header title={t('team.invite_by_email')} onClose={() => setInviteOpen(false)} />
                    <Modal.Body>
                        <form id="inviteForm" onSubmit={submitInvite} className="space-y-4">
                            <div>
                                <label className="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-1">{t('team.email_address')}</label>
                                <input
                                    type="email"
                                    value={inviteForm.data.email}
                                    onChange={e => inviteForm.setData('email', e.target.value)}
                                    className="w-full rounded-lg border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 px-3 py-2 text-sm"
                                    required
                                    autoFocus
                                />
                                {inviteForm.errors.email && <p className="text-coral-600 text-xs mt-1">{inviteForm.errors.email}</p>}
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-1">{t('client.role') || 'Role'}</label>
                                <select
                                    value={inviteForm.data.client_role}
                                    onChange={e => inviteForm.setData('client_role', e.target.value)}
                                    className="w-full rounded-lg border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 px-3 py-2 text-sm"
                                >
                                    <option value={CLIENT_ROLE_STAFF}>{t('admin.staff') || 'Staff'}</option>
                                    <option value={CLIENT_ROLE_ADMIN}>{t('admin.administrator') || 'Administrator'}</option>
                                </select>
                            </div>
                        </form>
                    </Modal.Body>
                    <Modal.Footer>
                        <Button type="button" variant="secondary" onClick={() => setInviteOpen(false)}>{t('client.cancel') || 'Cancel'}</Button>
                        <Button type="submit" form="inviteForm" variant="primary" disabled={inviteForm.processing}>
                            <Mail className="h-4 w-4 mr-1.5" />
                            {inviteForm.processing ? t('team.sending') : t('team.send_invitation')}
                        </Button>
                    </Modal.Footer>
                </Modal>

                {/* Add member modal */}
                <Modal show={addOpen} onClose={() => setAddOpen(false)} maxWidth="md">
                    <Modal.Header title={t('client.add_member') || 'Add member'} onClose={() => setAddOpen(false)} />
                    <Modal.Body>
                        <form id="addMemberForm" onSubmit={submitAdd} className="space-y-4">
                                <div>
                                    <label className="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-1">{t('client.name') || 'Name'}</label>
                                    <input type="text" value={addForm.data.name} onChange={e => addForm.setData('name', e.target.value)} className="w-full rounded-lg border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 px-3 py-2 text-sm" required />
                                    {addForm.errors.name && <p className="text-coral-600 text-xs mt-1">{addForm.errors.name}</p>}
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-1">{t('client.email') || 'Email'}</label>
                                    <input type="email" value={addForm.data.email} onChange={e => addForm.setData('email', e.target.value)} className="w-full rounded-lg border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 px-3 py-2 text-sm" required />
                                    {addForm.errors.email && <p className="text-coral-600 text-xs mt-1">{addForm.errors.email}</p>}
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-1">{t('client.password') || 'Password'}</label>
                                    <input type="password" value={addForm.data.password} onChange={e => addForm.setData('password', e.target.value)} className="w-full rounded-lg border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 px-3 py-2 text-sm" required />
                                    {addForm.errors.password && <p className="text-coral-600 text-xs mt-1">{addForm.errors.password}</p>}
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-1">{t('client.password_confirmation') || 'Confirm password'}</label>
                                    <input type="password" value={addForm.data.password_confirmation} onChange={e => addForm.setData('password_confirmation', e.target.value)} className="w-full rounded-lg border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 px-3 py-2 text-sm" />
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-1">{t('client.role') || 'Role'}</label>
                                    <select value={addForm.data.client_role} onChange={e => addForm.setData('client_role', e.target.value)} className="w-full rounded-lg border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 px-3 py-2 text-sm">
                                        <option value={CLIENT_ROLE_STAFF}>{t('admin.staff') || 'Staff'}</option>
                                        <option value={CLIENT_ROLE_ADMIN}>{t('admin.administrator') || 'Administrator'}</option>
                                    </select>
                                </div>
                        </form>
                    </Modal.Body>
                    <Modal.Footer>
                        <Button type="button" variant="secondary" onClick={() => setAddOpen(false)}>{t('client.cancel') || 'Cancel'}</Button>
                        <Button type="submit" form="addMemberForm" variant="primary" disabled={addForm.processing}>{t('client.add_member') || 'Add member'}</Button>
                    </Modal.Footer>
                </Modal>

                {/* Edit member modal */}
                <Modal show={!!(editOpen && editUser)} onClose={() => setEditOpen(false)} maxWidth="md">
                    <Modal.Header title={t('client.edit_member') || 'Edit member'} onClose={() => setEditOpen(false)} />
                    <Modal.Body>
                        <form id="editMemberForm" onSubmit={submitEdit} className="space-y-4">
                                <div>
                                    <label className="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-1">{t('client.name') || 'Name'}</label>
                                    <input type="text" value={editForm.data.name} onChange={e => editForm.setData('name', e.target.value)} className="w-full rounded-lg border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 px-3 py-2 text-sm" required />
                                    {editForm.errors.name && <p className="text-coral-600 text-xs mt-1">{editForm.errors.name}</p>}
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-1">{t('client.email') || 'Email'}</label>
                                    <input type="email" value={editForm.data.email} onChange={e => editForm.setData('email', e.target.value)} className="w-full rounded-lg border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 px-3 py-2 text-sm" required />
                                    {editForm.errors.email && <p className="text-coral-600 text-xs mt-1">{editForm.errors.email}</p>}
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-1">{t('team.password_leave_blank')}</label>
                                    <input type="password" value={editForm.data.password} onChange={e => editForm.setData('password', e.target.value)} className="w-full rounded-lg border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 px-3 py-2 text-sm" />
                                    {editForm.errors.password && <p className="text-coral-600 text-xs mt-1">{editForm.errors.password}</p>}
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-1">{t('client.role') || 'Role'}</label>
                                    <select value={editForm.data.client_role} onChange={e => editForm.setData('client_role', e.target.value)} className="w-full rounded-lg border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 px-3 py-2 text-sm">
                                        <option value={CLIENT_ROLE_STAFF}>{t('admin.staff') || 'Staff'}</option>
                                        <option value={CLIENT_ROLE_ADMIN}>{t('admin.administrator') || 'Administrator'}</option>
                                    </select>
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-1">{t('client.status') || 'Status'}</label>
                                    <select value={editForm.data.status} onChange={e => editForm.setData('status', e.target.value)} className="w-full rounded-lg border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 px-3 py-2 text-sm">
                                        <option value="active">{t('client.active') || 'Active'}</option>
                                        <option value="inactive">{t('client.inactive') || 'Inactive'}</option>
                                    </select>
                                </div>
                        </form>
                    </Modal.Body>
                    <Modal.Footer>
                        <Button type="button" variant="secondary" onClick={() => setEditOpen(false)}>{t('client.cancel') || 'Cancel'}</Button>
                        <Button type="submit" form="editMemberForm" variant="primary" disabled={editForm.processing}>{t('client.save') || 'Save'}</Button>
                    </Modal.Footer>
                </Modal>

                {/* Delete confirm */}
                <Modal show={!!deleteConfirm} onClose={() => setDeleteConfirm(null)} maxWidth="sm">
                    <Modal.Body>
                        <p className="text-neutral-700 dark:text-neutral-200">
                            {t('team.remove_member_confirm', { name: deleteConfirm?.name })}
                        </p>
                    </Modal.Body>
                    <Modal.Footer>
                        <Button variant="secondary" onClick={() => setDeleteConfirm(null)}>{t('client.cancel') || 'Cancel'}</Button>
                        <Button variant="primary" className="bg-coral-600 hover:bg-coral-500" onClick={() => doDelete(deleteConfirm)}>{t('client.remove') || 'Remove'}</Button>
                    </Modal.Footer>
                </Modal>
            </div>
        </ClientLayout>
    );
}
