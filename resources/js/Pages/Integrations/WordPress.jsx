import { useState } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import ClientLayout from '@/Layouts/ClientLayout';

export default function WordPress({ connection, items = [], loadError }) {
    const [editing, setEditing] = useState(null);
    const form = useForm({ type: 'posts', id: null, title: '', content: '', status: 'draft' });
    const edit = item => { setEditing(item.id); form.setData({ type: item.type === 'page' ? 'pages' : 'posts', id: item.id, title: item.title, content: item.content, status: item.status }); };
    const fresh = type => { setEditing('new'); form.setData({ type, id: null, title: '', content: '', status: 'draft' }); };
    const submit = e => { e.preventDefault(); form.post(route('client.integrations.wordpress.save', connection.uuid), { preserveScroll: true, onSuccess: () => setEditing(null) }); };

    return <ClientLayout title={`WordPress — ${connection.name}`}>
        <Head title={`WordPress — ${connection.name}`} />
        <div className="space-y-5 p-6">
            <div><Link href={route('client.integrations.index')} className="text-sm text-violet-600">← Integrações</Link><h1 className="mt-2 text-2xl font-semibold">Conteúdo do WordPress</h1><p className="text-sm text-gray-500">Crie e edite posts e páginas com a API oficial do WordPress.</p></div>
            {loadError && <div className="rounded-lg bg-red-50 p-3 text-sm text-red-700">{loadError}</div>}
            <div className="flex gap-2"><button className="rounded-lg bg-violet-600 px-4 py-2 text-white" onClick={() => fresh('posts')}>Novo post</button><button className="rounded-lg border px-4 py-2" onClick={() => fresh('pages')}>Nova página</button></div>
            <div className="rounded-xl border bg-white"><div className="divide-y">{items.map(item => <button key={`${item.type}-${item.id}`} onClick={() => edit(item)} className="flex w-full items-center justify-between p-4 text-left"><span><b>{item.title || '(Sem título)'}</b><small className="block text-gray-500">{item.type === 'page' ? 'Página' : 'Post'} · {item.status}</small></span><span className="text-sm text-violet-600">Editar</span></button>)}</div></div>
            {editing && <form onSubmit={submit} className="rounded-xl border bg-white p-5"><h2 className="mb-4 font-semibold">{form.data.id ? 'Editar conteúdo' : 'Novo conteúdo'}</h2><div className="grid gap-3"><input className="rounded-lg border p-2" value={form.data.title} onChange={e => form.setData('title', e.target.value)} placeholder="Título" required /><textarea className="min-h-72 rounded-lg border p-3 font-mono text-sm" value={form.data.content} onChange={e => form.setData('content', e.target.value)} placeholder="Conteúdo HTML ou texto" /><select className="rounded-lg border p-2" value={form.data.status} onChange={e => form.setData('status', e.target.value)}><option value="draft">Rascunho</option><option value="pending">Revisão pendente</option><option value="private">Privado</option><option value="publish">Publicado</option></select><div className="flex gap-2"><button className="rounded-lg bg-violet-600 px-4 py-2 text-white">Salvar no WordPress</button><button type="button" className="rounded-lg border px-4 py-2" onClick={() => setEditing(null)}>Cancelar</button></div></div></form>}
        </div>
    </ClientLayout>;
}
