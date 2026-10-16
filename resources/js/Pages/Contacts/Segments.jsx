import { Head, useForm, router } from '@inertiajs/react';
import ClientLayout from '@/Layouts/ClientLayout';
import EmptyState from '@/Components/EmptyState';
import { useState } from 'react';
import { Plus, Trash2, Filter, UserPlus } from 'lucide-react';
import { useTranslation } from 'react-i18next';

const FIELDS = ['first_name', 'last_name', 'phone_e164', 'email', 'country', 'language', 'source', 'opt_in_whatsapp', 'opt_in_sms', 'opt_in_email'];
const OPERATORS = ['=', '!=', 'like', 'not_like', 'is_null', 'is_not_null'];

function RuleRow({ condition, onChange, onRemove }) {
    const { t } = useTranslation();
    return (
        <div className="flex flex-wrap gap-2 items-center bg-neutral-50 dark:bg-neutral-800 rounded-lg px-3 py-2">
            <select value={condition.field} onChange={e => onChange({ ...condition, field: e.target.value })} className="rounded border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-700 px-2 py-1 text-sm">
                {FIELDS.map(f => <option key={f} value={f}>{f}</option>)}
            </select>
            <select value={condition.operator} onChange={e => onChange({ ...condition, operator: e.target.value })} className="rounded border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-700 px-2 py-1 text-sm">
                {OPERATORS.map(o => <option key={o} value={o}>{o}</option>)}
            </select>
            {!['is_null', 'is_not_null'].includes(condition.operator) && (
                <input type="text" value={condition.value ?? ''} onChange={e => onChange({ ...condition, value: e.target.value })} placeholder={t('contacts_page.seg_rule_value')} className="rounded border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-700 px-2 py-1 text-sm w-40" />
            )}
            <button type="button" onClick={onRemove} className="text-neutral-400 hover:text-red-500 ml-auto"><Trash2 className="h-4 w-4" /></button>
        </div>
    );
}

