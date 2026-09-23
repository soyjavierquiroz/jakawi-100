import { useForm, usePage } from '@inertiajs/react';
import { FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import PartnerLayout from '../partner/layout';

export default function ValidateRedemption({ partner }: { partner: { name: string; slug: string } }) {
    const { flash } = usePage().props as {
        flash?: { success?: string; error?: string };
    };
    const form = useForm({ code: '', pin: '' });

    function submit(event: FormEvent) {
        event.preventDefault();
        form.post(`/partner/${partner.slug}/validar`, { preserveScroll: true });
    }

    return (
        <PartnerLayout title="Validar" partner={partner}>
            <form
                onSubmit={submit}
                className="mx-auto flex w-full max-w-sm flex-col gap-4 rounded-md border border-border bg-surface p-5"
            >
                <h1 className="text-2xl font-semibold">Validar canje</h1>
                {flash?.success ? (
                    <p className="rounded-md bg-success/10 p-3 text-sm font-medium text-success">
                        {flash.success}
                    </p>
                ) : null}
                {flash?.error ? (
                    <p className="rounded-md border border-border p-3 text-sm font-medium">
                        {flash.error}
                    </p>
                ) : null}
                <label className="grid gap-2">
                    <Label>Código de canje</Label>
                    <Input
                        value={form.data.code}
                        onChange={(e) =>
                            form.setData('code', e.target.value.toUpperCase())
                        }
                        maxLength={6}
                        required
                    />
                </label>
                <label className="grid gap-2">
                    <Label>PIN del comercio</Label>
                    <Input
                        value={form.data.pin}
                        onChange={(e) => form.setData('pin', e.target.value)}
                        inputMode="numeric"
                        maxLength={6}
                        required
                    />
                </label>
                <Button disabled={form.processing}>Validar</Button>
            </form>
        </PartnerLayout>
    );
}
