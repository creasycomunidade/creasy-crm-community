import { useRef, useState } from 'react';
import AdminLayout from '@/Layouts/AdminLayout';
import { Button, Card, Tabs } from '@/Components/ui';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { Upload, X, Image, Globe, Palette, Settings2, Code2, Flame, TriangleAlert } from 'lucide-react';

// ─── General Settings Tab ─────────────────────────────────────────────────────

function GeneralTab({ general, flash }) {
    const { t } = useTranslation();
    const { data, setData, put, processing, errors } = useForm({
        app_name:      general?.app_name      ?? '',
        app_tagline:   general?.app_tagline   ?? '',
        support_email: general?.support_email ?? '',
        primary_color: general?.primary_color ?? '#7C3AED',
    });

    const submit = (e) => {
        e.preventDefault();
        put(route('admin.settings.general.update'), { preserveScroll: true });
    };

    return (
        <div className="space-y-6">
            {flash?.success && (
                <div className="rounded-soft-lg bg-green-50 dark:bg-green-900/30 text-green-800 dark:text-green-200 px-4 py-2 text-sm">
                    {flash.success}
                </div>
            )}

            <form onSubmit={submit}>
                <Card>
                    <Card.Body className="space-y-5">
                        <div className="flex items-center gap-3 pb-4 border-b border-neutral-100 dark:border-neutral-800">
                            <Globe className="h-5 w-5 text-brand-500" />
                            <div>
                                <h3 className="font-semibold text-neutral-900 dark:text-neutral-100">{t('settings.site_information')}</h3>
                                <p className="text-xs text-neutral-500 dark:text-neutral-400">{t('settings.site_info_desc')}</p>
                            </div>
                        </div>

                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-5">
                            <div className="space-y-1">
                                <label className="block text-sm font-medium text-neutral-700 dark:text-neutral-300">{t('settings.app_name')}</label>
                                <input
                                    type="text"
                                    value={data.app_name}
                                    onChange={(e) => setData('app_name', e.target.value)}
                                    placeholder={t('settings.app_name_placeholder')}
                                    className="w-full rounded-soft border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/20"
                                />
                                {errors.app_name && <p className="text-xs text-red-500">{errors.app_name}</p>}
                            </div>

                            <div className="space-y-1">
                                <label className="block text-sm font-medium text-neutral-700 dark:text-neutral-300">{t('settings.support_email')}</label>
                                <input
                                    type="email"
                                    value={data.support_email}
                                    onChange={(e) => setData('support_email', e.target.value)}
                                    placeholder="support@example.com"
                                    className="w-full rounded-soft border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/20"
                                />
                                {errors.support_email && <p className="text-xs text-red-500">{errors.support_email}</p>}
                            </div>

                            <div className="space-y-1 sm:col-span-2">
                                <label className="block text-sm font-medium text-neutral-700 dark:text-neutral-300">{t('settings.tagline')}</label>
                                <input
                                    type="text"
                                    value={data.app_tagline}
                                    onChange={(e) => setData('app_tagline', e.target.value)}
                                    placeholder={t('settings.tagline_placeholder')}
                                    className="w-full rounded-soft border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/20"
                                />
                                {errors.app_tagline && <p className="text-xs text-red-500">{errors.app_tagline}</p>}
                            </div>
                        </div>

                        <div className="flex items-center gap-3 pb-4 pt-2 border-b border-neutral-100 dark:border-neutral-800">
                            <Palette className="h-5 w-5 text-brand-500" />
                            <div>
                                <h3 className="font-semibold text-neutral-900 dark:text-neutral-100">{t('settings.appearance')}</h3>
                                <p className="text-xs text-neutral-500 dark:text-neutral-400">{t('settings.appearance_desc')}</p>
                            </div>
                        </div>

                        <div className="space-y-1">
                            <label className="block text-sm font-medium text-neutral-700 dark:text-neutral-300">{t('settings.primary_brand_color')}</label>
                            <div className="flex items-center gap-3">
                                <input
                                    type="color"
                                    value={data.primary_color}
                                    onChange={(e) => setData('primary_color', e.target.value)}
                                    className="h-10 w-16 cursor-pointer rounded-soft border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 p-1"
                                />
                                <input
                                    type="text"
                                    value={data.primary_color}
                                    onChange={(e) => setData('primary_color', e.target.value)}
                                    placeholder="#7C3AED"
                                    maxLength={7}
                                    className="w-32 rounded-soft border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 px-3 py-2 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-brand-500/20"
                                />
                                <span className="text-xs text-neutral-500 dark:text-neutral-400">{t('settings.hex_color_hint')}</span>
                            </div>
                            {errors.primary_color && <p className="text-xs text-red-500">{errors.primary_color}</p>}
                        </div>

                        <div className="flex justify-end pt-2">
                            <Button type="submit" variant="primary" disabled={processing}>
                                {processing ? t('settings.saving') : t('settings.save_general')}
                            </Button>
                        </div>
                    </Card.Body>
                </Card>
            </form>

            <Card>
                <Card.Body className="space-y-6">
                    <div className="flex items-center gap-3 pb-4 border-b border-neutral-100 dark:border-neutral-800">
                        <Image className="h-5 w-5 text-brand-500" />
                        <div>
                            <h3 className="font-semibold text-neutral-900 dark:text-neutral-100">{t('settings.logo_favicon')}</h3>
                            <p className="text-xs text-neutral-500 dark:text-neutral-400">{t('settings.logo_favicon_desc')}</p>
                        </div>
                    </div>

                    <ImageUploadWidget
                        label={t('settings.app_logo')}
                        description={t('settings.app_logo_desc')}
                        accept="image/png,image/jpeg,image/gif,image/svg+xml,image/webp"
                        currentUrl={general?.logo_url}
                        uploadRoute="admin.settings.logo.upload"
                        deleteRoute="admin.settings.logo.delete"
                        fieldName="logo"
                    />

                    <div className="border-t border-neutral-100 dark:border-neutral-800 pt-5">
                        <ImageUploadWidget
                            label={t('settings.favicon')}
                            description={t('settings.favicon_desc')}
                            accept="image/x-icon,image/vnd.microsoft.icon,image/png,image/svg+xml,image/gif,image/webp"
                            currentUrl={general?.favicon_url}
                            uploadRoute="admin.settings.favicon.upload"
                            deleteRoute="admin.settings.favicon.delete"
                            fieldName="favicon"
                        />
                    </div>
                </Card.Body>
            </Card>

            <Card>
                <Card.Body>
                    <p className="text-sm text-neutral-500 dark:text-neutral-400">
                        <strong className="text-neutral-700 dark:text-neutral-300">{t('settings.logo_usage_title')}</strong>
                        {' '}{t('settings.logo_usage_hint')}
                    </p>
                </Card.Body>
            </Card>
        </div>
    );
}

