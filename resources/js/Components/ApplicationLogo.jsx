import { usePage } from '@inertiajs/react';

export default function ApplicationLogo({ className, style, alt }) {
    let logoUrl = null;
    try {
        // eslint-disable-next-line react-hooks/rules-of-hooks
        logoUrl = usePage().props.branding?.logo_url ?? null;
    } catch {
        logoUrl = null;
    }

    if (logoUrl) {
        return (
            <img
                src={logoUrl}
                alt={alt ?? 'App Logo'}
                className={className}
                style={style}
            />
        );
    }

    // CREASY Community: an open C connects people around a shared center.
    return (
        <img
            src="/creasy-community-icon.svg"
            alt={alt ?? 'CREASY CRM Comunidade'}
            className={className}
            style={style}
        />
    );
}
