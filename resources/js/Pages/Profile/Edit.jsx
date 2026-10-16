import ClientLayout from '@/Layouts/ClientLayout';
import { Head } from '@inertiajs/react';
import DeleteUserForm from './Partials/DeleteUserForm';
import UpdatePasswordForm from './Partials/UpdatePasswordForm';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm';

export default function Edit({ mustVerifyEmail, status }) {
    return (
        <ClientLayout title="Profile">
            <Head title="Profile" />

            <div className="max-w-2xl space-y-6">
                <div className="rounded-xl border border-neutral-200 dark:border-neutral-700/50 bg-white dark:bg-neutral-800/70 p-5 shadow-soft">
                    <UpdateProfileInformationForm
                        mustVerifyEmail={mustVerifyEmail}
                        status={status}
                    />
                </div>

                <div className="rounded-xl border border-neutral-200 dark:border-neutral-700/50 bg-white dark:bg-neutral-800/70 p-5 shadow-soft">
                    <UpdatePasswordForm />
                </div>

                <div className="rounded-xl border border-neutral-200 dark:border-neutral-700/50 bg-white dark:bg-neutral-800/70 p-5 shadow-soft">
                    <DeleteUserForm />
                </div>
            </div>
        </ClientLayout>
    );
}
