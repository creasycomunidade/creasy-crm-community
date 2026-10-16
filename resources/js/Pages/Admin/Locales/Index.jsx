import AdminLayout from '@/Layouts/AdminLayout';
import { Button, Card, Tabs } from '@/Components/ui';
import { Head, router, useForm } from '@inertiajs/react';
import { useState, useCallback, useEffect } from 'react';
import { useTranslation } from 'react-i18next';

const TAB_LANGUAGES = 0;
const TAB_TRANSLATIONS = 1;

export default function AdminLocalesIndex({
    locales = [],
    translations = [],
    translationsLocale = 'en',
    translationsGroup = '',
    translationsSearch = '',
    translationsMissingOnly = false,
    groups = [],
    flash = {},
}) {
    const { t } = useTranslation();
    const [tabIndex, setTabIndex] = useState(TAB_LANGUAGES);
    const [adding, setAdding] = useState(false);
    const { data: newLocale, setData: setNewLocale } = useForm({
        code: '',
        name: '',
        native_name: '',
        flag: '',
        enabled: true,
        is_rtl: false,
        sort_order: 0,
    });
    const [savingLocale, setSavingLocale] = useState(false);

    const tabs = [
        { label: t('locales.languages'), key: 'languages' },
        { label: t('locales.translations_tab'), key: 'translations' },
    ];

    return (
        <AdminLayout title={t('locales.languages')}>
            <Head title={`${t('locales.languages')} · ${t('head.admin')}`} />
            <div className="space-y-6">
                <div className="flex justify-between items-center">
                    <h2 className="text-xl font-semibold text-neutral-900 dark:text-neutral-100">{t('locales.languages_and_translations')}</h2>
                </div>
                {(flash?.success || flash?.error || flash?.info) && (
                    <div
                        className={`rounded-soft-lg px-4 py-2 text-sm ${
                            flash.error ? 'bg-coral-50 dark:bg-coral-900/30 text-coral-800 dark:text-coral-200' :
                            flash.info ? 'bg-brand-50 dark:bg-brand-900/30 text-brand-800 dark:text-brand-200' :
                            'bg-green-50 dark:bg-green-900/30 text-green-800 dark:text-green-200'
                        }`}
                    >
                        {flash.success || flash.error || flash.info}
                    </div>
                )}

                <Tabs tabs={tabs} defaultIndex={tabIndex} onChange={setTabIndex}>
                    {tabIndex === TAB_LANGUAGES && (
                        <div className="space-y-4">
                            <div className="flex justify-end">
                                <Button variant="primary" size="sm" onClick={() => setAdding(true)}>{t('locales.add_language')}</Button>
                            </div>
                            {adding && (
                                <Card>
                                    <Card.Body>
                                        <form
                                            onSubmit={(e) => {
                                                e.preventDefault();
                                                setSavingLocale(true);
                                                router.post(route('admin.locales.store'), {
                                                    ...newLocale,
                                                    native_name: newLocale.native_name || newLocale.name,
                                                }, {
                                                    onSuccess: () => {
                                                        setAdding(false);
                                                        setNewLocale({ code: '', name: '', native_name: '', flag: '', enabled: true, is_rtl: false, sort_order: 0 });
                                                    },
                                                    onFinish: () => setSavingLocale(false),
                                                });
                                            }}
                                        >
                                            <div className="flex flex-wrap gap-3 items-end">
                                                <div>
                                                    <label className="block text-xs text-neutral-500 dark:text-neutral-400 mb-0.5">{t('locales.code')}</label>
                                                    <input
                                                        placeholder={t('locales.code')}
                                                        value={newLocale.code}
                                                        onChange={(e) => setNewLocale('code', e.target.value.toLowerCase())}
                                                        className="rounded-soft border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 px-3 py-2 text-sm w-24 focus:outline-none focus:ring-2 focus:ring-brand-500/20"
                                                        required
                                                    />
                                                </div>
                                                <div>
                                                    <label className="block text-xs text-neutral-500 dark:text-neutral-400 mb-0.5">{t('common.name')}</label>
                                                    <input
                                                        placeholder={t('common.name')}
                                                        value={newLocale.name}
                                                        onChange={(e) => setNewLocale('name', e.target.value)}
                                                        className="rounded-soft border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 px-3 py-2 text-sm w-32 focus:outline-none focus:ring-2 focus:ring-brand-500/20"
                                                        required
                                                    />
                                                </div>
                                                <div>
                                                    <label className="block text-xs text-neutral-500 dark:text-neutral-400 mb-0.5">{t('locales.native_name')}</label>
                                                    <input
                                                        placeholder={t('locales.native_name')}
                                                        value={newLocale.native_name}
                                                        onChange={(e) => setNewLocale('native_name', e.target.value)}
                                                        className="rounded-soft border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 px-3 py-2 text-sm w-32 focus:outline-none focus:ring-2 focus:ring-brand-500/20"
                                                    />
                                                </div>
                                                <div>
                                                    <label className="block text-xs text-neutral-500 dark:text-neutral-400 mb-0.5">{t('locales.flag')}</label>
                                                    <input
                                                        placeholder={t('common.optional')}
                                                        value={newLocale.flag}
                                                        onChange={(e) => setNewLocale('flag', e.target.value)}
                                                        className="rounded-soft border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 px-3 py-2 text-sm w-20 focus:outline-none focus:ring-2 focus:ring-brand-500/20"
                                                    />
                                                </div>
                                                <label className="flex items-center gap-1.5 text-neutral-900 dark:text-neutral-100">
                                                    <input type="checkbox" checked={newLocale.enabled} onChange={(e) => setNewLocale('enabled', e.target.checked)} className="rounded border-neutral-300 dark:border-neutral-600 text-brand-500" />
                                                    {t('common.enabled')}
                                                </label>
                                                <label className="flex items-center gap-1.5 text-neutral-900 dark:text-neutral-100">
                                                    <input type="checkbox" checked={newLocale.is_rtl} onChange={(e) => setNewLocale('is_rtl', e.target.checked)} className="rounded border-neutral-300 dark:border-neutral-600 text-brand-500" />
                                                    {t('locales.rtl')}
                                                </label>
                                                <div>
                                                    <label className="block text-xs text-neutral-500 dark:text-neutral-400 mb-0.5">{t('locales.sort')}</label>
                                                    <input type="number" min={0} value={newLocale.sort_order} onChange={(e) => setNewLocale('sort_order', parseInt(e.target.value, 10) || 0)} className="rounded-soft border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 px-3 py-2 text-sm w-16 focus:outline-none focus:ring-2 focus:ring-brand-500/20" />
                                                </div>
                                                <Button type="submit" size="sm" disabled={savingLocale}>{t('common.add')}</Button>
                                                <Button type="button" variant="ghost" size="sm" onClick={() => setAdding(false)}>{t('common.cancel')}</Button>
                                            </div>
                                        </form>
                                    </Card.Body>
                                </Card>
                            )}
                            <Card>
                                <div className="overflow-x-auto">
                                    <table className="min-w-full text-sm">
                                        <thead>
                                            <tr className="border-b border-neutral-200 dark:border-neutral-700 text-left text-neutral-500 dark:text-neutral-400">
                                                <th className="pb-2 pr-4 font-medium">{t('locales.languages')}</th>
                                                <th className="pb-2 pr-4 font-medium">{t('locales.code')}</th>
                                                <th className="pb-2 pr-4 font-medium">{t('admin.col_status')}</th>
                                                <th className="pb-2 pr-4 font-medium">{t('locales.rtl')}</th>
                                                <th className="pb-2"></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {locales.map((l) => (
                                                <LocaleRow key={l.code} locale={l} locales={locales} />
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                                {locales.length === 0 && !adding && <div className="py-8 text-center text-neutral-500 dark:text-neutral-400">{t('locales.no_languages')}</div>}
                            </Card>
                        </div>
                    )}
                    {tabIndex === TAB_TRANSLATIONS && (
                        <TranslationsTab
                            translations={translations}
                            translationsLocale={translationsLocale}
                            translationsGroup={translationsGroup}
                            translationsSearch={translationsSearch}
                            translationsMissingOnly={translationsMissingOnly}
                            groups={groups}
                            locales={locales}
                        />
                    )}
                </Tabs>
            </div>
        </AdminLayout>
    );
}

function LocaleRow({ locale, locales }) {
    const { t } = useTranslation();
    const [open, setOpen] = useState(false);
    const { data, setData, put, processing } = useForm({
        name: locale.name,
        native_name: locale.native_name ?? locale.name,
        flag: locale.flag ?? '',
        enabled: locale.enabled ?? true,
        is_rtl: locale.is_rtl ?? false,
        sort_order: locale.sort_order ?? 0,
    });

    const handleSetDefault = () => {
        router.post(route('admin.locales.set-default', locale.code), {}, { preserveScroll: true });
    };
    const handleDelete = () => {
        if (confirm(t('locales.remove_language'))) {
            router.delete(route('admin.locales.destroy', locale.code), { preserveScroll: true });
        }
    };

    const canDelete = !locale.is_default && locales.filter((l) => l.enabled).length > 1;

    return (
        <tr className="border-b border-neutral-100 dark:border-neutral-800">
            <td className="py-3 pr-4">
                <span className="font-medium text-neutral-900 dark:text-neutral-100">{locale.name}</span>
                {locale.native_name && locale.native_name !== locale.name && (
                    <span className="text-neutral-500 dark:text-neutral-400 ms-1.5">({locale.native_name})</span>
                )}
                {locale.flag && <span className="ms-1.5" aria-hidden>{locale.flag}</span>}
            </td>
            <td className="py-3 pr-4 font-mono text-neutral-700 dark:text-neutral-300">{locale.code}</td>
            <td className="py-3 pr-4">
                {locale.is_default ? <span className="rounded bg-brand-100 dark:bg-brand-900/40 text-brand-700 dark:text-brand-300 px-1.5 py-0.5 text-xs font-medium">{t('locales.default_badge')}</span> : locale.enabled ? t('common.active') : t('common.inactive')}
            </td>
            <td className="py-3 pr-4">{locale.is_rtl ? t('common.yes') : t('common.no')}</td>
            <td className="py-3 flex flex-wrap gap-2">
                <button type="button" className="text-brand-600 dark:text-brand-400 hover:underline text-sm font-medium" onClick={() => setOpen(!open)}>
                    {open ? t('common.cancel') : t('locales.edit')}
                </button>
                {!locale.is_default && <button type="button" className="text-brand-600 dark:text-brand-400 hover:underline text-sm font-medium" onClick={handleSetDefault}>{t('locales.set_default')}</button>}
                {canDelete && <button type="button" className="text-coral-600 dark:text-coral-400 hover:underline text-sm font-medium" onClick={handleDelete}>{t('common.delete')}</button>}
                {open && (
                    <form
                        className="inline-flex flex-wrap gap-2 items-center"
                        onSubmit={(e) => { e.preventDefault(); put(route('admin.locales.update', locale.code), { onSuccess: () => setOpen(false) }); }}
                    >
                        <input value={data.name} onChange={(e) => setData('name', e.target.value)} placeholder={t('common.name')} className="rounded-soft border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 px-2 py-1 text-sm w-28 focus:outline-none focus:ring-2 focus:ring-brand-500/20" />
                        <input value={data.native_name} onChange={(e) => setData('native_name', e.target.value)} placeholder={t('locales.native_name')} className="rounded-soft border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 px-2 py-1 text-sm w-28 focus:outline-none focus:ring-2 focus:ring-brand-500/20" />
                        <label className="text-neutral-900 dark:text-neutral-100 text-sm"><input type="checkbox" checked={data.enabled} onChange={(e) => setData('enabled', e.target.checked)} className="rounded border-neutral-300 dark:border-neutral-600 text-brand-500" /> {t('common.enabled')}</label>
                        <label className="text-neutral-900 dark:text-neutral-100 text-sm"><input type="checkbox" checked={data.is_rtl} onChange={(e) => setData('is_rtl', e.target.checked)} className="rounded border-neutral-300 dark:border-neutral-600 text-brand-500" /> {t('locales.rtl')}</label>
                        <Button type="submit" size="sm" disabled={processing}>{t('common.save')}</Button>
                    </form>
                )}
            </td>
        </tr>
    );
}

function TranslationsTab({
    translations,
    translationsLocale,
    translationsGroup,
    translationsSearch,
    translationsMissingOnly,
    groups,
    locales,
}) {
    const { t } = useTranslation();
    const [filters, setFilters] = useState({
        locale: translationsLocale,
        group: translationsGroup,
        search: translationsSearch,
        missingOnly: translationsMissingOnly,
    });

    useEffect(() => {
        setFilters({
            locale: translationsLocale,
            group: translationsGroup,
            search: translationsSearch,
            missingOnly: translationsMissingOnly,
        });
    }, [translationsLocale, translationsGroup, translationsSearch, translationsMissingOnly]);

    const applyFilters = useCallback(() => {
        const params = {};
        if (filters.locale) params.translations_locale = filters.locale;
        if (filters.group) params.translations_group = filters.group;
        if (filters.search) params.translations_search = filters.search;
        if (filters.missingOnly) params.translations_missing = '1';
        router.get(route('admin.locales.index'), params, { preserveState: true });
    }, [filters]);

    const handleAutoTranslate = () => {
        router.post(route('admin.translations.auto-translate'), { locale: filters.locale }, { preserveScroll: true });
    };

    return (
        <div className="space-y-4">
            <div className="flex flex-wrap gap-3 items-end">
                <select
                    value={filters.locale}
                    onChange={(e) => setFilters((f) => ({ ...f, locale: e.target.value }))}
                    className="rounded-soft border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/20"
                >
                    {locales.map((l) => (
                        <option key={l.code} value={l.code}>{l.name} ({l.code})</option>
                    ))}
                </select>
                <select
                    value={filters.group}
                    onChange={(e) => setFilters((f) => ({ ...f, group: e.target.value }))}
                    className="rounded-soft border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/20"
                >
                    <option value="">{t('locales.all_groups')}</option>
                    {groups.map((g) => (
                        <option key={g} value={g}>{g}</option>
                    ))}
                </select>
                <input
                    type="text"
                    placeholder={t('locales.search_key_value')}
                    value={filters.search}
                    onChange={(e) => setFilters((f) => ({ ...f, search: e.target.value }))}
                    className="rounded-soft border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 px-3 py-2 text-sm w-48 focus:outline-none focus:ring-2 focus:ring-brand-500/20"
                />
                <label className="flex items-center gap-1.5 text-neutral-900 dark:text-neutral-100 text-sm">
                    <input type="checkbox" checked={filters.missingOnly} onChange={(e) => setFilters((f) => ({ ...f, missingOnly: e.target.checked }))} className="rounded border-neutral-300 dark:border-neutral-600 text-brand-500" />
                    {t('locales.missing_only')}
                </label>
                <Button size="sm" variant="secondary" onClick={applyFilters}>{t('locales.apply_filters')}</Button>
                {filters.locale !== 'en' && (
                    <Button size="sm" variant="primary" onClick={handleAutoTranslate}>{t('locales.auto_translate_missing')}</Button>
                )}
            </div>
            <Card>
                <div className="overflow-x-auto max-h-[60vh] overflow-y-auto">
                    <table className="min-w-full text-sm">
                        <thead className="sticky top-0 bg-white dark:bg-neutral-950 border-b border-neutral-200 dark:border-neutral-700">
                            <tr className="text-left text-neutral-500 dark:text-neutral-400">
                                <th className="pb-2 pr-4 font-medium">{t('locales.col_key')}</th>
                                <th className="pb-2 pr-4 font-medium w-1/3">{t('locales.col_english')}</th>
                                <th className="pb-2 pr-4 font-medium w-1/3">{t('locales.translation')} ({translationsLocale})</th>
                                <th className="pb-2"></th>
                            </tr>
                        </thead>
                        <tbody>
                            {translations.map((tr) => (
                                <TranslationRow key={tr.flat_key} item={tr} localeCode={translationsLocale} />
                            ))}
                        </tbody>
                    </table>
                </div>
                {translations.length === 0 && <div className="py-8 text-center text-neutral-500 dark:text-neutral-400">{t('locales.no_translations')}</div>}
            </Card>
        </div>
    );
}

// Coerce any leaf to a renderable string. The backend guards against non-string
// leaves, but a malformed locale file (object/array/number value) must never reach
// JSX as a raw object — "Objects are not valid as a React child" unmounts the SPA.
function toStr(v) {
    if (v == null) return '';
    return typeof v === 'string' ? v : (typeof v === 'object' ? JSON.stringify(v) : String(v));
}

function TranslationRow({ item, localeCode }) {
    const { t } = useTranslation();
    const [value, setValue] = useState(toStr(item.value));

    useEffect(() => {
        setValue(toStr(item.value));
    }, [item.flat_key, item.value]);

    const [saving, setSaving] = useState(false);

    const debouncedSave = useCallback(() => {
        if (value === (item.value ?? '')) return;
        setSaving(true);
        router.put(route('admin.translations.update'), { locale: localeCode, flat_key: item.flat_key, value }, { preserveScroll: true, onFinish: () => setSaving(false) });
    }, [localeCode, item.flat_key, item.value, value]);

    return (
        <tr className="border-b border-neutral-100 dark:border-neutral-800">
            <td className="py-2 pr-4 font-mono text-neutral-700 dark:text-neutral-300 align-top">{toStr(item.flat_key)}</td>
            <td className="py-2 pr-4 text-neutral-600 dark:text-neutral-400 align-top whitespace-pre-wrap">{toStr(item.en_value) || '—'}</td>
            <td className="py-2 pr-4 align-top">
                <input
                    type="text"
                    value={value}
                    onChange={(e) => setValue(e.target.value)}
                    onBlur={debouncedSave}
                    className="w-full rounded-soft border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 px-2 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/20"
                    placeholder={t('locales.translation')}
                />
            </td>
            <td className="py-2">
                {saving && <span className="text-xs text-neutral-400">{t('locales.saving')}</span>}
            </td>
        </tr>
    );
}
