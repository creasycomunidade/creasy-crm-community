<?php

namespace App\Services\I18n;

/**
 * Default English strings for 100% translation coverage.
 * Used by TranslationSeeder to seed or fallback. Keys match t('section.key') in the app.
 */
class DefaultTranslations
{
    public static function all(): array
    {
        return array_merge(
            self::common(),
            self::nav(),
            self::topbar(),
            self::sidebar(),
            self::admin(),
            self::pagination(),
            self::auth(),
            self::welcome(),
            self::profile(),
            self::client(),
            self::plans(),
            self::locales(),
            self::misc()
        );
    }

    private static function common(): array
    {
        return [
            'common.save' => 'Save',
            'common.cancel' => 'Cancel',
            'common.close' => 'Close',
            'common.delete' => 'Delete',
            'common.edit' => 'Edit',
            'common.back' => 'Back',
            'common.next' => 'Next',
            'common.loading' => 'Loading...',
            'common.create' => 'Create',
            'common.tabs' => 'Tabs',
            'common.filter' => 'Filter',
            'common.add' => 'Add',
            'common.search' => 'Search',
            'common.actions' => 'Actions',
            'common.yes' => 'Yes',
            'common.no' => 'No',
            'common.enabled' => 'Enabled',
            'common.name' => 'Name',
            'common.optional' => 'Optional',
            'common.active' => 'Active',
            'common.inactive' => 'Inactive',
        ];
    }

    private static function nav(): array
    {
        return [
            'nav.home' => 'Home',
            'nav.dashboard' => 'Dashboard',
            'nav.profile' => 'Profile',
            'nav.logout' => 'Log out',
            'nav.login' => 'Log in',
            'nav.register' => 'Register',
            'nav.admin' => 'Admin',
            'nav.workspaces' => 'Workspaces',
            'nav.pricing' => 'Pricing',
            'nav.components' => 'Components',
            'nav.backToHome' => 'Back to home',
            'nav.backToApp' => 'Back to app',
            'nav.rtlPreview' => 'RTL Preview',
        ];
    }

    private static function topbar(): array
    {
        return [
            'topbar.no_workspace' => 'No workspace',
            'topbar.create_workspace' => 'Create workspace',
            'topbar.manage_workspaces' => 'Manage workspaces',
            'topbar.language' => 'Language',
            'topbar.currency' => 'Currency',
            'topbar.workspace' => 'Workspace',
            'topbar.account' => 'Account',
            'topbar.switch_theme' => 'Switch theme',
        ];
    }

    private static function sidebar(): array
    {
        return [
            'sidebar.menu' => 'Menu',
        ];
    }

