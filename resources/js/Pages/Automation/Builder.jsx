import extendedNodes from './nodeCatalog.json';
import AutomationVariables from './Variables';
import { Head, router, usePage } from '@inertiajs/react';
import ClientLayout from '@/Layouts/ClientLayout';
import { useCallback, useContext, useState, createContext } from 'react';
import {
    ArrowLeft, Save, Play, Pause, Copy, Check, RefreshCw,
    X, Zap, Mail, Phone, Clock, GitBranch,
    Tag, Scissors, UserCog, Megaphone, Bot, Webhook,
    Plus, Trash2, Settings, UserRound, Link2, MessageCircle, FileText,
    ShoppingBag, PackageCheck, XCircle, ShoppingCart, UserPlus,
    Image, Layers, MousePointerClick, List, HelpCircle, Workflow, Sparkles,
    UserCheck, ExternalLink, MapPin, BarChart3, CalendarClock, Video,
    ClipboardList, ClipboardCheck, Store, Sheet, LayoutTemplate, AlertTriangle, GripVertical,
    FlaskConical, Loader2, CheckCircle2, MinusCircle, AlertCircle,
} from 'lucide-react';
import { ChannelBrandIcon } from '@/Components/BrandIcons';
import MediaUpload from '@/Components/MediaUpload';
import { Link } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import axios from 'axios';
import {
    ReactFlow,
    ReactFlowProvider,
    addEdge,
    Background,
    Controls,
    MiniMap,
    useNodesState,
    useEdgesState,
    useReactFlow,
    Panel,
    Handle,
    Position,
    MarkerType,
} from '@xyflow/react';
import '@xyflow/react/dist/style.css';

/* ─── Constants ─────────────────────────────────────────────── */

const TRIGGER_TYPES = [
    { value: 'contact.created',   labelKey: 'automation.trigger_contact_created',   Icon: UserRound      },
    { value: 'message.received',  labelKey: 'automation.trigger_message_received',  Icon: MessageCircle  },
    { value: 'webhook.received',  labelKey: 'automation.trigger_webhook_received',  Icon: Link2          },
];

// Categories rendered (in order) in the node palette — mirrors the product node list.
const CATEGORY_ORDER = ['send', 'social', 'listen', 'logic', 'ai', 'contact', 'crm', 'engage', 'commerce', 'integrations'];

const NODE_DEFS = {
    whatsapp_catalog:    { labelKey: 'automation.node_whatsapp_catalog',    color: '#16a34a', bg: '#f0fdf4', icon: ShoppingBag,       category: 'commerce' },
    ...Object.fromEntries(Object.entries(extendedNodes).map(([key,def])=>[key,{...def,labelKey:def.label,color:'#7c3aed',bg:'#f5f3ff',icon:Workflow}])),
    // ── SEND ──────────────────────────────────────────────────────────────
    send_whatsapp:       { labelKey: 'automation.node_send_whatsapp',       color: '#25D366', bg: '#f0fdf4', icon: 'whatsapp',        category: 'send' },
    send_template:       { labelKey: 'automation.node_send_template',       color: '#16a34a', bg: '#f0fdf4', icon: LayoutTemplate,    category: 'send' },
    send_media:          { labelKey: 'automation.node_send_media',          color: '#0d9488', bg: '#f0fdfa', icon: Image,             category: 'send' },
    send_sequence:       { labelKey: 'automation.node_send_sequence',       color: '#0891b2', bg: '#ecfeff', icon: Layers,            category: 'send' },
    quick_replies:       { labelKey: 'automation.node_quick_replies',       color: '#7c3aed', bg: '#faf5ff', icon: MousePointerClick, category: 'send' },
    list_message:        { labelKey: 'automation.node_list_message',        color: '#6d28d9', bg: '#f5f3ff', icon: List,              category: 'send' },
    send_email:          { labelKey: 'automation.node_send_email',          color: '#0ea5e9', bg: '#f0f9ff', icon: Mail,              category: 'send' },
    // ── LISTEN ────────────────────────────────────────────────────────────
    ask_question:        { labelKey: 'automation.node_ask_question',        color: '#ea580c', bg: '#fff7ed', icon: HelpCircle,        category: 'listen' },
    // ── LOGIC ─────────────────────────────────────────────────────────────
    condition:           { labelKey: 'automation.node_condition',           color: '#8b5cf6', bg: '#f5f3ff', icon: GitBranch,         category: 'logic' },
    wait:                { labelKey: 'automation.node_wait',                color: '#f59e0b', bg: '#fffbeb', icon: Clock,             category: 'logic' },
    webhook:             { labelKey: 'automation.node_webhook',             color: '#64748b', bg: '#f8fafc', icon: Webhook,           category: 'logic' },
    run_subflow:         { labelKey: 'automation.node_run_subflow',         color: '#475569', bg: '#f8fafc', icon: Workflow,          category: 'logic' },
    // ── AI ────────────────────────────────────────────────────────────────
    ai_reply:            { labelKey: 'automation.node_ai_reply',            color: '#7c3aed', bg: '#faf5ff', icon: Sparkles,          category: 'ai' },
    // ── CONTACT ───────────────────────────────────────────────────────────
    add_tag:             { labelKey: 'automation.node_add_tag',             color: '#10b981', bg: '#ecfdf5', icon: Tag,               category: 'contact' },
    remove_tag:          { labelKey: 'automation.node_remove_tag',          color: '#f43f5e', bg: '#fff1f2', icon: Scissors,          category: 'contact' },
    update_contact:      { labelKey: 'automation.node_update_contact',      color: '#0ea5e9', bg: '#f0f9ff', icon: UserCog,           category: 'contact' },
    assign_agent:        { labelKey: 'automation.node_assign_agent',        color: '#0284c7', bg: '#f0f9ff', icon: UserCheck,         category: 'contact' },
    // ── ENGAGE ────────────────────────────────────────────────────────────
    cta_button:          { labelKey: 'automation.node_cta_button',          color: '#e11d48', bg: '#fff1f2', icon: ExternalLink,      category: 'engage' },
    send_location:       { labelKey: 'automation.node_send_location',       color: '#dc2626', bg: '#fef2f2', icon: MapPin,            category: 'engage' },
    send_poll:           { labelKey: 'automation.node_send_poll',           color: '#9333ea', bg: '#faf5ff', icon: BarChart3,         category: 'engage' },
    run_chatbot:         { labelKey: 'automation.node_run_chatbot',         color: '#7c3aed', bg: '#faf5ff', icon: Bot,               category: 'engage' },
    book_appointment:    { labelKey: 'automation.node_book_appointment',    color: '#2563eb', bg: '#eff6ff', icon: CalendarClock,     category: 'engage' },
    google_meet:         { labelKey: 'automation.node_google_meet',         color: '#16a34a', bg: '#f0fdf4', icon: Video,             category: 'engage' },
    whatsapp_form:       { labelKey: 'automation.node_whatsapp_form',       color: '#0891b2', bg: '#ecfeff', icon: ClipboardList,     category: 'engage' },
    // ── COMMERCE ──────────────────────────────────────────────────────────
    // ── INTEGRATIONS ──────────────────────────────────────────────────────
    google_sheets:       { labelKey: 'automation.node_google_sheets',       color: '#0f9d58', bg: '#f0fdf4', icon: Sheet,             category: 'integrations' },
    google_docs:         { labelKey: 'automation.node_google_docs',         color: '#4285f4', bg: '#eff6ff', icon: FileText,          category: 'integrations' },
    google_forms:        { labelKey: 'automation.node_google_forms',        color: '#7248b9', bg: '#f5f3ff', icon: ClipboardCheck,    category: 'integrations' },
};

const CONDITION_FIELDS = [
    { value: 'contact.name',      labelKey: 'automation.cond_field_contact_name' },
    { value: 'contact.email',     labelKey: 'automation.cond_field_contact_email' },
    { value: 'contact.phone',     labelKey: 'automation.cond_field_contact_phone' },
    { value: 'contact.tag',       labelKey: 'automation.cond_field_contact_tag' },
    { value: 'message.body',      labelKey: 'automation.cond_field_message_body' },
    { value: 'context.key',       labelKey: 'automation.cond_field_context_key' },
];

const CONDITION_OPERATORS = [
    { value: 'equals',       labelKey: 'automation.op_equals' },
    { value: 'not_equals',   labelKey: 'automation.op_not_equals' },
    { value: 'contains',     labelKey: 'automation.op_contains' },
    { value: 'not_contains', labelKey: 'automation.op_not_contains' },
    { value: 'exists',       labelKey: 'automation.op_exists' },
    { value: 'not_exists',   labelKey: 'automation.op_not_exists' },
];

const UPDATE_FIELDS = [
    { value: 'name',   labelKey: 'common.name' },
    { value: 'email',  labelKey: 'common.email' },
    { value: 'phone',  labelKey: 'automation.update_field_phone' },
    { value: 'notes',  labelKey: 'automation.update_field_notes' },
];

/* ─── Resources (builder reference data from the controller) ───── */
function useResources() {
    const { props } = usePage();
    return props.resources ?? {};
}

/* ─── Node icon helper ───────────────────────────────────────── */
function NodeIcon({ nodeType, size = 14 }) {
    const def = NODE_DEFS[nodeType];
    if (!def) return <Settings size={size} />;
    if (def.icon === 'whatsapp' || def.icon === 'sms' || def.icon === 'email') {
        return <ChannelBrandIcon channel={def.icon} className={`h-[${size}px] w-[${size}px] shrink-0`} />;
    }
    const Icon = def.icon;
    return <Icon size={size} />;
}

/* Lets custom nodes reach the builder's configure/delete handlers (context crosses the ReactFlow boundary). */
const NodeActionsContext = createContext({ onConfigure: () => {}, onDelete: () => {} });