// ─── Image Upload Widget ───────────────────────────────────────────────────────

function ImageUploadWidget({ label, description, accept, currentUrl, uploadRoute, deleteRoute, fieldName }) {
    const { t } = useTranslation();
    const fileRef = useRef(null);
    const [preview, setPreview] = useState(null);
    const [uploading, setUploading] = useState(false);
    const [deleting, setDeleting] = useState(false);

    const handleFile = (file) => {
        if (!file) return;
        setPreview(URL.createObjectURL(file));
        const fd = new FormData();
        fd.append(fieldName, file);
        setUploading(true);
        router.post(route(uploadRoute), fd, {
            forceFormData: true,
            preserveScroll: true,
            onFinish: () => {
                setUploading(false);
                setPreview(null);
            },
        });
    };

    const handleDelete = () => {
        setDeleting(true);
        router.delete(route(deleteRoute), {
            preserveScroll: true,
            onFinish: () => setDeleting(false),
        });
    };

    const displayUrl = preview || currentUrl;

    return (
        <div className="space-y-3">
            <div>
                <p className="text-sm font-medium text-neutral-700 dark:text-neutral-300">{label}</p>
                <p className="text-xs text-neutral-500 dark:text-neutral-400 mt-0.5">{description}</p>
            </div>

            <div className="flex items-start gap-4">
                {/* Preview box */}
                <div className="relative flex-shrink-0 w-24 h-24 rounded-soft-lg border-2 border-dashed border-neutral-300 dark:border-neutral-600 bg-neutral-50 dark:bg-neutral-800/50 flex items-center justify-center overflow-hidden">
                    {displayUrl ? (
                        <>
                            <img src={displayUrl} alt={label} className="w-full h-full object-contain p-1" />
                            {!uploading && currentUrl && (
                                <button
                                    type="button"
                                    onClick={handleDelete}
                                    disabled={deleting}
                                    className="absolute top-1 right-1 rounded-full bg-red-500 text-white p-0.5 hover:bg-red-600 transition"
                                >
                                    <X className="h-3 w-3" />
                                </button>
                            )}
                        </>
                    ) : (
                        <Image className="h-8 w-8 text-neutral-300 dark:text-neutral-600" />
                    )}
                    {(uploading || deleting) && (
                        <div className="absolute inset-0 bg-white/60 dark:bg-neutral-900/60 flex items-center justify-center">
                            <svg className="animate-spin h-5 w-5 text-brand-500" viewBox="0 0 24 24" fill="none">
                                <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z" />
                            </svg>
                        </div>
                    )}
                </div>

                {/* Actions */}
                <div className="flex flex-col gap-2 justify-center">
                    <button
                        type="button"
                        onClick={() => fileRef.current?.click()}
                        disabled={uploading || deleting}
                        className="inline-flex items-center gap-2 rounded-soft border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 px-3 py-2 text-sm text-neutral-700 dark:text-neutral-300 hover:bg-neutral-50 dark:hover:bg-neutral-700 transition disabled:opacity-50"
                    >
                        <Upload className="h-4 w-4" />
                        {currentUrl ? t('settings.replace') : t('settings.upload')}
                    </button>
                    {currentUrl && (
                        <button
                            type="button"
                            onClick={handleDelete}
                            disabled={deleting || uploading}
                            className="inline-flex items-center gap-2 rounded-soft border border-red-200 dark:border-red-900 bg-white dark:bg-neutral-800 px-3 py-2 text-sm text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 transition disabled:opacity-50"
                        >
                            <X className="h-4 w-4" />
                            {t('settings.remove')}
                        </button>
                    )}
                    <input
                        ref={fileRef}
                        type="file"
                        accept={accept}
                        className="hidden"
                        onChange={(e) => handleFile(e.target.files?.[0])}
                    />
                </div>
            </div>
        </div>
    );
}