    private static function admin(): array
    {
        return [
            'admin.client_login' => 'Client login',
            'admin.dashboard' => 'Dashboard',
            'admin.client_management' => 'Client Management',
            'admin.subscriptions' => 'Subscriptions',
            'admin.plans' => 'Plans',
            'admin.payment_gateways' => 'Payment Gateways',
            'admin.payments' => 'Payments',
            'admin.currencies' => 'Currencies',
            'admin.languages' => 'Languages',
            'admin.settings' => 'Settings',
            'admin.audit_log' => 'Audit log',
            'admin.admins' => 'Admins',
            'admin.roles_permissions' => 'Roles & Permissions',
            'admin.email' => 'Email',
            'admin.clients_count' => 'Total clients',
            'admin.users_count' => 'Total users',
            'admin.add_client' => 'Add Client',
            'admin.edit_client' => 'Edit Client',
            'admin.delete_client' => 'Delete Client',
            'admin.manage_client_users' => 'Manage Client Users',
            'admin.add_user' => 'Add User',
            'admin.edit_user' => 'Edit User',
            'admin.delete_user' => 'Delete User',
            'admin.assign_plan' => 'Assign Plan to Client',
            'admin.edit_client_title' => 'Edit client',
            'admin.manage_users_title' => 'Manage client users',
            'admin.assign_plan_title' => 'Assign plan',
            'admin.impersonate_title' => 'Login as client',
            'admin.delete_client_title' => 'Delete client',
            'admin.edit_user_title' => 'Edit user',
            'admin.delete_user_title' => 'Delete user',
            'admin.search_clients' => 'Search clients...',
            'admin.enter_client_name' => 'Enter client name',
            'admin.enter_email' => 'client@example.com',
            'admin.enter_phone' => '+1 (555) 123-4567',
            'admin.enter_address' => 'Enter full address',
            'admin.add_admin' => 'Add Admin',
            'admin.edit_admin' => 'Edit Admin',
            'admin.delete_admin' => 'Delete Admin',
            'admin.search_name_email' => 'Search name or email',
            'admin.edit_payment_gateway' => 'Edit Payment Gateway',
            'admin.stripe_pk_placeholder' => 'pk_test_…',
            'admin.stripe_sk_placeholder' => 'sk_test_…',
            'admin.webhook_secret_placeholder' => 'Enter webhook secret',
            'admin.stripe_pk_live_placeholder' => 'pk_live_…',
            'admin.stripe_sk_live_placeholder' => 'sk_live_…',
            'admin.delete_plan' => 'Delete Plan',
            'admin.price_plans' => 'Price Plans',
            'admin.plan_name' => 'Plan Name',
            'admin.slug' => 'Slug',
            'admin.short_description' => 'Short description for the plan',
            'admin.monthly_price_cents' => 'Monthly price (BRL)',
            'admin.yearly_price_cents' => 'Yearly price (BRL)',
            'admin.trial_days' => 'Trial Days',
            'admin.stripe_monthly_price_id' => 'Stripe Monthly Price ID',
            'admin.stripe_yearly_price_id' => 'Stripe Yearly Price ID',
            'admin.price_placeholder' => 'price_...',
            'admin.active' => 'Active',
            'admin.popular' => 'Popular',
            'admin.featured' => 'Featured',
            'admin.unlimited' => 'Unlimited',
            'admin.feature_description' => 'Feature description',
            'admin.remove_feature' => 'Remove feature',
            'admin.drag_to_reorder' => 'Drag to reorder',
            'admin.user_id' => 'User ID',
            'admin.action' => 'Action',
            'admin.search_roles' => 'Search roles',
            'admin.search_permissions' => 'Search key, name, category',
            'admin.add_role' => 'Add Role',
            'admin.edit_role' => 'Edit Role',
            'admin.delete_role' => 'Delete Role',
            'admin.add_permission' => 'Add Permission',
            'admin.edit_permission' => 'Edit Permission',
            'admin.delete_permission' => 'Delete Permission',
            'admin.role_key_placeholder' => 'SUPPORT',
            'admin.permission_key_placeholder' => 'view_something',
            'admin.system_settings' => 'System settings',
            'admin.admin_management' => 'Admin Management',
            'admin.admin_log_in' => 'Admin log in',
            'admin.mrr' => 'MRR',
            'admin.active_subscriptions' => 'Active subscriptions',
            'admin.ai_requests_this_month' => 'AI requests (this month)',
            'admin.ai_tokens_this_month' => 'AI tokens (this month)',
            'admin.payments_this_month' => 'Payments this month',
            'admin.dashboard_overview' => 'Overview of your platform metrics.',
            'admin.quick_links' => 'Quick links',
            'admin.clients' => 'Clients',
            'admin.view_payments' => 'View payments →',
            'admin.view_subscriptions' => 'View subscriptions →',
            'admin.all_statuses' => 'All statuses',
            'admin.status_success' => 'Success',
            'admin.status_pending' => 'Pending',
            'admin.status_failed' => 'Failed',
            // Client Management page
            'admin.client_management_description' => 'Manage all clients on the platform.',
            'admin.col_name' => 'Name',
            'admin.col_email' => 'Email',
            'admin.col_status' => 'Status',
            'admin.col_subscription' => 'Subscription',
            'admin.col_actions' => 'Actions',
            'admin.col_user' => 'User',
            'admin.col_roles' => 'Roles',
            'admin.no_plan' => 'No Plan',
            'admin.no_clients_found' => 'No clients found.',
            'admin.client_name' => 'Client Name',
            'admin.phone' => 'Phone',
            'admin.address' => 'Address',
            'admin.base_currency' => 'Base Currency',
            'admin.currency_symbol' => 'Currency Symbol',
            'admin.currency_position' => 'Currency Position',
            'admin.currency_position_before' => 'Before ($100)',
            'admin.currency_position_after' => 'After (100 $)',
            'admin.iso_currency_hint' => 'ISO currency code (e.g., USD, EUR)',
            'admin.symbol_hint' => 'Symbol to display (e.g., $, €, £)',
            'admin.create_client' => 'Create Client',
            'admin.client_label' => 'Client',
            'admin.manage_users_title_modal' => 'Manage Client Users',
            'admin.no_users_yet' => 'No users yet. Add one above.',
            'admin.loading_users' => 'Loading users...',
            'admin.administrator' => 'Administrator',
            'admin.staff' => 'Staff',
            'admin.client_administrator' => 'Client Administrator',
            'admin.client_staff' => 'Client Staff',
            'admin.role_label' => 'Role',
            'admin.new_password_leave_blank' => 'New Password (leave blank to keep)',
            'admin.confirm_password_label' => 'Confirm Password',
            'admin.create_user' => 'Create User',
            'admin.select_plan' => 'Select Plan',
            'admin.billing_cycle' => 'Billing cycle',
            'admin.monthly' => 'Monthly',
            'admin.yearly' => 'Yearly',
            'admin.assign_plan_btn' => 'Assign Plan',
            'admin.delete_client_confirm' => 'Are you sure you want to delete {{name}}? This will remove all client users and subscriptions.',
            'admin.delete_user_confirm' => 'Are you sure you want to remove {{name}} ({{email}}) from this client?',
        ];
    }