/* ─── Custom Nodes ───────────────────────────────────────────── */
function BaseNode({ id, data, selected }) {
    const { t } = useTranslation();
    const { onConfigure, onDelete } = useContext(NodeActionsContext);
    const { nodeType, label, configured } = data;
    const def = NODE_DEFS[nodeType];
    const defLabel = def ? t(def.labelKey) : nodeType;
    const defColor = def?.color ?? '#6b7280';
    const isCondition = ['condition','random_split'].includes(nodeType);

    const hasLabel = label && label !== defLabel;
    const summary = hasLabel ? label : (configured ? summarizeConfig(data, t) : '');

    const actionBtnStyle = {
        display: 'flex', alignItems: 'center', justifyContent: 'center',
        width: 22, height: 22, padding: 0, borderRadius: 6, border: 'none',
        background: 'transparent', color: '#9ca3af', cursor: 'pointer',
        transition: 'background 0.12s, color 0.12s',
    };

    return (
        <div
            style={{
                background: '#fff',
                border: `1px solid ${selected ? defColor : '#e5e7eb'}`,
                borderRadius: 12,
                minWidth: 210,
                boxShadow: selected
                    ? `0 0 0 3px ${defColor}1f, 0 8px 20px rgba(0,0,0,0.08)`
                    : '0 1px 3px rgba(0,0,0,0.06)',
                transition: 'box-shadow 0.15s, border-color 0.15s',
                overflow: 'hidden',
            }}
        >
            <Handle type="target" position={Position.Top} style={{ background: '#fff', width: 9, height: 9, border: `2px solid ${defColor}` }} />

            {/* Row: icon chip · text · actions */}
            <div style={{ display: 'flex', alignItems: 'center', gap: 9, padding: '9px 10px' }}>
                <span style={{
                    display: 'flex', alignItems: 'center', justifyContent: 'center',
                    width: 28, height: 28, borderRadius: 8, flexShrink: 0,
                    background: def?.bg ?? '#f3f4f6', color: defColor,
                }}>
                    <NodeIcon nodeType={nodeType} size={15} />
                </span>

                <div style={{ minWidth: 0, flex: 1 }}>
                    <div style={{ fontSize: 12, fontWeight: 600, color: '#111827', lineHeight: 1.3, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                        {defLabel}
                    </div>
                    <div style={{
                        fontSize: 10.5, lineHeight: 1.3, marginTop: 1,
                        color: summary ? '#6b7280' : '#9ca3af',
                        fontStyle: summary ? 'normal' : 'italic',
                        overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap',
                    }}>
                        {summary || t('automation.click_to_configure')}
                    </div>
                </div>

                <div className="nodrag" style={{ display: 'flex', alignItems: 'center', gap: 1, flexShrink: 0 }}>
                    <button
                        className="nodrag"
                        title={t('common.settings')}
                        onClick={(e) => { e.stopPropagation(); onConfigure(id); }}
                        style={actionBtnStyle}
                        onMouseEnter={e => { e.currentTarget.style.background = '#f3f4f6'; e.currentTarget.style.color = '#4b5563'; }}
                        onMouseLeave={e => { e.currentTarget.style.background = 'transparent'; e.currentTarget.style.color = '#9ca3af'; }}
                    >
                        <Settings size={13} />
                    </button>
                    <button
                        className="nodrag"
                        title={t('common.delete')}
                        onClick={(e) => { e.stopPropagation(); onDelete(id); }}
                        style={actionBtnStyle}
                        onMouseEnter={e => { e.currentTarget.style.background = '#fef2f2'; e.currentTarget.style.color = '#ef4444'; }}
                        onMouseLeave={e => { e.currentTarget.style.background = 'transparent'; e.currentTarget.style.color = '#9ca3af'; }}
                    >
                        <Trash2 size={13} />
                    </button>
                </div>
            </div>

            {/* Handles */}
            {isCondition ? (
                <>
                    <div style={{ display: 'flex', justifyContent: 'space-between', padding: '0 12px 7px', fontSize: 9, fontWeight: 600 }}>
                        <span style={{ color: '#10b981' }}>✓ {t('common.yes')}</span>
                        <span style={{ color: '#ef4444' }}>✗ {t('common.no')}</span>
                    </div>
                    <Handle type="source" id="true"  position={Position.Bottom} style={{ left: '30%', background: '#fff', width: 9, height: 9, border: '2px solid #10b981' }} />
                    <Handle type="source" id="false" position={Position.Bottom} style={{ left: '70%', background: '#fff', width: 9, height: 9, border: '2px solid #ef4444' }} />
                </>
            ) : (
                <Handle type="source" position={Position.Bottom} style={{ background: '#fff', width: 9, height: 9, border: `2px solid ${defColor}` }} />
            )}
        </div>
    );
}

function TriggerNode({ data, selected }) {
    const { t } = useTranslation();
    const { social_triggers = [] } = useResources();
    const trigger = [...TRIGGER_TYPES, ...social_triggers].find(tr => tr.value === data.triggerType);
    const accent = '#6366f1';
    return (
        <div style={{
            background: '#fff',
            border: `1px solid ${selected ? accent : '#e5e7eb'}`,
            borderRadius: 12,
            minWidth: 210,
            boxShadow: selected ? `0 0 0 3px ${accent}1f, 0 8px 20px rgba(0,0,0,0.08)` : '0 1px 3px rgba(0,0,0,0.06)',
            transition: 'box-shadow 0.15s, border-color 0.15s',
            overflow: 'hidden',
        }}>
            <div style={{ display: 'flex', alignItems: 'center', gap: 9, padding: '9px 10px' }}>
                <span style={{
                    display: 'flex', alignItems: 'center', justifyContent: 'center',
                    width: 28, height: 28, borderRadius: 8, flexShrink: 0,
                    background: '#fffbeb', color: '#f59e0b',
                }}>
                    <Zap size={15} />
                </span>
                <div style={{ minWidth: 0, flex: 1 }}>
                    <div style={{ fontSize: 9, fontWeight: 700, color: '#9ca3af', letterSpacing: '0.07em', textTransform: 'uppercase', lineHeight: 1.3 }}>{t('automation.trigger')}</div>
                    <div style={{ fontSize: 12, fontWeight: 600, color: trigger ? '#111827' : '#9ca3af', display: 'flex', alignItems: 'center', gap: 5, marginTop: 1, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                        {trigger?.Icon && <trigger.Icon size={12} style={{ color: accent, flexShrink: 0 }} />}
                        <span style={{ overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{trigger ? (trigger.label ?? t(trigger.labelKey)) : t('automation.select_trigger')}</span>
                    </div>
                </div>
            </div>
            <Handle type="source" position={Position.Bottom} style={{ background: '#fff', width: 9, height: 9, border: `2px solid ${accent}` }} />
        </div>
    );
}

function clip(str, n = 40) {
    if (!str) return '';
    return str.length > n ? str.slice(0, n) + '…' : str;
}

function summarizeConfig(data, t) {
    const { nodeType } = data;
    switch (nodeType) {
        case 'wait': return data.amount ? `${data.amount} ${data.unit ?? 'minutes'}` : '';
        case 'condition': return data.field ? `${data.field} ${data.operator ?? '='} ${data.value ?? ''}` : '';
        case 'add_tag':
        case 'remove_tag': return data.tag ?? '';
        case 'send_whatsapp':
        case 'send_sms': return clip(data.body);
        case 'send_email': return data.subject ?? '';
        case 'update_contact': return data.field ? `${data.field} = ${data.value ?? ''}` : '';
        case 'add_to_campaign': return data.campaign_id ? t('automation.campaign_ref', { id: data.campaign_id }) : '';
        case 'ai_reply':
        case 'run_chatbot': return clip(data.prompt || data.chatbot_id || '');
        case 'webhook': return data.url ? `${data.method ?? 'POST'} ${clip(data.url, 28)}` : '';
        case 'send_template': return data.template_name ?? '';
        case 'send_media': return data.link ? `${data.media_type ?? 'image'} · ${clip(data.link, 24)}` : (data.media_type ?? '');
        case 'send_sequence': return Array.isArray(data.steps) && data.steps.length ? t('automation.seq_steps', { count: data.steps.length }) : '';
        case 'quick_replies': return clip(data.body, 30);
        case 'list_message': return clip(data.body, 30);
        case 'ask_question': return data.question ? `${clip(data.question, 28)} → {{${data.variable || 'answer'}}}` : '';
        case 'run_subflow': return data.subflow_name ?? data.automation_uuid ?? '';
        case 'assign_agent': return data.agent_name ?? (data.user_id ? `#${data.user_id}` : t('automation.assign_unassigned'));
        case 'cta_button': return data.display_text ? `${data.display_text} · ${clip(data.url, 22)}` : clip(data.url, 28);
        case 'send_location': return data.name || (data.latitude ? `${data.latitude}, ${data.longitude}` : '');
        case 'send_poll': return clip(data.question, 30);
        case 'book_appointment':
        case 'google_meet': return data.start ? `${clip(data.summary, 18)} · ${data.start}` : clip(data.summary, 24);
        case 'whatsapp_form': return data.flow_id ? `flow ${data.flow_id}` : '';
        case 'whatsapp_catalog': return clip(data.body, 30);
        case 'woocommerce_product':
        case 'shopify_product': return data.product_id ? `#${data.product_id}` : '';
        case 'google_sheets': return data.spreadsheet_id ? `${data.mode ?? 'append'} · ${clip(data.range, 18)}` : '';
        case 'google_docs': return clip(data.title, 28);
        case 'google_forms': return data.form_id ? `${data.mode === 'read_response' ? 'read' : 'share'} · ${clip(data.form_id, 18)}` : '';
        default: return '';
    }
}

const nodeTypes = {
    automationNode: BaseNode,
    triggerNode: TriggerNode,
};

/* ─── Config Panel ───────────────────────────────────────────── */
const FIELD_COMPONENTS = {
    send_whatsapp: WhatsAppFields,
    send_email: EmailFields,
    send_template: TemplateFields,
    send_media: MediaFields,
    send_sequence: SequenceFields,
    quick_replies: QuickRepliesFields,
    list_message: ListMessageFields,
    ask_question: AskQuestionFields,
    wait: WaitFields,
    condition: ConditionFields,
    webhook: WebhookFields,
    run_subflow: SubflowFields,
    ai_reply: AIReplyFields,
    add_tag: TagFields,
    remove_tag: TagFields,
    update_contact: UpdateContactFields,
    assign_agent: AssignAgentFields,
    cta_button: CtaButtonFields,
    send_location: LocationFields,
    send_poll: PollFields,
    run_chatbot: RunChatbotFields,
    book_appointment: BookAppointmentFields,
    google_meet: GoogleMeetFields,
    whatsapp_form: WhatsappFormFields,
    google_sheets: GoogleSheetsFields,
    google_docs: GoogleDocsFields,
    google_forms: GoogleFormsFields,
};

function ConfigPanel({ node, onClose, onChange }) {
    const { t } = useTranslation();
    if (!node) return null;
    const { nodeType } = node.data;
    const def = NODE_DEFS[nodeType];
    const defLabel = def ? t(def.labelKey) : nodeType;
    const d = node.data;
    const set = (key, val) => onChange(node.id, { [key]: val, configured: true });
    const Fields = FIELD_COMPONENTS[nodeType] ?? (extendedNodes[nodeType] ? ExtendedFields : null);

    return (
        <div style={{
            position: 'absolute', top: 0, right: 0, bottom: 0, width: 320,
            background: '#fff', borderLeft: '1px solid #e5e7eb',
            boxShadow: '-4px 0 24px rgba(0,0,0,0.08)',
            zIndex: 10, display: 'flex', flexDirection: 'column',
            borderRadius: '0 0 12px 0',
        }}>
            {/* Header */}
            <div style={{
                background: def?.color ?? '#6366f1', padding: '14px 16px',
                display: 'flex', alignItems: 'center', justifyContent: 'space-between',
            }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                    <span style={{ color: '#fff' }}><NodeIcon nodeType={nodeType} size={15} /></span>
                    <span style={{ color: '#fff', fontWeight: 700, fontSize: 13 }}>{defLabel}</span>
                </div>
                <button onClick={onClose} style={{ color: '#ffffff99', background: 'none', border: 'none', cursor: 'pointer', display: 'flex' }}>
                    <X size={16} />
                </button>
            </div>

            {/* Fields */}
            <div style={{ flex: 1, overflowY: 'auto', padding: 16, paddingBottom: 64 }} className="space-y-4">
                {/* Label */}
                <Field label={t('automation.node_label_optional')}>
                    <input className={inputCls} value={d.label ?? ''} onChange={e => set('label', e.target.value)} placeholder={defLabel} />
                </Field>

                {/* Per-type fields */}
                {Fields && <Fields d={d} set={set} />}

                <AutomationVariables />
            </div>
        </div>
    );
}

const inputCls = "w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-transparent transition";
const textareaCls = "w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-transparent transition resize-none";
const selectCls = "w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-transparent transition";
const labelCls = "block text-xs font-semibold text-gray-600 mb-1";

function Field({ label, children }) {
    return <div><label className={labelCls}>{label}</label>{children}</div>;
}

/* ─── Trigger Config Panel ───────────────────────────────────────
   Edits the automation's trigger directly from the trigger node on the canvas.
   trigger_type / trigger_config remain the saved source of truth. */
export function TriggerConfigPanel({ automation, onTypeChange, onConfigChange, webhookUrl, copied, onCopy, onGenerateToken, generatingToken, onClose }) {
    const { t } = useTranslation();
    const triggerType = automation.trigger_type ?? '';
    const { social_triggers = [], trigger_accounts = [] } = useResources();
    const socialTrigger = social_triggers.find(item => item.value === triggerType);
    const eventType = socialTrigger?.event ?? triggerType;
    const platform = socialTrigger?.platform ?? automation.trigger_config?.platform ?? '';
    const networks = social_triggers.filter(item => item.event === eventType);
    const availableAccounts = trigger_accounts.filter(a => (!platform || a.platform === platform)
        && (!a.events || a.events.includes(eventType)) && ['active', 'connected'].includes(a.status));
    const socialEvents = [...new Map(social_triggers.filter(item => item.event !== 'message.received')
        .map(item => [item.event, { value: item.event, label: item.label.split(' · ').slice(1).join(' · ') }])).values()];
    const changeEvent = (value) => {
        const first = social_triggers.find(item => item.event === value);
        onTypeChange(value === 'message.received' || !first ? value : first.value);
    };
    const changePlatform = (value) => {
        const config = { ...automation.trigger_config, platform: value, account_key: '' };
        onTypeChange(eventType === 'message.received' ? 'message.received' : `${value}.${eventType}`);
        onConfigChange(config);
    };
    const orderTokens = triggerType === 'cart.abandoned'
        ? ['{{context.cart_total}}', '{{context.recovery_url}}', '{{context.order_currency}}']
        : ['{{context.order_number}}', '{{context.order_total}}', '{{context.order_currency}}', '{{context.tracking_url}}', '{{context.store_name}}'];

    return (
        <div style={{
            position: 'absolute', top: 0, right: 0, bottom: 0, width: 320,
            background: '#fff', borderLeft: '1px solid #e5e7eb',
            boxShadow: '-4px 0 24px rgba(0,0,0,0.08)',
            zIndex: 10, display: 'flex', flexDirection: 'column',
            borderRadius: '0 0 12px 0',
        }}>
            {/* Header */}
            <div style={{ background: '#1e293b', padding: '14px 16px', display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                    <Zap size={15} color="#f59e0b" />
                    <span style={{ color: '#fff', fontWeight: 700, fontSize: 13 }}>{t('automation.trigger')}</span>
                </div>
                <button onClick={onClose} style={{ color: '#ffffff99', background: 'none', border: 'none', cursor: 'pointer', display: 'flex' }}>
                    <X size={16} />
                </button>
            </div>

            {/* Fields */}
            <div style={{ flex: 1, overflowY: 'auto', padding: 16 }} className="space-y-4">
                <p style={{ fontSize: 11, color: '#64748b' }}>{t('automation.trigger_panel_intro')}</p>

                <Field label={t('automation.trigger')}>
                    <p style={{ fontSize: 11, color: '#64748b', lineHeight: 1.6 }}>Escolha o evento, o canal e a conta. Depois salve e ative o fluxo.</p>
                    <select className={selectCls} aria-label="Gatilho" value={eventType} onChange={e => changeEvent(e.target.value)}>
                        <option value="">{t('automation.select_trigger')}</option>
                        {[...TRIGGER_TYPES, ...socialEvents].map(tr => <option key={tr.value} value={tr.value}>{tr.label ?? t(tr.labelKey)}</option>)}
                    </select>
                </Field>

                {(triggerType === 'message.received' || socialTrigger) && (<>
                    <Field label="Canal">
                        <select aria-label="Canal do gatilho" className={selectCls} value={platform} onChange={e => changePlatform(e.target.value)}>
                            {eventType === 'message.received' && <option value="">Todos os canais</option>}
                            {networks.map(item => <option key={item.platform} value={item.platform}>{item.network === 'Facebook / Messenger' ? 'Messenger' : item.network}</option>)}
                        </select>
                    </Field>
                    <Field label="Conta que inicia o fluxo">
                        <select aria-label="Conta que inicia o fluxo" className={selectCls} value={automation.trigger_config?.account_key ?? ''} onChange={e => onConfigChange({ account_key: e.target.value })}>
                            <option value="">{platform ? "Todas as contas deste canal" : "Todas as contas conectadas"}</option>
                            {automation.trigger_config?.account_key && !availableAccounts.some(a => a.key === automation.trigger_config.account_key) && <option value={automation.trigger_config.account_key} disabled>Conta indisponível — selecione outra</option>}
                            {availableAccounts.map(a => <option key={a.key} value={a.key}>{a.name} · {a.provider}</option>)}
                        </select>
                        {availableAccounts.length === 0 && <p className="text-xs text-amber-700 mt-2">Nenhuma conta desta rede conectada neste espaço. Conecte uma conta antes de usar o gatilho.</p>}
                    </Field>
                    {socialTrigger && (socialTrigger.event === 'comment.received' || socialTrigger.event.startsWith('post.')) && <Field label="ID da publicação (opcional)"><input aria-label="ID da publicação" className={inputCls} value={automation.trigger_config?.post_id ?? ''} onChange={e => onConfigChange({ post_id: e.target.value })} placeholder="Em branco: todas as publicações" /></Field>}
                    {socialTrigger?.event === 'comment.received' && <p className="text-xs text-slate-600">O comentário inicia o fluxo, mas não abre uma conversa privada. Use os dados context.comment_text, context.comment_id e context.post_id. Um envio de Direct exige uma ação compatível e as permissões da rede.</p>}

                </>)}

                {(triggerType === 'message.received' || socialTrigger) && (
                    <Field label={t('automation.keyword_filter_optional')}>
                        <p style={{ fontSize: 10, color: '#94a3b8', marginBottom: 6 }}>{t('automation.keyword_filter_hint')}</p>
                        <input
                            className={inputCls}
                            value={(automation.trigger_config?.keywords ?? []).join(', ')}
                            onChange={e => onConfigChange({ keywords: e.target.value.split(',').map(k => k.trim()).filter(Boolean) })}
                            placeholder={t('automation.placeholder_keywords')}
                        />
                    </Field>
                )}

                {triggerType === 'webhook.received' && (
                    <Field label={t('automation.webhook_url')}>
                        {webhookUrl ? (
                            <div style={{ display: 'flex', gap: 4, alignItems: 'center' }}>
                                <input readOnly className={inputCls} value={webhookUrl} style={{ fontFamily: 'monospace', fontSize: 10 }} />
                                <button onClick={onCopy} style={{ background: 'none', border: 'none', cursor: 'pointer', color: '#64748b', display: 'flex', padding: 4 }}>
                                    {copied ? <Check size={14} color="#10b981" /> : <Copy size={14} />}
                                </button>
                            </div>
                        ) : (
                            <p style={{ fontSize: 11, color: '#94a3b8' }}>{t('automation.no_token_yet')}</p>
                        )}
                        <button onClick={onGenerateToken} disabled={generatingToken} style={{ marginTop: 8, display: 'flex', alignItems: 'center', gap: 4, fontSize: 11, color: '#6366f1', background: 'none', border: 'none', cursor: 'pointer', padding: 0 }}>
                            <RefreshCw size={11} style={{ animation: generatingToken ? 'spin 1s linear infinite' : 'none' }} />
                            {automation.trigger_token ? t('automation.regenerate_token') : t('automation.generate_token')}
                        </button>
                    </Field>
                )}

                {['order.placed', 'order.fulfilled', 'order.cancelled', 'cart.abandoned'].includes(triggerType) && (
                    <div style={{ background: '#f8fafc', border: '1px solid #e2e8f0', borderRadius: 8, padding: '8px 10px' }}>
                        <p style={{ fontSize: 10, fontWeight: 700, color: '#64748b', marginBottom: 4 }}>{t('automation.available_tokens_heading')}</p>
                        <p style={{ fontSize: 10, color: '#94a3b8', marginBottom: 6 }}>{t('automation.available_tokens_hint')}</p>
                        <div style={{ display: 'flex', flexWrap: 'wrap', gap: 4 }}>
                            {orderTokens.map(tok => (
                                <code key={tok} style={{ fontSize: 9, color: '#475569', background: '#e2e8f0', borderRadius: 4, padding: '2px 5px' }}>{tok}</code>
                            ))}
                        </div>
                    </div>
                )}
            </div>
        </div>
    );
}

export function ExtendedFields({ d, set }) {
    const resources=useResources();
    const def=extendedNodes[d.nodeType];
    if (!def) return null;
    return <>
        <p className="rounded-lg bg-slate-50 p-3 text-xs leading-relaxed text-slate-600">{def.help}</p>
        {def.fields.map(f=>{
            const value=d[f.key] ?? f.default ?? '';
            const options=f.type==='resource' ? (resources[f.resource] ?? []).filter(r=>!f.channel || r.channel===f.channel).map(r=>[String(r.id),r.name || r.display_name || `#${r.id}`]) : (f.options ?? []);
            return <Field key={f.key} label={f.label+(f.required?' *':'')}>
                {f.type==='textarea' ? <textarea aria-label={f.label} className={textareaCls} rows={4} value={value} onChange={e=>set(f.key,e.target.value)} /> :
                ['resource','select'].includes(f.type) ? <select aria-label={f.label} className={selectCls} value={value} onChange={e=>set(f.key,e.target.value)}><option value="">Selecione…</option>{options.map(([v,label])=><option key={v} value={v}>{label}</option>)}</select> :
                <input aria-label={f.label} className={inputCls} type={f.type==='number'?'number':'text'} min={f.min} max={f.max} value={value} onChange={e=>set(f.key,e.target.value)} />}
            </Field>;
        })}
    </>;
}

function CheckField({ label, checked, onChange }) {
    return (
        <label style={{ display: 'flex', alignItems: 'center', gap: 8, fontSize: 12, color: '#374151', cursor: 'pointer' }}>
            <input type="checkbox" checked={!!checked} onChange={e => onChange(e.target.checked)} style={{ width: 14, height: 14 }} />
            {label}
        </label>
    );
}

function ChannelSelect({ d, set, imageOnlyHint = false, selectAccount = true, whatsappOnly = false }) {
    const { t } = useTranslation();
    const ch = whatsappOnly ? 'whatsapp' : (d.channel ?? 'whatsapp');
    const { channel_accounts = [] } = useResources();
    const selectedAccount=channel_accounts.find(a=>String(a.id)===String(d.channel_account_id));
    return (
        <>
            <Field label={t('automation.field_channel')}>
                <select className={selectCls} value={ch} onChange={e => { set('channel', e.target.value); set('channel_account_id', ''); }}>
                    <option value="whatsapp">WhatsApp</option>
                    {!whatsappOnly && <option value="telegram">Telegram</option>}
                    {!whatsappOnly && <option value="messenger">Messenger</option>}
                    {!whatsappOnly && <option value="instagram">Instagram</option>}
                    {!whatsappOnly && <option value="sms">SMS</option>}
                </select>
            </Field>
            {selectAccount && ch !== 'sms' && (
                <Field label="Conta de envio">
                    <select className={selectCls} value={d.channel_account_id ?? ''} onChange={e => set('channel_account_id', e.target.value)}>
                        <option value="">Automática — conta da conversa</option>
                        {d.channel_account_id && !channel_accounts.some(a => String(a.id) === String(d.channel_account_id) && a.channel === ch) && <option value={d.channel_account_id} disabled>Conta indisponível — escolha outra</option>}
                        {channel_accounts.filter(a => a.channel === ch).map(a => (
                            <option key={a.id} value={a.id} disabled={a.status !== 'active'}>
                                {a.display_name || `Conta #${a.id}`} · {a.provider} · #{a.id}{a.status !== 'active' ? ' (desconectada)' : ''}
                            </option>
                        ))}
                    </select>
                    {selectedAccount?.message_types && <p className="mt-2 text-xs text-slate-600">Formatos: {selectedAccount.message_types.map(type=>({text:'texto',image:'imagem',video:'vídeo',audio:'áudio',document:'arquivo',interactive:'botões/listas',location:'localização',template:'modelo'})[type] ?? type).join(', ')}.</p>}
                    <p style={{ fontSize: 10, color: '#64748b', marginTop: 6 }}>A conta escolhida será usada neste envio. Se ficar indisponível, a execução exibirá um erro, sem trocar de conta.</p>
                </Field>
            )}
            {(ch === 'messenger' || ch === 'instagram') && (
                <div style={{ display: 'flex', gap: 6, background: '#eff6ff', border: '1px solid #bfdbfe', borderRadius: 8, padding: '8px 10px', fontSize: 10, color: '#1e40af' }}>
                    <AlertTriangle size={14} style={{ flexShrink: 0, marginTop: 1 }} />
                    <span>{imageOnlyHint ? t('automation.channel_meta_image_hint') : t('automation.channel_meta_hint')}</span>
                </div>
            )}
        </>
    );
}

function GoogleWarning() {
    const { t } = useTranslation();
    const r = useResources();
    if (r.integrations?.google) return null;
    return (
        <div style={{ display: 'flex', gap: 6, background: '#fffbeb', border: '1px solid #fde68a', borderRadius: 8, padding: '8px 10px', fontSize: 10, color: '#92400e' }}>
            <AlertTriangle size={14} style={{ flexShrink: 0, marginTop: 1 }} />
            <span>{t('automation.google_not_configured')}</span>
        </div>
    );
}

/* ─── Field components ────────────────────────────────────────── */

function WhatsAppFields({ d, set }) {
    const { t } = useTranslation();
    return (
        <>
            <Field label={t('automation.field_message_body_required')}>
                <textarea className={textareaCls} rows={4} value={d.body ?? ''} onChange={e => set('body', e.target.value)} placeholder={t('automation.placeholder_whatsapp_body', { token: '{{contact.name}}' })} />
            </Field>
            <ChannelSelect d={d} set={set} selectAccount />
        </>
    );
}

function SmsFields({ d, set }) {
    const { t } = useTranslation();
    return (
        <Field label={t('automation.field_message_body_required')}>
            <textarea className={textareaCls} rows={4} value={d.body ?? ''} onChange={e => set('body', e.target.value)} placeholder={t('automation.placeholder_sms_body', { token: '{{contact.name}}' })} />
        </Field>
    );
}

function EmailFields({ d, set }) {
    const { t } = useTranslation();
    return (
        <>
            <Field label={t('automation.field_subject_required')}>
                <input className={inputCls} value={d.subject ?? ''} onChange={e => set('subject', e.target.value)} placeholder={t('automation.placeholder_email_subject')} />
            </Field>
            <Field label={t('automation.field_from_name_optional')}>
                <input className={inputCls} value={d.from_name ?? ''} onChange={e => set('from_name', e.target.value)} placeholder={t('automation.placeholder_from_name')} />
            </Field>
            <Field label={t('automation.field_body_required')}>
                <textarea className={textareaCls} rows={6} value={d.body ?? ''} onChange={e => set('body', e.target.value)} placeholder={t('automation.placeholder_email_body', { token: '{{contact.name}}' })} />
            </Field>
        </>
    );
}

function templateBodyText(components) {
    const body = (Array.isArray(components) ? components : []).find(c => (c.type || '').toUpperCase() === 'BODY');
    return body?.text || '';
}

function templateBodyVarCount(components) {
    const text = templateBodyText(components);
    const matches = text.match(/\{\{\s*(\d+)\s*\}\}/g) || [];
    return matches.reduce((max, m) => Math.max(max, parseInt(m.replace(/[^\d]/g, ''), 10) || 0), 0);
}

function TemplateFields({ d, set }) {
    const { t } = useTranslation();
    const { templates = [] } = useResources();
    const tpl = templates.find(x => x.name === d.template_name && x.language === d.language)
        || templates.find(x => x.name === d.template_name);
    const varCount = tpl ? templateBodyVarCount(tpl.components) : 0;
    const vars = Array.isArray(d.variables)
        ? d.variables
        : (typeof d.variables === 'string' && d.variables ? d.variables.split('\n') : []);

    const onPick = (val) => {
        const [name, language] = val.split('||');
        set('template_name', name);
        set('language', language || 'en');
        set('variables', []); // reset on template change so indices match the new body
    };
    const setVar = (i, val) => {
        const next = Array.from({ length: varCount }, (_, idx) => vars[idx] ?? '');
        next[i] = val;
        set('variables', next);
    };

    return (
        <>
            <Field label={t('automation.field_template_required')}>
                {templates.length ? (
                    <select className={selectCls} value={tpl ? `${tpl.name}||${tpl.language}` : ''} onChange={e => onPick(e.target.value)}>
                        <option value="">{t('automation.select_template')}</option>
                        {templates.map(x => (
                            <option key={`${x.name}-${x.language}`} value={`${x.name}||${x.language}`}>
                                {x.name} ({x.language}){x.status && x.status !== 'APPROVED' ? ` · ${x.status}` : ''}
                            </option>
                        ))}
                    </select>
                ) : (
                    <input className={inputCls} value={d.template_name ?? ''} onChange={e => set('template_name', e.target.value)} placeholder="my_template_name" />
                )}
            </Field>

            {!templates.length && (
                <Field label={t('automation.field_language')}>
                    <input className={inputCls} value={d.language ?? 'en'} onChange={e => set('language', e.target.value)} placeholder="en" />
                </Field>
            )}

            {tpl && tpl.status && tpl.status !== 'APPROVED' && (
                <div style={{ display: 'flex', gap: 6, background: '#fffbeb', border: '1px solid #fde68a', borderRadius: 8, padding: '8px 10px', fontSize: 10, color: '#92400e' }}>
                    <AlertTriangle size={14} style={{ flexShrink: 0, marginTop: 1 }} />
                    <span>{t('automation.template_not_approved', { status: tpl.status })}</span>
                </div>
            )}

            {tpl && templateBodyText(tpl.components) && (
                <div>
                    <label className={labelCls}>{t('automation.template_preview')}</label>
                    <div style={{ background: '#f8fafc', border: '1px solid #e2e8f0', borderRadius: 8, padding: '8px 10px', fontSize: 11, color: '#475569', whiteSpace: 'pre-wrap' }}>
                        {templateBodyText(tpl.components)}
                    </div>
                </div>
            )}

            {varCount > 0 && Array.from({ length: varCount }).map((_, i) => (
                <Field key={i} label={t('automation.template_var_n', { n: i + 1 })}>
                    <input className={inputCls} value={vars[i] ?? ''} onChange={e => setVar(i, e.target.value)} placeholder={t('automation.template_var_placeholder', { n: i + 1 })} />
                </Field>
            ))}

            {!tpl && templates.length === 0 && (
                <Field label={t('automation.field_template_vars_optional')}>
                    <textarea className={textareaCls} rows={3} value={typeof d.variables === 'string' ? d.variables : ''} onChange={e => set('variables', e.target.value)} placeholder={t('automation.placeholder_template_vars')} />
                </Field>
            )}
        </>
    );
}

function mediaAccept(type) {
    return type === 'video' ? 'video/*'
        : type === 'audio' ? 'audio/*'
            : type === 'document' ? '.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv'
                : 'image/*';
}

function MediaFields({ d, set }) {
    const { t } = useTranslation();
    return (
        <>
            <Field label={t('automation.field_media_type')}>
                <select className={selectCls} value={d.media_type ?? 'image'} onChange={e => set('media_type', e.target.value)}>
                    <option value="image">{t('automation.media_image')}</option>
                    <option value="video">{t('automation.media_video')}</option>
                    <option value="document">{t('automation.media_document')}</option>
                    <option value="audio">{t('automation.media_audio')}</option>
                </select>
            </Field>
            <MediaUpload
                label={t('automation.field_media_required')}
                value={d.link ?? ''}
                onChange={url => set('link', url)}
                accept={mediaAccept(d.media_type)}
                collection="automation"
                placeholder="https://example.com/file.pdf"
            />
            {d.media_type !== 'audio' && (
                <Field label={t('automation.field_caption_optional')}>
                    <textarea className={textareaCls} rows={2} value={d.caption ?? ''} onChange={e => set('caption', e.target.value)} placeholder={t('automation.placeholder_caption')} />
                </Field>
            )}
            {d.media_type === 'document' && (
                <Field label={t('automation.field_filename_optional')}>
                    <input className={inputCls} value={d.filename ?? ''} onChange={e => set('filename', e.target.value)} placeholder="invoice.pdf" />
                </Field>
            )}
            <ChannelSelect d={d} set={set} imageOnlyHint />
        </>
    );
}

function SequenceFields({ d, set }) {
    const { t } = useTranslation();
    const steps = Array.isArray(d.steps) ? d.steps : [];
    const update = (i, patch) => set('steps', steps.map((s, idx) => idx === i ? { ...s, ...patch } : s));
    const add = (kind) => set('steps', [...steps, kind === 'media' ? { kind: 'media', media_type: 'image', link: '', caption: '' } : { kind: 'text', body: '' }]);
    const remove = (i) => set('steps', steps.filter((_, idx) => idx !== i));

    return (
        <>
            <ChannelSelect d={d} set={set} selectAccount />
            <p style={{ fontSize: 10, color: '#64748b' }}>{t('automation.sequence_hint')}</p>
            {steps.map((s, i) => (
                <div key={i} style={{ border: '1px solid #e5e7eb', borderRadius: 8, padding: 8 }} className="space-y-2">
                    <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                        <span style={{ fontSize: 10, fontWeight: 700, color: '#64748b' }}>{t('automation.step_n', { n: i + 1 })}</span>
                        <div style={{ display: 'flex', gap: 6, alignItems: 'center' }}>
                            <select className="rounded border border-gray-200 text-[10px] px-1 py-0.5" value={s.kind ?? 'text'} onChange={e => update(i, { kind: e.target.value })}>
                                <option value="text">{t('automation.step_text')}</option>
                                <option value="media">{t('automation.step_media')}</option>
                            </select>
                            <button onClick={() => remove(i)} style={{ color: '#dc2626', background: 'none', border: 'none', cursor: 'pointer', display: 'flex' }}><Trash2 size={13} /></button>
                        </div>
                    </div>
                    {s.kind === 'media' ? (
                        <>
                            <select className={selectCls} value={s.media_type ?? 'image'} onChange={e => update(i, { media_type: e.target.value })}>
                                <option value="image">{t('automation.media_image')}</option>
                                <option value="video">{t('automation.media_video')}</option>
                                <option value="document">{t('automation.media_document')}</option>
                                <option value="audio">{t('automation.media_audio')}</option>
                            </select>
                            <MediaUpload
                                value={s.link ?? ''}
                                onChange={url => update(i, { link: url })}
                                accept={mediaAccept(s.media_type)}
                                collection="automation"
                                placeholder="https://example.com/file.jpg"
                            />
                            <input className={inputCls} value={s.caption ?? ''} onChange={e => update(i, { caption: e.target.value })} placeholder={t('automation.field_caption_optional')} />
                        </>
                    ) : (
                        <textarea className={textareaCls} rows={2} value={s.body ?? ''} onChange={e => update(i, { body: e.target.value })} placeholder={t('automation.placeholder_whatsapp_body', { token: '{{contact.name}}' })} />
                    )}
                </div>
            ))}
            <div style={{ display: 'flex', gap: 6 }}>
                <button onClick={() => add('text')} style={addBtnStyle}><Plus size={11} /> {t('automation.step_text')}</button>
                <button onClick={() => add('media')} style={addBtnStyle}><Plus size={11} /> {t('automation.step_media')}</button>
            </div>
        </>
    );
}

const addBtnStyle = {
    display: 'flex', alignItems: 'center', gap: 4, flex: 1, justifyContent: 'center',
    border: '1px dashed #cbd5e1', borderRadius: 8, padding: '6px 0', fontSize: 11,
    fontWeight: 600, color: '#475569', background: '#f8fafc', cursor: 'pointer',
};

function QuickRepliesFields({ d, set }) {
    const { t } = useTranslation();
    const buttons = Array.isArray(d.buttons) ? d.buttons : ['', '', ''];
    const setBtn = (i, val) => {
        const next = [...buttons];
        next[i] = val;
        set('buttons', next);
    };
    return (
        <>
            <Field label={t('automation.field_message_body_required')}>
                <textarea className={textareaCls} rows={3} value={d.body ?? ''} onChange={e => set('body', e.target.value)} placeholder={t('automation.placeholder_quick_replies_body')} />
            </Field>
            {[0, 1, 2].map(i => (
                <Field key={i} label={t('automation.field_button_n', { n: i + 1 })}>
                    <input className={inputCls} maxLength={20} value={buttons[i] ?? ''} onChange={e => setBtn(i, e.target.value)} placeholder={i === 0 ? t('automation.placeholder_button_required') : t('automation.placeholder_button_optional')} />
                </Field>
            ))}
        </>
    );
}

function ListMessageFields({ d, set }) {
    const { t } = useTranslation();
    return (
        <>
            <ChannelSelect d={d} set={set} whatsappOnly />
            <p className="text-xs text-slate-600">Envia um botão que abre uma lista com até 10 opções, uma por linha. Disponível na API oficial e no WAHA com NOWEB ou GOWS, em conversas individuais. O mecanismo é verificado no envio.</p>
            <Field label={t('automation.field_message_body_required')}>
                <textarea className={textareaCls} rows={3} value={d.body ?? ''} onChange={e => set('body', e.target.value)} placeholder={t('automation.placeholder_list_body')} />
            </Field>
            <Field label={t('automation.field_list_button')}>
                <input className={inputCls} maxLength={20} value={d.button_label ?? ''} onChange={e => set('button_label', e.target.value)} placeholder={t('automation.placeholder_list_button')} />
            </Field>
            <Field label={t('automation.field_section_title_optional')}>
                <input className={inputCls} maxLength={24} value={d.section_title ?? ''} onChange={e => set('section_title', e.target.value)} placeholder={t('automation.placeholder_section_title')} />
            </Field>
            <Field label={t('automation.field_list_rows_required')}>
                <textarea className={textareaCls} rows={4} value={d.rows ?? ''} onChange={e => set('rows', e.target.value)} placeholder={t('automation.placeholder_list_rows')} />
            </Field>
        </>
    );
}

function AskQuestionFields({ d, set }) {
    const { t } = useTranslation();
    return (
        <>
            <Field label={t('automation.field_question_required')}>
                <textarea className={textareaCls} rows={3} value={d.question ?? ''} onChange={e => set('question', e.target.value)} placeholder={t('automation.placeholder_question')} />
            </Field>
            <Field label={t('automation.field_save_to_var_required')}>
                <input className={inputCls} value={d.variable ?? 'answer'} onChange={e => set('variable', e.target.value)} placeholder="answer" />
            </Field>
            <ChannelSelect d={d} set={set} />
            <div style={{ background: '#fff7ed', border: '1px solid #fed7aa', borderRadius: 8, padding: '8px 10px', fontSize: 10, color: '#9a3412' }}>
                {t('automation.ask_question_hint', { var: `{{context.${d.variable || 'answer'}}}` })}
            </div>
        </>
    );
}

function WaitFields({ d, set }) {
    const { t } = useTranslation();
    return (
        <div className="flex gap-2">
            <div className="flex-1">
                <label className={labelCls}>{t('automation.field_amount_required')}</label>
                <input type="number" min={1} className={inputCls} value={d.amount ?? ''} onChange={e => set('amount', e.target.value)} placeholder="1" />
            </div>
            <div className="flex-1">
                <label className={labelCls}>{t('automation.field_unit')}</label>
                <select className={selectCls} value={d.unit ?? 'minutes'} onChange={e => set('unit', e.target.value)}>
                    <option value="minutes">{t('automation.unit_minutes')}</option>
                    <option value="hours">{t('automation.unit_hours')}</option>
                    <option value="days">{t('automation.unit_days')}</option>
                </select>
            </div>
        </div>
    );
}

function ConditionFields({ d, set }) {
    const { t } = useTranslation();
    const noValue = d.operator === 'exists' || d.operator === 'not_exists';
    return (
        <>
            <Field label={t('automation.field_check_field_required')}>
                <select className={selectCls} value={d.field ?? ''} onChange={e => set('field', e.target.value)}>
                    <option value="">{t('automation.select_field')}</option>
                    {CONDITION_FIELDS.map(f => <option key={f.value} value={f.value}>{t(f.labelKey)}</option>)}
                </select>
            </Field>
            <Field label={t('automation.field_operator')}>
                <select className={selectCls} value={d.operator ?? 'equals'} onChange={e => set('operator', e.target.value)}>
                    {CONDITION_OPERATORS.map(o => <option key={o.value} value={o.value}>{t(o.labelKey)}</option>)}
                </select>
            </Field>
            {!noValue && (
                <Field label={t('automation.field_value_required')}>
                    <input className={inputCls} value={d.value ?? ''} onChange={e => set('value', e.target.value)} placeholder={t('automation.placeholder_compare_value')} />
                </Field>
            )}
            <div style={{ background: '#f0fdf4', border: '1px solid #bbf7d0', borderRadius: 8, padding: '8px 10px', fontSize: 10, color: '#166534' }}>
                <strong>✓ {t('common.yes')}</strong> — {t('automation.condition_yes_hint')}<br />
                <strong>✗ {t('common.no')}</strong> — {t('automation.condition_no_hint')}
            </div>
        </>
    );
}

function TagFields({ d, set }) {
    const { t } = useTranslation();
    return (
        <Field label={t('automation.field_tag_name_required')}>
            <input className={inputCls} value={d.tag ?? ''} onChange={e => set('tag', e.target.value)} placeholder={t('automation.placeholder_tag_name')} />
        </Field>
    );
}

function UpdateContactFields({ d, set }) {
    const { t } = useTranslation();
    return (
        <>
            <Field label={t('automation.field_field_to_update_required')}>
                <select className={selectCls} value={d.field ?? ''} onChange={e => set('field', e.target.value)}>
                    <option value="">{t('automation.select_field')}</option>
                    {UPDATE_FIELDS.map(f => <option key={f.value} value={f.value}>{t(f.labelKey)}</option>)}
                </select>
            </Field>
            <Field label={t('automation.field_new_value_required')}>
                <input className={inputCls} value={d.value ?? ''} onChange={e => set('value', e.target.value)} placeholder={t('automation.placeholder_new_value', { token: '{{token}}' })} />
            </Field>
        </>
    );
}

function CampaignFields({ d, set }) {
    const { t } = useTranslation();
    const { campaigns = [] } = useResources();
    return (
        <Field label={t('automation.field_campaign_required')}>
            {campaigns.length ? (
                <select className={selectCls} value={d.campaign_id ?? ''} onChange={e => set('campaign_id', e.target.value)}>
                    <option value="">{t('automation.select_campaign')}</option>
                    {campaigns.map(c => <option key={c.id} value={c.id}>{c.name}</option>)}
                </select>
            ) : (
                <input type="number" className={inputCls} value={d.campaign_id ?? ''} onChange={e => set('campaign_id', e.target.value)} placeholder="123" />
            )}
        </Field>
    );
}

function SubflowFields({ d, set }) {
    const { t } = useTranslation();
    const { subflows = [] } = useResources();
    const pick = (uuid) => {
        set('automation_uuid', uuid);
        set('subflow_name', subflows.find(s => s.uuid === uuid)?.name ?? '');
    };
    return (
        <Field label={t('automation.field_subflow_required')}>
            {subflows.length ? (
                <select className={selectCls} value={d.automation_uuid ?? ''} onChange={e => pick(e.target.value)}>
                    <option value="">{t('automation.select_subflow')}</option>
                    {subflows.map(s => <option key={s.uuid} value={s.uuid}>{s.name}{s.status !== 'active' ? ` (${s.status})` : ''}</option>)}
                </select>
            ) : (
                <p style={{ fontSize: 11, color: '#94a3b8' }}>{t('automation.no_subflows')}</p>
            )}
        </Field>
    );
}

function AIReplyFields({ d, set }) {
    const { t } = useTranslation();
    const { chatbots = [] } = useResources();
    return (
        <>
            <Field label={t('automation.field_chatbot_optional')}>
                <select className={selectCls} value={d.chatbot_id ?? ''} onChange={e => set('chatbot_id', e.target.value)}>
                    <option value="">{t('automation.ai_use_prompt')}</option>
                    {chatbots.map(c => <option key={c.id} value={c.id}>{c.name}</option>)}
                </select>
            </Field>
            <Field label={d.chatbot_id ? t('automation.field_prompt_optional') : t('automation.field_prompt_instructions_required')}>
                <textarea className={textareaCls} rows={5} value={d.prompt ?? ''} onChange={e => set('prompt', e.target.value)} placeholder={t('automation.placeholder_ai_prompt')} />
            </Field>
            <ChannelSelect d={d} set={set} />
        </>
    );
}

function RunChatbotFields({ d, set }) {
    const { t } = useTranslation();
    const { chatbots = [] } = useResources();
    return (
        <>
            <Field label={t('automation.field_chatbot_required')}>
                {chatbots.length ? (
                    <select className={selectCls} value={d.chatbot_id ?? ''} onChange={e => set('chatbot_id', e.target.value)}>
                        <option value="">{t('automation.select_chatbot')}</option>
                        {chatbots.map(c => <option key={c.id} value={c.id}>{c.name}</option>)}
                    </select>
                ) : (
                    <p style={{ fontSize: 11, color: '#94a3b8' }}>{t('automation.no_chatbots')}</p>
                )}
            </Field>
            <Field label={t('automation.field_prompt_optional')}>
                <textarea className={textareaCls} rows={3} value={d.prompt ?? ''} onChange={e => set('prompt', e.target.value)} placeholder={t('automation.placeholder_chatbot_prompt')} />
            </Field>
            <ChannelSelect d={d} set={set} />
        </>
    );
}

function AssignAgentFields({ d, set }) {
    const { t } = useTranslation();
    const { agents = [] } = useResources();
    const pick = (id) => {
        set('user_id', id);
        set('agent_name', agents.find(a => String(a.id) === String(id))?.name ?? '');
    };
    return (
        <Field label={t('automation.field_assign_to')}>
            <select className={selectCls} value={d.user_id ?? ''} onChange={e => pick(e.target.value)}>
                <option value="">{t('automation.assign_unassigned')}</option>
                {agents.map(a => <option key={a.id} value={a.id}>{a.name}</option>)}
            </select>
        </Field>
    );
}

function CtaButtonFields({ d, set }) {
    const { t } = useTranslation();
    return (
        <>
            <Field label={t('automation.field_message_body_required')}>
                <textarea className={textareaCls} rows={3} value={d.body ?? ''} onChange={e => set('body', e.target.value)} placeholder={t('automation.placeholder_cta_body')} />
            </Field>
            <Field label={t('automation.field_button_text_required')}>
                <input className={inputCls} maxLength={20} value={d.display_text ?? ''} onChange={e => set('display_text', e.target.value)} placeholder={t('automation.placeholder_button_text')} />
            </Field>
            <Field label={t('automation.field_url_required')}>
                <input className={inputCls} value={d.url ?? ''} onChange={e => set('url', e.target.value)} placeholder="https://example.com" />
            </Field>
        </>
    );
}

function LocationFields({ d, set }) {
    const { t } = useTranslation();
    return (
        <>
            <div className="flex gap-2">
                <div className="flex-1">
                    <label className={labelCls}>{t('automation.field_latitude_required')}</label>
                    <input className={inputCls} value={d.latitude ?? ''} onChange={e => set('latitude', e.target.value)} placeholder="37.4220" />
                </div>
                <div className="flex-1">
                    <label className={labelCls}>{t('automation.field_longitude_required')}</label>
                    <input className={inputCls} value={d.longitude ?? ''} onChange={e => set('longitude', e.target.value)} placeholder="-122.0841" />
                </div>
            </div>
            <Field label={t('automation.field_place_name_optional')}>
                <input className={inputCls} value={d.name ?? ''} onChange={e => set('name', e.target.value)} placeholder={t('automation.placeholder_place_name')} />
            </Field>
            <Field label={t('automation.field_address_optional')}>
                <input className={inputCls} value={d.address ?? ''} onChange={e => set('address', e.target.value)} placeholder={t('automation.placeholder_address')} />
            </Field>
        </>
    );
}

function PollFields({ d, set }) {
    const { t } = useTranslation();
    return (
        <>
            <Field label={t('automation.field_question_required')}>
                <textarea className={textareaCls} rows={2} value={d.question ?? ''} onChange={e => set('question', e.target.value)} placeholder={t('automation.placeholder_poll_question')} />
            </Field>
            <Field label={t('automation.field_poll_options_required')}>
                <textarea className={textareaCls} rows={4} value={d.options ?? ''} onChange={e => set('options', e.target.value)} placeholder={t('automation.placeholder_poll_options')} />
            </Field>
            <p style={{ fontSize: 10, color: '#64748b' }}>{t('automation.poll_hint')}</p>
        </>
    );
}

function BookAppointmentFields({ d, set }) {
    const { t } = useTranslation();
    const resources = useResources();
    const services = resources.appointment_services ?? [];
    const professionals = resources.appointment_resources ?? [];
    const local = (d.booking_mode ?? 'local') === 'local';
    return (
        <>
            <Field label="Onde criar o agendamento">
                <select className={selectCls} value={d.booking_mode ?? 'local'} onChange={e => set('booking_mode', e.target.value)}>
                    <option value="local">Agenda do Respondesk</option>
                    <option value="google">Somente Google Agenda</option>
                </select>
            </Field>
            {local ? <>
                <Field label="Serviço">
                    <select className={selectCls} value={d.service_uuid ?? ''} onChange={e => set('service_uuid', e.target.value)}>
                        <option value="">Selecione o serviço</option>{services.map(item => <option key={item.uuid} value={item.uuid}>{item.name}</option>)}
                    </select>
                </Field>
                <Field label="Profissional ou recurso">
                    <select className={selectCls} value={d.resource_uuid ?? ''} onChange={e => set('resource_uuid', e.target.value)}>
                        <option value="">Selecione</option>{professionals.map(item => <option key={item.uuid} value={item.uuid}>{item.name}</option>)}
                    </select>
                </Field>
                {(services.length === 0 || professionals.length === 0) && <p style={{ fontSize: 10, color: '#b45309' }}>Cadastre pelo menos um serviço e um profissional em Agendamentos.</p>}
            </> : <GoogleWarning />}
            <Field label={t('automation.field_summary_required')}>
                <input className={inputCls} value={d.summary ?? ''} onChange={e => set('summary', e.target.value)} placeholder={t('automation.placeholder_appointment_summary')} />
            </Field>
            <Field label={t('automation.field_start_required')}>
                <input className={inputCls} value={d.start ?? ''} onChange={e => set('start', e.target.value)} placeholder="2026-07-01 14:00 or {{context.start}}" />
            </Field>
            <div className="flex gap-2">
                <div className="flex-1">
                    <label className={labelCls}>{t('automation.field_duration_min')}</label>
                    <input type="number" min={1} className={inputCls} value={d.duration_minutes ?? ''} onChange={e => set('duration_minutes', e.target.value)} placeholder="30" />
                </div>
                <div className="flex-1">
                    <label className={labelCls}>{t('automation.field_timezone_optional')}</label>
                    <input className={inputCls} value={d.timezone ?? ''} onChange={e => set('timezone', e.target.value)} placeholder="UTC" />
                </div>
            </div>
            {!local && <Field label={t('automation.field_calendar_id_optional')}>
                <input className={inputCls} value={d.calendar_id ?? ''} onChange={e => set('calendar_id', e.target.value)} placeholder="primary" />
            </Field>}
            {local && <div className="flex gap-2">
                <div className="flex-1"><label className={labelCls}>Lembrete do cliente</label><select className={selectCls} value={d.customer_reminder_minutes ?? 1440} onChange={e => set('customer_reminder_minutes', Number(e.target.value))}><option value="0">Sem lembrete</option><option value="60">1 hora</option><option value="120">2 horas</option><option value="1440">1 dia</option></select></div>
                <div className="flex-1"><label className={labelCls}>Lembrete profissional</label><select className={selectCls} value={d.professional_reminder_minutes ?? 120} onChange={e => set('professional_reminder_minutes', Number(e.target.value))}><option value="0">Sem lembrete</option><option value="60">1 hora</option><option value="120">2 horas</option><option value="1440">1 dia</option></select></div>
            </div>}
            <CheckField label={t('automation.field_send_confirmation')} checked={d.send_confirmation} onChange={v => set('send_confirmation', v)} />
        </>
    );
}

function GoogleMeetFields({ d, set }) {
    const { t } = useTranslation();
    return (
        <>
            <GoogleWarning />
            <Field label={t('automation.field_summary_required')}>
                <input className={inputCls} value={d.summary ?? ''} onChange={e => set('summary', e.target.value)} placeholder={t('automation.placeholder_meeting_summary')} />
            </Field>
            <Field label={t('automation.field_start_required')}>
                <input className={inputCls} value={d.start ?? ''} onChange={e => set('start', e.target.value)} placeholder="2026-07-01 14:00 or {{context.start}}" />
            </Field>
            <div className="flex gap-2">
                <div className="flex-1">
                    <label className={labelCls}>{t('automation.field_duration_min')}</label>
                    <input type="number" min={1} className={inputCls} value={d.duration_minutes ?? ''} onChange={e => set('duration_minutes', e.target.value)} placeholder="30" />
                </div>
                <div className="flex-1">
                    <label className={labelCls}>{t('automation.field_timezone_optional')}</label>
                    <input className={inputCls} value={d.timezone ?? ''} onChange={e => set('timezone', e.target.value)} placeholder="UTC" />
                </div>
            </div>
            <CheckField label={t('automation.field_send_link')} checked={d.send_link ?? true} onChange={v => set('send_link', v)} />
        </>
    );
}

function WhatsappFormFields({ d, set }) {
    const { t } = useTranslation();
    return (
        <>
            <Field label={t('automation.field_flow_id_required')}>
                <input className={inputCls} value={d.flow_id ?? ''} onChange={e => set('flow_id', e.target.value)} placeholder="1234567890" />
            </Field>
            <Field label={t('automation.field_message_body_required')}>
                <textarea className={textareaCls} rows={3} value={d.body ?? ''} onChange={e => set('body', e.target.value)} placeholder={t('automation.placeholder_form_body')} />
            </Field>
            <Field label={t('automation.field_flow_cta')}>
                <input className={inputCls} maxLength={20} value={d.flow_cta ?? ''} onChange={e => set('flow_cta', e.target.value)} placeholder={t('automation.placeholder_flow_cta')} />
            </Field>
            <Field label={t('automation.field_flow_screen_optional')}>
                <input className={inputCls} value={d.screen ?? ''} onChange={e => set('screen', e.target.value)} placeholder="WELCOME_SCREEN" />
            </Field>
        </>
    );
}

function WhatsappCatalogFields({ d, set }) {
    const { t } = useTranslation();
    return (
        <>
            <Field label={t('automation.field_message_body_required')}>
                <textarea className={textareaCls} rows={3} value={d.body ?? ''} onChange={e => set('body', e.target.value)} placeholder={t('automation.placeholder_catalog_body')} />
            </Field>
            <Field label={t('automation.field_thumbnail_product_optional')}>
                <input className={inputCls} value={d.thumbnail_product_retailer_id ?? ''} onChange={e => set('thumbnail_product_retailer_id', e.target.value)} placeholder="SKU_123" />
            </Field>
        </>
    );
}

function ProductFields({ d, set, platform }) {
    const { t } = useTranslation();
    const { stores = [] } = useResources();
    const platformStores = stores.filter(s => s.platform === platform);
    return (
        <>
            <Field label={t('automation.field_store_required')}>
                {platformStores.length ? (
                    <select className={selectCls} value={d.store_id ?? ''} onChange={e => set('store_id', e.target.value)}>
                        <option value="">{t('automation.select_store')}</option>
                        {platformStores.map(s => <option key={s.id} value={s.id}>{s.name || s.platform}</option>)}
                    </select>
                ) : (
                    <p style={{ fontSize: 11, color: '#94a3b8' }}>{t('automation.no_stores', { platform })}</p>
                )}
            </Field>
            <Field label={t('automation.field_product_id_required')}>
                <input className={inputCls} value={d.product_id ?? ''} onChange={e => set('product_id', e.target.value)} placeholder={t('automation.placeholder_product_id')} />
            </Field>
            <Field label={t('automation.field_intro_text_optional')}>
                <textarea className={textareaCls} rows={2} value={d.body ?? ''} onChange={e => set('body', e.target.value)} placeholder={t('automation.placeholder_product_intro')} />
            </Field>
        </>
    );
}

function GoogleSheetsFields({ d, set }) {
    const { t } = useTranslation();
    const mode = d.mode ?? 'append';
    return (
        <>
            <GoogleWarning />
            <Field label={t('automation.field_sheets_mode')}>
                <select className={selectCls} value={mode} onChange={e => set('mode', e.target.value)}>
                    <option value="append">{t('automation.sheets_append')}</option>
                    <option value="read">{t('automation.sheets_read')}</option>
                </select>
            </Field>
            <Field label={t('automation.field_spreadsheet_id_required')}>
                <input className={inputCls} value={d.spreadsheet_id ?? ''} onChange={e => set('spreadsheet_id', e.target.value)} placeholder="1AbC...xyz" />
            </Field>
            <Field label={t('automation.field_range_required')}>
                <input className={inputCls} value={d.range ?? ''} onChange={e => set('range', e.target.value)} placeholder="Sheet1!A:D" />
            </Field>
            {mode === 'append' ? (
                <Field label={t('automation.field_row_values_required')}>
                    <textarea className={textareaCls} rows={4} value={d.values ?? ''} onChange={e => set('values', e.target.value)} placeholder={t('automation.placeholder_row_values')} />
                </Field>
            ) : (
                <Field label={t('automation.field_result_var')}>
                    <input className={inputCls} value={d.result_var ?? ''} onChange={e => set('result_var', e.target.value)} placeholder="sheet" />
                </Field>
            )}
        </>
    );
}

function GoogleDocsFields({ d, set }) {
    const { t } = useTranslation();
    return (
        <>
            <GoogleWarning />
            <Field label={t('automation.field_template_doc_id_required')}>
                <input className={inputCls} value={d.template_doc_id ?? ''} onChange={e => set('template_doc_id', e.target.value)} placeholder="1AbC...xyz" />
            </Field>
            <Field label={t('automation.field_doc_title')}>
                <input className={inputCls} value={d.title ?? ''} onChange={e => set('title', e.target.value)} placeholder={t('automation.placeholder_doc_title', { token: '{{contact.name}}' })} />
            </Field>
            <Field label={t('automation.field_replacements_optional')}>
                <textarea className={textareaCls} rows={4} value={d.replacements ?? ''} onChange={e => set('replacements', e.target.value)} placeholder={t('automation.placeholder_replacements')} />
            </Field>
            <CheckField label={t('automation.field_send_doc_link')} checked={d.send_link} onChange={v => set('send_link', v)} />
        </>
    );
}

function GoogleFormsFields({ d, set }) {
    const { t } = useTranslation();
    const mode = d.mode ?? 'send_link';
    return (
        <>
            <GoogleWarning />
            <Field label={t('automation.field_forms_mode')}>
                <select className={selectCls} value={mode} onChange={e => set('mode', e.target.value)}>
                    <option value="send_link">{t('automation.forms_send_link')}</option>
                    <option value="read_response">{t('automation.forms_read_response')}</option>
                </select>
            </Field>
            <Field label={t('automation.field_form_id_required')}>
                <input className={inputCls} value={d.form_id ?? ''} onChange={e => set('form_id', e.target.value)} placeholder="1AbC...xyz" />
            </Field>
            {mode === 'send_link' ? (
                <>
                    <Field label={t('automation.field_message_body_optional')}>
                        <textarea className={textareaCls} rows={2} value={d.body ?? ''} onChange={e => set('body', e.target.value)} placeholder={t('automation.placeholder_form_intro')} />
                    </Field>
                    <CheckField label={t('automation.field_send_form_link')} checked={d.send_link ?? true} onChange={v => set('send_link', v)} />
                </>
            ) : (
                <Field label={t('automation.field_result_var')}>
                    <input className={inputCls} value={d.result_var ?? ''} onChange={e => set('result_var', e.target.value)} placeholder="form" />
                </Field>
            )}
        </>
    );
}

function WebhookFields({ d, set }) {
    const { t } = useTranslation();
    return (
        <>
            <Field label={t('automation.field_url_required')}>
                <input className={inputCls} value={d.url ?? ''} onChange={e => set('url', e.target.value)} placeholder="https://example.com/webhook" />
            </Field>
            <Field label={t('automation.field_method')}>
                <select className={selectCls} value={d.method ?? 'POST'} onChange={e => set('method', e.target.value)}>
                    <option>POST</option>
                    <option>GET</option>
                    <option>PUT</option>
                    <option>PATCH</option>
                </select>
            </Field>
            <Field label={t('automation.field_headers_json_optional')}>
                <textarea className={textareaCls} rows={3} value={d.headers ?? ''} onChange={e => set('headers', e.target.value)} placeholder={'{"Authorization": "Bearer {{token}}"}'} />
            </Field>
            <Field label={t('automation.field_payload_json_optional')}>
                <textarea className={textareaCls} rows={4} value={d.payload ?? ''} onChange={e => set('payload', e.target.value)} placeholder={'{"contact_id": "{{contact.id}}"}'} />
            </Field>
        </>
    );
}

/* ─── Edge defaults ──────────────────────────────────────────── */
const defaultEdgeOptions = {
    animated: true,
    style: { stroke: '#6366f1', strokeWidth: 2 },
    markerEnd: { type: MarkerType.ArrowClosed, color: '#6366f1' },
};

/* ─── Builder Inner ──────────────────────────────────────────── */
function makeNode(type, existingCount, position) {
    const id = `${type}-${Date.now()}`;
    return {
        id,
        type: 'automationNode',
        position: position ?? { x: 300, y: 200 + existingCount * 100 },
        data: { ...Object.fromEntries((extendedNodes[type]?.fields ?? []).filter(f=>f.default!==undefined).map(f=>[f.key,f.default])), nodeType: type, label: '', configured: false },
    };
}

function serializeNodes(nodes) {
    return nodes.map(n => ({
        id: n.id,
        type: n.type === 'triggerNode' ? 'trigger' : (n.data?.nodeType ?? n.type),
        position: n.position,
        data: n.data,
    }));
}

const TRIGGER_NODE_ID = 'trigger-1';

function deserializeNodes(raw) {
    return (raw ?? []).map(n => {
        // The trigger node is seeded by the backend as type 'trigger'; older saves use 'triggerNode'.
        if (n.data?.triggerType !== undefined || n.type === 'triggerNode' || n.type === 'trigger') {
            return { ...n, type: 'triggerNode', deletable: false, data: { ...n.data } };
        }
        return {
            ...n,
            type: 'automationNode',
            data: { ...n.data, nodeType: n.data?.nodeType ?? n.type },
        };
    });
}

// Guarantee a single, non-deletable trigger node anchored at the top of the canvas, with its
// display synced to the automation's trigger_type (the backend source of truth).
function withTriggerNode(nodes, triggerType) {
    if (!nodes.some(n => n.type === 'triggerNode')) {
        return [
            { id: TRIGGER_NODE_ID, type: 'triggerNode', position: { x: 250, y: 50 }, deletable: false, data: { triggerType, label: 'Trigger' } },
            ...nodes,
        ];
    }
    return nodes.map(n => n.type === 'triggerNode'
        ? { ...n, deletable: false, data: { ...n.data, triggerType } }
        : n);
}

/* ─── Test & AI Generate modals ──────────────────────────────── */
const overlayStyle = { position: 'fixed', inset: 0, zIndex: 60, background: 'rgba(15,23,42,0.45)', display: 'flex', alignItems: 'center', justifyContent: 'center', padding: 16 };
const modalStyle = { background: '#fff', borderRadius: 16, boxShadow: '0 20px 60px rgba(0,0,0,0.25)', maxWidth: '92vw', overflow: 'hidden' };
const modalHeaderStyle = { display: 'flex', alignItems: 'center', justifyContent: 'space-between', padding: '14px 16px', borderBottom: '1px solid #f0f0f0' };
const modalFooterStyle = { display: 'flex', alignItems: 'center', justifyContent: 'flex-end', gap: 8, padding: '12px 16px', borderTop: '1px solid #f0f0f0', background: '#fafafa' };
const iconBtnStyle = { background: 'none', border: 'none', cursor: 'pointer', color: '#9ca3af', display: 'flex' };
const ghostBtnStyle = { borderRadius: 8, padding: '7px 14px', fontSize: 12, fontWeight: 600, border: '1px solid #e5e7eb', background: '#fff', color: '#374151', cursor: 'pointer' };
const primaryBtnStyle = { display: 'flex', alignItems: 'center', gap: 6, borderRadius: 8, padding: '7px 14px', fontSize: 12, fontWeight: 600, border: 'none', background: '#6366f1', color: '#fff', cursor: 'pointer' };
const chipBtnStyle = { borderRadius: 999, padding: '5px 10px', fontSize: 10.5, fontWeight: 500, border: '1px solid #e5e7eb', background: '#fff', color: '#475569', cursor: 'pointer' };

const RESULT_META = {
    ok:      { Icon: CheckCircle2, color: '#16a34a' },
    skipped: { Icon: MinusCircle,  color: '#6b7280' },
    error:   { Icon: AlertCircle,  color: '#dc2626' },
};

function TestResultModal({ result, loading, onClose, onRerun }) {
    const { t } = useTranslation();
    return (
        <div onClick={onClose} style={overlayStyle}>
            <div onClick={e => e.stopPropagation()} style={{ ...modalStyle, width: 560, maxHeight: '82vh', display: 'flex', flexDirection: 'column' }}>
                <div style={modalHeaderStyle}>
                    <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                        <span style={{ display: 'flex', width: 30, height: 30, borderRadius: 8, background: '#eef2ff', color: '#6366f1', alignItems: 'center', justifyContent: 'center' }}><FlaskConical size={16} /></span>
                        <div>
                            <div style={{ fontSize: 14, fontWeight: 700, color: '#111827' }}>{t('automation.test_title')}</div>
                            <div style={{ fontSize: 11, color: '#6b7280' }}>{t('automation.test_subtitle')}</div>
                        </div>
                    </div>
                    <button onClick={onClose} style={iconBtnStyle}><X size={18} /></button>
                </div>

                <div style={{ padding: 16, overflowY: 'auto', flex: 1 }}>
                    {loading ? (
                        <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center', padding: '36px 0' }}>
                            <Loader2 size={22} className="animate-spin" style={{ color: '#6366f1' }} />
                            <span style={{ fontSize: 12, color: '#6b7280', marginTop: 8 }}>{t('automation.test_running')}</span>
                        </div>
                    ) : !result ? null : !result.ok ? (
                        <div style={{ display: 'flex', gap: 8, background: '#fff7ed', border: '1px solid #fed7aa', borderRadius: 10, padding: '12px 14px', fontSize: 12.5, color: '#9a3412' }}>
                            <AlertTriangle size={16} style={{ flexShrink: 0, marginTop: 1 }} /> {result.error}
                        </div>
                    ) : (
                        <>
                            <div style={{ display: 'flex', alignItems: 'center', gap: 8, marginBottom: 12, fontSize: 11.5, color: '#475569', background: '#f8fafc', border: '1px solid #e2e8f0', borderRadius: 8, padding: '8px 10px' }}>
                                <span>{t('automation.test_steps_count', { count: result.steps.length })}</span>
                                {result.contact && <span style={{ color: '#94a3b8' }}>· {t('automation.test_sample_contact', { name: result.contact.name })}</span>}
                            </div>
                            <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
                                {result.steps.map((s, i) => {
                                    const meta = RESULT_META[s.result] ?? RESULT_META.ok;
                                    const def = NODE_DEFS[s.node_type];
                                    return (
                                        <div key={i} style={{ display: 'flex', gap: 10, padding: '10px 12px', border: '1px solid #eef0f2', borderRadius: 10, background: '#fff' }}>
                                            <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', width: 18 }}>
                                                <span style={{ fontSize: 9, fontWeight: 700, color: '#9ca3af' }}>{i + 1}</span>
                                                <span style={{ marginTop: 4, color: def?.color ?? '#6b7280', display: 'flex' }}><NodeIcon nodeType={s.node_type} size={15} /></span>
                                            </div>
                                            <div style={{ flex: 1, minWidth: 0 }}>
                                                <div style={{ display: 'flex', alignItems: 'center', gap: 6 }}>
                                                    <span style={{ fontSize: 12, fontWeight: 600, color: '#111827' }}>{s.label || (def ? t(def.labelKey) : s.node_type)}</span>
                                                    {s.branch && <span style={{ fontSize: 9, fontWeight: 700, padding: '1px 6px', borderRadius: 999, background: s.branch === 'true' ? '#dcfce7' : '#fee2e2', color: s.branch === 'true' ? '#166534' : '#991b1b' }}>{s.branch === 'true' ? t('common.yes') : t('common.no')}</span>}
                                                </div>
                                                <div style={{ fontSize: 11, color: '#6b7280', marginTop: 2, wordBreak: 'break-word' }}>{s.message}</div>
                                            </div>
                                            <meta.Icon size={16} style={{ color: meta.color, flexShrink: 0, marginTop: 2 }} />
                                        </div>
                                    );
                                })}
                            </div>
                            <div style={{ display: 'flex', gap: 6, marginTop: 14, fontSize: 10.5, color: '#94a3b8', alignItems: 'flex-start' }}>
                                <AlertCircle size={13} style={{ flexShrink: 0, marginTop: 1 }} /> {t('automation.test_disclaimer')}
                            </div>
                        </>
                    )}
                </div>

                <div style={modalFooterStyle}>
                    <button onClick={onClose} style={ghostBtnStyle}>{t('common.close')}</button>
                    <button onClick={onRerun} disabled={loading} style={{ ...primaryBtnStyle, opacity: loading ? 0.6 : 1 }}>
                        {loading ? <Loader2 size={13} className="animate-spin" /> : <FlaskConical size={13} />} {t('automation.test_run_again')}
                    </button>
                </div>
            </div>
        </div>
    );
}

function deleteTargetName(node, t) {
    const nt = node?.data?.nodeType;
    const def = NODE_DEFS[nt];
    return node?.data?.label || (def ? t(def.labelKey) : nt) || t('automation.this_node');
}

function ConfirmDeleteModal({ target, onCancel, onConfirm }) {
    const { t } = useTranslation();
    const nodes = target?.nodes ?? [];
    const body = nodes.length > 1
        ? t('automation.delete_nodes_confirm_body', { count: nodes.length })
        : t('automation.delete_node_confirm_body', { name: deleteTargetName(nodes[0], t) });
    return (
        <div onClick={onCancel} style={overlayStyle}>
            <div onClick={e => e.stopPropagation()} style={{ ...modalStyle, width: 384 }}>
                <div style={{ padding: '20px 20px 4px', display: 'flex', gap: 12 }}>
                    <span style={{ display: 'flex', width: 38, height: 38, borderRadius: 10, background: '#fef2f2', color: '#dc2626', alignItems: 'center', justifyContent: 'center', flexShrink: 0 }}><Trash2 size={18} /></span>
                    <div>
                        <div style={{ fontSize: 14, fontWeight: 700, color: '#111827' }}>{t('automation.delete_node_confirm_title')}</div>
                        <div style={{ fontSize: 12, color: '#6b7280', marginTop: 4, lineHeight: 1.5 }}>{body}</div>
                    </div>
                </div>
                <div style={modalFooterStyle}>
                    <button onClick={onCancel} style={ghostBtnStyle}>{t('common.cancel')}</button>
                    <button onClick={onConfirm} style={{ ...primaryBtnStyle, background: '#dc2626' }}><Trash2 size={13} /> {t('common.delete')}</button>
                </div>
            </div>
        </div>
    );
}

const AI_EXAMPLES = ['automation.ai_example_welcome', 'automation.ai_example_abandoned', 'automation.ai_example_faq'];

function AiGenerateModal({ prompt, setPrompt, loading, error, onClose, onGenerate }) {
    const { t } = useTranslation();
    return (
        <div onClick={loading ? undefined : onClose} style={overlayStyle}>
            <div onClick={e => e.stopPropagation()} style={{ ...modalStyle, width: 520 }}>
                <div style={modalHeaderStyle}>
                    <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                        <span style={{ display: 'flex', width: 30, height: 30, borderRadius: 8, background: '#faf5ff', color: '#7c3aed', alignItems: 'center', justifyContent: 'center' }}><Sparkles size={16} /></span>
                        <div>
                            <div style={{ fontSize: 14, fontWeight: 700, color: '#111827' }}>{t('automation.ai_title')}</div>
                            <div style={{ fontSize: 11, color: '#6b7280' }}>{t('automation.ai_subtitle')}</div>
                        </div>
                    </div>
                    <button onClick={onClose} disabled={loading} style={iconBtnStyle}><X size={18} /></button>
                </div>
                <div style={{ padding: 16 }} className="space-y-3">
                    <textarea autoFocus rows={5} className={textareaCls} value={prompt} onChange={e => setPrompt(e.target.value)} placeholder={t('automation.ai_placeholder')} disabled={loading} />
                    <div style={{ display: 'flex', flexWrap: 'wrap', gap: 6 }}>
                        {AI_EXAMPLES.map(k => (
                            <button key={k} disabled={loading} onClick={() => setPrompt(t(k))} style={chipBtnStyle}>{t(k)}</button>
                        ))}
                    </div>
                    {error && <div style={{ display: 'flex', gap: 8, background: '#fef2f2', border: '1px solid #fecaca', borderRadius: 8, padding: '8px 10px', fontSize: 11.5, color: '#b91c1c' }}><AlertTriangle size={14} style={{ flexShrink: 0, marginTop: 1 }} />{error}</div>}
                    <div style={{ fontSize: 10.5, color: '#94a3b8', display: 'flex', gap: 6, alignItems: 'flex-start' }}><AlertCircle size={13} style={{ flexShrink: 0, marginTop: 1 }} />{t('automation.ai_disclaimer')}</div>
                </div>
                <div style={modalFooterStyle}>
                    <button onClick={onClose} disabled={loading} style={ghostBtnStyle}>{t('common.cancel')}</button>
                    <button onClick={onGenerate} disabled={loading || !prompt.trim()} className="ai-glow" style={{ ...primaryBtnStyle, background: '#7c3aed', opacity: (loading || !prompt.trim()) ? 0.6 : 1 }}>
                        {loading ? <><Loader2 size={13} className="animate-spin" /> {t('automation.ai_generating')}</> : <><Sparkles size={13} /> {t('automation.ai_generate')}</>}
                    </button>
                </div>
            </div>
        </div>
    );
}

function AutomationBuilderInner({ automation: initial }) {
    const { t } = useTranslation();
    const [automation, setAutomation] = useState(initial);
    const [nodes, setNodes, onNodesChange] = useNodesState(
        withTriggerNode(deserializeNodes(initial.nodes ?? []), initial.trigger_type ?? '')
    );
    const [edges, setEdges, onEdgesChange] = useEdgesState(
        (initial.edges ?? []).map(e => ({
            ...e,
            animated: true,
            style: { stroke: e.sourceHandle === 'false' ? '#ef4444' : '#6366f1', strokeWidth: 2 },
            markerEnd: { type: MarkerType.ArrowClosed, color: e.sourceHandle === 'false' ? '#ef4444' : '#6366f1' },
        }))
    );
    const [saving, setSaving] = useState(false);
    const [selectedNode, setSelectedNode] = useState(null);
    const [copied, setCopied] = useState(false);
    const [generatingToken, setGeneratingToken] = useState(false);
    const [search, setSearch] = useState('');
    const [testing, setTesting] = useState(false);
    const [testResult, setTestResult] = useState(null);
    const [showTest, setShowTest] = useState(false);
    const [aiOpen, setAiOpen] = useState(false);
    const [aiPrompt, setAiPrompt] = useState('');
    const [aiLoading, setAiLoading] = useState(false);
    const [aiError, setAiError] = useState(null);
    const [confirmDelete, setConfirmDelete] = useState(null);

    const webhookUrl = automation.trigger_token
        ? `${window.location.origin}/webhooks/automation/${automation.trigger_token}`
        : null;

    const copyWebhookUrl = () => {
        if (!webhookUrl) return;
        navigator.clipboard.writeText(webhookUrl).then(() => {
            setCopied(true);
            setTimeout(() => setCopied(false), 2000);
        });
    };

    const generateToken = () => {
        setGeneratingToken(true);
        axios.post(route('client.automations.generate-token', automation.uuid))
            .then(res => setAutomation(a => ({ ...a, trigger_token: res.data.trigger_token })))
            .finally(() => setGeneratingToken(false));
    };

    const onConnect = useCallback((params) => {
        const isNo = params.sourceHandle === 'false';
        setEdges(eds => addEdge({
            ...params,
            animated: true,
            style: { stroke: isNo ? '#ef4444' : '#6366f1', strokeWidth: 2 },
            markerEnd: { type: MarkerType.ArrowClosed, color: isNo ? '#ef4444' : '#6366f1' },
        }, eds));
    }, []);

    const { screenToFlowPosition, deleteElements } = useReactFlow();

    // Single confirmation gate for every delete path (node trash icon, panel button, Delete key).
    // deleteElements + the Delete key both run through onBeforeDelete, so we resolve its promise
    // from the modal: confirm → proceed, cancel → abort. Edge-only deletions are not confirmed.
    const onBeforeDelete = useCallback(({ nodes: delNodes, edges: delEdges }) => {
        if (!delNodes || delNodes.length === 0) return Promise.resolve(true);
        return new Promise((resolve) => setConfirmDelete({ nodes: delNodes, edges: delEdges, resolve }));
    }, []);

    const resolveDelete = (ok) => {
        confirmDelete?.resolve?.(ok);
        if (ok) setSelectedNode(null);
        setConfirmDelete(null);
    };

    const addNode = (type, position) => {
        const n = makeNode(type, nodes.length, position);
        setNodes(nds => [...nds, n]);
    };

    const onDragStart = (e, type) => {
        e.dataTransfer.setData('application/automation-node', type);
        e.dataTransfer.effectAllowed = 'move';
    };

    const onDragOver = useCallback((e) => {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
    }, []);

    const onDrop = useCallback((e) => {
        e.preventDefault();
        const type = e.dataTransfer.getData('application/automation-node');
        if (!type || !NODE_DEFS[type]) return;
        const position = screenToFlowPosition({ x: e.clientX, y: e.clientY });
        setNodes(nds => [...nds, makeNode(type, nds.length, position)]);
    }, [screenToFlowPosition, setNodes]);

    const updateNodeData = (nodeId, newData) => {
        setNodes(nds => nds.map(n => n.id === nodeId ? { ...n, data: { ...n.data, ...newData } } : n));
        setSelectedNode(prev => prev?.id === nodeId ? { ...prev, data: { ...prev.data, ...newData } } : prev);
    };

    // Routes through deleteElements so it hits onBeforeDelete (the confirmation gate); ReactFlow
    // also removes any edges connected to the node automatically.
    const deleteNode = (nodeId) => {
        deleteElements({ nodes: [{ id: nodeId }] });
    };

    // Opens the config panel for a node by id (used by the on-node settings icon).
    const configureNode = (nodeId) => {
        const node = nodes.find(n => n.id === nodeId);
        if (node) setSelectedNode(node);
    };

    // Trigger lives on the canvas as a node; trigger_type/trigger_config stay the saved source of truth.
    const setTriggerType = (value) => {
        setAutomation(a => ({ ...a, trigger_type: value, trigger_config: {} }));
        setNodes(nds => nds.map(n => n.type === 'triggerNode' ? { ...n, data: { ...n.data, triggerType: value } } : n));
    };

    const setTriggerConfig = (patch) =>
        setAutomation(a => ({ ...a, trigger_config: { ...(a.trigger_config ?? {}), ...patch } }));

    const save = (status = automation.status) => {
        if (status === 'active') {
            const reachable = new Set();
            const visit = (id) => {
                if (reachable.has(id)) return;
                reachable.add(id);
                edges.filter(e => e.source === id).forEach(e => visit(e.target));
            };
            nodes.filter(n => n.type === 'triggerNode').forEach(n => visit(n.id));
            if (nodes.some(n => reachable.has(n.id) && n.data?.nodeType === 'send_whatsapp' && !n.data.body?.trim())) {
                window.alert('Preencha o corpo da mensagem no bloco Enviar WhatsApp antes de salvar a automação ativa.');
                return;
            }
        }
        setSaving(true);
        router.put(route('client.automations.update', automation.uuid), {
            nodes: serializeNodes(nodes),
            edges: edges.map(e => ({ id: e.id, source: e.source, target: e.target, sourceHandle: e.sourceHandle, targetHandle: e.targetHandle })),
            trigger_type: automation.trigger_type,
            trigger_config: automation.trigger_config,
            name: automation.name,
            status,
        }, {
            preserveScroll: true,
            onError: errors => window.alert(Object.values(errors).join('\n')),
            onFinish: () => setSaving(false),
            onSuccess: () => setAutomation(a => ({ ...a, status })),
        });
    };

    const toggleStatus = () => {
        const newStatus = automation.status === 'active' ? 'paused' : 'active';
        save(newStatus);
    };

    // Dry-run the live canvas through the engine and show a step-by-step trace (no real sends).
    const runTest = () => {
        setShowTest(true);
        setTesting(true);
        setTestResult(null);
        axios.post(route('client.automations.test', automation.uuid), {
            nodes: serializeNodes(nodes),
            edges: edges.map(e => ({ id: e.id, source: e.source, target: e.target, sourceHandle: e.sourceHandle })),
            trigger_type: automation.trigger_type,
            trigger_config: automation.trigger_config,
        })
            .then(res => setTestResult(res.data))
            .catch(err => setTestResult({ ok: false, error: err.response?.data?.error || err.response?.data?.message || t('automation.test_failed'), steps: [] }))
            .finally(() => setTesting(false));
    };

    // Replace the canvas with an AI-generated (or otherwise supplied) graph for review before saving.
    const applyGraph = (graph) => {
        setNodes(withTriggerNode(deserializeNodes(graph.nodes ?? []), graph.trigger_type ?? ''));
        setEdges((graph.edges ?? []).map(e => ({
            ...e,
            animated: true,
            style: { stroke: e.sourceHandle === 'false' ? '#ef4444' : '#6366f1', strokeWidth: 2 },
            markerEnd: { type: MarkerType.ArrowClosed, color: e.sourceHandle === 'false' ? '#ef4444' : '#6366f1' },
        })));
        setAutomation(a => ({ ...a, trigger_type: graph.trigger_type ?? a.trigger_type, trigger_config: graph.trigger_config ?? a.trigger_config, name: graph.name || a.name }));
        setSelectedNode(null);
    };

    const generateAi = () => {
        setAiLoading(true);
        setAiError(null);
        axios.post(route('client.automations.generate'), { prompt: aiPrompt, persist: false })
            .then(res => {
                if (res.data?.ok && res.data.graph) {
                    applyGraph(res.data.graph);
                    setAiOpen(false);
                    setAiPrompt('');
                } else {
                    setAiError(res.data?.error || t('automation.ai_failed'));
                }
            })
            .catch(err => setAiError(err.response?.data?.error || err.response?.data?.message || t('automation.ai_failed')))
            .finally(() => setAiLoading(false));
    };

    const q = search.trim().toLowerCase();
    const chooseMessageTrigger = () => {
        setTriggerType('message.received');
        const trigger = nodes.find(n => n.type === 'triggerNode');
        if (trigger) setSelectedNode({ ...trigger, data: { ...trigger.data, triggerType: 'message.received' } });
    };
    const grouped = CATEGORY_ORDER.map(cat => ({
        cat,
        items: Object.entries(NODE_DEFS).filter(([type, def]) =>
            def.category === cat && (q === '' || type.includes(q) || t(def.labelKey).toLowerCase().includes(q))
        ),
    })).filter(g => g.items.length > 0);

    return (
        <NodeActionsContext.Provider value={{ onConfigure: configureNode, onDelete: deleteNode }}>
        <div style={{ display: 'flex', height: 'calc(100vh - 130px)', borderRadius: 16, overflow: 'hidden', border: '1px solid #e5e7eb', boxShadow: '0 4px 24px rgba(0,0,0,0.07)' }}>
            {/* ── Sidebar ── */}
            <div style={{ width: 234, display: 'flex', flexDirection: 'column', background: '#fafafa', borderRight: '1px solid #e5e7eb', overflowY: 'auto' }}>
                {/* Node palette */}
                <div style={{ padding: 12, flex: 1 }}>
                    <div style={{ fontSize: 10, fontWeight: 700, color: '#9ca3af', letterSpacing: '0.08em', textTransform: 'uppercase', marginBottom: 4 }}>{t('automation.add_node')}</div>
                    <div style={{ display: 'flex', alignItems: 'center', gap: 4, fontSize: 9, color: '#94a3b8', marginBottom: 8 }}>
                        <GripVertical size={10} /> {t('automation.drag_node_hint')}
                    </div>

                    <input
                        value={search}
                        onChange={e => setSearch(e.target.value)}
                        placeholder={t('automation.search_nodes')}
                        style={{ width: '100%', borderRadius: 8, border: '1px solid #e5e7eb', background: '#fff', padding: '6px 9px', fontSize: 11, marginBottom: 10, boxSizing: 'border-box', outline: 'none' }}
                    />

                    {(!q || 'mensagem recebida gatilho'.includes(q)) && (
                        <button type="button" onClick={chooseMessageTrigger} className="mb-4 flex min-h-11 w-full items-center gap-2 rounded-lg border border-violet-200 bg-violet-50 px-3 py-2 text-left text-xs font-medium text-violet-800">
                            <Zap size={14} /> Mensagem recebida
                        </button>
                    )}
                    {grouped.map(({ cat, items }) => (
                        <details key={`${cat}-${Boolean(q)}`} open={!!q || ['send','social'].includes(cat)} style={{ marginBottom: 12 }}>
                            <summary style={{ fontSize: 9, fontWeight: 700, color: '#94a3b8', letterSpacing: '0.1em', textTransform: 'uppercase', marginBottom: 5 }}>
                                {({social:'Redes sociais',crm:'CRM e tarefas'})[cat] ?? t(`automation.category_${cat}`)} <span>({items.length})</span>
                            </summary>
                            <div style={{ display: 'flex', flexDirection: 'column', gap: 3 }}>
                                {items.map(([type, def]) => (
                                    <button
                                        key={type}
                                        draggable
                                        onDragStart={e => onDragStart(e, type)}
                                        onClick={() => addNode(type)}
                                        title={t('automation.drag_node_hint')}
                                        style={{
                                            display: 'flex', alignItems: 'center', gap: 8, padding: '7px 10px',
                                            borderRadius: 8, border: '1px solid transparent', background: 'white',
                                            cursor: 'grab', textAlign: 'left', fontSize: 11, color: '#374151',
                                            transition: 'all 0.12s', boxShadow: '0 1px 3px rgba(0,0,0,0.05)',
                                        }}
                                        onMouseEnter={e => { e.currentTarget.style.borderColor = def.color; e.currentTarget.style.background = def.bg; }}
                                        onMouseLeave={e => { e.currentTarget.style.borderColor = 'transparent'; e.currentTarget.style.background = 'white'; }}
                                    >
                                        <span style={{ color: def.color, display: 'flex', flexShrink: 0 }}>
                                            <NodeIcon nodeType={type} size={13} />
                                        </span>
                                        <span style={{ fontWeight: 500 }}>{t(def.labelKey)}</span>
                                        <GripVertical size={11} style={{ marginLeft: 'auto', color: '#d1d5db', flexShrink: 0 }} />
                                    </button>
                                ))}
                            </div>
                        </details>
                    ))}
                </div>
            </div>

            {/* ── Canvas ── */}
            <div style={{ flex: 1, position: 'relative' }} onDrop={onDrop} onDragOver={onDragOver}>
                <ReactFlow
                    nodes={nodes}
                    edges={edges}
                    onNodesChange={onNodesChange}
                    onEdgesChange={onEdgesChange}
                    onConnect={onConnect}
                    onNodeClick={(_, node) => setSelectedNode(node)}
                    onPaneClick={() => setSelectedNode(null)}
                    onBeforeDelete={onBeforeDelete}
                    nodeTypes={nodeTypes}
                    defaultEdgeOptions={defaultEdgeOptions}
                    fitView
                    deleteKeyCode="Delete"
                >
                    <Background color="#e5e7eb" gap={20} />
                    <Controls style={{ bottom: 20, left: 20 }} />
                    <MiniMap
                        nodeColor={n => NODE_DEFS[n.data?.nodeType]?.color ?? '#6366f1'}
                        style={{ background: '#f8fafc', border: '1px solid #e5e7eb', borderRadius: 8 }}
                    />

                    {/* Top toolbar */}
                    <Panel position="top-right">
                        <div style={{ display: 'flex', alignItems: 'center', gap: 8, background: '#fff', borderRadius: 12, border: '1px solid #e5e7eb', padding: '8px 12px', boxShadow: '0 4px 12px rgba(0,0,0,0.08)' }}>
                            <div style={{ display: 'flex', alignItems: 'center', gap: 6, paddingRight: 8, borderRight: '1px solid #f0f0f0' }}>
                                <div style={{ width: 8, height: 8, borderRadius: '50%', background: automation.status === 'active' ? '#10b981' : '#f59e0b' }} />
                                <span style={{ fontSize: 11, color: '#6b7280', fontWeight: 500 }}>{t(`automation.status_${automation.status}`)}</span>
                            </div>
                            <button onClick={() => { setAiError(null); setAiOpen(true); }} title={t('automation.ai_title')} style={{
                                display: 'flex', alignItems: 'center', gap: 6, borderRadius: 8,
                                background: '#faf5ff', padding: '6px 12px', fontSize: 12, fontWeight: 600,
                                color: '#7c3aed', border: '1px solid #e9d5ff', cursor: 'pointer', transition: 'all 0.15s',
                            }}>
                                <Sparkles size={13} /> {t('automation.ai_generate_short')}
                            </button>
                            <button onClick={runTest} disabled={testing} title={t('automation.test_title')} style={{
                                display: 'flex', alignItems: 'center', gap: 6, borderRadius: 8,
                                background: '#eef2ff', padding: '6px 12px', fontSize: 12, fontWeight: 600,
                                color: '#4f46e5', border: '1px solid #e0e7ff', cursor: testing ? 'not-allowed' : 'pointer',
                                opacity: testing ? 0.7 : 1, transition: 'all 0.15s',
                            }}>
                                {testing ? <Loader2 size={13} className="animate-spin" /> : <FlaskConical size={13} />} {t('automation.test')}
                            </button>
                            <button onClick={() => save()} disabled={saving} style={{
                                display: 'flex', alignItems: 'center', gap: 6, borderRadius: 8,
                                background: '#6366f1', padding: '6px 14px', fontSize: 12, fontWeight: 600,
                                color: '#fff', border: 'none', cursor: saving ? 'not-allowed' : 'pointer',
                                opacity: saving ? 0.7 : 1, transition: 'all 0.15s',
                            }}>
                                <Save size={13} /> {saving ? t('automation.saving') : t('common.save')}
                            </button>
                            <button onClick={toggleStatus} style={{
                                display: 'flex', alignItems: 'center', gap: 6, borderRadius: 8,
                                padding: '6px 14px', fontSize: 12, fontWeight: 600, border: 'none', cursor: 'pointer',
                                background: automation.status === 'active' ? '#fef3c7' : '#dcfce7',
                                color: automation.status === 'active' ? '#92400e' : '#166534',
                                transition: 'all 0.15s',
                            }}>
                                {automation.status === 'active'
                                    ? <><Pause size={13} /> {t('automation.pause')}</>
                                    : <><Play size={13} /> {t('automation.activate')}</>}
                            </button>
                        </div>
                    </Panel>

                    {/* Hint */}
                    <Panel position="bottom-center">
                        <div style={{ fontSize: 10, color: '#9ca3af', background: '#fff', borderRadius: 999, padding: '4px 12px', border: '1px solid #f0f0f0', boxShadow: '0 1px 4px rgba(0,0,0,0.05)' }}>
                            {t('automation.canvas_hint')}
                        </div>
                    </Panel>
                </ReactFlow>

                {/* Node config panel */}
                {selectedNode && selectedNode.type === 'automationNode' && (
                    <>
                        <div onClick={() => setSelectedNode(null)} style={{ position: 'absolute', inset: 0, zIndex: 9 }} />
                        <div style={{ position: 'absolute', top: 0, right: 0, bottom: 0, zIndex: 10, pointerEvents: 'all' }}>
                            <ConfigPanel
                                node={selectedNode}
                                onClose={() => setSelectedNode(null)}
                                onChange={updateNodeData}
                            />
                            <button
                                onClick={() => deleteNode(selectedNode.id)}
                                style={{
                                    position: 'absolute', bottom: 16, left: 16, right: 16,
                                    display: 'flex', alignItems: 'center', justifyContent: 'center', gap: 6,
                                    background: '#fef2f2', border: '1px solid #fecaca', borderRadius: 8,
                                    padding: '8px 0', fontSize: 12, fontWeight: 600, color: '#dc2626',
                                    cursor: 'pointer', zIndex: 11,
                                }}
                            >
                                <Trash2 size={13} /> {t('automation.delete_node')}
                            </button>
                        </div>
                    </>
                )}

                {/* Trigger config panel */}
                {selectedNode && selectedNode.type === 'triggerNode' && (
                    <>
                        <div onClick={() => setSelectedNode(null)} style={{ position: 'absolute', inset: 0, zIndex: 9 }} />
                        <div style={{ position: 'absolute', top: 0, right: 0, bottom: 0, zIndex: 10, pointerEvents: 'all' }}>
                            <TriggerConfigPanel
                                automation={automation}
                                onTypeChange={setTriggerType}
                                onConfigChange={setTriggerConfig}
                                webhookUrl={webhookUrl}
                                copied={copied}
                                onCopy={copyWebhookUrl}
                                onGenerateToken={generateToken}
                                generatingToken={generatingToken}
                                onClose={() => setSelectedNode(null)}
                            />
                        </div>
                    </>
                )}
            </div>

            {showTest && <TestResultModal result={testResult} loading={testing} onClose={() => setShowTest(false)} onRerun={runTest} />}
            {aiOpen && <AiGenerateModal prompt={aiPrompt} setPrompt={setAiPrompt} loading={aiLoading} error={aiError} onClose={() => setAiOpen(false)} onGenerate={generateAi} />}
            {confirmDelete && <ConfirmDeleteModal target={confirmDelete} onCancel={() => resolveDelete(false)} onConfirm={() => resolveDelete(true)} />}
        </div>
        </NodeActionsContext.Provider>
    );
}

/* ─── Page ───────────────────────────────────────────────────── */
export default function AutomationBuilder({ automation }) {
    const { t } = useTranslation();
    return (
        <ClientLayout title={automation.name}>
            <Head title={`${automation.name} · ${t('automation.builder')}`} />
            <div className="space-y-3">
                <div className="flex items-center gap-3">
                    <Link href={route('client.automations.index')} className="flex items-center justify-center w-8 h-8 rounded-lg bg-white border border-gray-200 text-gray-400 hover:text-gray-700 hover:border-gray-300 shadow-sm transition">
                        <ArrowLeft className="h-4 w-4" />
                    </Link>
                    <div>
                        <h2 className="text-lg font-bold text-neutral-900 dark:text-neutral-100 leading-tight">{automation.name}</h2>
                        <p className="text-xs text-neutral-500">{t('automation.builder')}</p>
                    </div>
                    <div className="ml-auto flex items-center gap-2">
                        <Link href={route('client.automations.runs', automation.uuid)} className="flex items-center gap-1.5 text-xs text-neutral-500 hover:text-neutral-700 transition">
                            {t('automation.view_runs_arrow')}
                        </Link>
                    </div>
                </div>
                <ReactFlowProvider>
                    <AutomationBuilderInner automation={automation} />
                </ReactFlowProvider>
            </div>
        </ClientLayout>
    );
}
