import { Button, Input, Modal } from '@/Components/ui';
import { useForm } from '@inertiajs/react';
import { useRef, useState } from 'react';
import { Trash2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';

export default function DeleteUserForm({ className = '' }) {
    const { t } = useTranslation();
    const [confirming, setConfirming] = useState(false);
    const passwordInput = useRef();

    const { data, setData, delete: destroy, processing, reset, errors, clearErrors } = useForm({
        password: '',
    });

    const confirmDeletion = () => setConfirming(true);

    const deleteUser = (e) => {
        e.preventDefault();
        destroy(route('client.profile.destroy'), {
            preserveScroll: true,
            onSuccess: () => closeModal(),
            onError: () => passwordInput.current?.focus(),
            onFinish: () => reset(),
        });
    };

    const closeModal = () => {
        setConfirming(false);
        clearErrors();
        reset();
    };

    return (
        <section className={`space-y-4 ${className}`}>
            <header>
                <h2 className="text-base font-semibold text-neutral-900 dark:text-white">
                    {t('profile.delete_account')}
                </h2>
                <p className="mt-1 text-sm text-neutral-500 dark:text-neutral-400">
                    {t('profile.delete_account_intro')}
                </p>
            </header>

            <Button variant="danger" onClick={confirmDeletion}>
                <Trash2 className="mr-2 h-4 w-4" />
                {t('profile.delete_account')}
            </Button>

            <Modal show={confirming} onClose={closeModal} maxWidth="md">
                <Modal.Header title={t('profile.delete_account')} onClose={closeModal} />
                <form onSubmit={deleteUser}>
                    <Modal.Body>
                        <p className="text-sm text-neutral-600 dark:text-neutral-400">
                            {t('profile.delete_account_confirm')}
                        </p>
                        <div className="mt-4">
                            <Input
                                id="delete-password"
                                ref={passwordInput}
                                type="password"
                                name="password"
                                label={t('profile.password')}
                                value={data.password}
                                onChange={(e) => setData('password', e.target.value)}
                                placeholder={t('profile.password_placeholder')}
                                error={errors.password}
                                autoFocus
                            />
                        </div>
                    </Modal.Body>
                    <Modal.Footer>
                        <Button type="button" variant="secondary" onClick={closeModal}>
                            {t('common.cancel')}
                        </Button>
                        <Button type="submit" variant="danger" disabled={processing}>
                            {processing ? t('common.deleting') : t('profile.delete_account')}
                        </Button>
                    </Modal.Footer>
                </form>
            </Modal>
        </section>
    );
}
