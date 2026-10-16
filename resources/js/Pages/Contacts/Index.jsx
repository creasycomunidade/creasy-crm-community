import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import ClientLayout from '@/Layouts/ClientLayout';
import EmptyState from '@/Components/EmptyState';
import { useState, useRef, useCallback } from 'react';
import { UserPlus, Upload, Search, Tag, Trash2, Eye, Users, Table2, Download, CheckSquare, Square, X, Globe, Instagram, Send, Facebook, Linkedin, Music2, AtSign, Youtube, Plus, ChevronDown, ContactRound } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { sanitizeHtml } from '@/Utils/sanitizeHtml';

function ContactAvatar({ contact, size = 8 }) {
    const { t } = useTranslation();
    const [imageFailed, setImageFailed] = useState(false);
    const name = `${contact.first_name ?? ''} ${contact.last_name ?? ''}`.trim();
    const initials = name
        ? name.split(' ').map(p => p[0]).slice(0, 2).join('').toUpperCase()
        : '?';

    if (contact.avatar_url && !imageFailed) {
        return (
            <img
                src={contact.avatar_url}
                alt={name || t('contacts_page.contact_alt')}
                className={`h-${size} w-${size} rounded-full object-cover flex-shrink-0`}
                onError={() => setImageFailed(true)}
            />
        );
    }
    return (
        <div className={`h-${size} w-${size} rounded-full bg-brand-100 dark:bg-brand-900/40 text-brand-700 dark:text-brand-300 flex items-center justify-center text-xs font-semibold flex-shrink-0`}>
            {initials}
        </div>
    );
}

function ContactRow({ contact, selected, onToggle, onDelete }) {
    const { t } = useTranslation();
    return (
        <tr className={`hover:bg-neutral-50 dark:hover:bg-neutral-800/50 ${selected ? 'bg-brand-50 dark:bg-brand-900/10' : ''}`}>
            <td className="px-4 py-3">
                <button type="button" onClick={() => onToggle(contact.uuid)} className="text-neutral-400 hover:text-brand-600 transition">
                    {selected
                        ? <CheckSquare className="h-4 w-4 text-brand-600" />
                        : <Square className="h-4 w-4" />
                    }
                </button>
            </td>
            <td className="px-4 py-3 text-sm text-neutral-900 dark:text-neutral-100">
                <div className="flex items-center gap-2.5">
                    <ContactAvatar contact={contact} size={8} />
                    <span>
                        {contact.first_name || contact.last_name
                            ? `${contact.first_name ?? ''} ${contact.last_name ?? ''}`.trim()
                            : <span className="text-neutral-400">—</span>
                        }
                    </span>
                </div>
            </td>
            <td className="px-4 py-3 text-sm text-neutral-600 dark:text-neutral-400">{contact.phone_e164 || '—'}</td>
            <td className="px-4 py-3 text-sm text-neutral-600 dark:text-neutral-400">{contact.email || '—'}</td>
            <td className="px-4 py-3">
                <div className="flex flex-wrap gap-1">
                    {contact.tags?.map(tag => (
                        <span key={tag.id} className="rounded-full px-2 py-0.5 text-xs font-medium" style={{ backgroundColor: tag.color + '33', color: tag.color }}>
                            {tag.name}
                        </span>
                    ))}
                </div>
            </td>
            <td className="px-4 py-3">
                <div className="flex items-center gap-1">
                    {contact.opt_in_whatsapp && <span className="text-xs bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300 rounded px-1">{t('contacts_page.channel_wa')}</span>}
                    {contact.opt_in_sms      && <span className="text-xs bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300 rounded px-1">{t('contacts_page.channel_sms')}</span>}
                    {contact.opt_in_email    && <span className="text-xs bg-purple-100 text-purple-700 dark:bg-purple-900/40 dark:text-purple-300 rounded px-1">{t('contacts_page.channel_email')}</span>}
                </div>
            </td>
            <td className="px-4 py-3">
                <div className="flex items-center gap-2">
                    <Link href={route('client.contacts.show', contact.uuid)} className="text-neutral-400 hover:text-brand-600 transition">
                        <Eye className="h-4 w-4" />
                    </Link>
                    <button type="button" onClick={() => onDelete(contact.uuid)} className="text-neutral-400 hover:text-red-500 transition">
                        <Trash2 className="h-4 w-4" />
                    </button>
                </div>
            </td>
        </tr>
    );
}

