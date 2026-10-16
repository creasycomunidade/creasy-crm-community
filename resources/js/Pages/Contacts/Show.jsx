import { Head, useForm, router } from '@inertiajs/react';
import ClientLayout from '@/Layouts/ClientLayout';
import {
    ArrowLeft, MessageSquare, Phone, Mail, Globe, Camera, Trash2, Upload,
    Instagram, Send, Facebook, Linkedin, Music2, AtSign, Youtube,
    ExternalLink, Plus, X,
} from 'lucide-react';
import { useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';

function OptInBadge({ label, active }) {
    return (
        <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${active ? 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300' : 'bg-neutral-100 text-neutral-500 dark:bg-neutral-800 dark:text-neutral-400'}`}>
            {active ? '✓' : '✗'} {label}
        </span>
    );
}

const SOCIAL_FIELDS = [
    { key: 'instagram', label: 'Instagram', placeholder: '@usuario', Icon: Instagram, href: value => `https://instagram.com/${value.replace(/^@/, '')}` },
    { key: 'telegram', label: 'Telegram', placeholder: '@usuario', Icon: Send, href: value => `https://t.me/${value.replace(/^@/, '')}` },
    { key: 'facebook', label: 'Facebook / Messenger', placeholder: 'https://facebook.com/usuario', Icon: Facebook, href: value => value },
    { key: 'linkedin', label: 'LinkedIn', placeholder: 'https://linkedin.com/in/usuario', Icon: Linkedin, href: value => value },
    { key: 'tiktok', label: 'TikTok', placeholder: '@usuario', Icon: Music2, href: value => `https://tiktok.com/@${value.replace(/^@/, '')}` },
    { key: 'x', label: 'X', placeholder: '@usuario', Icon: AtSign, href: value => `https://x.com/${value.replace(/^@/, '')}` },
    { key: 'youtube', label: 'YouTube', placeholder: 'https://youtube.com/@canal', Icon: Youtube, href: value => value },
];

function ContactLink({ href, label, Icon = ExternalLink }) {
    return (
        <a
            href={href}
            target="_blank"
            rel="noopener noreferrer"
            className="inline-flex min-h-11 max-w-full items-center gap-2 rounded-xl border border-neutral-200 bg-white px-3 py-2 text-sm font-medium text-neutral-700 transition hover:border-brand-300 hover:bg-brand-50 hover:text-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-300 dark:hover:border-brand-700 dark:hover:bg-brand-950/30 dark:hover:text-brand-300"
        >
            <Icon className="h-4 w-4 shrink-0" aria-hidden="true" />
            <span className="truncate">{label}</span>
            <ExternalLink className="h-3.5 w-3.5 shrink-0 text-neutral-400" aria-hidden="true" />
        </a>
    );
}

function AvatarUploader({ contact }) {
    const { t } = useTranslation();
    const fileInput = useRef();
    const [preview, setPreview] = useState(contact.avatar_url ?? null);
    const [uploading, setUploading] = useState(false);
    const [dragOver, setDragOver] = useState(false);

    const name = `${contact.first_name ?? ''} ${contact.last_name ?? ''}`.trim();
    const initials = name
        ? name.split(' ').map(p => p[0]).slice(0, 2).join('').toUpperCase()
        : '?';

    const uploadFile = (file) => {
        if (!file || !file.type.startsWith('image/')) return;
        setPreview(URL.createObjectURL(file));
        setUploading(true);

        const formData = new FormData();
        formData.append('avatar', file);
        router.post(route('client.contacts.avatar.upload', contact.uuid), formData, {
            forceFormData: true,
            preserveScroll: true,
            onFinish: () => setUploading(false),
        });
    };

    const handleFileChange = (e) => {
        const file = e.target.files[0];
        if (file) uploadFile(file);
    };

    const handleDrop = (e) => {
        e.preventDefault();
        setDragOver(false);
        const file = e.dataTransfer.files[0];
        if (file) uploadFile(file);
    };

    const handleDelete = () => {
        if (!confirm(t('contacts_page.avatar_confirm_remove'))) return;
        setPreview(null);
        router.delete(route('client.contacts.avatar.delete', contact.uuid), { preserveScroll: true });
    };

    return (
        <div className="flex flex-col items-center gap-3">
            {/* Avatar circle */}
            <div
                className={`relative group cursor-pointer rounded-full transition ${dragOver ? 'ring-4 ring-brand-400' : ''}`}
                onClick={() => fileInput.current?.click()}
                onDragOver={(e) => { e.preventDefault(); setDragOver(true); }}
                onDragLeave={() => setDragOver(false)}
                onDrop={handleDrop}
            >
                {preview ? (
                    <img
                        src={preview}
                        alt={name || t('contacts_page.contact_alt')}
                        className="h-24 w-24 rounded-full object-cover border-2 border-neutral-200 dark:border-neutral-700"
                    />
                ) : (
                    <div className="h-24 w-24 rounded-full bg-brand-100 dark:bg-brand-900/40 text-brand-700 dark:text-brand-300 flex items-center justify-center text-2xl font-bold border-2 border-neutral-200 dark:border-neutral-700">
                        {initials}
                    </div>
                )}
                {/* Overlay on hover */}
                <div className="absolute inset-0 rounded-full bg-black/40 opacity-0 group-hover:opacity-100 flex items-center justify-center transition">
                    <Camera className="h-6 w-6 text-white" />
                </div>
                {uploading && (
                    <div className="absolute inset-0 rounded-full bg-black/50 flex items-center justify-center">
                        <div className="h-5 w-5 border-2 border-white border-t-transparent rounded-full animate-spin" />
                    </div>
                )}
            </div>

            <input
                ref={fileInput}
                type="file"
                accept="image/jpeg,image/png,image/webp,image/gif"
                className="hidden"
                onChange={handleFileChange}
            />

            {/* Action buttons */}
            <div className="flex items-center gap-2">
                <button
                    type="button"
                    onClick={() => fileInput.current?.click()}
                    className="flex items-center gap-1.5 rounded-lg border border-neutral-300 dark:border-neutral-600 px-3 py-1.5 text-xs text-neutral-700 dark:text-neutral-300 hover:bg-neutral-50 dark:hover:bg-neutral-800 transition"
                >
                    <Upload className="h-3.5 w-3.5" /> {t('contacts_page.avatar_upload')}
                </button>
                {preview && (
                    <button
                        type="button"
                        onClick={handleDelete}
                        className="flex items-center gap-1.5 rounded-lg border border-red-200 dark:border-red-800 px-3 py-1.5 text-xs text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 transition"
                    >
                        <Trash2 className="h-3.5 w-3.5" /> {t('common.remove')}
                    </button>
                )}
            </div>
            <p className="text-xs text-neutral-400">{t('contacts_page.avatar_hint')}</p>
            {contact.avatar_url && contact.avatar_url.startsWith('http') && !contact.avatar_url.includes('/storage/') && (
                <p className="text-xs text-blue-500 dark:text-blue-400">{t('contacts_page.avatar_synced')}</p>
            )}
        </div>
    );
}

export default function ContactShow({ contact, staticSegments = [] }) {
    const { t } = useTranslation();
    const { data, setData, put, processing, errors, recentlySuccessful } = useForm({
        first_name: contact.first_name ?? '',
        last_name: contact.last_name ?? '',
        email: contact.email ?? '',
        website_url: contact.website_url ?? '',
        country: contact.country ?? '',
        language: contact.language ?? '',
        social_profiles: Object.fromEntries(SOCIAL_FIELDS.map(({ key }) => [key, contact.social_profiles?.[key] ?? ''])),
        contact_links: Array.isArray(contact.contact_links) ? contact.contact_links : [],
        opt_in_whatsapp: contact.opt_in_whatsapp,
        opt_in_sms: contact.opt_in_sms,
        opt_in_email: contact.opt_in_email,
        segment_ids: (contact.segments ?? []).filter(s => s.type === 'static').map(s => s.id),
    });

    const handleSave = (e) => {
        e.preventDefault();
        put(route('client.contacts.update', contact.uuid), { preserveScroll: true });
    };

    const profileLinks = [
        ...(contact.website_url ? [{ href: contact.website_url, label: 'Site', Icon: Globe }] : []),
        ...SOCIAL_FIELDS
            .filter(({ key }) => contact.social_profiles?.[key])
            .map(field => ({ href: field.href(contact.social_profiles[field.key]), label: field.label, Icon: field.Icon })),
        ...((contact.contact_links ?? []).filter(link => link?.label && link?.url).map(link => ({
            href: link.url,
            label: link.label,
            Icon: ExternalLink,
        }))),
    ];

    const updateAdditionalLink = (index, field, value) => {
        setData('contact_links', data.contact_links.map((link, linkIndex) => (
            linkIndex === index ? { ...link, [field]: value } : link
        )));
    };

    const inputClass = 'mt-1 min-h-11 w-full rounded-xl border border-neutral-300 bg-white px-3 py-2 text-base text-neutral-900 outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-neutral-600 dark:bg-neutral-800 dark:text-neutral-100 sm:text-sm';

    return (
        <ClientLayout title={t('contacts_page.contact_alt')}>
            <Head title={`${contact.first_name ?? ''} ${contact.last_name ?? ''} · ${t('contacts_page.contact_alt')}`} />
            <div className="max-w-3xl space-y-6">
                <div className="flex items-center gap-3">
                    <a href={route('client.contacts.index')} className="text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-200 transition">
                        <ArrowLeft className="h-5 w-5" />
                    </a>
                    <h2 className="text-xl font-semibold text-neutral-900 dark:text-neutral-100">
                        {contact.first_name || contact.last_name ? `${contact.first_name ?? ''} ${contact.last_name ?? ''}`.trim() : t('contacts_page.unknown_contact')}
                    </h2>
                </div>

                {/* Avatar + Contact info */}
                <div className="rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900 p-5">
                    <div className="flex flex-col sm:flex-row gap-6 items-start">
                        {/* Avatar uploader */}
                        <AvatarUploader contact={contact} />

                        {/* Contact details */}
                        <div className="flex-1 space-y-3">
                            <h3 className="font-medium text-neutral-800 dark:text-neutral-200">{t('contacts_page.contact_details')}</h3>
                            {contact.phone_e164 && <div className="flex items-center gap-2 text-sm text-neutral-600 dark:text-neutral-400"><Phone className="h-4 w-4" />{contact.phone_e164}</div>}
                            {contact.email      && <div className="flex items-center gap-2 text-sm text-neutral-600 dark:text-neutral-400"><Mail className="h-4 w-4" />{contact.email}</div>}
                            {contact.country    && <div className="flex items-center gap-2 text-sm text-neutral-600 dark:text-neutral-400"><Globe className="h-4 w-4" />{contact.country}</div>}
                            {profileLinks.length > 0 && (
                                <div>
                                    <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-neutral-500 dark:text-neutral-400">Redes e links</p>
                                    <div className="flex flex-wrap gap-2">
                                        {profileLinks.map((link, index) => <ContactLink key={`${link.href}-${index}`} {...link} />)}
                                    </div>
                                </div>
                            )}
                            <div className="flex flex-wrap gap-2 pt-2">
                                <OptInBadge label="WhatsApp" active={contact.opt_in_whatsapp} />
                                <OptInBadge label={t('contacts_page.channel_sms')} active={contact.opt_in_sms} />
                                <OptInBadge label={t('common.email')}    active={contact.opt_in_email} />
                            </div>
                        </div>
                    </div>
                </div>

                {/* Edit form */}
                <form onSubmit={handleSave} className="rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900 p-5 space-y-6">
                    <h3 className="font-medium text-neutral-800 dark:text-neutral-200">{t('common.edit')}</h3>
                    <section aria-labelledby="basic-contact-heading" className="space-y-3">
                        <h4 id="basic-contact-heading" className="text-sm font-semibold text-neutral-700 dark:text-neutral-200">Dados básicos</h4>
                        <div className="grid gap-3 sm:grid-cols-2">
                            {[['first_name', t('contacts_page.first_name'), 'text'], ['last_name', t('contacts_page.last_name'), 'text'], ['email', t('common.email'), 'email'], ['country', t('contacts_page.country_label'), 'text'], ['language', t('contacts_page.language_label'), 'text']].map(([key, label, type]) => (
                                <label key={key} className={key === 'language' ? 'sm:col-span-2' : ''}>
                                    <span className="text-xs font-medium text-neutral-600 dark:text-neutral-300">{label}</span>
                                    <input id={`contact-${key}`} type={type} value={data[key]} onChange={event => setData(key, event.target.value)} className={inputClass} />
                                    {errors[key] && <span role="alert" className="mt-1 block text-xs text-red-600 dark:text-red-400">{errors[key]}</span>}
                                </label>
                            ))}
                        </div>
                    </section>

                    <section aria-labelledby="social-contact-heading" className="space-y-3 border-t border-neutral-200 pt-5 dark:border-neutral-700">
                        <div>
                            <h4 id="social-contact-heading" className="text-sm font-semibold text-neutral-700 dark:text-neutral-200">Redes sociais e site</h4>
                            <p className="mt-1 text-xs text-neutral-500 dark:text-neutral-400">Cadastre onde esse cliente pode ser encontrado. Os links aparecerão como ações no perfil.</p>
                        </div>
                        <label className="block">
                            <span className="text-xs font-medium text-neutral-600 dark:text-neutral-300">Site principal</span>
                            <div className="relative">
                                <Globe className="pointer-events-none absolute left-3 top-4 h-4 w-4 text-neutral-400" aria-hidden="true" />
                                <input type="url" inputMode="url" value={data.website_url} onChange={event => setData('website_url', event.target.value)} placeholder="https://empresa.com.br" className={`${inputClass} pl-10`} />
                            </div>
                            {errors.website_url && <span role="alert" className="mt-1 block text-xs text-red-600 dark:text-red-400">{errors.website_url}</span>}
                        </label>
                        <div className="grid gap-3 sm:grid-cols-2">
                            {SOCIAL_FIELDS.map(({ key, label, placeholder, Icon }) => (
                                <label key={key} className="block">
                                    <span className="text-xs font-medium text-neutral-600 dark:text-neutral-300">{label}</span>
                                    <div className="relative">
                                        <Icon className="pointer-events-none absolute left-3 top-4 h-4 w-4 text-neutral-400" aria-hidden="true" />
                                        <input
                                            type={['facebook', 'linkedin', 'youtube'].includes(key) ? 'url' : 'text'}
                                            inputMode={['facebook', 'linkedin', 'youtube'].includes(key) ? 'url' : 'text'}
                                            value={data.social_profiles[key]}
                                            onChange={event => setData('social_profiles', { ...data.social_profiles, [key]: event.target.value })}
                                            placeholder={placeholder}
                                            className={`${inputClass} pl-10`}
                                        />
                                    </div>
                                    {errors[`social_profiles.${key}`] && <span role="alert" className="mt-1 block text-xs text-red-600 dark:text-red-400">{errors[`social_profiles.${key}`]}</span>}
                                </label>
                            ))}
                        </div>
                    </section>

                    <section aria-labelledby="additional-links-heading" className="space-y-3 border-t border-neutral-200 pt-5 dark:border-neutral-700">
                        <div className="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <h4 id="additional-links-heading" className="text-sm font-semibold text-neutral-700 dark:text-neutral-200">Outros links de contato</h4>
                                <p className="mt-1 text-xs text-neutral-500 dark:text-neutral-400">Portfólio, catálogo, página de agendamento ou outro endereço útil.</p>
                            </div>
                            <button
                                type="button"
                                disabled={data.contact_links.length >= 10}
                                onClick={() => setData('contact_links', [...data.contact_links, { label: '', url: '' }])}
                                className="inline-flex min-h-11 items-center gap-2 rounded-xl border border-neutral-300 px-3 text-sm font-medium text-neutral-700 transition hover:bg-neutral-50 focus:outline-none focus:ring-2 focus:ring-brand-500 disabled:cursor-not-allowed disabled:opacity-50 dark:border-neutral-600 dark:text-neutral-200 dark:hover:bg-neutral-800"
                            >
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
                                            <input value={link.label} onChange={event => updateAdditionalLink(index, 'label', event.target.value)} placeholder="Catálogo" className={inputClass} />
                                        </label>
                                        <label>
                                            <span className="text-xs font-medium text-neutral-600 dark:text-neutral-300">Endereço</span>
                                            <input type="url" inputMode="url" value={link.url} onChange={event => updateAdditionalLink(index, 'url', event.target.value)} placeholder="https://..." className={inputClass} />
                                        </label>
                                        <button
                                            type="button"
                                            onClick={() => setData('contact_links', data.contact_links.filter((_, linkIndex) => linkIndex !== index))}
                                            className="mt-auto flex h-11 w-11 items-center justify-center rounded-xl text-red-600 transition hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-500 dark:text-red-400 dark:hover:bg-red-950/30"
                                            aria-label={`Remover link ${index + 1}`}
                                        >
                                            <X className="h-4 w-4" aria-hidden="true" />
                                        </button>
                                        {(errors[`contact_links.${index}.label`] || errors[`contact_links.${index}.url`]) && (
                                            <span role="alert" className="text-xs text-red-600 dark:text-red-400 sm:col-span-3">{errors[`contact_links.${index}.label`] || errors[`contact_links.${index}.url`]}</span>
                                        )}
                                    </div>
                                ))}
                            </div>
                        )}
                    </section>

                    <div className="flex gap-3 flex-wrap">
                        {[['opt_in_whatsapp', t('contacts_page.channel_wa')], ['opt_in_sms', t('contacts_page.channel_sms')], ['opt_in_email', t('common.email')]].map(([key, label]) => (
                            <label key={key} className="flex items-center gap-1.5 text-sm cursor-pointer">
                                <input type="checkbox" checked={data[key]} onChange={e => setData(key, e.target.checked)} className="rounded" />
                                {label}
                            </label>
                        ))}
                    </div>
                    {staticSegments.length > 0 && (
                        <div>
                            <label className="text-xs font-medium text-neutral-500 dark:text-neutral-400">{t('contacts_page.segments')}</label>
                            <div className="mt-1.5 flex flex-wrap gap-2">
                                {staticSegments.map(seg => {
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
                    {recentlySuccessful && <p role="status" className="text-center text-sm font-medium text-green-700 dark:text-green-400">Alterações salvas.</p>}
                    <button type="submit" disabled={processing} className="min-h-11 w-full rounded-xl bg-brand-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 dark:focus:ring-offset-neutral-900">
                        {processing ? t('common.saving') : t('contacts_page.save_changes')}
                    </button>
                </form>

                {/* Conversation timeline */}
                {contact.conversations?.length > 0 && (
                    <div>
                        <h3 className="mb-3 font-medium text-neutral-800 dark:text-neutral-200 flex items-center gap-2">
                            <MessageSquare className="h-4 w-4" /> {t('contacts_page.recent_conversations')}
                        </h3>
                        <div className="space-y-3">
                            {contact.conversations.map(conv => (
                                <div key={conv.id} className="rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900 p-4">
                                    <div className="flex items-center justify-between text-xs text-neutral-400 mb-2">
                                        <span className="capitalize font-medium text-neutral-600 dark:text-neutral-300">{conv.channel_account?.channel ?? t('contacts_page.unknown_channel')}</span>
                                        <span className={`rounded-full px-2 py-0.5 font-medium ${conv.status === 'open' ? 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300' : 'bg-neutral-100 text-neutral-500 dark:bg-neutral-700 dark:text-neutral-400'}`}>{conv.status}</span>
                                    </div>
                                    {conv.messages?.map(msg => (
                                        <div key={msg.id} className={`text-sm p-2 rounded-lg mb-1 max-w-xs ${msg.direction === 'in' ? 'bg-neutral-100 dark:bg-neutral-800 text-neutral-800 dark:text-neutral-200' : 'ml-auto bg-brand-600 text-white'}`}>
                                            {msg.body || t('contacts_page.media_placeholder')}
                                        </div>
                                    ))}
                                </div>
                            ))}
                        </div>
                    </div>
                )}
            </div>
        </ClientLayout>
    );
}
