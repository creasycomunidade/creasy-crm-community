import { Head, Link, router, useForm } from '@inertiajs/react';
import ClientLayout from '@/Layouts/ClientLayout';
import { Bot, ChevronLeft, Clock3, Copy, Globe2, MessageCircle, Pencil, Plus, Save, Trash2, X, Zap } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';

const days = [
    ['monday','Segunda-feira'],['tuesday','Terça-feira'],['wednesday','Quarta-feira'],
    ['thursday','Quinta-feira'],['friday','Sexta-feira'],['saturday','Sábado'],['sunday','Domingo'],
];
const hours = Object.fromEntries(days.map(([key], index) => [key,{ enabled:index < 5,start:'08:00',end:'18:00' }]));
const blank = {
    name:'Atendimento do site',title:'Atendimento',welcome_message:'Olá! Como podemos ajudar?',
    input_placeholder:'Digite sua mensagem...',primary_color:'#7c3aed',position:'bottom_right',
    allowed_domains:[],require_name:false,require_email:false,enabled:true,chatbot_id:'',
    timezone:'America/Sao_Paulo',business_hours:hours,outside_hours_enabled:false,
    outside_hours_message:'No momento estamos fora do horário de atendimento. Deixe sua mensagem e responderemos assim que possível.',
    allowed_user_ids:[],
};
function fromAccount(account){
    const meta=account.meta_json||{},chat=meta.webchat||{},ops=meta.operations||{};
    return {...blank,name:account.display_name,title:chat.title||account.display_name,welcome_message:chat.welcome_message||blank.welcome_message,
        input_placeholder:chat.input_placeholder||blank.input_placeholder,primary_color:chat.primary_color||blank.primary_color,
        position:chat.position||blank.position,allowed_domains:chat.allowed_domains||[],require_name:!!chat.require_name,
        require_email:!!chat.require_email,enabled:account.status==='active',chatbot_id:meta.ai_chatbot_id||'',
        timezone:ops.timezone||blank.timezone,business_hours:{...hours,...(ops.business_hours||{})},
        outside_hours_enabled:!!ops.outside_hours_enabled,outside_hours_message:ops.outside_hours_message||blank.outside_hours_message,
        allowed_user_ids:(ops.allowed_user_ids||[]).map(Number)};
}
function Field({label,error,help,children}){return <label className="grid gap-1.5 text-sm font-medium text-neutral-800 dark:text-neutral-200"><span>{label}</span>{children}{help&&<span className="text-xs font-normal text-neutral-500">{help}</span>}{error&&<span className="text-xs font-normal text-red-600">{error}</span>}</label>}
function Toggle({checked,onChange,label,description}){return <label className="flex cursor-pointer items-start gap-3 rounded-xl border border-neutral-200 p-3 dark:border-neutral-700"><input type="checkbox" checked={checked} onChange={e=>onChange(e.target.checked)} className="mt-1 h-4 w-4 rounded border-neutral-300 text-brand-600 focus:ring-brand-500"/><span><strong className="block text-sm text-neutral-800 dark:text-neutral-100">{label}</strong>{description&&<small className="mt-0.5 block text-xs leading-5 text-neutral-500">{description}</small>}</span></label>}
export default function WebchatChannels({accounts=[],chatbots=[],humanAgents=[]}){
    const [editing,setEditing]=useState(accounts.length?null:'new');
    const selected=editing==='new'?null:accounts.find(a=>a.id===editing);
    const {data,setData,post,put,processing,errors,reset,transform}=useForm(blank);
    const [domains,setDomains]=useState('');
    const open=(account=null)=>{const value=account?fromAccount(account):blank;setData(value);setDomains(value.allowed_domains.join('\n'));setEditing(account?.id||'new')};
    const close=()=>{setEditing(null);reset();setDomains('')};
    const submit=e=>{e.preventDefault();transform(values=>({...values,allowed_domains:domains.split(/\r?\n|,/).map(v=>v.trim()).filter(Boolean),chatbot_id:values.chatbot_id||null}));
        const options={preserveScroll:true,onSuccess:()=>{close();toast.success(selected?'Canal atualizado.':'Canal criado.')}};
        selected?put(route('client.inbox.webchat.update',{channelAccount:selected.id}),options):post(route('client.inbox.webchat.store'),options);
    };
    const copy=async code=>{await navigator.clipboard.writeText(code);toast.success('Código copiado.')};
    const toggleUser=id=>setData('allowed_user_ids',data.allowed_user_ids.includes(id)?data.allowed_user_ids.filter(v=>v!==id):[...data.allowed_user_ids,id]);
    return <ClientLayout title="Chat do site">
        <Head title="Chat do site"/>
        <div className="mx-auto max-w-7xl space-y-6">
            <header className="flex flex-wrap items-start justify-between gap-4">
                <div className="flex items-start gap-3"><Link href={route('client.inbox.setup')} className="mt-0.5 grid h-11 w-11 place-items-center rounded-xl border border-neutral-200 text-neutral-600 hover:bg-neutral-50 dark:border-neutral-700 dark:text-neutral-300" aria-label="Voltar"><ChevronLeft className="h-5 w-5"/></Link><div><h1 className="text-2xl font-bold text-neutral-950 dark:text-white">Chat do site</h1><p className="mt-1 max-w-2xl text-sm text-neutral-500">Atenda visitantes dentro do seu site com equipe humana, horários, respostas automáticas e IA.</p></div></div>
                <div className="flex flex-wrap gap-2">
                    <Link href={route('client.whatsapp.auto-replies.index')} className="inline-flex min-h-11 items-center gap-2 rounded-xl border border-neutral-200 px-4 py-2 text-sm font-semibold text-neutral-700 hover:bg-neutral-50 focus:outline-none focus:ring-2 focus:ring-brand-500 dark:border-neutral-700 dark:text-neutral-200"><Zap className="h-4 w-4"/>Respostas automáticas</Link>
                    <button type="button" onClick={()=>open()} className="inline-flex min-h-11 items-center gap-2 rounded-xl bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500"><Plus className="h-4 w-4"/>Novo canal</button>
                </div>
            </header>

            {accounts.length===0&&editing!=='new'&&<div className="rounded-2xl border border-dashed border-brand-300 bg-brand-50/50 p-10 text-center dark:border-brand-800 dark:bg-brand-950/20"><MessageCircle className="mx-auto h-10 w-10 text-brand-600"/><h2 className="mt-4 font-semibold">Crie seu primeiro chat</h2><p className="mx-auto mt-2 max-w-lg text-sm text-neutral-500">Cada canal possui código, aparência, domínio, equipe e automações próprios.</p><button onClick={()=>open()} className="mt-5 min-h-11 rounded-xl bg-brand-600 px-4 text-sm font-semibold text-white">Criar canal</button></div>}

            {accounts.length>0&&<section className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">{accounts.map(account=><article key={account.id} className="rounded-2xl border border-neutral-200 bg-white p-5 shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
                <div className="flex items-start gap-3"><div className="grid h-11 w-11 place-items-center rounded-xl bg-brand-50 text-brand-600 dark:bg-brand-950/40"><Globe2 className="h-5 w-5"/></div><div className="min-w-0 flex-1"><h2 className="truncate font-semibold">{account.display_name}</h2><span className={'mt-1 inline-flex items-center gap-1 text-xs '+(account.status==='active'?'text-emerald-600':'text-neutral-500')}><i className={'h-2 w-2 rounded-full '+(account.status==='active'?'bg-emerald-500':'bg-neutral-400')}/>{account.status==='active'?'Ativo':'Desativado'}</span></div><button onClick={()=>open(account)} className="grid h-11 w-11 place-items-center rounded-xl text-neutral-500 hover:bg-neutral-100" aria-label={'Editar '+account.display_name}><Pencil className="h-4 w-4"/></button></div>
                <div className="mt-4 rounded-xl bg-neutral-950 p-3 text-xs text-emerald-300"><code className="block truncate">{account.embed_code}</code></div>
                <div className="mt-3 flex gap-2"><button onClick={()=>copy(account.embed_code)} className="inline-flex min-h-11 flex-1 items-center justify-center gap-2 rounded-xl border border-neutral-200 text-sm font-medium hover:bg-neutral-50 dark:border-neutral-700"><Copy className="h-4 w-4"/>Copiar código</button><button onClick={()=>{if(confirm('Remover este canal? As conversas já registradas serão preservadas.'))router.delete(route('client.inbox.webchat.destroy',{channelAccount:account.id}))}} className="grid h-11 w-11 place-items-center rounded-xl border border-red-200 text-red-600 hover:bg-red-50" aria-label="Remover canal"><Trash2 className="h-4 w-4"/></button></div>
            </article>)}</section>}

            {editing&&<form onSubmit={submit} className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
                <div className="space-y-5">
                    <section className="rounded-2xl border border-neutral-200 bg-white p-5 dark:border-neutral-700 dark:bg-neutral-900"><div className="mb-5 flex items-center gap-2"><MessageCircle className="h-5 w-5 text-brand-600"/><h2 className="font-semibold">Identidade e aparência</h2></div><div className="grid gap-4 md:grid-cols-2">
                        <Field label="Nome interno" error={errors.name}><input className="min-h-11 rounded-xl border-neutral-300 dark:border-neutral-700 dark:bg-neutral-950" value={data.name} onChange={e=>setData('name',e.target.value)}/></Field>
                        <Field label="Título exibido" error={errors.title}><input className="min-h-11 rounded-xl border-neutral-300 dark:border-neutral-700 dark:bg-neutral-950" value={data.title} onChange={e=>setData('title',e.target.value)}/></Field>
                        <Field label="Mensagem de boas-vindas" error={errors.welcome_message}><textarea rows="3" className="rounded-xl border-neutral-300 dark:border-neutral-700 dark:bg-neutral-950" value={data.welcome_message} onChange={e=>setData('welcome_message',e.target.value)}/></Field>
                        <Field label="Texto do campo de mensagem" error={errors.input_placeholder}><input className="min-h-11 rounded-xl border-neutral-300 dark:border-neutral-700 dark:bg-neutral-950" value={data.input_placeholder} onChange={e=>setData('input_placeholder',e.target.value)}/></Field>
                        <Field label="Cor principal"><div className="flex gap-2"><input type="color" className="h-11 w-14 rounded-xl border-neutral-300 p-1" value={data.primary_color} onChange={e=>setData('primary_color',e.target.value)}/><input className="min-h-11 flex-1 rounded-xl border-neutral-300 dark:border-neutral-700 dark:bg-neutral-950" value={data.primary_color} onChange={e=>setData('primary_color',e.target.value)}/></div></Field>
                        <Field label="Posição"><select className="min-h-11 rounded-xl border-neutral-300 dark:border-neutral-700 dark:bg-neutral-950" value={data.position} onChange={e=>setData('position',e.target.value)}><option value="bottom_right">Inferior direito</option><option value="bottom_left">Inferior esquerdo</option></select></Field>
                    </div></section>

                    <section className="rounded-2xl border border-neutral-200 bg-white p-5 dark:border-neutral-700 dark:bg-neutral-900"><div className="mb-5 flex items-center gap-2"><Globe2 className="h-5 w-5 text-brand-600"/><h2 className="font-semibold">Sites e identificação</h2></div>
                        <Field label="Domínios permitidos" help="Um por linha. Deixe vazio para permitir qualquer site durante os testes." error={errors.allowed_domains}><textarea rows="4" className="rounded-xl border-neutral-300 dark:border-neutral-700 dark:bg-neutral-950" placeholder={'meusite.com.br\nloja.meusite.com.br'} value={domains} onChange={e=>setDomains(e.target.value)}/></Field>
                        <div className="mt-4 grid gap-3 md:grid-cols-2"><Toggle checked={data.require_name} onChange={v=>setData('require_name',v)} label="Solicitar nome"/><Toggle checked={data.require_email} onChange={v=>setData('require_email',v)} label="Solicitar e-mail"/></div>
                    </section>

                    <section className="rounded-2xl border border-neutral-200 bg-white p-5 dark:border-neutral-700 dark:bg-neutral-900"><div className="mb-5 flex items-center gap-2"><Bot className="h-5 w-5 text-brand-600"/><h2 className="font-semibold">IA e equipe</h2></div>
                        <Field label="Chatbot de IA" help="A IA responde automaticamente até a conversa ser transferida para uma pessoa."><select className="min-h-11 rounded-xl border-neutral-300 dark:border-neutral-700 dark:bg-neutral-950" value={data.chatbot_id} onChange={e=>setData('chatbot_id',e.target.value)}><option value="">Sem IA automática</option>{chatbots.map(bot=><option key={bot.id} value={bot.id}>{bot.name}</option>)}</select></Field>
                        <div className="mt-4"><p className="mb-2 text-sm font-medium">Atendentes com acesso</p><div className="grid gap-2 md:grid-cols-2">{humanAgents.map(user=><Toggle key={user.id} checked={data.allowed_user_ids.includes(user.id)} onChange={()=>toggleUser(user.id)} label={user.name} description={user.email}/>)}</div>{humanAgents.length===0&&<p className="text-sm text-neutral-500">Nenhum atendente ativo cadastrado.</p>}</div>
                    </section>

                    <section className="rounded-2xl border border-neutral-200 bg-white p-5 dark:border-neutral-700 dark:bg-neutral-900"><div className="mb-5 flex items-center gap-2"><Clock3 className="h-5 w-5 text-brand-600"/><h2 className="font-semibold">Horário de funcionamento</h2></div>
                        <Field label="Fuso horário"><input className="min-h-11 rounded-xl border-neutral-300 dark:border-neutral-700 dark:bg-neutral-950" value={data.timezone} onChange={e=>setData('timezone',e.target.value)}/></Field>
                        <div className="mt-4 space-y-2">{days.map(([key,label])=>{const value=data.business_hours[key]||hours[key];return <div key={key} className="grid grid-cols-[1fr_92px_92px] items-center gap-2 rounded-xl border border-neutral-200 p-3 dark:border-neutral-700"><label className="flex items-center gap-2 text-sm"><input type="checkbox" checked={value.enabled} onChange={e=>setData('business_hours',{...data.business_hours,[key]:{...value,enabled:e.target.checked}})}/>{label}</label><input type="time" disabled={!value.enabled} className="min-h-11 rounded-lg border-neutral-300 text-sm dark:border-neutral-700 dark:bg-neutral-950" value={value.start} onChange={e=>setData('business_hours',{...data.business_hours,[key]:{...value,start:e.target.value}})}/><input type="time" disabled={!value.enabled} className="min-h-11 rounded-lg border-neutral-300 text-sm dark:border-neutral-700 dark:bg-neutral-950" value={value.end} onChange={e=>setData('business_hours',{...data.business_hours,[key]:{...value,end:e.target.value}})}/></div>})}</div>
                        <div className="mt-4"><Toggle checked={data.outside_hours_enabled} onChange={v=>setData('outside_hours_enabled',v)} label="Responder fora do horário" description="Envia uma resposta automática e mantém a mensagem na caixa de entrada."/></div>
                        {data.outside_hours_enabled&&<div className="mt-3"><Field label="Mensagem fora do horário"><textarea rows="3" className="rounded-xl border-neutral-300 dark:border-neutral-700 dark:bg-neutral-950" value={data.outside_hours_message} onChange={e=>setData('outside_hours_message',e.target.value)}/></Field></div>}
                    </section>
                </div>

                <aside className="space-y-4 xl:sticky xl:top-5 xl:self-start">
                    <section className="rounded-2xl border border-neutral-200 bg-white p-5 dark:border-neutral-700 dark:bg-neutral-900"><h2 className="font-semibold">Visualização</h2><div className="mt-4 overflow-hidden rounded-2xl border border-neutral-200 bg-neutral-50 shadow-lg">
                        <div className="p-4 text-white" style={{backgroundColor:data.primary_color}}><strong className="block">{data.title||'Atendimento'}</strong><span className="text-xs opacity-90">● Atendimento disponível</span></div>
                        <div className="min-h-56 p-4"><div className="max-w-[85%] rounded-xl rounded-bl-sm border bg-white p-3 text-sm">{data.welcome_message||'Olá! Como podemos ajudar?'}</div></div>
                        <div className="border-t bg-white p-3"><div className="rounded-xl border px-3 py-3 text-xs text-neutral-400">{data.input_placeholder||'Digite sua mensagem...'}</div></div>
                    </div></section>
                    <section className="rounded-2xl border border-neutral-200 bg-white p-5 dark:border-neutral-700 dark:bg-neutral-900"><Toggle checked={data.enabled} onChange={v=>setData('enabled',v)} label="Canal ativo" description="Quando desativado, o widget deixa de carregar nos sites."/><div className="mt-4 flex gap-2"><button type="button" onClick={close} className="inline-flex min-h-11 flex-1 items-center justify-center gap-2 rounded-xl border border-neutral-200 text-sm font-semibold hover:bg-neutral-50 dark:border-neutral-700"><X className="h-4 w-4"/>Cancelar</button><button disabled={processing} className="inline-flex min-h-11 flex-1 items-center justify-center gap-2 rounded-xl bg-brand-600 text-sm font-semibold text-white disabled:opacity-50"><Save className="h-4 w-4"/>{processing?'Salvando...':'Salvar'}</button></div></section>
                </aside>
            </form>}
        </div>
    </ClientLayout>;
}