const SOCIAL_FIELDS = [
    { key: 'instagram', label: 'Instagram', placeholder: '@usuario', Icon: Instagram, isUrl: false },
    { key: 'telegram', label: 'Telegram', placeholder: '@usuario', Icon: Send, isUrl: false },
    { key: 'facebook', label: 'Facebook / Messenger', placeholder: 'https://facebook.com/usuario', Icon: Facebook, isUrl: true },
    { key: 'linkedin', label: 'LinkedIn', placeholder: 'https://linkedin.com/in/usuario', Icon: Linkedin, isUrl: true },
    { key: 'tiktok', label: 'TikTok', placeholder: '@usuario', Icon: Music2, isUrl: false },
    { key: 'x', label: 'X', placeholder: '@usuario', Icon: AtSign, isUrl: false },
    { key: 'youtube', label: 'YouTube', placeholder: 'https://youtube.com/@canal', Icon: Youtube, isUrl: true },
];

export default function ContactsIndex({ contacts, filters, segments = [] }) {
    const { t } = useTranslation();
    const { props } = usePage();
    const flash = props.flash ?? {};
    const [search, setSearch] = useState(filters.search ?? '');
    const [showAddModal, setShowAddModal] = useState(false);
    const [showContactDetails, setShowContactDetails] = useState(false);
    const [selected, setSelected] = useState(new Set());
    const fileInput = useRef();

    const { data, setData, post, processing, reset, errors, clearErrors } = useForm({
        first_name: '', last_name: '', phone_e164: '', email: '',
        country: '', language: '', website_url: '',
        social_profiles: Object.fromEntries(SOCIAL_FIELDS.map(({ key }) => [key, ''])),
        contact_links: [],
        opt_in_whatsapp: true, opt_in_sms: true, opt_in_email: true,
        segment_ids: [],
    });

    const inputClass = 'mt-1 min-h-11 w-full rounded-xl border border-neutral-300 bg-white px-3 py-2 text-base text-neutral-900 outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-neutral-600 dark:bg-neutral-800 dark:text-neutral-100 sm:text-sm';

    const allUuids = contacts.data.map(c => c.uuid);
    const allSelected = allUuids.length > 0 && allUuids.every(id => selected.has(id));
    const someSelected = selected.size > 0;

    const toggleAll = useCallback(() => {
        if (allSelected) {
            setSelected(prev => { const next = new Set(prev); allUuids.forEach(id => next.delete(id)); return next; });
        } else {
            setSelected(prev => new Set([...prev, ...allUuids]));
        }
    }, [allSelected, allUuids]);

    const toggleOne = useCallback((uuid) => {
        setSelected(prev => { const next = new Set(prev); next.has(uuid) ? next.delete(uuid) : next.add(uuid); return next; });
    }, []);

    const clearSelection = () => setSelected(new Set());

    const handleSearch = (e) => {
        e.preventDefault();
        router.get(route('client.contacts.index'), { search }, { preserveState: true, replace: true });
    };

    const handleDelete = (uuid) => {
        if (confirm(t('contacts_page.confirm_delete_one'))) {
            router.delete(route('client.contacts.destroy', uuid), { preserveScroll: true });
        }
    };

    const handleBulkDelete = () => {
        if (!confirm(t('contacts_page.confirm_delete_selected', { count: selected.size }))) return;
        router.delete(route('client.contacts.bulk-destroy'), {
            data: { uuids: [...selected] },
            preserveScroll: true,
            onSuccess: () => clearSelection(),
        });
    };

    const handleExport = (selectedOnly = false) => {
        const params = new URLSearchParams();
        if (selectedOnly && someSelected) {
            params.set('uuids', [...selected].join(','));
        } else if (filters.search) {
            params.set('search', filters.search);
        }
        window.location.href = route('client.contacts.export') + (params.toString() ? '?' + params.toString() : '');
    };

    const handleImport = (e) => {
        const file = e.target.files[0];
        if (!file) return;
        const formData = new FormData();
        formData.append('file', file);
        router.post(route('client.contacts.import'), formData, { preserveScroll: true });
    };

    const handlePhoneChange = (value) => {
        setData(prev => ({
            ...prev,
            phone_e164: value,
            opt_in_whatsapp: value.trim() ? prev.opt_in_whatsapp : false,
            opt_in_sms: value.trim() ? prev.opt_in_sms : false,
        }));
    };

    const handleEmailChange = (value) => {
        setData(prev => ({
            ...prev,
            email: value,
            opt_in_email: value.trim() ? prev.opt_in_email : false,
        }));
    };

    const submitAdd = (e) => {
        e.preventDefault();
        if (!data.phone_e164.trim() && !data.email.trim()) {
            alert(t('contacts_page.alert_phone_or_email'));
            return;
        }
        post(route('client.contacts.store'), {
            onSuccess: () => {
                reset();
                setShowContactDetails(false);
                setShowAddModal(false);
            },
            onError: currentErrors => {
                if (Object.keys(currentErrors).some(key => key === 'website_url' || key.startsWith('social_profiles.') || key.startsWith('contact_links.'))) {
                    setShowContactDetails(true);
                }
            },
        });
    };

    const closeAddModal = () => {
        clearErrors();
        reset();
        setShowContactDetails(false);
        setShowAddModal(false);
    };

    const updateAdditionalLink = (index, field, value) => {
        setData('contact_links', data.contact_links.map((link, linkIndex) => (
            linkIndex === index ? { ...link, [field]: value } : link
        )));
    };

    return (
        <ClientLayout title={t('contacts_page.title')}>
            <Head title={t('contacts_page.title')} />
            <div className="space-y-5">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <h2 className="text-xl font-semibold text-neutral-900 dark:text-neutral-100">{t('contacts_page.title')}</h2>
                    <div className="flex flex-wrap gap-2">
                        <Link
                            href={route('client.contacts.whatsapp-import')}
                            className="flex min-h-11 items-center gap-1.5 rounded-lg border border-green-300 bg-green-50 px-3 py-2 text-sm font-medium text-green-800 transition hover:bg-green-100 focus:outline-none focus:ring-2 focus:ring-green-500 dark:border-green-800 dark:bg-green-950/30 dark:text-green-300 dark:hover:bg-green-950/50"
                        >
                            <ContactRound className="h-4 w-4" aria-hidden="true" /> Extrair do WhatsApp
                        </Link>
                        {(
                            <Link
                                href={route('client.contacts.bulk-import')}
                                className="flex items-center gap-1.5 rounded-lg border border-neutral-300 dark:border-neutral-600 px-3 py-2 text-sm text-neutral-700 dark:text-neutral-300 hover:bg-neutral-50 dark:hover:bg-neutral-800 transition"
                            >
                                <Table2 className="h-4 w-4" /> {t('contacts_page.bulk_import')}
                            </Link>
                        )}
                        {(
                            <button type="button" onClick={() => fileInput.current?.click()} className="flex items-center gap-1.5 rounded-lg border border-neutral-300 dark:border-neutral-600 px-3 py-2 text-sm text-neutral-700 dark:text-neutral-300 hover:bg-neutral-50 dark:hover:bg-neutral-800 transition">
                                <Upload className="h-4 w-4" /> {t('contacts_page.import_csv')}
                            </button>
                        )}
                        <input ref={fileInput} type="file" accept=".csv,.txt" className="hidden" onChange={handleImport} />
                        <button type="button" onClick={() => handleExport(false)} className="flex items-center gap-1.5 rounded-lg border border-neutral-300 dark:border-neutral-600 px-3 py-2 text-sm text-neutral-700 dark:text-neutral-300 hover:bg-neutral-50 dark:hover:bg-neutral-800 transition">
                            <Download className="h-4 w-4" /> {t('contacts_page.export_csv')}
                        </button>
                        <Link href={route('client.segments.index')} className="flex items-center gap-1.5 rounded-lg border border-neutral-300 dark:border-neutral-600 px-3 py-2 text-sm text-neutral-700 dark:text-neutral-300 hover:bg-neutral-50 dark:hover:bg-neutral-800 transition">
                            <Tag className="h-4 w-4" /> {t('contacts_page.segments')}
                        </Link>
                        {(
                            <button type="button" onClick={() => setShowAddModal(true)} className="flex items-center gap-1.5 rounded-lg bg-brand-600 px-3 py-2 text-sm font-medium text-white hover:bg-brand-700 transition">
                                <UserPlus className="h-4 w-4" /> {t('contacts_page.add_contact')}
                            </button>
                        )}
                    </div>
                </div>

                {flash.success && <div className="rounded-lg bg-green-50 dark:bg-green-900/30 text-green-800 dark:text-green-200 px-4 py-2 text-sm">{flash.success}</div>}

                {/* Search */}
                <form onSubmit={handleSearch} className="flex gap-2">
                    <div className="relative flex-1">
                        <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-neutral-400" />
                        <input
                            type="text"
                            value={search}
                            onChange={e => setSearch(e.target.value)}
                            placeholder={t('contacts_page.search_placeholder')}
                            className="w-full rounded-lg border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 pl-9 pr-3 py-2 text-sm text-neutral-900 dark:text-neutral-100 focus:outline-none focus:ring-2 focus:ring-brand-500"
                        />
                    </div>
                    <button type="submit" className="rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700 transition">{t('common.search')}</button>
                </form>

                {/* Bulk action bar */}
                {someSelected && (
                    <div className="flex items-center gap-3 rounded-lg bg-brand-50 dark:bg-brand-900/20 border border-brand-200 dark:border-brand-700 px-4 py-2.5">
                        <span className="text-sm font-medium text-brand-700 dark:text-brand-300">{t('contacts_page.n_selected', { count: selected.size })}</span>
                        <div className="flex gap-2 ml-auto">
                            <button type="button" onClick={() => handleExport(true)} className="flex items-center gap-1.5 rounded-lg border border-brand-300 dark:border-brand-600 px-3 py-1.5 text-xs font-medium text-brand-700 dark:text-brand-300 hover:bg-brand-100 dark:hover:bg-brand-900/40 transition">
                                <Download className="h-3.5 w-3.5" /> {t('contacts_page.export_selected')}
                            </button>
                            <button type="button" onClick={handleBulkDelete} className="flex items-center gap-1.5 rounded-lg border border-red-300 dark:border-red-700 px-3 py-1.5 text-xs font-medium text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 transition">
                                <Trash2 className="h-3.5 w-3.5" /> {t('contacts_page.delete_selected')}
                            </button>
                            <button type="button" onClick={clearSelection} className="text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-200 transition">
                                <X className="h-4 w-4" />
                            </button>
                        </div>
                    </div>
                )}

                {/* Table */}
                <div className="overflow-x-auto rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900">
                    <table className="min-w-full divide-y divide-neutral-200 dark:divide-neutral-700 text-sm">
                        <thead className="bg-neutral-50 dark:bg-neutral-800">
                            <tr>
                                <th className="px-4 py-3 w-10">
                                    <button type="button" onClick={toggleAll} className="text-neutral-400 hover:text-brand-600 transition">
                                        {allSelected
                                            ? <CheckSquare className="h-4 w-4 text-brand-600" />
                                            : <Square className="h-4 w-4" />
                                        }
                                    </button>
                                </th>
                                {[t('common.name'), t('contacts_page.col_phone'), t('common.email'), t('contacts_page.col_tags'), t('contacts_page.col_optins'), ''].map(h => (
                                    <th key={h} className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-neutral-500 dark:text-neutral-400">{h}</th>
                                ))}
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-neutral-100 dark:divide-neutral-800">
                            {contacts.data.map(c => (
                                <ContactRow
                                    key={c.id}
                                    contact={c}
                                    selected={selected.has(c.uuid)}
                                    onToggle={toggleOne}
                                    onDelete={handleDelete}
                                />
                            ))}
                            {contacts.data.length === 0 && (
                                <tr>
                                    <td colSpan={7}>
                                        <EmptyState
                                            icon={<Users className="h-8 w-8" />}
                                            title={t('contacts_page.empty_title')}
                                            description={t('contacts_page.empty_description')}
                                            action={{ label: t('contacts_page.add_contact'), onClick: () => setShowAddModal(true) }}
                                            secondaryAction={{ label: t('contacts_page.bulk_import'), href: route('client.contacts.bulk-import') }}
                                        />
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                {/* Pagination */}
                {contacts.last_page > 1 && (
                    <div className="flex gap-1">
                        {contacts.links.map((link, i) => (
                            <a key={i} href={link.url ?? '#'} className={`px-3 py-1.5 rounded text-sm border ${link.active ? 'bg-brand-600 text-white border-brand-600' : 'border-neutral-300 dark:border-neutral-600 text-neutral-700 dark:text-neutral-300 hover:bg-neutral-50 dark:hover:bg-neutral-800'} ${!link.url ? 'opacity-40 pointer-events-none' : ''}`} dangerouslySetInnerHTML={{ __html: sanitizeHtml(link.label) }} />
                        ))}
                    </div>
                )}
            </div>

            {/* Add Contact Modal */}
            {showAddModal && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
                    <div role="dialog" aria-modal="true" aria-labelledby="add-contact-title" className="flex max-h-[92dvh] w-full max-w-2xl flex-col overflow-hidden rounded-2xl bg-white shadow-xl dark:bg-neutral-900">
                        <div className="flex items-start justify-between gap-4 border-b border-neutral-200 px-6 py-5 dark:border-neutral-700">
                            <div>
                                <h3 id="add-contact-title" className="text-lg font-semibold text-neutral-900 dark:text-neutral-100">{t('contacts_page.add_contact')}</h3>
                                <p className="mt-1 text-sm text-neutral-500 dark:text-neutral-400">Cadastre os canais e endereços usados para falar com este cliente.</p>
                            </div>
                            <button type="button" onClick={closeAddModal} aria-label="Fechar" className="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl text-neutral-500 transition hover:bg-neutral-100 focus:outline-none focus:ring-2 focus:ring-brand-500 dark:hover:bg-neutral-800">
                                <X className="h-5 w-5" aria-hidden="true" />
                            </button>
                        </div>
                        <form onSubmit={submitAdd} className="flex min-h-0 flex-1 flex-col">
                            <div className="min-h-0 flex-1 space-y-4 overflow-y-auto px-6 py-5">
                            <div className="grid grid-cols-2 gap-3">
                                <div>
                                    <label className="text-xs font-medium text-neutral-500 dark:text-neutral-400">{t('contacts_page.first_name')}</label>
                                    <input type="text" value={data.first_name} onChange={e => setData('first_name', e.target.value)} className={inputClass} />
                                    {errors.first_name && <span role="alert" className="mt-1 block text-xs text-red-600 dark:text-red-400">{errors.first_name}</span>}
                                </div>
                                <div>
                                    <label className="text-xs font-medium text-neutral-500 dark:text-neutral-400">{t('contacts_page.last_name')}</label>
                                    <input type="text" value={data.last_name} onChange={e => setData('last_name', e.target.value)} className={inputClass} />
                                    {errors.last_name && <span role="alert" className="mt-1 block text-xs text-red-600 dark:text-red-400">{errors.last_name}</span>}
                                </div>
                            </div>
                            <div>
                                <label className="text-xs font-medium text-neutral-500 dark:text-neutral-400">{t('contacts_page.phone_e164')}</label>
                                <input type="tel" inputMode="tel" value={data.phone_e164} onChange={e => handlePhoneChange(e.target.value)} placeholder="+5511999999999" className={inputClass} />
                                {errors.phone_e164 && <span role="alert" className="mt-1 block text-xs text-red-600 dark:text-red-400">{errors.phone_e164}</span>}
                            </div>
                            <div>
                                <label className="text-xs font-medium text-neutral-500 dark:text-neutral-400">{t('common.email')}</label>
                                <input type="email" inputMode="email" value={data.email} onChange={e => handleEmailChange(e.target.value)} className={inputClass} />
                                {errors.email && <span role="alert" className="mt-1 block text-xs text-red-600 dark:text-red-400">{errors.email}</span>}
                            </div>
                            <div className="grid gap-3 sm:grid-cols-2">
                                <div>
                                    <label className="text-xs font-medium text-neutral-500 dark:text-neutral-400">País (2 letras)</label>
                                    <input type="text" maxLength={2} value={data.country} onChange={e => setData('country', e.target.value.toUpperCase())} placeholder="BR" className={inputClass} />
                                    {errors.country && <span role="alert" className="mt-1 block text-xs text-red-600 dark:text-red-400">{errors.country}</span>}
                                </div>
                                <div>
                                    <label className="text-xs font-medium text-neutral-500 dark:text-neutral-400">Idioma</label>
                                    <input type="text" value={data.language} onChange={e => setData('language', e.target.value)} placeholder="pt-BR" className={inputClass} />
                                    {errors.language && <span role="alert" className="mt-1 block text-xs text-red-600 dark:text-red-400">{errors.language}</span>}
                                </div>
                            </div>
                            <div className="flex gap-4">
                                {[['opt_in_whatsapp', 'WhatsApp', !data.phone_e164.trim()], ['opt_in_sms', t('contacts_page.channel_sms'), !data.phone_e164.trim()], ['opt_in_email', t('common.email'), !data.email.trim()]].map(([key, label, disabled]) => (
                                    <label key={key} className={`flex items-center gap-1.5 text-sm ${disabled ? 'opacity-40 cursor-not-allowed' : 'cursor-pointer'}`}>
                                        <input type="checkbox" checked={data[key]} onChange={e => setData(key, e.target.checked)} disabled={disabled} className="rounded" />
                                        {label}
                                    </label>
                                ))}
                            </div>

                            <section className="rounded-2xl border border-neutral-200 dark:border-neutral-700">
                                <button
                                    type="button"
                                    aria-expanded={showContactDetails}
                                    aria-controls="add-contact-social-details"
                                    onClick={() => setShowContactDetails(value => !value)}
                                    className="flex min-h-14 w-full items-center justify-between gap-4 rounded-2xl px-4 text-left transition hover:bg-neutral-50 focus:outline-none focus:ring-2 focus:ring-brand-500 dark:hover:bg-neutral-800/60"
                                >
                                    <span>
                                        <span className="block text-sm font-semibold text-neutral-800 dark:text-neutral-100">Redes sociais, site e outros links</span>
                                        <span className="mt-0.5 block text-xs text-neutral-500 dark:text-neutral-400">Instagram, Telegram, Facebook, LinkedIn, TikTok, X, YouTube e links personalizados.</span>
                                    </span>
                                    <ChevronDown className={`h-5 w-5 shrink-0 text-neutral-500 transition-transform ${showContactDetails ? 'rotate-180' : ''}`} aria-hidden="true" />
                                </button>

                                {showContactDetails && (
                                    <div id="add-contact-social-details" className="space-y-5 border-t border-neutral-200 px-4 py-5 dark:border-neutral-700">
                                        <label className="block">
                                            <span className="text-xs font-medium text-neutral-600 dark:text-neutral-300">Site principal</span>
                                            <div className="relative">
                                                <Globe className="pointer-events-none absolute left-3 top-4 h-4 w-4 text-neutral-400" aria-hidden="true" />
                                                <input type="url" inputMode="url" value={data.website_url} onChange={e => setData('website_url', e.target.value)} placeholder="https://empresa.com.br" className={`${inputClass} pl-10`} />
                                            </div>
                                            {errors.website_url && <span role="alert" className="mt-1 block text-xs text-red-600 dark:text-red-400">{errors.website_url}</span>}
                                        </label>

                                        <div className="grid gap-3 sm:grid-cols-2">
                                            {SOCIAL_FIELDS.map(({ key, label, placeholder, Icon, isUrl }) => (
                                                <label key={key} className="block">
                                                    <span className="text-xs font-medium text-neutral-600 dark:text-neutral-300">{label}</span>
                                                    <div className="relative">
                                                        <Icon className="pointer-events-none absolute left-3 top-4 h-4 w-4 text-neutral-400" aria-hidden="true" />
                                                        <input
                                                            type={isUrl ? 'url' : 'text'}
                                                            inputMode={isUrl ? 'url' : 'text'}
                                                            value={data.social_profiles[key]}
                                                            onChange={e => setData('social_profiles', { ...data.social_profiles, [key]: e.target.value })}
                                                            placeholder={placeholder}
                                                            className={`${inputClass} pl-10`}
                                                        />
                                                    </div>
                                                    {errors[`social_profiles.${key}`] && <span role="alert" className="mt-1 block text-xs text-red-600 dark:text-red-400">{errors[`social_profiles.${key}`]}</span>}
                                                </label>
                                            ))}
                                        </div>

                                        <div className="space-y-3 border-t border-neutral-200 pt-5 dark:border-neutral-700">
                                            <div className="flex flex-wrap items-start justify-between gap-3">
                                                <div>
                                                    <h4 className="text-sm font-semibold text-neutral-700 dark:text-neutral-200">Outros links de contato</h4>
                                                    <p className="mt-1 text-xs text-neutral-500 dark:text-neutral-400">Portfólio, catálogo, agenda ou outro endereço útil.</p>
                                                </div>
                                                <button type="button" disabled={data.contact_links.length >= 10} onClick={() => setData('contact_links', [...data.contact_links, { label: '', url: '' }])} className="inline-flex min-h-11 items-center gap-2 rounded-xl border border-neutral-300 px-3 text-sm font-medium text-neutral-700 transition hover:bg-neutral-50 focus:outline-none focus:ring-2 focus:ring-brand-500 disabled:cursor-not-allowed disabled:opacity-50 dark:border-neutral-600 dark:text-neutral-200 dark:hover:bg-neutral-800">
                                                    <Plus className="h-4 w-4" aria-hidden="true" /> Adicionar link
                                                </button>
                                            </div>
                                            {data.contact_links.length === 0 ? (
                                                <p className="rounded-xl border border-dashed border-neutral-300 px-4 py-5 text-center text-sm text-neutral-500 dark:border-neutral-700 dark:text-neutral-400">Nenhum link adicional cadastrado.</p>
                                            ) : (
                                                <div className="space-y-3">
                                                    {data.contact_links.map((link, index) => (
                                                        <div key={index} className="grid gap-2 rounded-xl border border-neutral-200 p-3 dark:border-neutral-700 sm:grid-cols-[0.8fr_1.6fr_44px]">
                                                            <label>
                                                                <span className="text-xs font-medium text-neutral-600 dark:text-neutral-300">Nome do link</span>
                                                                <input value={link.label} onChange={e => updateAdditionalLink(index, 'label', e.target.value)} placeholder="Catálogo" className={inputClass} />
                                                            </label>
                                                            <label>
                                                                <span className="text-xs font-medium text-neutral-600 dark:text-neutral-300">Endereço</span>
                                                                <input type="url" inputMode="url" value={link.url} onChange={e => updateAdditionalLink(index, 'url', e.target.value)} placeholder="https://..." className={inputClass} />
                                                            </label>
                                                            <button type="button" onClick={() => setData('contact_links', data.contact_links.filter((_, linkIndex) => linkIndex !== index))} className="mt-auto flex h-11 w-11 items-center justify-center rounded-xl text-red-600 transition hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-500 dark:text-red-400 dark:hover:bg-red-950/30" aria-label={`Remover link ${index + 1}`}>
                                                                <X className="h-4 w-4" aria-hidden="true" />
                                                            </button>
                                                            {(errors[`contact_links.${index}.label`] || errors[`contact_links.${index}.url`]) && <span role="alert" className="text-xs text-red-600 dark:text-red-400 sm:col-span-3">{errors[`contact_links.${index}.label`] || errors[`contact_links.${index}.url`]}</span>}
                                                        </div>
                                                    ))}
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                )}
                            </section>

                            {segments.length > 0 && (
                                <div>
                                    <label className="text-xs font-medium text-neutral-500 dark:text-neutral-400">{t('contacts_page.add_to_segments')}</label>
                                    <div className="mt-1.5 flex flex-wrap gap-2">
                                        {segments.map(seg => {
                                            const checked = data.segment_ids.includes(seg.id);
                                            return (
                                                <label key={seg.id} className={`flex items-center gap-1.5 rounded-full border px-3 py-1 text-xs cursor-pointer transition ${checked ? 'border-brand-500 bg-brand-50 dark:bg-brand-900/30 text-brand-700 dark:text-brand-300' : 'border-neutral-300 dark:border-neutral-600 text-neutral-600 dark:text-neutral-400 hover:border-brand-400'}`}>
                                                    <input type="checkbox" className="sr-only" checked={checked} onChange={() => {
                                                        const ids = checked ? data.segment_ids.filter(id => id !== seg.id) : [...data.segment_ids, seg.id];
                                                        setData('segment_ids', ids);
                                                    }} />
                                                    {seg.name}
                                                </label>
                                            );
                                        })}
                                    </div>
                                </div>
                            )}
                            </div>
                            <div className="flex gap-2 border-t border-neutral-200 px-6 py-4 dark:border-neutral-700">
                                <button type="submit" disabled={processing} className="flex-1 rounded-lg bg-brand-600 py-2 text-sm font-medium text-white hover:bg-brand-700 disabled:opacity-60 transition">
                                    {processing ? t('common.saving') : t('common.save')}
                                </button>
                                <button type="button" onClick={closeAddModal} className="rounded-lg border border-neutral-300 dark:border-neutral-600 px-4 py-2 text-sm text-neutral-700 dark:text-neutral-300 hover:bg-neutral-50 dark:hover:bg-neutral-800 transition">
                                    {t('common.cancel')}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </ClientLayout>
    );
}
