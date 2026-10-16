import { usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { ChannelBrandIcon } from '@/Components/BrandIcons';
import {
    LayoutDashboard, CreditCard, Package, FileText, Users, Settings,
    Layers, Webhook, Key, BookOpen, Image, Radio, Inbox, Bot, Database,
    Zap, Share2, MapPin, Tag, LifeBuoy, ExternalLink, Mail, MessageSquare,
    ShoppingBag, BarChart3, Plug, ListTodo, Link2, FolderOpen, CalendarClock,
    CalendarDays, ClipboardList, BriefcaseBusiness, Clock3, UserRoundSearch,
    BrainCircuit,
    Network,
    GitFork,
} from 'lucide-react';

const iconClass = 'h-4 w-4';
const whatsappNavIcon = <ChannelBrandIcon channel="whatsapp" className={iconClass} />;

function safeRoute(name, ...args) {
    try { return route(name, ...args); } catch { return '#'; }
}

/**
 * Single source of truth for the client-panel sidebar navigation.
 *
 * Both ClientLayout and InboxLayout consume this so the sidebar is identical on
 * every client page. Previously each layout kept its own copy and they drifted —
 * the inbox sidebar was missing whole groups (Social Media, Automations, Leads)
 * and items. Keep all nav changes here only.
 */
export default function useClientNav() {
    const { auth, branding, planModules = {} } = usePage().props;
    const { t } = useTranslation();
    const user = auth?.user;
    const docsUrl = branding?.docs_url;
    const isClientAdmin = user?.client_role === 'administrator';

    const accountItems = [
        { label: t('nav.dashboard'), href: safeRoute('client.dashboard'), icon: <LayoutDashboard className={iconClass} />, activePattern: 'client.dashboard' },
    ];

    const accountSettingsItems = [
        { label: t('nav.workspaces'), href: safeRoute('client.workspaces.index'), icon: <Layers className={iconClass} />,   activePattern: 'client.workspaces.*' },
        { label: t('nav.settings'),   href: safeRoute('client.settings.index'),   icon: <Settings className={iconClass} />, activePattern: 'client.settings.*' },
    ];

    if (isClientAdmin) {
        accountSettingsItems.push(
            { label: t('nav.team'),      href: safeRoute('client.team.index'),      icon: <Users className={iconClass} />,    activePattern: 'client.team.*' },
            { label: t('nav.audit_log'), href: safeRoute('client.audit-log.index'), icon: <FileText className={iconClass} />, activePattern: 'client.audit-log.*' },
        );
    }


    const developerItems = [
        { label: t('nav.integrations'),   href: safeRoute('client.integrations.index'), icon: <Plug className={iconClass} />, activePattern: 'client.integrations.*', moduleKey: 'integrations' },
        { label: t('nav.api_tokens'),    href: safeRoute('client.api-tokens.index'), icon: <Key className={iconClass} />,     activePattern: 'client.api-tokens.*', moduleKey: 'developer_api' },
        { label: t('nav.webhooks'),      href: safeRoute('client.webhooks.index'),    icon: <Webhook className={iconClass} />,  activePattern: 'client.webhooks.*', moduleKey: 'webhooks' },
        { label: t('nav.api_docs'),      href: safeRoute('client.api-docs'),          icon: <BookOpen className={iconClass} />, activePattern: 'client.api-docs', moduleKey: 'developer_api' },
        { label: t('nav.media_library'), href: safeRoute('client.media.index'),       icon: <Image className={iconClass} />,   activePattern: 'client.media.*', moduleKey: 'media_library' },
    ];

    const supportItems = [
        { label: t('nav.support_tickets'), href: safeRoute('client.support.index'), icon: <LifeBuoy className={iconClass} />,   activePattern: 'client.support.*' },
    ];

    if (docsUrl) {
        supportItems.push({ label: t('nav.help_docs'), href: docsUrl, icon: <ExternalLink className={iconClass} />, external: true });
    }

    const contactsItems = [
        { label: t('nav.contacts'),  href: safeRoute('client.contacts.index'),  icon: <Users className={iconClass} />,  activePattern: 'client.contacts.*', hardReload: true },
        { label: t('nav.segments'),  href: safeRoute('client.segments.index'),  icon: <Tag className={iconClass} />,    activePattern: 'client.segments.*' },
    ];

    const messagingItems = [
        { label: t('nav.templates'),     href: safeRoute('client.whatsapp.templates.index'),     icon: whatsappNavIcon, activePattern: 'client.whatsapp.templates.*' },
        { label: t('nav.auto_replies'),  href: safeRoute('client.whatsapp.auto-replies.index'),  icon: whatsappNavIcon, activePattern: 'client.whatsapp.auto-replies.*' },
        { label: t('nav.chat_widget'),   href: safeRoute('client.whatsapp.widget.index'),         icon: whatsappNavIcon, activePattern: 'client.whatsapp.widget.*' },
    ];

    const broadcastItems = [
        { label: t('nav.campaigns'),    href: safeRoute('client.campaigns.index'),    icon: <Radio className={iconClass} />,        activePattern: 'client.campaigns.*' },
        { label: t('nav.sms_gateways'), href: safeRoute('client.sms-gateways.index'), icon: <MessageSquare className={iconClass} />, activePattern: 'client.sms-gateways.*' },
        { label: t('nav.email_server'), href: safeRoute('client.email-server.index'), icon: <Mail className={iconClass} />,          activePattern: 'client.email-server.*' },
    ];

    const inboxItems = [
        { label: t('nav.inbox'),         href: safeRoute('client.inbox.index'), icon: <Inbox className={iconClass} />, activePattern: 'client.inbox.index', moduleKey: 'inbox' },
        { label: t('nav.message_scheduler'), href: safeRoute('client.inbox.scheduled-messages.index'), icon: <CalendarClock className={iconClass} />, activePattern: 'client.inbox.scheduled-messages.*', moduleKey: 'message_scheduler' },
        { label: t('nav.channel_setup'), href: safeRoute('client.inbox.setup'), icon: <Inbox className={iconClass} />, activePattern: 'client.inbox.setup', moduleKey: 'channel_management' },
    ];

    const aiItems = [
        { label: 'Chatbots de IA', href: safeRoute('client.ai.chatbots.index'), icon: <Bot className={iconClass} />, activePattern: 'client.ai.chatbots.*' },
        { label: t('nav.knowledge_bases'), href: safeRoute('client.ai.knowledge-bases.index'), icon: <Database className={iconClass} />, activePattern: 'client.ai.knowledge-bases.*' },
        { label: t('nav.ai_providers'), href: safeRoute('client.ai.providers.index'), icon: <Bot className={iconClass} />, activePattern: 'client.ai.providers.*' },
    ];

    const socialItems = [
        { label: t('nav.post_composer'),   href: safeRoute('client.social.composer'),        icon: <FileText className={iconClass} />,       activePattern: 'client.social.composer' },
        { label: t('nav.posts'),           href: safeRoute('client.social.posts.index'),     icon: <Radio className={iconClass} />,           activePattern: 'client.social.posts.*' },
        { label: t('nav.calendar'),        href: safeRoute('client.social.calendar'),         icon: <LayoutDashboard className={iconClass} />, activePattern: 'client.social.calendar' },
        { label: t('nav.social_accounts'), href: safeRoute('client.social.accounts.index'),  icon: <Share2 className={iconClass} />,          activePattern: 'client.social.accounts.*' },
    ];

    const leadsItems = [
        { label: t('nav.ads_attribution'), href: safeRoute('client.ads.index'), icon: <BarChart3 className={iconClass} />, activePattern: 'client.ads.*', moduleKey: 'ads' },
        { label: t('nav.lead_scraper'), href: safeRoute('client.leads.index'), icon: <MapPin className={iconClass} />, activePattern: 'client.leads.*', moduleKey: 'leads' },
    ];

    const agendaItems = [
        { label: t('nav.agenda_overview'), href: safeRoute('client.appointments.index'), icon: <LayoutDashboard className={iconClass} />, activePattern: 'client.appointments.index' },
        { label: t('nav.agenda_calendar'), href: safeRoute('client.appointments.calendar'), icon: <CalendarDays className={iconClass} />, activePattern: 'client.appointments.calendar' },
        { label: t('nav.agenda_appointments'), href: safeRoute('client.appointments.list'), icon: <ClipboardList className={iconClass} />, activePattern: 'client.appointments.list' },
        { label: t('nav.agenda_clients'), href: safeRoute('client.appointments.clients'), icon: <UserRoundSearch className={iconClass} />, activePattern: 'client.appointments.clients' },
        { label: t('nav.agenda_services'), href: safeRoute('client.appointments.services.index'), icon: <BriefcaseBusiness className={iconClass} />, activePattern: 'client.appointments.services.*' },
        { label: t('nav.agenda_professionals'), href: safeRoute('client.appointments.resources.index'), icon: <Users className={iconClass} />, activePattern: 'client.appointments.resources.*' },
        { label: t('nav.agenda_availability'), href: safeRoute('client.appointments.availability.index'), icon: <Clock3 className={iconClass} />, activePattern: 'client.appointments.availability.*' },
        { label: t('nav.agenda_channels'), href: safeRoute('client.appointments.channels.index'), icon: <MessageSquare className={iconClass} />, activePattern: 'client.appointments.channels.*' },
    ];

    const automationItems = [
        { label: t('nav.automations'), href: safeRoute('client.automations.index'), icon: <Zap className={iconClass} />, activePattern: 'client.automations.*' },
    ];

    const ecommerceItems = [
        { label: t('nav.orders'),   href: safeRoute('client.ecommerce.orders.index'),   icon: <Package className={iconClass} />,     activePattern: 'client.ecommerce.orders.*' },
        { label: t('nav.products'), href: safeRoute('client.ecommerce.products.index'), icon: <Tag className={iconClass} />,         activePattern: 'client.ecommerce.products.*' },
        { label: t('nav.stores'),   href: safeRoute('client.ecommerce.stores.index'),   icon: <ShoppingBag className={iconClass} />, activePattern: 'client.ecommerce.stores.*' },
    ];

    const reportsItems = [
        { label: t('nav.reports_inbox'),       href: safeRoute('client.reports.inbox.index'),       icon: <Inbox className={iconClass} />,  activePattern: 'client.reports.inbox.*' },
        { label: t('nav.campaigns'),           href: safeRoute('client.reports.campaigns.index'),   icon: <Radio className={iconClass} />,  activePattern: 'client.reports.campaigns.*' },
        { label: t('nav.automations'),         href: safeRoute('client.reports.automations.index'), icon: <Zap className={iconClass} />,    activePattern: 'client.reports.automations.*' },
        { label: t('nav.ai_usage'),            href: safeRoute('client.reports.ai.index'),          icon: <Bot className={iconClass} />,    activePattern: 'client.reports.ai.*' },
        { label: t('nav.social'),              href: safeRoute('client.reports.social.index'),      icon: <Share2 className={iconClass} />, activePattern: 'client.reports.social.*' },
    ];

    // Group order: daily operations first, then growth tools, periodic review, then account-adjacent config (usage-frequency–based).
    const groups = [
        { type: 'group', label: null,                         items: accountItems },
        { type: 'group', label: t('nav.group_inbox'),         items: inboxItems },
        { type: 'group', label: t('nav.group_social_media'),  items: socialItems, moduleKey: 'social' },
        { type: 'group', label: t('nav.group_messaging'),     items: messagingItems, moduleKey: 'whatsapp_tools' },
        { type: 'group', label: t('nav.group_contacts'),      items: contactsItems, moduleKey: 'contacts' },
        { type: 'group', label: t('nav.group_broadcasting'),  items: broadcastItems, moduleKey: 'broadcasting' },
        { type: 'group', label: t('nav.group_agenda'),        items: agendaItems, moduleKey: 'appointments' },
        { type: 'group', label: t('nav.group_automations'),   items: automationItems, moduleKey: 'automations' },
        { type: 'group', label: t('nav.group_ecommerce'),    items: ecommerceItems, moduleKey: 'ecommerce' },
        { type: 'group', label: t('nav.group_ai'),            items: aiItems, moduleKey: 'ai' },
        { type: 'group', label: t('nav.group_leads'),         items: leadsItems },
        { type: 'group', label: t('nav.group_reports'),       items: reportsItems, moduleKey: 'reports' },
        { type: 'group', label: t('nav.group_support'),       items: supportItems },
        { type: 'group', label: t('nav.group_developer'),     items: developerItems },
        { type: 'group', label: t('nav.group_account'),       items: accountSettingsItems },
    ];

    const isEnabled = (key) => !key || planModules?.[key] === true;

    return groups
        .filter((group) => isEnabled(group.moduleKey))
        .map((group) => ({ ...group, items: group.items.filter((item) => isEnabled(item.moduleKey) && !/reports\.(campaigns|automations|ai|social)/.test(item.activePattern ?? '')) }))
        .filter((group) => group.items.length > 0);
}
