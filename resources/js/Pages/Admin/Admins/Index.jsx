import AdminLayout from '@/Layouts/AdminLayout';
import { Badge, Button, Card, Modal, Pagination } from '@/Components/ui';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { Pencil, Trash2, Lock, Unlock } from 'lucide-react';
import { useTranslation } from 'react-i18next';

const STATUS_ACTIVE = 'ACTIVE';
const STATUS_INACTIVE = 'INACTIVE';

export default function AdminAdminsIndex({ admins, roles, filters = {}, flash }) {
    const { t } = useTranslation();
    const { auth } = usePage().props;
    const pageFlash = usePage().props.flash || flash;
    const permissions = auth?.permissions ?? [];
    const canCreate = permissions.includes('create_admins');
    const canUpdate = permissions.includes('update_admins');
    const canDelete = permissions.includes('delete_admins');

    const [search, setSearch] = useState(filters.search ?? '');
    const [addOpen, setAddOpen] = useState(false);
    const [editOpen, setEditOpen] = useState(false);
    const [editAdmin, setEditAdmin] = useState(null);
    const [deleteConfirm, setDeleteConfirm] = useState(null);

    const addForm = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
        status: STATUS_ACTIVE,
        role_ids: [],
    });

    const editForm = useForm({
        name: '',
        email: '',
        status: STATUS_ACTIVE,
        role_ids: [],
    });

    const submitSearch = (e) => {
        e.preventDefault();
        router.get(route('admin.admins.index'), { search: search || undefined }, { preserveState: true });
    };

    const openEdit = (admin) => {
        setEditAdmin(admin);
        editForm.setData({
            name: admin.name,
            email: admin.email,
            status: admin.status,
            role_ids: admin.roles?.map((r) => r.id) ?? [],
        });
        setEditOpen(true);
    };

    const toggleStatus = (admin) => {
        if (!canUpdate) return;
        router.post(route('admin.admins.toggle-status', admin.id), {}, { preserveScroll: true });
    };

    const doDelete = (admin) => {
        if (!canDelete) return;
        router.delete(route('admin.admins.destroy', admin.id), { preserveScroll: true });
        setDeleteConfirm(null);
    };

    const data = admins?.data ?? [];
    const links = admins?.links ?? [];

    return (
        <AdminLayout title={t('admin.admin_management')}>
            <Head title={`${t('admin.admin_management')} · Admin`} />
            <div className="space-y-6">
                {pageFlash?.success && (
                    <div className="rounded-soft-lg bg-green-50 dark:bg-green-900/30 text-green-800 dark:text-green-200 px-4 py-2 text-sm">{pageFlash.success}</div>
                )}
                {pageFlash?.error && (
                    <div className="rounded-soft-lg bg-red-50 dark:bg-red-900/30 text-red-800 dark:text-red-200 px-4 py-2 text-sm">{pageFlash.error}</div>
                )}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 className="text-xl font-semibold text-neutral-900 dark:text-neutral-100">{t('admin.admin_management')}</h2>
                        <p className="mt-0.5 text-sm text-neutral-500 dark:text-neutral-400">{t('admin.admin_management_desc')}</p>
                    </div>
                    {canCreate && (
                        <Button onClick={() => setAddOpen(true)}>{t('admin.add_admin')}</Button>
                    )}
                </div>

                <Card>
                    <Card.Body>
                        <p className="text-sm text-neutral-500 dark:text-neutral-400">{t('admin.total_admins')}</p>
                        <p className="text-2xl font-semibold text-neutral-900 dark:text-neutral-100">{admins?.total ?? 0}</p>
                    </Card.Body>
                </Card>

                <form onSubmit={submitSearch} className="flex flex-wrap gap-2">
                    <input
                        type="text"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder={t('admin.search_name_email')}
                        className="rounded-soft border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 px-3 py-2 text-sm w-56 focus:outline-none focus:ring-2 focus:ring-brand-500/20"
                    />
                    <Button type="submit" variant="outline" size="sm">{t('common.search')}</Button>
                </form>

                <Card>
                    <div className="overflow-x-auto">
                        <table className="min-w-full text-sm">
                            <thead>
                                <tr className="border-b border-neutral-200 dark:border-neutral-700 text-left text-neutral-500 dark:text-neutral-400">
                                    <th className="pb-2 pr-4 font-medium">{t('admin.col_admin')}</th>
                                    <th className="pb-2 pr-4 font-medium">{t('admin.col_roles')}</th>
                                    <th className="pb-2 pr-4 font-medium">{t('admin.col_status')}</th>
                                    <th className="pb-2 pr-4 font-medium w-28">{t('common.actions')}</th>
                                </tr>
                            </thead>
                            <tbody>
                                {data.map((a) => (
                                    <tr key={a.id} className="border-b border-neutral-100 dark:border-neutral-800">
                                        <td className="py-3 pr-4">
                                            <div className="font-medium text-neutral-900 dark:text-neutral-100">{a.name}</div>
                                            <div className="text-neutral-500 dark:text-neutral-400">{a.email}</div>
                                        </td>
                                        <td className="py-3 pr-4">
                                            <div className="flex flex-wrap gap-1">
                                                {a.roles?.map((r) => (
                                                    <Badge key={r.id} variant="default" className="text-xs">{r.key}</Badge>
                                                ))}
                                                {(!a.roles || a.roles.length === 0) && <span className="text-neutral-400">—</span>}
                                            </div>
                                        </td>
                                        <td className="py-3 pr-4">
                                            <Badge variant={a.status === STATUS_ACTIVE ? 'success' : 'default'}>
                                                {a.status === STATUS_ACTIVE ? t('admin.status_active') : t('admin.status_inactive')}
                                            </Badge>
                                        </td>
                                        <td className="py-3 pr-4">
                                            <div className="flex items-center gap-1">
                                                {canUpdate && (
                                                    <>
                                                        <button
                                                            type="button"
                                                            onClick={() => toggleStatus(a)}
                                                            className="rounded-soft p-1.5 text-neutral-500 hover:bg-neutral-100 hover:text-neutral-700 dark:hover:bg-neutral-800 dark:hover:text-neutral-300"
                                                            title={a.status === STATUS_ACTIVE ? t('admin.deactivate') : t('admin.activate')}
                                                        >
                                                            {a.status === STATUS_ACTIVE ? <Lock className="h-4 w-4" /> : <Unlock className="h-4 w-4" />}
                                                        </button>
                                                        <button
                                                            type="button"
                                                            onClick={() => openEdit(a)}
                                                            className="rounded-soft p-1.5 text-neutral-500 hover:bg-neutral-100 hover:text-neutral-700 dark:hover:bg-neutral-800 dark:hover:text-neutral-300"
                                                            title={t('common.edit')}
                                                        >
                                                            <Pencil className="h-4 w-4" />
                                                        </button>
                                                    </>
                                                )}
                                                {canDelete && (
                                                    <button
                                                        type="button"
                                                        onClick={() => setDeleteConfirm(a)}
                                                        className="rounded-soft p-1.5 text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20"
                                                        title={t('common.delete')}
                                                    >
                                                        <Trash2 className="h-4 w-4" />
                                                    </button>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    {data.length === 0 && (
                        <div className="py-8 text-center text-neutral-500 dark:text-neutral-400">{t('admin.no_admins_found')}</div>
                    )}
                    <Pagination data={admins} />
                </Card>
            </div>

            {/* Add Admin Modal */}
            <Modal show={addOpen} onClose={() => setAddOpen(false)}>
                <Modal.Header title={t('admin.add_admin')} onClose={() => setAddOpen(false)} />
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        addForm.post(route('admin.admins.store'), {
                            onSuccess: () => {
                                setAddOpen(false);
                                addForm.reset();
                            },
                        });
                    }}
                >
                    <Modal.Body>
                        <div className="space-y-4">
                            <div>
                                <label className="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-1">{t('common.name')}</label>
                                <input
                                    type="text"
                                    value={addForm.data.name}
                                    onChange={(e) => addForm.setData('name', e.target.value)}
                                    className="w-full rounded-soft border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 px-3 py-2 text-sm"
                                    required
                                />
                                {addForm.errors.name && <p className="mt-1 text-sm text-red-500">{addForm.errors.name}</p>}
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-1">{t('admin.email')}</label>
                                <input
                                    type="email"
                                    value={addForm.data.email}
                                    onChange={(e) => addForm.setData('email', e.target.value)}
                                    className="w-full rounded-soft border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 px-3 py-2 text-sm"
                                    required
                                />
                                {addForm.errors.email && <p className="mt-1 text-sm text-red-500">{addForm.errors.email}</p>}
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-1">{t('client.password')}</label>
                                <input
                                    type="password"
                                    value={addForm.data.password}
                                    onChange={(e) => addForm.setData('password', e.target.value)}
                                    className="w-full rounded-soft border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 px-3 py-2 text-sm"
                                    required
                                />
                                {addForm.errors.password && <p className="mt-1 text-sm text-red-500">{addForm.errors.password}</p>}
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-1">{t('admin.confirm_password_label')}</label>
                                <input
                                    type="password"
                                    value={addForm.data.password_confirmation}
                                    onChange={(e) => addForm.setData('password_confirmation', e.target.value)}
                                    className="w-full rounded-soft border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 px-3 py-2 text-sm"
                                />
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-1">{t('admin.col_status')}</label>
                                <select
                                    value={addForm.data.status}
                                    onChange={(e) => addForm.setData('status', e.target.value)}
                                    className="w-full rounded-soft border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 px-3 py-2 text-sm"
                                >
                                    <option value={STATUS_ACTIVE}>{t('admin.status_active')}</option>
                                    <option value={STATUS_INACTIVE}>{t('admin.status_inactive')}</option>
                                </select>
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-2">{t('admin.col_roles')}</label>
                                <div className="space-y-2">
                                    {roles?.map((r) => (
                                        <label key={r.id} className="flex items-center gap-2">
                                            <input
                                                type="checkbox"
                                                checked={addForm.data.role_ids.includes(r.id)}
                                                onChange={(e) => {
                                                    const ids = e.target.checked
                                                        ? [...addForm.data.role_ids, r.id]
                                                        : addForm.data.role_ids.filter((id) => id !== r.id);
                                                    addForm.setData('role_ids', ids);
                                                }}
                                                className="rounded border-neutral-300 dark:border-neutral-600"
                                            />
                                            <span className="text-sm">{r.name} ({r.key})</span>
                                        </label>
                                    ))}
                                </div>
                            </div>
                        </div>
                    </Modal.Body>
                    <Modal.Footer>
                        <Button type="button" variant="outline" onClick={() => setAddOpen(false)}>{t('common.cancel')}</Button>
                        <Button type="submit" disabled={addForm.processing}>{t('common.create')}</Button>
                    </Modal.Footer>
                </form>
            </Modal>

            {/* Edit Admin Modal */}
            <Modal show={editOpen} onClose={() => setEditOpen(false)}>
                <Modal.Header title={t('admin.edit_admin')} onClose={() => setEditOpen(false)} />
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        if (!editAdmin) return;
                        editForm.put(route('admin.admins.update', editAdmin.id), {
                            onSuccess: () => {
                                setEditOpen(false);
                                setEditAdmin(null);
                            },
                        });
                    }}
                >
                    <Modal.Body>
                        <div className="space-y-4">
                            <div>
                                <label className="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-1">{t('common.name')}</label>
                                <input
                                    type="text"
                                    value={editForm.data.name}
                                    onChange={(e) => editForm.setData('name', e.target.value)}
                                    className="w-full rounded-soft border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 px-3 py-2 text-sm"
                                    required
                                />
                                {editForm.errors.name && <p className="mt-1 text-sm text-red-500">{editForm.errors.name}</p>}
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-1">{t('admin.email')}</label>
                                <input
                                    type="email"
                                    value={editForm.data.email}
                                    onChange={(e) => editForm.setData('email', e.target.value)}
                                    className="w-full rounded-soft border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 px-3 py-2 text-sm"
                                    required
                                />
                                {editForm.errors.email && <p className="mt-1 text-sm text-red-500">{editForm.errors.email}</p>}
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-1">{t('admin.col_status')}</label>
                                <select
                                    value={editForm.data.status}
                                    onChange={(e) => editForm.setData('status', e.target.value)}
                                    className="w-full rounded-soft border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 px-3 py-2 text-sm"
                                >
                                    <option value={STATUS_ACTIVE}>{t('admin.status_active')}</option>
                                    <option value={STATUS_INACTIVE}>{t('admin.status_inactive')}</option>
                                </select>
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-2">{t('admin.col_roles')}</label>
                                <div className="space-y-2">
                                    {roles?.map((r) => (
                                        <label key={r.id} className="flex items-center gap-2">
                                            <input
                                                type="checkbox"
                                                checked={editForm.data.role_ids.includes(r.id)}
                                                onChange={(e) => {
                                                    const ids = e.target.checked
                                                        ? [...editForm.data.role_ids, r.id]
                                                        : editForm.data.role_ids.filter((id) => id !== r.id);
                                                    editForm.setData('role_ids', ids);
                                                }}
                                                className="rounded border-neutral-300 dark:border-neutral-600"
                                            />
                                            <span className="text-sm">{r.name} ({r.key})</span>
                                        </label>
                                    ))}
                                </div>
                            </div>
                        </div>
                    </Modal.Body>
                    <Modal.Footer>
                        <Button type="button" variant="outline" onClick={() => setEditOpen(false)}>{t('common.cancel')}</Button>
                        <Button type="submit" disabled={editForm.processing}>{t('admin.update')}</Button>
                    </Modal.Footer>
                </form>
            </Modal>

            {/* Delete confirm */}
            <Modal show={!!deleteConfirm} onClose={() => setDeleteConfirm(null)} maxWidth="sm">
                <Modal.Header title={t('admin.delete_admin')} onClose={() => setDeleteConfirm(null)} />
                <Modal.Body>
                    <p className="text-sm text-neutral-600 dark:text-neutral-400">
                        {t('admin.confirm_delete_admin', { name: deleteConfirm?.name })}
                    </p>
                </Modal.Body>
                <Modal.Footer>
                    <Button variant="outline" onClick={() => setDeleteConfirm(null)}>{t('common.cancel')}</Button>
                    <Button variant="danger" onClick={() => doDelete(deleteConfirm)}>{t('common.delete')}</Button>
                </Modal.Footer>
            </Modal>
        </AdminLayout>
    );
}