// ─── Advanced Tab ─────────────────────────────────────────────────────────────

function AdvancedTab({ settingsByGroup, flash }) {
    const { t } = useTranslation();
    const flat = Object.entries(settingsByGroup).flatMap(([group, items]) =>
        items.map((s) => ({ ...s, group }))
    );
    const { data, setData, put, processing } = useForm({ settings: flat });

    if (flat.length === 0) {
        return (
            <Card>
                <Card.Body>
                    <div className="text-center py-8">
                        <Code2 className="h-10 w-10 mx-auto text-neutral-300 dark:text-neutral-600 mb-3" />
                        <p className="text-sm text-neutral-500 dark:text-neutral-400">{t('settings.no_advanced')}</p>
                        <p className="text-xs text-neutral-400 dark:text-neutral-500 mt-1">{t('settings.no_advanced_desc')}</p>
                    </div>
                </Card.Body>
            </Card>
        );
    }

    return (
        <form
            onSubmit={(e) => {
                e.preventDefault();
                put(route('admin.settings.update'), { preserveScroll: true });
            }}
            className="space-y-6"
        >
            {flash?.success && (
                <div className="rounded-soft-lg bg-green-50 dark:bg-green-900/30 text-green-800 dark:text-green-200 px-4 py-2 text-sm">
                    {flash.success}
                </div>
            )}

            <div className="flex gap-3 rounded-soft-lg border border-amber-200 bg-amber-50 px-4 py-3 text-amber-900 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-200">
                <TriangleAlert className="mt-0.5 h-5 w-5 shrink-0" />
                <div>
                    <p className="text-sm font-semibold">{t('settings.advanced_warning_title')}</p>
                    <p className="mt-1 text-xs leading-5">{t('settings.advanced_warning_desc')}</p>
                    <p className="mt-1 text-xs leading-5">{t('settings.advanced_keys_hint')}</p>
                </div>
            </div>

            {Object.entries(
                data.settings.reduce((acc, s, i) => {
                    const g = s.group || t('settings.ungrouped');
                    if (!acc[g]) acc[g] = [];
                    acc[g].push({ ...s, _index: i });
                    return acc;
                }, {})
            ).map(([group, items]) => (
                <Card key={group}>
                    <Card.Body className="space-y-3">
                        <div className="flex items-center gap-2 pb-3 border-b border-neutral-100 dark:border-neutral-800">
                            <Settings2 className="h-4 w-4 text-neutral-400" />
                            <h3 className="text-sm font-semibold text-neutral-700 dark:text-neutral-300 capitalize">{group}</h3>
                        </div>
                        {items.map((s) => {
                            const i = s._index;
                            return (
                                <div key={s.id || i} className="flex flex-wrap gap-2 items-center border-b border-neutral-100 dark:border-neutral-800 pb-2 last:border-0 last:pb-0">
                                    <span className="font-mono text-xs w-44 text-neutral-600 dark:text-neutral-400 truncate" title={s.key}>{s.key}</span>
                                    <input
                                        type={s.is_secret ? 'password' : 'text'}
                                        value={s.value}
                                        onChange={(e) => {
                                            const next = [...data.settings];
                                            next[i] = { ...next[i], value: e.target.value };
                                            setData('settings', next);
                                        }}
                                        placeholder={s.is_secret ? t('admin.secret_value') : t('admin.value_label')}
                                        className="rounded-soft border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 px-3 py-1.5 text-sm flex-1 min-w-[180px] focus:outline-none focus:ring-2 focus:ring-brand-500/20"
                                    />
                                    <label className="flex items-center gap-1 text-xs text-neutral-600 dark:text-neutral-400">
                                        <input
                                            type="checkbox"
                                            checked={s.is_secret}
                                            onChange={(e) => {
                                                const n = [...data.settings];
                                                n[i] = { ...n[i], is_secret: e.target.checked };
                                                setData('settings', n);
                                            }}
                                            className="rounded border-neutral-300 dark:border-neutral-600 text-brand-500"
                                        />
                                        {t('settings.secret')}
                                    </label>
                                    <input
                                        type="text"
                                        value={s.group ?? ''}
                                        onChange={(e) => {
                                            const n = [...data.settings];
                                            n[i] = { ...n[i], group: e.target.value };
                                            setData('settings', n);
                                        }}
                                        placeholder={t('admin.group_label')}
                                        className="rounded-soft border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 px-2 py-1.5 w-24 text-xs focus:outline-none focus:ring-2 focus:ring-brand-500/20"
                                    />
                                </div>
                            );
                        })}
                    </Card.Body>
                </Card>
            ))}

            <div className="flex justify-end">
                <Button type="submit" variant="primary" disabled={processing}>
                    {processing ? t('settings.saving') : t('settings.save_advanced')}
                </Button>
            </div>
        </form>
    );
}

