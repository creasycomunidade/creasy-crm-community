import ReactMarkdown from 'react-markdown';
import remarkGfm from 'remark-gfm';

const components = {
    h1: ({ children }) => <h1 className="mb-6 mt-10 text-3xl font-bold tracking-tight first:mt-0">{children}</h1>,
    h2: ({ children }) => <h2 className="mb-4 mt-8 border-b border-neutral-200 pb-3 text-2xl font-semibold dark:border-neutral-700">{children}</h2>,
    h3: ({ children }) => <h3 className="mb-3 mt-6 text-xl font-semibold">{children}</h3>,
    h4: ({ children }) => <h4 className="mb-2 mt-5 text-lg font-semibold">{children}</h4>,
    p: ({ children }) => <p className="my-4">{children}</p>,
    ul: ({ children }) => <ul className="my-4 list-disc space-y-2 pl-6">{children}</ul>,
    ol: ({ children, start }) => <ol start={start} className="my-4 list-decimal space-y-2 pl-6">{children}</ol>,
    blockquote: ({ children }) => <blockquote className="my-5 border-l-4 border-brand-400 bg-neutral-50 px-5 py-1 text-neutral-600 dark:bg-neutral-900 dark:text-neutral-300">{children}</blockquote>,
    pre: ({ children }) => <pre className="my-5 max-w-full overflow-x-auto rounded-xl border border-neutral-700 bg-neutral-900 p-5 text-sm leading-7 text-neutral-100 [&>code]:bg-transparent [&>code]:p-0 [&>code]:text-inherit">{children}</pre>,
    code: ({ children }) => <code className="rounded bg-neutral-100 px-1.5 py-0.5 font-mono text-[0.9em] text-brand-700 dark:bg-neutral-800 dark:text-brand-300">{children}</code>,
    table: ({ children }) => <div className="my-5 overflow-x-auto rounded-lg border border-neutral-200 dark:border-neutral-700"><table className="w-full border-collapse text-left text-sm">{children}</table></div>,
    th: ({ children }) => <th className="border-b border-neutral-200 bg-neutral-50 px-4 py-3 font-semibold dark:border-neutral-700 dark:bg-neutral-800">{children}</th>,
    td: ({ children }) => <td className="border-b border-neutral-200 px-4 py-3 dark:border-neutral-700">{children}</td>,
    a: ({ href, children }) => <a href={href} target="_blank" rel="noopener noreferrer" className="text-brand-600 underline underline-offset-4 dark:text-brand-400">{children}</a>,
    img: ({ alt }) => <span className="text-neutral-500">[Imagem: {alt || 'sem descrição'}]</span>,
    hr: () => <hr className="my-8 border-neutral-200 dark:border-neutral-700" />,
};

export default function MarkdownDocument({ content }) {
    return <div className="h-full overflow-auto bg-white dark:bg-neutral-950"><article className="mx-auto max-w-4xl break-words px-5 py-7 text-base leading-8 text-neutral-800 sm:px-10 sm:py-10 dark:text-neutral-200">
        {content ? <ReactMarkdown remarkPlugins={[remarkGfm]} components={components} skipHtml>{content}</ReactMarkdown> : <p className="text-neutral-500">Este arquivo está vazio.</p>}
    </article></div>;
}