export default function ContactsSegments({ segments }) {
    const { t } = useTranslation();
    const [showCreate, setShowCreate] = useState(false);
    const { data, setData, post, processing, reset } = useForm({
        name: '',
        type: 'static',
        rules_json: { combinator: 'AND', conditions: [] },
    });

    const addCondition = () => setData('rules_json', {
        ...data.rules_json,
        conditions: [...data.rules_json.conditions, { field: 'phone_e164', operator: '=', value: '' }],
    });

    const updateCondition = (idx, cond) => {
        const conditions = [...data.rules_json.conditions];
        conditions[idx] = cond;
        setData('rules_json', { ...data.rules_json, conditions });
    };

    const removeCondition = (idx) => {
        const conditions = data.rules_json.conditions.filter((_, i) => i !== idx);
        setData('rules_json', { ...data.rules_json, conditions });
    };

    const submitCreate = (e) => {
        e.preventDefault();
        post(route('client.segments.store'), { onSuccess: () => { reset(); setShowCreate(false); } });
    };

    const handleDelete = (id) => {
        if (confirm(t('contacts_page.seg_confirm_delete'))) {
            router.delete(route('client.segments.destroy', id), { preserveScroll: true });
        }
    };

    return (
        <ClientLayout title={t('contacts_page.segments')}>
            <Head title={t('contacts_page.segments')} />
            <div className="space-y-5">
                <div className="flex items-center justify-between gap-3">
                    <div>
                        <h2 className="text-xl font-semibold text-neutral-900 dark:text-neutral-100">{t('contacts_page.segments')}</h2>
                        <p className="text-sm text-neutral-500 dark:text-neutral-400 mt-0.5">{t('contacts_page.seg_subtitle')}</p>
                    </div>
                    {(
                        <button type="button" onClick={() => setShowCreate(true)} className="flex items-center gap-1.5 rounded-lg bg-brand-600 px-3 py-2 text-sm font-medium text-white hover:bg-brand-700 transition">
                            <Plus className="h-4 w-4" /> {t('contacts_page.seg_new')}
                        </button>
                    )}
                </div>

                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    {segments.map(seg => (
                        <div key={seg.id} className="rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900 p-5 flex flex-col gap-2">
                            <div className="flex items-start justify-between gap-2">
                                <span className="font-semibold text-neutral-900 dark:text-neutral-100">{seg.name}</span>
                                <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${seg.type === 'dynamic' ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300' : 'bg-neutral-100 text-neutral-600 dark:bg-neutral-700 dark:text-neutral-300'}`}>
                                    {seg.type}
                                </span>
                            </div>
                            <p className="text-sm text-neutral-500 dark:text-neutral-400">{t('contacts_page.seg_contact_count', { count: seg.contact_count })}</p>
                            <div className="flex gap-2 mt-auto pt-2 border-t border-neutral-100 dark:border-neutral-800">
                                <a href={route('client.contacts.index', { segment: seg.id })} className="flex-1 text-center text-xs font-medium text-brand-600 hover:text-brand-700 dark:text-brand-400">{t('contacts_page.seg_view_contacts')}</a>
                                {seg.type === 'static' && (
                                    <a href={route('client.segments.contacts', seg.id)} className="flex items-center gap-1 text-xs font-medium text-emerald-600 hover:text-emerald-700 dark:text-emerald-400 px-1" title={t('contacts_page.seg_add_contacts')}>
                                        <UserPlus className="h-4 w-4" />
                                    </a>
                                )}
                                <button type="button" onClick={() => handleDelete(seg.id)} className="text-neutral-400 hover:text-red-500 transition"><Trash2 className="h-4 w-4" /></button>
                            </div>
                        </div>
                    ))}
                    {segments.length === 0 && (
                        <div className="col-span-3">
                            <EmptyState
                                icon={<Filter className="h-8 w-8" />}
                                title={t('contacts_page.seg_empty_title')}
                                description={t('contacts_page.seg_empty_description')}
                                action={{ label: t('contacts_page.seg_new'), onClick: () => setShowCreate(true) }}
                            />
                        </div>
                    )}
                </div>
            </div>

            {showCreate && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
                    <div className="w-full max-w-lg rounded-xl bg-white dark:bg-neutral-900 p-6 shadow-xl space-y-4 max-h-[90vh] overflow-y-auto">
                        <h3 className="text-lg font-semibold text-neutral-900 dark:text-neutral-100">{t('contacts_page.seg_new')}</h3>
                        <form onSubmit={submitCreate} className="space-y-4">
                            <div>
                                <label className="text-xs font-medium text-neutral-500 dark:text-neutral-400">{t('common.name')}</label>
                                <input type="text" value={data.name} onChange={e => setData('name', e.target.value)} required className="mt-1 w-full rounded border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 px-3 py-1.5 text-sm" />
                            </div>
                            <div className="flex gap-4">
                                {['static', 'dynamic'].map(type => (
                                    <label key={type} className="flex items-center gap-1.5 text-sm cursor-pointer">
                                        <input type="radio" value={type} checked={data.type === type} onChange={() => setData('type', type)} />
                                        <span className="capitalize">{type}</span>
                                    </label>
                                ))}
                            </div>

                            {data.type === 'dynamic' && (
                                <div className="space-y-2">
                                    <div className="flex items-center justify-between">
                                        <label className="text-xs font-medium text-neutral-500 dark:text-neutral-400">{t('contacts_page.seg_rules')}</label>
                                        <select value={data.rules_json.combinator} onChange={e => setData('rules_json', { ...data.rules_json, combinator: e.target.value })} className="rounded border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-700 px-2 py-1 text-xs">
                                            <option value="AND">{t('contacts_page.seg_match_all')}</option>
                                            <option value="OR">{t('contacts_page.seg_match_any')}</option>
                                        </select>
                                    </div>
                                    {data.rules_json.conditions.map((cond, i) => (
                                        <RuleRow key={i} condition={cond} onChange={c => updateCondition(i, c)} onRemove={() => removeCondition(i)} />
                                    ))}
                                    <button type="button" onClick={addCondition} className="text-sm text-brand-600 hover:text-brand-700 dark:text-brand-400">{t('contacts_page.seg_add_condition')}</button>
                                </div>
                            )}

                            <div className="flex gap-2 pt-2">
                                <button type="submit" disabled={processing} className="flex-1 rounded-lg bg-brand-600 py-2 text-sm font-medium text-white hover:bg-brand-700 disabled:opacity-60 transition">
                                    {processing ? t('common.creating') : t('contacts_page.seg_create')}
                                </button>
                                <button type="button" onClick={() => setShowCreate(false)} className="rounded-lg border border-neutral-300 dark:border-neutral-600 px-4 py-2 text-sm text-neutral-700 dark:text-neutral-300 hover:bg-neutral-50 dark:hover:bg-neutral-800 transition">
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
