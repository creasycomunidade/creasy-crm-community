import AdminLayout from '@/Layouts/AdminLayout';
import { Badge, Button, Card, Modal, Pagination, Tooltip } from '@/Components/ui';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useState, useCallback } from 'react';
import { useTranslation } from 'react-i18next';
import { Download } from 'lucide-react';
import {
    Pencil,
    Users,
    CheckCircle,
    LogIn,
    Trash2,
} from 'lucide-react';
import axios from 'axios';

const STATUS_ACTIVE = 'active';
const CLIENT_ROLE_ADMIN = 'administrator';
const CLIENT_ROLE_STAFF = 'staff';

function ActionIcon({ onClick, title, icon: Icon, className = 'text-brand-500 hover:text-brand-600' }) {
    return (
        <Tooltip content={title}>
            <button
                type="button"
                onClick={onClick}
                className={`rounded-soft p-1.5 transition ${className}`}
                aria-label={title}
            >
                <Icon className="h-4 w-4" />
            </button>
        </Tooltip>
    );
}

export default function AdminClientsIndex({ clients, filters = {} }) {
    const { t } = useTranslation();
    const page = usePage();
    const pageFlash = page.props.flash || {};
    const pageErrors = page.props.errors || {};
    const auth = page.props.auth || {};
    const permissions = auth.permissions || [];
    const canCreate = permissions.includes('create_clients');
    const canUpdate = permissions.includes('update_clients');
    const canDelete = permissions.includes('delete_clients');

    const [search, setSearch] = useState(filters.search ?? '');
    const [addClientOpen, setAddClientOpen] = useState(false);
    const [editClientOpen, setEditClientOpen] = useState(false);
    const [editClient, setEditClient] = useState(null);
    const [manageUsersOpen, setManageUsersOpen] = useState(false);
    const [manageUsersClient, setManageUsersClient] = useState(null);
    const [clientUsers, setClientUsers] = useState([]);
    const [deleteClientConfirm, setDeleteClientConfirm] = useState(null);
    const [addUserOpen, setAddUserOpen] = useState(false);
    const [editUserOpen, setEditUserOpen] = useState(false);
    const [editUser, setEditUser] = useState(null);
    const [deleteUserConfirm, setDeleteUserConfirm] = useState(null);
    const [usersLoading, setUsersLoading] = useState(false);

    const addClientForm = useForm({
        name: '',
        email: '',
        phone: '',
        address: '',
        status: STATUS_ACTIVE,
        base_currency: 'BRL',
        currency_symbol: 'R$',
        currency_position: 'before',
    });

    const editClientForm = useForm({
        name: '',
        email: '',
        phone: '',
        address: '',
        status: STATUS_ACTIVE,
        base_currency: 'BRL',
        currency_symbol: 'R$',
        currency_position: 'before',
    });

    const addUserForm = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
        client_role: CLIENT_ROLE_STAFF,
        status: STATUS_ACTIVE,
    });

    const editUserForm = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
        client_role: CLIENT_ROLE_STAFF,
        status: STATUS_ACTIVE,
    });


    const submitSearch = (e) => {
        e.preventDefault();
        router.get(route('admin.clients.index'), { search: search || undefined }, { preserveState: true });
    };

    const openEditClient = (c) => {
        setEditClient(c);
        editClientForm.setData({
            name: c.name,
            email: c.email ?? '',
            phone: c.phone ?? '',
            address: c.address ?? '',
            status: c.status,
            base_currency: c.base_currency ?? 'BRL',
            currency_symbol: c.currency_symbol ?? 'R$',
            currency_position: c.currency_position ?? 'before',
        });
        setEditClientOpen(true);
    };

    const fetchClientUsers = useCallback(async (client) => {
        setUsersLoading(true);
        try {
            const { data } = await axios.get(route('admin.clients.users.index', { client: client.id }));
            setClientUsers(data.users || []);
            setManageUsersClient(data.client || { id: client.id, name: client.name });
        } finally {
            setUsersLoading(false);
        }
    }, []);

    const openManageUsers = (client) => {
        setManageUsersClient({ id: client.id, name: client.name });
        setManageUsersOpen(true);
        fetchClientUsers(client);
    };

    const openAddUser = () => {
        addUserForm.reset();
        addUserForm.setData({ client_role: CLIENT_ROLE_STAFF, status: STATUS_ACTIVE });
        setAddUserOpen(true);
    };

    const submitAddUser = (e) => {
        e.preventDefault();
        addUserForm.post(route('admin.clients.users.store', { client: manageUsersClient.id }), {
            preserveScroll: true,
            onSuccess: () => {
                addUserForm.reset();
                setAddUserOpen(false);
                fetchClientUsers({ id: manageUsersClient.id, name: manageUsersClient.name });
                router.reload({ only: ['clients'] });
            },
        });
    };

    const openEditUser = (u) => {
        setEditUser(u);
        editUserForm.setData({
            name: u.name,
            email: u.email,
            password: '',
            password_confirmation: '',
            client_role: u.client_role || CLIENT_ROLE_STAFF,
            status: u.status,
        });
        setEditUserOpen(true);
    };

    const submitEditUser = (e) => {
        e.preventDefault();
        editUserForm.put(route('admin.clients.users.update', { client: manageUsersClient.id, user: editUser.id }), {
            preserveScroll: true,
            onSuccess: () => {
                setEditUserOpen(false);
                setEditUser(null);
                fetchClientUsers({ id: manageUsersClient.id, name: manageUsersClient.name });
                router.reload({ only: ['clients'] });
            },
        });
    };

    const doDeleteUser = (u) => {
        router.delete(route('admin.clients.users.destroy', { client: manageUsersClient.id, user: u.id }), {
            preserveScroll: true,
            onSuccess: () => {
                setDeleteUserConfirm(null);
                fetchClientUsers({ id: manageUsersClient.id, name: manageUsersClient.name });
                router.reload({ only: ['clients'] });
            },
        });
    };

    const doImpersonate = (client) => {
        router.post(route('admin.clients.impersonate', { client: client.id }));
    };

    const doDeleteClient = (client) => {
        router.delete(route('admin.clients.destroy', { client: client.id }), {
            preserveScroll: true,
            onSuccess: () => setDeleteClientConfirm(null),
        });
    };

    const data = clients?.data ?? [];
    const links = clients?.links ?? [];

    return (
        <AdminLayout title={t('admin.client_management')}>
            <Head title={`${t('admin.client_management')} · ${t('head.admin')}`} />
            <div className="space-y-6">
                {pageFlash.success && (
                    <div className="rounded-soft-lg bg-green-50 dark:bg-green-900/30 text-green-800 dark:text-green-200 px-4 py-2 text-sm">
                        {pageFlash.success}
                    </div>
                )}
                {pageFlash.error && (
                    <div className="rounded-soft-lg bg-red-50 dark:bg-red-900/30 text-red-800 dark:text-red-200 px-4 py-2 text-sm">
                        {pageFlash.error}
                    </div>
                )}

                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 className="text-xl font-semibold text-neutral-900 dark:text-neutral-100">{t('admin.client_management')}</h2>
                        <p className="mt-0.5 text-sm text-neutral-500 dark:text-neutral-400">{t('admin.client_management_description')}</p>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        {canCreate && (
                            <Button onClick={() => { addClientForm.reset(); setAddClientOpen(true); }}>
                                + {t('admin.add_client')}
                            </Button>
                        )}
                        <a
                            href={route('admin.clients.export') + (search ? `?search=${encodeURIComponent(search)}` : '')}
                            className="inline-flex items-center gap-2 rounded-lg border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 px-3 py-2 text-sm font-medium text-neutral-700 dark:text-neutral-300 hover:bg-neutral-50 dark:hover:bg-neutral-700 transition"
                        >
                            <Download className="h-4 w-4" /> {t('admin.export_csv')}
                        </a>
                    </div>
                </div>

                <form onSubmit={submitSearch} className="flex flex-wrap gap-2">
                    <input
                        type="text"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder={t('admin.search_clients')}
                        className="rounded-soft border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 px-3 py-2 text-sm w-56 focus:outline-none focus:ring-2 focus:ring-brand-500/20"
                    />
                    <Button type="submit" variant="outline" size="sm">{t('common.search')}</Button>
                </form>

                <Card>
                    <div className="overflow-x-auto">
                        <table className="min-w-full text-sm">
                            <thead>
                                <tr className="border-b border-neutral-200 dark:border-neutral-700 text-left text-neutral-500 dark:text-neutral-400">
                                    <th className="pb-2 pr-4 font-medium uppercase">{t('admin.col_name')}</th>
                                    <th className="pb-2 pr-4 font-medium uppercase">{t('admin.col_email')}</th>
                                    <th className="pb-2 pr-4 font-medium uppercase">{t('admin.col_status')}</th>
                                    <th className="pb-2 pr-4 font-medium uppercase">Edição</th>
                                    <th className="pb-2 pr-4 font-medium text-right uppercase">{t('admin.col_actions')}</th>
                                </tr>
                            </thead>
                            <tbody>
                                {data.map((c) => (
                                    <tr key={c.id} className="border-b border-neutral-100 dark:border-neutral-800">
                                        <td className="py-3 pr-4 font-medium text-neutral-900 dark:text-neutral-100">{c.name}</td>
                                        <td className="py-3 pr-4 text-neutral-600 dark:text-neutral-300">{c.email ?? '—'}</td>
                                        <td className="py-3 pr-4">
                                            <Badge variant={c.status === STATUS_ACTIVE ? 'success' : 'default'}>
                                                {c.status === STATUS_ACTIVE ? t('common.active') : t('common.inactive')}
                                            </Badge>
                                        </td>
                                        <td className="py-3 pr-4 text-neutral-900 dark:text-neutral-100">
                                            CREASY CRM Community
                                        </td>
                                        <td className="py-3 pr-4">
                                            <div className="flex items-center justify-end gap-0.5">
                                                {canUpdate && (
                                                    <>
                                                        <ActionIcon title={t('admin.edit_client_title')} icon={Pencil} onClick={() => openEditClient(c)} />
                                                        <ActionIcon title={t('admin.manage_users_title')} icon={Users} onClick={() => openManageUsers(c)} />
                                                        <ActionIcon title={t('admin.impersonate_title')} icon={LogIn} onClick={() => doImpersonate(c)} />
                                                    </>
                                                )}
                                                {canDelete && (
                                                    <ActionIcon
                                                        title={t('admin.delete_client_title')}
                                                        icon={Trash2}
                                                        className="text-amber-500 hover:text-amber-600"
                                                        onClick={() => setDeleteClientConfirm(c)}
                                                    />
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    {data.length === 0 && (
                        <div className="py-8 text-center text-neutral-500 dark:text-neutral-400">{t('admin.no_clients_found')}</div>
                    )}
                    <Pagination data={clients} />
                </Card>
            </div>

            {/* Add Client Modal */}
            <Modal show={addClientOpen} onClose={() => setAddClientOpen(false)} maxWidth="2xl">
                <Modal.Header title={t('admin.add_client')} onClose={() => setAddClientOpen(false)} />
                <form onSubmit={(e) => { e.preventDefault(); addClientForm.post(route('admin.clients.store'), { preserveScroll: true, onSuccess: () => setAddClientOpen(false) }); }}>
                    <Modal.Body className="space-y-4">
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label className="mb-1 block text-sm font-medium text-neutral-700 dark:text-neutral-300">{t('admin.client_name')} <span className="text-red-500">*</span></label>
                                <input
                                    type="text"
                                    value={addClientForm.data.name}
                                    onChange={(e) => addClientForm.setData('name', e.target.value)}
                                    placeholder={t('admin.enter_client_name')}
                                    className="w-full rounded-soft border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 px-3 py-2 text-sm"
                                />
                                {addClientForm.errors.name && <p className="mt-0.5 text-xs text-red-500">{addClientForm.errors.name}</p>}
                            </div>
                            <div>
                                <label className="mb-1 block text-sm font-medium text-neutral-700 dark:text-neutral-300">{t('admin.col_email')}</label>
                                <input
                                    type="email"
                                    value={addClientForm.data.email}
                                    onChange={(e) => addClientForm.setData('email', e.target.value)}
                                    placeholder={t('admin.enter_email')}
                                    className="w-full rounded-soft border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 px-3 py-2 text-sm"
                                />
                                {addClientForm.errors.email && <p className="mt-0.5 text-xs text-red-500">{addClientForm.errors.email}</p>}
                            </div>
                        </div>
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label className="mb-1 block text-sm font-medium text-neutral-700 dark:text-neutral-300">{t('admin.phone')}</label>
                                <input
                                    type="text"
                                    value={addClientForm.data.phone}
                                    onChange={(e) => addClientForm.setData('phone', e.target.value)}
                                    placeholder={t('admin.enter_phone')}
                                    className="w-full rounded-soft border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 px-3 py-2 text-sm"
                                />
                            </div>
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-neutral-700 dark:text-neutral-300">{t('admin.address')}</label>
                            <textarea
                                value={addClientForm.data.address}
                                onChange={(e) => addClientForm.setData('address', e.target.value)}
                                placeholder={t('admin.enter_address')}
                                rows={2}
                                className="w-full rounded-soft border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 px-3 py-2 text-sm"
                            />
                        </div>
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <div>
                                <label className="mb-1 block text-sm font-medium text-neutral-700 dark:text-neutral-300">{t('admin.base_currency')}</label>
                                <input
                                    type="text"
                                    value={addClientForm.data.base_currency}
                                    onChange={(e) => addClientForm.setData('base_currency', e.target.value)}
                                    className="w-full rounded-soft border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 px-3 py-2 text-sm"
                                />
                                <p className="mt-0.5 text-xs text-neutral-500">{t('admin.iso_currency_hint')}</p>
                            </div>
                            <div>
                                <label className="mb-1 block text-sm font-medium text-neutral-700 dark:text-neutral-300">{t('admin.currency_symbol')}</label>
                                <input
                                    type="text"
                                    value={addClientForm.data.currency_symbol}
                                    onChange={(e) => addClientForm.setData('currency_symbol', e.target.value)}
                                    className="w-full rounded-soft border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 px-3 py-2 text-sm"
                                />
                                <p className="mt-0.5 text-xs text-neutral-500">{t('admin.symbol_hint')}</p>
                            </div>
                            <div>
                                <label className="mb-1 block text-sm font-medium text-neutral-700 dark:text-neutral-300">{t('admin.currency_position')}</label>
                                <select
                                    value={addClientForm.data.currency_position}
                                    onChange={(e) => addClientForm.setData('currency_position', e.target.value)}
                                    className="w-full rounded-soft border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 px-3 py-2 text-sm"
                                >
                                    <option value="before">{t('admin.currency_position_before')}</option>
                                    <option value="after">{t('admin.currency_position_after')}</option>
                                </select>
                            </div>
                        </div>
                    </Modal.Body>
                    <Modal.Footer>
                        <Button type="button" variant="outline" onClick={() => setAddClientOpen(false)}>{t('common.cancel')}</Button>
                        <Button type="submit" disabled={addClientForm.processing}>{t('admin.create_client')}</Button>
                    </Modal.Footer>
                </form>
            </Modal>

            {/* Edit Client Modal */}
            <Modal show={editClientOpen} onClose={() => setEditClientOpen(false)} maxWidth="2xl">
                <Modal.Header title={t('admin.edit_client')} onClose={() => setEditClientOpen(false)} />
                {editClient && (
                    <form onSubmit={(e) => { e.preventDefault(); editClientForm.put(route('admin.clients.update', editClient.id), { preserveScroll: true, onSuccess: () => setEditClientOpen(false) }); }}>
                        <Modal.Body className="space-y-4">
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-neutral-700 dark:text-neutral-300">{t('admin.client_name')} <span className="text-red-500">*</span></label>
                                    <input
                                        type="text"
                                        value={editClientForm.data.name}
                                        onChange={(e) => editClientForm.setData('name', e.target.value)}
                                        className="w-full rounded-soft border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 px-3 py-2 text-sm"
                                    />
                                    {editClientForm.errors.name && <p className="mt-0.5 text-xs text-red-500">{editClientForm.errors.name}</p>}
                                </div>
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-neutral-700 dark:text-neutral-300">{t('admin.col_email')}</label>
                                    <input
                                        type="email"
                                        value={editClientForm.data.email}
                                        onChange={(e) => editClientForm.setData('email', e.target.value)}
                                        className="w-full rounded-soft border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 px-3 py-2 text-sm"
                                    />
                                </div>
                            </div>
                            <div>
                                <label className="mb-1 block text-sm font-medium text-neutral-700 dark:text-neutral-300">{t('admin.phone')}</label>
                                <input
                                    type="text"
                                    value={editClientForm.data.phone}
                                    onChange={(e) => editClientForm.setData('phone', e.target.value)}
                                    className="w-full rounded-soft border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 px-3 py-2 text-sm"
                                />
                            </div>
                            <div>
                                <label className="mb-1 block text-sm font-medium text-neutral-700 dark:text-neutral-300">{t('admin.address')}</label>
                                <textarea
                                    value={editClientForm.data.address}
                                    onChange={(e) => editClientForm.setData('address', e.target.value)}
                                    rows={2}
                                    className="w-full rounded-soft border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 px-3 py-2 text-sm"
                                />
                            </div>
                            <div>
                                <label className="mb-1 block text-sm font-medium text-neutral-700 dark:text-neutral-300">{t('admin.col_status')}</label>
                                <select
                                    value={editClientForm.data.status}
                                    onChange={(e) => editClientForm.setData('status', e.target.value)}
                                    className="w-full rounded-soft border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 px-3 py-2 text-sm"
                                >
                                    <option value="active">{t('common.active')}</option>
                                    <option value="inactive">{t('common.inactive')}</option>
                                </select>
                            </div>
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-neutral-700 dark:text-neutral-300">{t('admin.base_currency')}</label>
                                    <input
                                        type="text"
                                        value={editClientForm.data.base_currency}
                                        onChange={(e) => editClientForm.setData('base_currency', e.target.value)}
                                        className="w-full rounded-soft border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 px-3 py-2 text-sm"
                                    />
                                </div>
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-neutral-700 dark:text-neutral-300">{t('admin.currency_symbol')}</label>
                                    <input
                                        type="text"
                                        value={editClientForm.data.currency_symbol}
                                        onChange={(e) => editClientForm.setData('currency_symbol', e.target.value)}
                                        className="w-full rounded-soft border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 px-3 py-2 text-sm"
                                    />
                                </div>
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-neutral-700 dark:text-neutral-300">{t('admin.currency_position')}</label>
                                    <select
                                        value={editClientForm.data.currency_position}
                                        onChange={(e) => editClientForm.setData('currency_position', e.target.value)}
                                        className="w-full rounded-soft border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 px-3 py-2 text-sm"
                                    >
                                        <option value="before">{t('admin.currency_position_before')}</option>
                                        <option value="after">{t('admin.currency_position_after')}</option>
                                    </select>
                                </div>
                            </div>
                        </Modal.Body>
                        <Modal.Footer>
                            <Button type="button" variant="outline" onClick={() => setEditClientOpen(false)}>{t('common.cancel')}</Button>
                            <Button type="submit" disabled={editClientForm.processing}>{t('common.save')}</Button>
                        </Modal.Footer>
                    </form>
                )}
            </Modal>

            {/* Manage Client Users Modal */}
            <Modal show={manageUsersOpen} onClose={() => { setManageUsersOpen(false); setManageUsersClient(null); setClientUsers([]); }} maxWidth="3xl">
                <Modal.Header title={t('admin.manage_users_title_modal')} onClose={() => { setManageUsersOpen(false); setManageUsersClient(null); }} />
                {manageUsersClient && (
                    <>
                        <Modal.Body>
                            <div className="mb-4 flex items-center justify-between">
                                <p className="text-sm text-neutral-600 dark:text-neutral-400">{t('admin.client_label')}: <span className="font-semibold text-neutral-900 dark:text-neutral-100">{manageUsersClient.name}</span></p>
                                <Button size="sm" onClick={openAddUser}>+ {t('admin.add_user')}</Button>
                            </div>
                            {usersLoading ? (
                                <p className="py-4 text-center text-neutral-500">{t('admin.loading_users')}</p>
                            ) : (
                                <div className="overflow-x-auto">
                                    <table className="min-w-full text-sm">
                                        <thead>
                                            <tr className="border-b border-neutral-200 dark:border-neutral-700 text-left text-neutral-500 dark:text-neutral-400">
                                                <th className="pb-2 pr-4 font-medium uppercase">{t('admin.col_user')}</th>
                                                <th className="pb-2 pr-4 font-medium uppercase">{t('admin.col_roles')}</th>
                                                <th className="pb-2 pr-4 font-medium uppercase">{t('admin.col_status')}</th>
                                                <th className="pb-2 pr-4 font-medium text-right uppercase">{t('admin.col_actions')}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {clientUsers.map((u) => (
                                                <tr key={u.id} className="border-b border-neutral-100 dark:border-neutral-800">
                                                    <td className="py-3 pr-4">
                                                        <div className="font-medium text-neutral-900 dark:text-neutral-100">{u.name}</div>
                                                        <div className="text-neutral-500 dark:text-neutral-400">{u.email}</div>
                                                    </td>
                                                    <td className="py-3 pr-4">
                                                        <Badge variant="default" className="text-xs">{u.client_role === CLIENT_ROLE_ADMIN ? t('admin.administrator') : t('admin.staff')}</Badge>
                                                    </td>
                                                    <td className="py-3 pr-4">
                                                        <Badge variant={u.status === STATUS_ACTIVE ? 'success' : 'default'}>{u.status === STATUS_ACTIVE ? t('common.active') : t('common.inactive')}</Badge>
                                                    </td>
                                                    <td className="py-3 pr-4 text-right">
                                                        <button type="button" onClick={() => openEditUser(u)} className="rounded-soft p-1.5 text-brand-500 hover:bg-brand-50 dark:hover:bg-brand-900/20" title={t('admin.edit_user_title')}><Pencil className="h-4 w-4" /></button>
                                                        <button type="button" onClick={() => setDeleteUserConfirm(u)} className="rounded-soft p-1.5 text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20" title={t('admin.delete_user_title')}><Trash2 className="h-4 w-4" /></button>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                    {clientUsers.length === 0 && !usersLoading && <p className="py-4 text-center text-neutral-500">{t('admin.no_users_yet')}</p>}
                                </div>
                            )}
                        </Modal.Body>
                    </>
                )}
            </Modal>

            {/* Add User (nested in Manage Users) */}
            <Modal show={addUserOpen} onClose={() => setAddUserOpen(false)} maxWidth="lg">
                <Modal.Header title={t('admin.add_user')} onClose={() => setAddUserOpen(false)} />
                <form onSubmit={submitAddUser}>
                    <Modal.Body className="space-y-4">
                        <input type="hidden" name="client_id" value={manageUsersClient?.id} />
                        <div>
                            <label className="mb-1 block text-sm font-medium">{t('common.name')} <span className="text-red-500">*</span></label>
                            <input type="text" value={addUserForm.data.name} onChange={(e) => addUserForm.setData('name', e.target.value)} className="w-full rounded-soft border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 px-3 py-2 text-sm" required />
                            {addUserForm.errors.name && <p className="text-xs text-red-500">{addUserForm.errors.name}</p>}
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium">{t('admin.col_email')} <span className="text-red-500">*</span></label>
                            <input type="email" value={addUserForm.data.email} onChange={(e) => addUserForm.setData('email', e.target.value)} className="w-full rounded-soft border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 px-3 py-2 text-sm" required />
                            {addUserForm.errors.email && <p className="text-xs text-red-500">{addUserForm.errors.email}</p>}
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium">{t('auth.password')} <span className="text-red-500">*</span></label>
                            <input type="password" value={addUserForm.data.password} onChange={(e) => addUserForm.setData('password', e.target.value)} className="w-full rounded-soft border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 px-3 py-2 text-sm" required />
                            {addUserForm.errors.password && <p className="text-xs text-red-500">{addUserForm.errors.password}</p>}
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium">{t('admin.confirm_password_label')} <span className="text-red-500">*</span></label>
                            <input type="password" value={addUserForm.data.password_confirmation} onChange={(e) => addUserForm.setData('password_confirmation', e.target.value)} className="w-full rounded-soft border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 px-3 py-2 text-sm" required />
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium">{t('admin.role_label')}</label>
                            <select value={addUserForm.data.client_role} onChange={(e) => addUserForm.setData('client_role', e.target.value)} className="w-full rounded-soft border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 px-3 py-2 text-sm">
                                <option value="administrator">{t('admin.client_administrator')}</option>
                                <option value="staff">{t('admin.client_staff')}</option>
                            </select>
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium">{t('admin.col_status')}</label>
                            <select value={addUserForm.data.status} onChange={(e) => addUserForm.setData('status', e.target.value)} className="w-full rounded-soft border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 px-3 py-2 text-sm">
                                <option value="active">{t('common.active')}</option>
                                <option value="inactive">{t('common.inactive')}</option>
                            </select>
                        </div>
                    </Modal.Body>
                    <Modal.Footer>
                        <Button type="button" variant="outline" onClick={() => setAddUserOpen(false)}>{t('common.cancel')}</Button>
                        <Button type="submit" disabled={addUserForm.processing}>{t('admin.create_user')}</Button>
                    </Modal.Footer>
                </form>
            </Modal>

            {/* Edit User */}
            <Modal show={editUserOpen} onClose={() => { setEditUserOpen(false); setEditUser(null); }} maxWidth="lg">
                <Modal.Header title={t('admin.edit_user')} onClose={() => { setEditUserOpen(false); setEditUser(null); }} />
                {editUser && (
                    <form onSubmit={submitEditUser}>
                        <Modal.Body className="space-y-4">
                            <div>
                                <label className="mb-1 block text-sm font-medium">{t('common.name')} <span className="text-red-500">*</span></label>
                                <input type="text" value={editUserForm.data.name} onChange={(e) => editUserForm.setData('name', e.target.value)} className="w-full rounded-soft border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 px-3 py-2 text-sm" required />
                                {editUserForm.errors.name && <p className="text-xs text-red-500">{editUserForm.errors.name}</p>}
                            </div>
                            <div>
                                <label className="mb-1 block text-sm font-medium">{t('admin.col_email')} <span className="text-red-500">*</span></label>
                                <input type="email" value={editUserForm.data.email} onChange={(e) => editUserForm.setData('email', e.target.value)} className="w-full rounded-soft border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 px-3 py-2 text-sm" required />
                                {editUserForm.errors.email && <p className="text-xs text-red-500">{editUserForm.errors.email}</p>}
                            </div>
                            <div>
                                <label className="mb-1 block text-sm font-medium">{t('admin.new_password_leave_blank')}</label>
                                <input type="password" value={editUserForm.data.password} onChange={(e) => editUserForm.setData('password', e.target.value)} className="w-full rounded-soft border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 px-3 py-2 text-sm" />
                                {editUserForm.errors.password && <p className="text-xs text-red-500">{editUserForm.errors.password}</p>}
                            </div>
                            <div>
                                <label className="mb-1 block text-sm font-medium">{t('admin.confirm_password_label')}</label>
                                <input type="password" value={editUserForm.data.password_confirmation} onChange={(e) => editUserForm.setData('password_confirmation', e.target.value)} className="w-full rounded-soft border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 px-3 py-2 text-sm" />
                            </div>
                            <div>
                                <label className="mb-1 block text-sm font-medium">{t('admin.role_label')}</label>
                                <select value={editUserForm.data.client_role} onChange={(e) => editUserForm.setData('client_role', e.target.value)} className="w-full rounded-soft border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 px-3 py-2 text-sm">
                                    <option value="administrator">{t('admin.client_administrator')}</option>
                                    <option value="staff">{t('admin.client_staff')}</option>
                                </select>
                            </div>
                            <div>
                                <label className="mb-1 block text-sm font-medium">{t('admin.col_status')}</label>
                                <select value={editUserForm.data.status} onChange={(e) => editUserForm.setData('status', e.target.value)} className="w-full rounded-soft border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 px-3 py-2 text-sm">
                                    <option value="active">{t('common.active')}</option>
                                    <option value="inactive">{t('common.inactive')}</option>
                                </select>
                            </div>
                        </Modal.Body>
                        <Modal.Footer>
                            <Button type="button" variant="outline" onClick={() => { setEditUserOpen(false); setEditUser(null); }}>{t('common.cancel')}</Button>
                            <Button type="submit" disabled={editUserForm.processing}>{t('common.save')}</Button>
                        </Modal.Footer>
                    </form>
                )}
            </Modal>

            {/* Delete User Confirm */}
            <Modal show={!!deleteUserConfirm} onClose={() => setDeleteUserConfirm(null)}>
                <Modal.Header title={t('admin.delete_user_title')} onClose={() => setDeleteUserConfirm(null)} />
                {deleteUserConfirm && (
                    <>
                        <Modal.Body>
                            <p className="text-sm text-neutral-600 dark:text-neutral-400">{t('admin.delete_user_confirm', { name: deleteUserConfirm.name, email: deleteUserConfirm.email })}</p>
                        </Modal.Body>
                        <Modal.Footer>
                            <Button variant="outline" onClick={() => setDeleteUserConfirm(null)}>{t('common.cancel')}</Button>
                            <Button variant="danger" onClick={() => doDeleteUser(deleteUserConfirm)}>{t('common.delete')}</Button>
                        </Modal.Footer>
                    </>
                )}
            </Modal>


            {/* Delete Client Confirm */}
            <Modal show={!!deleteClientConfirm} onClose={() => setDeleteClientConfirm(null)}>
                <Modal.Header title={t('admin.delete_client')} onClose={() => setDeleteClientConfirm(null)} />
                {deleteClientConfirm && (
                    <>
                        <Modal.Body>
                            <p className="text-sm text-neutral-600 dark:text-neutral-400">{t('admin.delete_client_confirm', { name: deleteClientConfirm.name })}</p>
                        </Modal.Body>
                        <Modal.Footer>
                            <Button variant="outline" onClick={() => setDeleteClientConfirm(null)}>{t('common.cancel')}</Button>
                            <Button variant="danger" onClick={() => doDeleteClient(deleteClientConfirm)}>{t('common.delete')}</Button>
                        </Modal.Footer>
                    </>
                )}
            </Modal>
        </AdminLayout>
    );
}
