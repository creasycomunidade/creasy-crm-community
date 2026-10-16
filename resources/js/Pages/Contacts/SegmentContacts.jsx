import { Head, useForm, router } from '@inertiajs/react';
import ClientLayout from '@/Layouts/ClientLayout';
import { useState } from 'react';
import { UserPlus, Trash2, ArrowLeft, Search, Users } from 'lucide-react';
import { useTranslation } from 'react-i18next';

function ContactRow({ contact }) {
    const name = [contact.first_name, contact.last_name].filter(Boolean).join(' ') || '—';
    return (
        <div className="flex items-center gap-3">
            <div className="h-8 w-8 rounded-full bg-brand-100 dark:bg-brand-900/30 flex items-center justify-center text-xs font-semibold text-brand-700 dark:text-brand-300 shrink-0">
                {(contact.first_name?.[0] ?? contact.phone_e164?.[0] ?? '?').toUpperCase()}
            </div>
            <div className="min-w-0">
                <p className="text-sm font-medium text-neutral-900 dark:text-neutral-100 truncate">{name}</p>
                <p className="text-xs text-neutral-500 dark:text-neutral-400 truncate">{contact.phone_e164 ?? contact.email ?? ''}</p>
            </div>
        </div>
    );
}

export default function SegmentContacts({ segment, segmentContacts, availableContacts, filters }) {
    const { t } = useTranslation();
    const [selected, setSelected] = useState([]);
    const [search, setSearch] = useState(filters.search ?? '');

    const { post: attachPost, processing: attaching } = useForm({});
    const { delete: detachDelete, processing: detaching } = useForm({});

    const toggleSelect = (id) => {
        setSelected(prev => prev.includes(id) ? prev.filter(x => x !== id) : [...prev, id]);
    };

    const handleSearch = (e) => {
        e.preventDefault();
        router.get(route('client.segments.contacts', segment.id), { search }, { preserveState: true, replace: true });
    };

    const handleAttach = () => {
        if (!selected.length) return;
        router.post(route('client.segments.contacts.attach', segment.id), { contact_ids: selected }, {
            onSuccess: () => setSelected([]),
            preserveScroll: true,
        });
    };

    const handleDetach = (contactId) => {
        if (!confirm(t('contacts_page.seg_confirm_remove'))) return;
        router.delete(route('client.segments.contacts.detach', [segment.id, contactId]), { preserveScroll: true });
    };

    return (
        <ClientLayout title={t('contacts_page.seg_manage_title', { name: segment.name })}>
            <Head title={t('contacts_page.seg_head_title', { name: segment.name })} />
            <div className="space-y-6 max-w-4xl">
                <div className="flex items-center gap-3">
                    <a href={route('client.segments.index')} className="text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-200 transition">
                        <ArrowLeft className="h-5 w-5" />
                    </a>
                    <div>
                        <h2 className="text-xl font-semibold text-neutral-900 dark:text-neutral-100">{segment.name}</h2>
                        <p className="text-sm text-neutral-500 dark:text-neutral-400 mt-0.5">{t('contacts_page.seg_manage_subtitle')}</p>
                    </div>
                </div>

                <div className="grid gap-6 lg:grid-cols-2">
                    {/* Current segment contacts */}
                    <div className="rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900">
                        <div className="flex items-center justify-between gap-2 px-4 py-3 border-b border-neutral-100 dark:border-neutral-800">
                            <div className="flex items-center gap-2">
                                <Users className="h-4 w-4 text-neutral-400" />
                                <span className="text-sm font-semibold text-neutral-800 dark:text-neutral-200">{t('contacts_page.seg_in_segment')}</span>
                                <span className="rounded-full bg-neutral-100 dark:bg-neutral-700 px-2 py-0.5 text-xs text-neutral-600 dark:text-neutral-300">{segmentContacts.length}</span>
                            </div>
                        </div>
                        <div className="divide-y divide-neutral-100 dark:divide-neutral-800 max-h-[420px] overflow-y-auto">
                            {segmentContacts.length === 0 && (
                                <p className="text-sm text-neutral-500 dark:text-neutral-400 px-4 py-6 text-center">{t('contacts_page.seg_empty_current')}</p>
                            )}
                            {segmentContacts.map(contact => (
                                <div key={contact.id} className="flex items-center justify-between gap-2 px-4 py-3 hover:bg-neutral-50 dark:hover:bg-neutral-800/50 transition">
                                    <ContactRow contact={contact} />
                                    <button type="button" onClick={() => handleDetach(contact.id)} className="text-neutral-400 hover:text-red-500 transition shrink-0" title={t('contacts_page.seg_remove_title')}>
                                        <Trash2 className="h-4 w-4" />
                                    </button>
                                </div>
                            ))}
                        </div>
                    </div>

                    {/* Available contacts */}
                    <div className="rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900">
                        <div className="px-4 py-3 border-b border-neutral-100 dark:border-neutral-800 space-y-2">
                            <div className="flex items-center justify-between gap-2">
                                <span className="text-sm font-semibold text-neutral-800 dark:text-neutral-200">{t('contacts_page.seg_add_contacts')}</span>
                                {selected.length > 0 && (
                                    <button type="button" onClick={handleAttach} disabled={attaching} className="flex items-center gap-1.5 rounded-lg bg-brand-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-brand-700 disabled:opacity-60 transition">
                                        <UserPlus className="h-3.5 w-3.5" />
                                        {t('contacts_page.seg_add_n', { count: selected.length })}
                                    </button>
                                )}
                            </div>
                            <form onSubmit={handleSearch} className="relative">
                                <Search className="absolute left-2.5 top-1/2 -translate-y-1/2 h-4 w-4 text-neutral-400 pointer-events-none" />
                                <input
                                    type="text"
                                    value={search}
                                    onChange={e => setSearch(e.target.value)}
                                    placeholder={t('contacts_page.seg_search_placeholder')}
                                    className="w-full rounded border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 pl-8 pr-3 py-1.5 text-sm"
                                />
                            </form>
                        </div>
                        <div className="divide-y divide-neutral-100 dark:divide-neutral-800 max-h-[380px] overflow-y-auto">
                            {availableContacts.length === 0 && (
                                <p className="text-sm text-neutral-500 dark:text-neutral-400 px-4 py-6 text-center">{t('contacts_page.seg_empty_available')}</p>
                            )}
                            {availableContacts.map(contact => {
                                const isSelected = selected.includes(contact.id);
                                return (
                                    <label key={contact.id} className={`flex items-center gap-3 px-4 py-3 cursor-pointer hover:bg-neutral-50 dark:hover:bg-neutral-800/50 transition ${isSelected ? 'bg-brand-50 dark:bg-brand-900/20' : ''}`}>
                                        <input
                                            type="checkbox"
                                            checked={isSelected}
                                            onChange={() => toggleSelect(contact.id)}
                                            className="rounded border-neutral-300 dark:border-neutral-600 text-brand-600 focus:ring-brand-500"
                                        />
                                        <ContactRow contact={contact} />
                                    </label>
                                );
                            })}
                        </div>
                        {availableContacts.length > 0 && (
                            <div className="px-4 py-2 border-t border-neutral-100 dark:border-neutral-800 text-xs text-neutral-400 dark:text-neutral-500">
                                {t('contacts_page.seg_showing_limit')}
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </ClientLayout>
    );
}