// ─── Firebase Tab ─────────────────────────────────────────────────────────────

function FirebaseTab({ firebase, flash }) {
    const { t } = useTranslation();
    const { data, setData, put, processing, errors } = useForm({
        firebase_enabled:     firebase?.enabled    ? 'true' : 'false',
        firebase_api_key:     firebase?.apiKey     ?? '',
        firebase_auth_domain: firebase?.authDomain ?? '',
        firebase_project_id:  firebase?.projectId  ?? '',
        firebase_app_id:      firebase?.appId      ?? '',
    });

    const submit = (e) => {
        e.preventDefault();
        put(route('admin.settings.firebase.update'), { preserveScroll: true });
    };

    const field = (label, key, description, placeholder = '') => (
        <div className="space-y-1">
            <label className="block text-sm font-medium text-neutral-700 dark:text-neutral-300">{label}</label>
            {description && <p className="text-xs text-neutral-400 dark:text-neutral-500">{description}</p>}
            <input
                type="text"
                value={data[key]}
                onChange={(e) => setData(key, e.target.value)}
                placeholder={placeholder}
                className="w-full rounded-soft border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 px-3 py-2 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-brand-500/20"
            />
            {errors[key] && <p className="text-xs text-red-500">{errors[key]}</p>}
        </div>
    );

    return (
        <form onSubmit={submit} className="space-y-6">
            {flash?.success && (
                <div className="rounded-soft-lg bg-green-50 dark:bg-green-900/30 text-green-800 dark:text-green-200 px-4 py-2 text-sm">
                    {flash.success}
                </div>
            )}

            <Card>
                <Card.Body className="space-y-5">
                    <div className="flex items-center gap-3 pb-4 border-b border-neutral-100 dark:border-neutral-800">
                        <Flame className="h-5 w-5 text-orange-500" />
                        <div>
                            <h3 className="font-semibold text-neutral-900 dark:text-neutral-100">{t('settings.firebase_auth')}</h3>
                            <p className="text-xs text-neutral-500 dark:text-neutral-400">
                                {t('settings.firebase_auth_desc')}
                            </p>
                        </div>
                    </div>

                    <div className="flex items-center justify-between rounded-soft border border-neutral-200 dark:border-neutral-700 px-4 py-3">
                        <div>
                            <p className="text-sm font-medium text-neutral-700 dark:text-neutral-300">{t('settings.enable_firebase')}</p>
                            <p className="text-xs text-neutral-400 dark:text-neutral-500 mt-0.5">{t('settings.enable_firebase_desc')}</p>
                        </div>
                        <button
                            type="button"
                            role="switch"
                            aria-checked={data.firebase_enabled === 'true'}
                            onClick={() => setData('firebase_enabled', data.firebase_enabled === 'true' ? 'false' : 'true')}
                            className={`relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-brand-500/20 ${
                                data.firebase_enabled === 'true' ? 'bg-brand-500' : 'bg-neutral-300 dark:bg-neutral-600'
                            }`}
                        >
                            <span
                                className={`inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform ${
                                    data.firebase_enabled === 'true' ? 'translate-x-6' : 'translate-x-1'
                                }`}
                            />
                        </button>
                    </div>

                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-5">
                        {field(t('settings.api_key'), 'firebase_api_key', t('settings.firebase_api_key_desc'), 'AIzaSy...')}
                        {field(t('settings.auth_domain'), 'firebase_auth_domain', t('settings.firebase_auth_domain_desc'), 'your-project.firebaseapp.com')}
                        {field(t('settings.project_id'), 'firebase_project_id', t('settings.firebase_project_id_desc'), 'your-project-id')}
                        {field(t('settings.app_id'), 'firebase_app_id', t('settings.firebase_app_id_desc'), '1:123456:web:abc...')}
                    </div>

                    <div className="rounded-soft border border-amber-200 dark:border-amber-800 bg-amber-50 dark:bg-amber-900/20 px-4 py-3 text-xs text-amber-700 dark:text-amber-300 space-y-1">
                        <p className="font-semibold">{t('settings.setup_checklist')}</p>
                        <ol className="list-decimal list-inside space-y-0.5 mt-1">
                            <li>{t('settings.firebase_step_1')}</li>
                            <li>{t('settings.firebase_step_2')}</li>
                            <li>{t('settings.firebase_step_3')}</li>
                        </ol>
                    </div>
                </Card.Body>
            </Card>

            <div className="flex justify-end">
                <Button type="submit" variant="primary" disabled={processing}>
                    {processing ? t('settings.saving') : t('settings.save_firebase')}
                </Button>
            </div>
        </form>
    );
}