    private static function pagination(): array
    {
        return [
            'pagination.label' => 'Pagination',
            'pagination.first_page' => 'First page',
            'pagination.last_page' => 'Last page',
            'pagination.previous' => 'Previous',
            'pagination.previous_page' => 'Previous page',
            'pagination.next' => 'Next',
            'pagination.next_page' => 'Next page',
            'pagination.page_num' => 'Page {{num}}',
            'pagination.more_pages' => 'More pages',
            'pagination.page_of' => 'Page {{current}} of {{last}}',
            'pagination.showing' => 'Showing {{from}}–{{to}} of {{total}}',
        ];
    }

    private static function auth(): array
    {
        return [
            'auth.email' => 'Email',
            'auth.password' => 'Password',
            'auth.remember_me' => 'Remember me',
            'auth.forgot_password' => 'Forgot your password?',
            'auth.log_in' => 'Log in',
            'auth.register' => 'Register',
            'auth.name' => 'Name',
            'auth.confirm_password' => 'Confirm Password',
            'auth.reset_password' => 'Reset Password',
            'auth.send_reset_link' => 'Email Password Reset Link',
            'auth.verify_email' => 'Verify Email',
            'auth.forgot_password_title' => 'Forgot Password',
            'auth.forgot_password_intro' => 'Forgot your password? No problem. Just let us know your email address and we will email you a password reset link that will allow you to choose a new one.',
            'auth.confirm_password_title' => 'Confirm Password',
            'auth.confirm_password_intro' => 'This is a secure area of the application. Please confirm your password before continuing.',
            'auth.confirm' => 'Confirm',
            'auth.already_registered' => 'Already registered?',
            'auth.verify_email_title' => 'Email Verification',
            'auth.verify_email_intro' => 'Thanks for signing up! Before getting started, could you verify your email address by clicking on the link we just emailed to you? If you didn\'t receive the email, we will gladly send you another.',
            'auth.verification_sent' => 'A new verification link has been sent to the email address you provided during registration.',
            'auth.resend_verification' => 'Resend Verification Email',
            'auth.log_out' => 'Log Out',
        ];
    }

