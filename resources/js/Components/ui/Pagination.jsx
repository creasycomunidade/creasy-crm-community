import { Link } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { ChevronLeft, ChevronRight, ChevronsLeft, ChevronsRight } from 'lucide-react';

/**
 * Renders Laravel-style pagination links with proper arrows and UX.
 * Pass either (links + meta) or the full paginator object as `data` (e.g. data={logs}).
 * @param {{ links?: array, first_page_url?: string, last_page_url?: string, current_page?: number, last_page?: number, total?: number, from?: number, to?: number, className?: string, data?: object }} props
 */
export default function Pagination({
    links = [],
    first_page_url,
    last_page_url,
    current_page,
    last_page,
    total,
    from,
    to,
    className = '',
    data,
}) {
    const { t } = useTranslation();
    const meta = data
        ? {
              links: data.links ?? [],
              first_page_url: data.first_page_url,
              last_page_url: data.last_page_url,
              current_page: data.current_page,
              last_page: data.last_page,
              total: data.total,
              from: data.from,
              to: data.to,
          }
        : {
              links,
              first_page_url,
              last_page_url,
              current_page,
              last_page,
              total,
              from,
              to,
          };

    links = meta.links ?? [];
    if (!links.length) return null;

    const prevLink = links[0];
    const nextLink = links[links.length - 1];
    const pageLinks = links.slice(1, -1);
    const isPrevDisabled = !prevLink?.url;
    const isNextDisabled = !nextLink?.url;
    const cur = meta.current_page ?? 1;
    const last = meta.last_page ?? 1;

    const renderLink = (link, content, ariaLabel, isDisabled) => {
        const baseClass =
            'inline-flex items-center justify-center min-w-[2.25rem] h-9 px-2.5 text-sm font-medium rounded-soft transition-colors focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 dark:focus:ring-offset-neutral-900';
        const activeClass =
            'bg-brand-500 text-white hover:bg-brand-600 dark:bg-brand-600 dark:hover:bg-brand-500 shadow-sm';
        const inactiveClass =
            'text-neutral-600 dark:text-neutral-400 bg-white dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 hover:bg-neutral-50 hover:border-neutral-300 dark:hover:bg-neutral-700 dark:hover:border-neutral-600';
        const disabledClass =
            'opacity-50 pointer-events-none cursor-not-allowed text-neutral-400 dark:text-neutral-500 border border-neutral-100 dark:border-neutral-800';

        if (isDisabled || !link?.url) {
            return (
                <span
                    aria-disabled="true"
                    aria-label={ariaLabel}
                    className={`${baseClass} ${disabledClass}`}
                >
                    {content}
                </span>
            );
        }

        return (
            <Link
                href={link.url}
                aria-label={ariaLabel}
                className={`${baseClass} ${link.active ? activeClass : inactiveClass}`}
                preserveState
            >
                {content}
            </Link>
        );
    };

    const labelToDisplay = (label) => {
        if (!label) return '';
        return String(label)
            .replace(/^\s*&laquo;\s*/i, '')
            .replace(/\s*&raquo;\s*$/i, '')
            .trim();
    };

    const btnClass =
        'inline-flex items-center justify-center min-w-[2.25rem] h-9 px-2.5 text-sm font-medium rounded-soft text-neutral-600 dark:text-neutral-400 bg-white dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 hover:bg-neutral-50 dark:hover:bg-neutral-700 transition-colors focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 dark:focus:ring-offset-neutral-900';

    return (
        <nav
            role="navigation"
            aria-label={t('pagination.label')}
            className={`flex flex-wrap items-center justify-center gap-1.5 py-4 border-t border-neutral-100 dark:border-neutral-800 ${className}`}
        >
            {/* First page */}
            {last > 1 && cur > 1 && meta.first_page_url && (
                <Link href={meta.first_page_url} aria-label={t('pagination.first_page')} className={btnClass} preserveState>
                    <ChevronsLeft className="w-4 h-4 shrink-0" aria-hidden />
                </Link>
            )}

            {/* Previous */}
            {renderLink(
                prevLink,
                <>
                    <ChevronLeft className="w-4 h-4 shrink-0" aria-hidden />
                    <span className="sr-only sm:not-sr-only sm:ml-1">{t('pagination.previous')}</span>
                </>,
                t('pagination.previous_page'),
                isPrevDisabled
            )}

            {/* Page numbers */}
            <div className="flex items-center gap-1">
                {pageLinks.map((link, i) => {
                    const label = labelToDisplay(link.label);
                    const isNum = /^\d+$/.test(label);
                    const content = isNum ? label : link.label;
                    return (
                        <span key={i}>
                            {renderLink(link, content, isNum ? t('pagination.page_num', { num: label }) : t('pagination.more_pages'), false)}
                        </span>
                    );
                })}
            </div>

            {/* Next */}
            {renderLink(
                nextLink,
                <>
                    <span className="sr-only sm:not-sr-only sm:mr-1">{t('pagination.next')}</span>
                    <ChevronRight className="w-4 h-4 shrink-0" aria-hidden />
                </>,
                t('pagination.next_page'),
                isNextDisabled
            )}

            {/* Last page */}
            {last > 1 && cur < last && meta.last_page_url && (
                <Link href={meta.last_page_url} aria-label={t('pagination.last_page')} className={btnClass} preserveState>
                    <ChevronsRight className="w-4 h-4 shrink-0" aria-hidden />
                </Link>
            )}

            {/* Summary */}
            {(meta.current_page != null || meta.total != null) && (
                <div className="w-full sm:w-auto text-center sm:ml-3 mt-2 sm:mt-0 text-xs text-neutral-500 dark:text-neutral-400">
                    {meta.current_page != null && meta.last_page != null && (
                        <span>
                            {t('pagination.page_of', { current: meta.current_page, last: meta.last_page })}
                        </span>
                    )}
                    {meta.from != null && meta.to != null && meta.total != null && (
                        <span className="ml-2">
                            {t('pagination.showing', { from: meta.from, to: meta.to, total: meta.total })}
                        </span>
                    )}
                </div>
            )}
        </nav>
    );
}