// ─── Page ─────────────────────────────────────────────────────────────────────

export default function AdminSettingsIndex({ general = {}, settingsByGroup = {}, firebase = {} }) {
    const { t } = useTranslation();
    const { props } = usePage();
    const flash = props.flash ?? {};

    const tabs = [
        { key: 'general',  label: t('settings.tab_general') },
        { key: 'firebase', label: t('settings.tab_firebase') },
        { key: 'advanced', label: t('settings.tab_advanced') },
    ];

    const [activeIndex, setActiveIndex] = useState(0);

    return (
        <AdminLayout title={t('admin.system_settings')}>
            <Head title={`${t('admin.nav.settings')} · ${t('head.admin')}`} />
            <div className="space-y-6">
                <h2 className="text-xl font-semibold text-neutral-900 dark:text-neutral-100">{t('admin.system_settings')}</h2>

                <Tabs tabs={tabs} defaultIndex={0} onChange={(i) => setActiveIndex(i)}>
                    <Tabs.Panel index={0} activeIndex={activeIndex}>
                        <GeneralTab general={general} flash={flash} />
                    </Tabs.Panel>
                    <Tabs.Panel index={1} activeIndex={activeIndex}>
                        <FirebaseTab firebase={firebase} flash={flash} />
                    </Tabs.Panel>
                    <Tabs.Panel index={2} activeIndex={activeIndex}>
                        <AdvancedTab settingsByGroup={settingsByGroup} flash={flash} />
                    </Tabs.Panel>
                </Tabs>
            </div>
        </AdminLayout>
    );
}