    private static function welcome(): array
    {
        return [
            'welcome.title' => 'Build websites with AI.',
            'welcome.subtitle' => 'No code required.',
            'welcome.description' => 'Create, edit, and publish multi-page sites using natural language and a visual builder. One account, multiple projects.',
            'welcome.getStarted' => 'Start building free',
            'welcome.signIn' => 'Sign in',
            'welcome.goToDashboard' => 'Go to Dashboard',
            'welcome.featuresTitle' => 'Everything you need to launch',
            'welcome.feature1Title' => 'AI-powered generation',
            'welcome.feature1Desc' => 'Generate full sites or edit sections with natural language.',
            'welcome.feature2Title' => 'Visual builder',
            'welcome.feature2Desc' => 'Drag-and-drop sections, property editor, and live preview.',
            'welcome.feature3Title' => 'Publish & export',
            'welcome.feature3Desc' => 'Go live on our hosting or download static HTML.',
        ];
    }

    private static function profile(): array
    {
        return [
            'profile.edit' => 'Profile',
            'profile.update_profile' => 'Update Profile Information',
            'profile.update_password' => 'Update Password',
            'profile.delete_account' => 'Delete Account',
        ];
    }

    private static function client(): array
    {
        return [
            'client.panel' => 'Client Panel',
            'client.dashboard' => 'Dashboard',
            'client.workspaces' => 'Workspaces',
            'client.pricing' => 'Pricing',
            'client.no_workspace' => 'No workspace',
            'client.create_workspace' => 'Create workspace',
            'client.manage_workspaces' => 'Manage workspaces',
            'client.team_members' => 'Team members',
            'client.manage_team' => 'Manage team',
            'client.plan' => 'Plan',
        ];
    }

    private static function plans(): array
    {
        return [
            'plans.name' => 'Name',
            'plans.slug' => 'Slug',
            'plans.monthly_price_cents' => 'Monthly price (BRL)',
            'plans.yearly_price_cents' => 'Yearly price (BRL)',
            'plans.currency_code' => 'Currency code',
            'plans.sort_order' => 'Sort order',
            'plans.users_limit' => 'Users limit',
            'plans.storage_limit' => 'Storage limit (MB)',
        ];
    }

    private static function locales(): array
    {
        return [
            'locales.title' => 'Languages',
            'locales.languages_translations' => 'Languages & Translations',
            'locales.add_language' => 'Add Language',
            'locales.code' => 'Code',
            'locales.native_name' => 'Native name',
            'locales.translations' => 'Translations',
            'locales.languages' => 'Languages',
            'locales.apply_filters' => 'Apply filters',
            'locales.missing_only' => 'Missing only',
            'locales.auto_translate_missing' => 'Auto Translate Missing',
            'locales.search_key_value' => 'Search key or value',
            'locales.translation' => 'Translation',
            'locales.all_groups' => 'All groups',
            'locales.no_languages' => 'No languages. Add one above.',
            'locales.no_translations' => 'No translations match filters. Run i18n:seed-defaults to populate.',
            'locales.saving' => 'Saving…',
            'locales.edit' => 'Edit',
            'locales.set_default' => 'Set Default',
            'locales.remove_language' => 'Remove this language? Translations for this locale will remain in the database.',
        ];
    }

    private static function misc(): array
    {
        return [
            'rtl.title' => 'RTL Preview',
            'rtl.description' => 'This page is displayed in right-to-left layout. Use the language switcher to switch to Arabic (العربية) and see the full app in RTL.',
            'rtl.ltrNote' => 'Switch back to English or Bangla for LTR.',
            'open_menu' => 'Open menu',
            'head.welcome' => 'Welcome',
            'head.admin' => 'Admin',
            'app.name' => config('app.name', 'CREASY CRM'),
            'impersonation.impersonating' => 'Impersonating: {{name}}',
            'impersonation.return_to_admin' => 'Return to Admin',
        ];
    }
}
