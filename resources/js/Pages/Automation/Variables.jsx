import { useState } from 'react';
import { useReactFlow } from '@xyflow/react';

const GROUPS = {
    'Contato': ['contact.id','contact.name','contact.first_name','contact.last_name','contact.email','contact.phone','contact.country','contact.language','contact.source','contact.opt_in_whatsapp','contact.opt_in_sms','contact.opt_in_email'],
    'Mensagem e conversa': ['message.body','message.channel','context.conversation_id','context.message_id'],
    'Redes sociais': ['context.trigger_platform','context.trigger_event','context.trigger_account','context.comment_text','context.comment_id','context.post_id','context.author_id','context.review_text','context.review_rating','context.review_id','context.post_content','context.post_url','context.post_error','context.social_response_id','context.social_post_id'],
    'Pedidos e carrinho': ['context.order_number','context.order_total','context.order_currency','context.tracking_url','context.store_name','context.cart_total','context.recovery_url','context.order_found','context.order.id','context.order.number','context.order.status','context.order.total','context.order.currency','context.order.tracking_url'],
    'CRM, tarefas e prospecção': ['context.deal_id','context.task_id','context.lead_found','context.lead.id','context.lead.name','context.lead.email','context.lead.city','context.lead.category'],
    'IA, agenda e integrações': ['context.last_ai_reply','context.appointment_link','context.meet_url','context.memory_id','context.service_order_found','context.service_order.number','context.service_order.status','context.service_order.total','context.doc_url','context.webhook_status','context.webhook_body','context.ads.count'],
};
export default function AutomationVariables() {
    const [search,setSearch]=useState('');
    const [copied,setCopied]=useState('');
    const { getNodes }=useReactFlow();
    const custom=[...new Set(getNodes().map(n=>n.data?.variable).filter(Boolean))].map(v=>`context.${v}`);
    const groups={...GROUPS, 'Variáveis deste fluxo': custom};
    return <details className="rounded-lg border border-violet-200 bg-violet-50/50 p-3 text-xs">
        <summary className="cursor-pointer font-semibold text-violet-900">Variáveis disponíveis</summary>
        <p className="my-2 leading-relaxed text-slate-600">Clique para copiar e cole no campo desejado. Os dados dependem do gatilho e dos nós anteriores; valores ausentes ficam vazios.</p>
        <input aria-label="Buscar variável" value={search} onChange={e=>setSearch(e.target.value)} placeholder="Buscar variável…" className="mb-2 w-full rounded border px-2 py-2" />
        <div className="max-h-72 overflow-y-auto space-y-3">
            {Object.entries(groups).map(([group,vars])=>{
                const items=vars.filter(v=>`${v} ${group}`.toLowerCase().includes(search.toLowerCase()));
                return items.length>0 && <div key={group}><p className="mb-1 font-semibold text-slate-700">{group}</p>{items.map(v=><button type="button" key={v} onClick={async()=>{try {await navigator.clipboard.writeText(`{{${v}}}`);setCopied(v);} catch {setCopied('Selecione o texto para copiar.');}}} className="block min-h-8 w-full break-all rounded px-1 py-1 text-left font-mono text-[11px] text-violet-700 hover:bg-violet-100">{`{{${v}}}`}</button>)}</div>;
            })}
        </div>
        <p aria-live="polite" className="mt-2 text-slate-500">{copied ? `Copiado: ${copied}` : 'Campos personalizados: {{contact.custom_fields.nome}}. Variáveis criadas: {{context.nome}}.'}</p>
    </details>;
}
