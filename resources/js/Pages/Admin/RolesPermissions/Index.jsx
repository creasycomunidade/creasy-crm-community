import AdminLayout from '@/Layouts/AdminLayout';
import { Badge, Button, Card, Modal, Tabs } from '@/Components/ui';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { Pencil, Trash2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';

export default function AdminRolesPermissionsIndex({ roles = [], permissions = [], filters = {}, flash }) {
    const { t } = useTranslation();
    const { auth } = usePage().props;
    const pageFlash = usePage().props.flash || flash;
    const permissionsList = auth?.permissions ?? [];
    const canManageRoles = permissionsList.includes('manage_admin_roles');

    const [tabIndex, setTabIndex] = useState(0);
    const [roleSearch, setRoleSearch] = useState(filters.role_search ?? '');
    const [permissionSearch, setPermissionSearch] = useState(filters.permission_search ?? '');

    const [addRoleOpen, setAddRoleOpen] = useState(false);
    const [editRoleOpen, setEditRoleOpen] = useState(false);
    const [editingRole, setEditingRole] = useState(null);
    const [deleteRoleConfirm, setDeleteRoleConfirm] = useState(null);

    const [addPermOpen, setAddPermOpen] = useState(false);
    const [editPermOpen, setEditPermOpen] = useState(false);
    const [editingPerm, setEditingPerm] = useState(null);
    const [deletePermConfirm, setDeletePermConfirm] = useState(null);

    const roleForm = useForm({ name: '', key: '', description: '', permission_ids: [] });
    const editRoleForm = useForm({ name: '', key: '', description: '', permission_ids: [] });
    const permForm = useForm({ key: '', name: '', category: '', description: '' });
    const editPermForm = useForm({ key: '', name: '', category: '', description: '' });

    const submitRoleSearch = (e) => {
        e.preventDefault();
        router.get(route('admin.roles-permissions.index'), { role_search: roleSearch || undefined, permission_search: permissionSearch || undefined }, { preserveState: true });
    };

    const submitPermissionSearch = (e) => {
        e.preventDefault();
        router.get(route('admin.roles-permissions.index'), { role_search: roleSearch || undefined, permission_search: permissionSearch || undefined }, { preserveState: true });
    };

    const openEditRole = (role) => {
        setEditingRole(role);
        editRoleForm.setData({
            name: role.name,
            key: role.key,
            description: role.description ?? '',
            permission_ids: role.permission_ids ?? [],
        });
        setEditRoleOpen(true);
    };

    const openEditPermission = (perm) => {
        setEditingPerm(perm);
        editPermForm.setData({
            key: perm.key,
            name: perm.name,
            category: perm.category,
            description: perm.description ?? '',
        });
        setEditPermOpen(true);
    };

    const categories = [...new Set((permissions || []).map((p) => p.category))].sort();

    return (
        <AdminLayout title={t('admin.roles_permissions')}>
            <Head title={`${t('admin.roles_permissions')} · ${t('head.admin')}`} />
            <div className="space-y-6">
                {pageFlash?.success && (
                    <div className="rounded-soft-lg bg-green-50 dark:bg-green-900/30 text-green-800 dark:text-green-200 px-4 py-2 text-sm">{pageFlash.success}</div>
                )}
                {pageFlash?.error && (
                    <div className="rounded-soft-lg bg-red-50 dark:bg-red-900/30 text-red-800 dark:text-red-200 px-4 py-2 text-sm">{pageFlash.error}</div>
                )}
                <div>
                    <h2 className="text-xl font-semibold text-neutral-900 dark:text-neutral-100">{t('admin.roles_permissions')}</h2>
                    <p className="mt-0.5 text-sm text-neutral-500 dark:text-neutral-400">{t('admin.roles_permissions_desc')}</p>
                </div>

                <div className="grid gap-4 sm:grid-cols-2">
                    <Card>
                        <Card.Body>
                            <p className="text-sm text-neutral-500 dark:text-neutral-400">{t('admin.total_roles')}</p>
                            <p className="text-2xl font-semibold text-neutral-900 dark:text-neutral-100">{roles?.length ?? 0}</p>
                        </Card.Body>
                    </Card>
                    <Card>
                        <Card.Body>
                            <p className="text-sm text-neutral-500 dark:text-neutral-400">{t('admin.total_permissions')}</p>
                            <p className="text-2xl font-semibold text-neutral-900 dark:text-neutral-100">{permissions?.length ?? 0}</p>
                        </Card.Body>
                    </Card>
                </div>

                <Tabs tabs={[{ label: t('admin.tab_roles') }, { label: t('admin.tab_permissions') }]} defaultIndex={0} onChange={(i) => setTabIndex(i)}>
                    <div className="py-4">
                        {tabIndex === 0 && (
                            <div className="space-y-4">
                                <div className="flex flex-wrap items-center justify-between gap-2">
                                    <form onSubmit={submitRoleSearch} className="flex gap-2">
                                        <input type="text" value={roleSearch} onChange={(e) => setRoleSearch(e.target.value)} placeholder={t('admin.search_roles')} className="rounded-soft border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 px-3 py-2 text-sm w-48" />
                                        <Button type="submit" variant="outline" size="sm">{t('common.search')}</Button>
                                    </form>
                                    {canManageRoles && <Button onClick={() => setAddRoleOpen(true)}>{t('admin.add_role')}</Button>}
                                </div>
                                <Card>
                                    <div className="overflow-x-auto">
                                        <table className="min-w-full text-sm">
                                            <thead>
                                                <tr className="border-b border-neutral-200 dark:border-neutral-700 text-left text-neutral-500 dark:text-neutral-400">
                                                    <th className="pb-2 pr-4 font-medium">{t('admin.role_name_label')}</th>
                                                    <th className="pb-2 pr-4 font-medium">{t('admin.col_description')}</th>
                                                    <th className="pb-2 pr-4 font-medium">{t('admin.col_permissions')}</th>
                                                    <th className="pb-2 pr-4 font-medium">{t('admin.col_admins')}</th>
                                                    <th className="pb-2 pr-4 w-24">{t('common.actions')}</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {(roles || []).map((r) => (
                                                    <tr key={r.id} className="border-b border-neutral-100 dark:border-neutral-800">
                                                        <td className="py-3 pr-4 font-medium text-neutral-900 dark:text-neutral-100">{r.name}</td>
                                                        <td className="py-3 pr-4 text-neutral-600 dark:text-neutral-400">{r.description || '—'}</td>
                                                        <td className="py-3 pr-4"><Badge variant="default" size="sm">{r.permissions_count ?? 0}</Badge></td>
                                                        <td className="py-3 pr-4">{r.admins_count ?? 0}</td>
                                                        <td className="py-3 pr-4">
                                                            {canManageRoles && !r.is_system && (
                                                                <div className="flex gap-1">
                                                                    <button type="button" onClick={() => openEditRole(r)} className="rounded-soft p-1.5 text-neutral-500 hover:bg-neutral-100 dark:hover:bg-neutral-800" title={t('admin.edit_role')}><Pencil className="h-4 w-4" /></button>
                                                                    <button type="button" onClick={() => setDeleteRoleConfirm(r)} className="rounded-soft p-1.5 text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20" title={t('admin.delete_role')}><Trash2 className="h-4 w-4" /></button>
                                                                </div>
                                                            )}
                                                            {r.is_system && <span className="text-xs text-neutral-400">{t('admin.system_role_badge')}</span>}
                                                        </td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                    {(!roles || roles.length === 0) && <div className="py-8 text-center text-neutral-500 dark:text-neutral-400">{t('admin.no_roles_found')}</div>}
                                </Card>
                            </div>
                        )}

                        {tabIndex === 1 && (
                            <div className="space-y-4">
                                <div className="flex flex-wrap items-center justify-between gap-2">
                                    <form onSubmit={submitPermissionSearch} className="flex gap-2">
                                        <input type="text" value={permissionSearch} onChange={(e) => setPermissionSearch(e.target.value)} placeholder={t('admin.search_permissions')} className="rounded-soft border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 px-3 py-2 text-sm w-56" />
                                        <Button type="submit" variant="outline" size="sm">{t('common.search')}</Button>
                                    </form>
                                    {canManageRoles && <Button onClick={() => setAddPermOpen(true)}>{t('admin.add_permission')}</Button>}
                                </div>
                                <Card>
                                    <div className="overflow-x-auto">
                                        <table className="min-w-full text-sm">
                                            <thead>
                                                <tr className="border-b border-neutral-200 dark:border-neutral-700 text-left text-neutral-500 dark:text-neutral-400">
                                                    <th className="pb-2 pr-4 font-medium">{t('admin.col_key')}</th>
                                                    <th className="pb-2 pr-4 font-medium">{t('common.name')}</th>
                                                    <th className="pb-2 pr-4 font-medium">{t('admin.col_category')}</th>
                                                    <th className="pb-2 pr-4 font-medium">{t('admin.col_description')}</th>
                                                    <th className="pb-2 pr-4 w-24">{t('common.actions')}</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {(permissions || []).map((p) => (
                                                    <tr key={p.id} className="border-b border-neutral-100 dark:border-neutral-800">
                                                        <td className="py-3 pr-4 font-mono text-neutral-900 dark:text-neutral-100">{p.key}</td>
                                                        <td className="py-3 pr-4 text-neutral-900 dark:text-neutral-100">{p.name}</td>
                                                        <td className="py-3 pr-4"><Badge variant="default" size="sm">{p.category}</Badge></td>
                                                        <td className="py-3 pr-4 text-neutral-600 dark:text-neutral-400">{p.description || '—'}</td>
                                                        <td className="py-3 pr-4">
                                                            {canManageRoles && (
                                                                <div className="flex gap-1">
                                                                    <button type="button" onClick={() => openEditPermission(p)} className="rounded-soft p-1.5 text-neutral-500 hover:bg-neutral-100 dark:hover:bg-neutral-800" title={t('admin.edit_permission')}><Pencil className="h-4 w-4" /></button>
                                                                    <button type="button" onClick={() => setDeletePermConfirm(p)} className="rounded-soft p-1.5 text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20" title={t('admin.delete_permission')}><Trash2 className="h-4 w-4" /></button>
                                                                </div>
                                                            )}
                                                        </td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                    {(!permissions || permissions.length === 0) && <div className="py-8 text-center text-neutral-500 dark:text-neutral-400">{t('admin.no_permissions_found')}</div>}
                                </Card>
                            </div>
                        )}
                    </div>
                </Tabs>
            </div>

            {/* Add Role Modal */}
            <Modal show={addRoleOpen} onClose={() => setAddRoleOpen(false)} maxWidth="2xl">
                <Modal.Header title={t('admin.add_role')} onClose={() => setAddRoleOpen(false)} />
                <form onSubmit={(e) => { e.preventDefault(); roleForm.post(route('admin.roles.store'), { onSuccess: () => { setAddRoleOpen(false); roleForm.reset(); } }); }}>
                    <Modal.Body>
                        <div className="space-y-4">
                            <div>
                                <label className="block text-sm font-medium mb-1">{t('admin.role_name_label')}</label>
                                <input type="text" value={roleForm.data.name} onChange={(e) => roleForm.setData('name', e.target.value)} className="w-full rounded-soft border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 px-3 py-2 text-sm" required />
                                {roleForm.errors.name && <p className="mt-1 text-sm text-red-500">{roleForm.errors.name}</p>}
                            </div>
                            <div>
                                <label className="block text-sm font-medium mb-1">{t('admin.role_key_label')}</label>
                                <input type="text" value={roleForm.data.key} onChange={(e) => roleForm.setData('key', e.target.value.toUpperCase())} className="w-full rounded-soft border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 px-3 py-2 text-sm font-mono" placeholder={t('admin.role_key_placeholder')} required />
                                {roleForm.errors.key && <p className="mt-1 text-sm text-red-500">{roleForm.errors.key}</p>}
                            </div>
                            <div>
                                <label className="block text-sm font-medium mb-1">{t('admin.col_description')}</label>
                                <input type="text" value={roleForm.data.description} onChange={(e) => roleForm.setData('description', e.target.value)} className="w-full rounded-soft border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 px-3 py-2 text-sm" />
                            </div>
                            <div>
                                <label className="block text-sm font-medium mb-2">{t('admin.col_permissions')}</label>
                                <div className="max-h-48 overflow-y-auto space-y-2 border border-neutral-200 dark:border-neutral-700 rounded-soft p-3">
                                    {categories.map((cat) => (
                                        <div key={cat}>
                                            <p className="text-xs font-medium text-neutral-500 dark:text-neutral-400 mb-1">{cat}</p>
                                            {permissions.filter((p) => p.category === cat).map((p) => (
                                                <label key={p.id} className="flex items-center gap-2 ml-2">
                                                    <input type="checkbox" checked={roleForm.data.permission_ids.includes(p.id)} onChange={(e) => { const ids = e.target.checked ? [...roleForm.data.permission_ids, p.id] : roleForm.data.permission_ids.filter((id) => id !== p.id); roleForm.setData('permission_ids', ids); }} className="rounded" />
                                                    <span className="text-sm">{p.key}</span>
                                                </label>
                                            ))}
                                        </div>
                                    ))}
                                </div>
                            </div>
                        </div>
                    </Modal.Body>
                    <Modal.Footer>
                        <Button type="button" variant="outline" onClick={() => setAddRoleOpen(false)}>{t('common.cancel')}</Button>
                        <Button type="submit" disabled={roleForm.processing}>{t('common.create')}</Button>
                    </Modal.Footer>
                </form>
            </Modal>

            {/* Edit Role Modal */}
            <Modal show={editRoleOpen} onClose={() => { setEditRoleOpen(false); setEditingRole(null); }} maxWidth="2xl">
                <Modal.Header title={t('admin.edit_role')} onClose={() => { setEditRoleOpen(false); setEditingRole(null); }} />
                {editingRole && (
                    <form onSubmit={(e) => { e.preventDefault(); editRoleForm.put(route('admin.roles.update', editingRole.id), { onSuccess: () => { setEditRoleOpen(false); setEditingRole(null); } }); }}>
                        <Modal.Body>
                            <div className="space-y-4">
                                <div>
                                    <label className="block text-sm font-medium mb-1">{t('admin.role_name_label')}</label>
                                    <input type="text" value={editRoleForm.data.name} onChange={(e) => editRoleForm.setData('name', e.target.value)} className="w-full rounded-soft border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 px-3 py-2 text-sm" required />
                                    {editRoleForm.errors.name && <p className="mt-1 text-sm text-red-500">{editRoleForm.errors.name}</p>}
                                </div>
                                <div>
                                    <label className="block text-sm font-medium mb-1">{t('admin.role_key_label')}</label>
                                    <input type="text" value={editRoleForm.data.key} onChange={(e) => editRoleForm.setData('key', e.target.value.toUpperCase())} className="w-full rounded-soft border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 px-3 py-2 text-sm font-mono" required readOnly={editingRole?.is_system} />
                                    {editRoleForm.errors.key && <p className="mt-1 text-sm text-red-500">{editRoleForm.errors.key}</p>}
                                </div>
                                <div>
                                    <label className="block text-sm font-medium mb-1">{t('admin.col_description')}</label>
                                    <input type="text" value={editRoleForm.data.description} onChange={(e) => editRoleForm.setData('description', e.target.value)} className="w-full rounded-soft border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 px-3 py-2 text-sm" />
                                </div>
                                <div>
                                    <label className="block text-sm font-medium mb-2">{t('admin.col_permissions')}</label>
                                    <div className="max-h-48 overflow-y-auto space-y-2 border border-neutral-200 dark:border-neutral-700 rounded-soft p-3">
                                        {categories.map((cat) => (
                                            <div key={cat}>
                                                <p className="text-xs font-medium text-neutral-500 dark:text-neutral-400 mb-1">{cat}</p>
                                                {permissions.filter((p) => p.category === cat).map((p) => (
                                                    <label key={p.id} className="flex items-center gap-2 ml-2">
                                                        <input type="checkbox" checked={editRoleForm.data.permission_ids.includes(p.id)} onChange={(e) => { const ids = e.target.checked ? [...editRoleForm.data.permission_ids, p.id] : editRoleForm.data.permission_ids.filter((id) => id !== p.id); editRoleForm.setData('permission_ids', ids); }} className="rounded" />
                                                        <span className="text-sm">{p.key}</span>
                                                    </label>
                                                ))}
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            </div>
                        </Modal.Body>
                        <Modal.Footer>
                            <Button type="button" variant="outline" onClick={() => { setEditRoleOpen(false); setEditingRole(null); }}>{t('common.cancel')}</Button>
                            <Button type="submit" disabled={editRoleForm.processing}>{t('common.save')}</Button>
                        </Modal.Footer>
                    </form>
                )}
            </Modal>

            {/* Add Permission Modal */}
            <Modal show={addPermOpen} onClose={() => setAddPermOpen(false)}>
                <Modal.Header title={t('admin.add_permission')} onClose={() => setAddPermOpen(false)} />
                <form onSubmit={(e) => { e.preventDefault(); permForm.post(route('admin.permissions.store'), { onSuccess: () => { setAddPermOpen(false); permForm.reset(); } }); }}>
                    <Modal.Body>
                        <div className="space-y-4">
                            <div>
                                <label className="block text-sm font-medium mb-1">{t('admin.permission_key_label')}</label>
                                <input type="text" value={permForm.data.key} onChange={(e) => permForm.setData('key', e.target.value)} className="w-full rounded-soft border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 px-3 py-2 text-sm font-mono" placeholder={t('admin.permission_key_placeholder')} required />
                                {permForm.errors.key && <p className="mt-1 text-sm text-red-500">{permForm.errors.key}</p>}
                            </div>
                            <div>
                                <label className="block text-sm font-medium mb-1">{t('admin.permission_name_label')}</label>
                                <input type="text" value={permForm.data.name} onChange={(e) => permForm.setData('name', e.target.value)} className="w-full rounded-soft border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 px-3 py-2 text-sm" required />
                                {permForm.errors.name && <p className="mt-1 text-sm text-red-500">{permForm.errors.name}</p>}
                            </div>
                            <div>
                                <label className="block text-sm font-medium mb-1">{t('admin.permission_category_label')}</label>
                                <input type="text" value={permForm.data.category} onChange={(e) => permForm.setData('category', e.target.value)} className="w-full rounded-soft border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 px-3 py-2 text-sm" required />
                                {permForm.errors.category && <p className="mt-1 text-sm text-red-500">{permForm.errors.category}</p>}
                            </div>
                            <div>
                                <label className="block text-sm font-medium mb-1">{t('admin.permission_description_label')}</label>
                                <input type="text" value={permForm.data.description} onChange={(e) => permForm.setData('description', e.target.value)} className="w-full rounded-soft border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 px-3 py-2 text-sm" />
                            </div>
                        </div>
                    </Modal.Body>
                    <Modal.Footer>
                        <Button type="button" variant="outline" onClick={() => setAddPermOpen(false)}>{t('common.cancel')}</Button>
                        <Button type="submit" disabled={permForm.processing}>{t('common.create')}</Button>
                    </Modal.Footer>
                </form>
            </Modal>

            {/* Edit Permission Modal */}
            <Modal show={editPermOpen} onClose={() => { setEditPermOpen(false); setEditingPerm(null); }}>
                <Modal.Header title={t('admin.edit_permission')} onClose={() => { setEditPermOpen(false); setEditingPerm(null); }} />
                {editingPerm && (
                    <form onSubmit={(e) => { e.preventDefault(); editPermForm.put(route('admin.permissions.update', editingPerm.id), { onSuccess: () => { setEditPermOpen(false); setEditingPerm(null); } }); }}>
                        <Modal.Body>
                            <div className="space-y-4">
                                <div>
                                    <label className="block text-sm font-medium mb-1">{t('admin.permission_key_label')}</label>
                                    <input type="text" value={editPermForm.data.key} onChange={(e) => editPermForm.setData('key', e.target.value)} className="w-full rounded-soft border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 px-3 py-2 text-sm font-mono" required />
                                    {editPermForm.errors.key && <p className="mt-1 text-sm text-red-500">{editPermForm.errors.key}</p>}
                                </div>
                                <div>
                                    <label className="block text-sm font-medium mb-1">{t('admin.permission_name_label')}</label>
                                    <input type="text" value={editPermForm.data.name} onChange={(e) => editPermForm.setData('name', e.target.value)} className="w-full rounded-soft border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 px-3 py-2 text-sm" required />
                                    {editPermForm.errors.name && <p className="mt-1 text-sm text-red-500">{editPermForm.errors.name}</p>}
                                </div>
                                <div>
                                    <label className="block text-sm font-medium mb-1">{t('admin.permission_category_label')}</label>
                                    <input type="text" value={editPermForm.data.category} onChange={(e) => editPermForm.setData('category', e.target.value)} className="w-full rounded-soft border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 px-3 py-2 text-sm" required />
                                    {editPermForm.errors.category && <p className="mt-1 text-sm text-red-500">{editPermForm.errors.category}</p>}
                                </div>
                                <div>
                                    <label className="block text-sm font-medium mb-1">{t('admin.permission_description_label')}</label>
                                    <input type="text" value={editPermForm.data.description} onChange={(e) => editPermForm.setData('description', e.target.value)} className="w-full rounded-soft border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 px-3 py-2 text-sm" />
                                </div>
                            </div>
                        </Modal.Body>
                        <Modal.Footer>
                            <Button type="button" variant="outline" onClick={() => { setEditPermOpen(false); setEditingPerm(null); }}>{t('common.cancel')}</Button>
                            <Button type="submit" disabled={editPermForm.processing}>{t('common.save')}</Button>
                        </Modal.Footer>
                    </form>
                )}
            </Modal>

            {/* Delete Role Confirm */}
            <Modal show={!!deleteRoleConfirm} onClose={() => setDeleteRoleConfirm(null)} maxWidth="sm">
                <Modal.Header title={t('admin.delete_role')} onClose={() => setDeleteRoleConfirm(null)} />
                <Modal.Body>
                    <p className="text-sm text-neutral-600 dark:text-neutral-400">
                        {t('admin.confirm_delete_role', { name: deleteRoleConfirm?.name })}
                    </p>
                </Modal.Body>
                <Modal.Footer>
                    <Button variant="outline" onClick={() => setDeleteRoleConfirm(null)}>{t('common.cancel')}</Button>
                    <Button variant="danger" onClick={() => { router.delete(route('admin.roles.destroy', deleteRoleConfirm?.id), { preserveScroll: true }); setDeleteRoleConfirm(null); }}>{t('common.delete')}</Button>
                </Modal.Footer>
            </Modal>

            {/* Delete Permission Confirm */}
            <Modal show={!!deletePermConfirm} onClose={() => setDeletePermConfirm(null)} maxWidth="sm">
                <Modal.Header title={t('admin.delete_permission')} onClose={() => setDeletePermConfirm(null)} />
                <Modal.Body>
                    <p className="text-sm text-neutral-600 dark:text-neutral-400">
                        {t('admin.confirm_delete_permission', { name: deletePermConfirm?.key })}
                    </p>
                </Modal.Body>
                <Modal.Footer>
                    <Button variant="outline" onClick={() => setDeletePermConfirm(null)}>{t('common.cancel')}</Button>
                    <Button variant="danger" onClick={() => { router.delete(route('admin.permissions.destroy', deletePermConfirm?.id), { preserveScroll: true }); setDeletePermConfirm(null); }}>{t('common.delete')}</Button>
                </Modal.Footer>
            </Modal>
        </AdminLayout>
    );
}
