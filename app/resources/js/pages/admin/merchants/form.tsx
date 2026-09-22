import { Head, Link, router, useForm } from '@inertiajs/react';
import { FormEvent, type ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AdminLayout from '../layout';

type Merchant = {
    id: number;
    name: string;
    short_description?: string | null;
    description?: string | null;
    category?: string | null;
    address?: string | null;
    city?: string | null;
    instagram?: string | null;
    whatsapp?: string | null;
    is_active: boolean;
    is_featured: boolean;
    sort_order: number;
    has_redemption_pin?: boolean;
} | null;

export default function MerchantForm({ merchant }: { merchant: Merchant }) {
    const isEdit = Boolean(merchant);
    const form = useForm({
        name: merchant?.name ?? '',
        short_description: merchant?.short_description ?? '',
        description: merchant?.description ?? '',
        category: merchant?.category ?? '',
        address: merchant?.address ?? '',
        city: merchant?.city ?? '',
        instagram: merchant?.instagram ?? '',
        whatsapp: merchant?.whatsapp ?? '',
        logo: null as File | null,
        cover: null as File | null,
        redemption_pin: '',
        is_active: merchant?.is_active ?? true,
        is_featured: merchant?.is_featured ?? false,
        sort_order: merchant?.sort_order ?? 0,
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        if (isEdit && merchant) {
            router.post(`/admin/merchants/${merchant.id}`, {
                ...form.data,
                _method: 'put',
            }, {
                forceFormData: true,
            });
        } else {
            form.post('/admin/merchants', { forceFormData: true });
        }
    }

    return (
        <AdminLayout>
            <Head title={isEdit ? 'Editar comercio' : 'Crear comercio'} />
            <form onSubmit={submit} className="space-y-5 rounded-md border border-border bg-surface p-4">
                <div className="flex items-center justify-between gap-3">
                    <h1 className="text-2xl font-semibold">{isEdit ? 'Editar comercio' : 'Crear comercio'}</h1>
                    <Link href="/admin/merchants" className="text-sm font-medium text-muted-foreground hover:text-foreground">Volver</Link>
                </div>
                <div className="grid gap-4 sm:grid-cols-2">
                    <Field label="Nombre"><Input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} required /></Field>
                    <Field label="Categoría"><Input value={form.data.category} onChange={(e) => form.setData('category', e.target.value)} /></Field>
                    <Field label="Ciudad"><Input value={form.data.city} onChange={(e) => form.setData('city', e.target.value)} /></Field>
                    <Field label="Orden"><Input type="number" min="0" value={form.data.sort_order} onChange={(e) => form.setData('sort_order', Number(e.target.value))} /></Field>
                    <Field label="Instagram"><Input value={form.data.instagram} onChange={(e) => form.setData('instagram', e.target.value)} /></Field>
                    <Field label="WhatsApp"><Input value={form.data.whatsapp} onChange={(e) => form.setData('whatsapp', e.target.value)} /></Field>
                    <Field label="Logo"><Input type="file" accept="image/jpeg,image/png,image/webp" onChange={(e) => form.setData('logo', e.target.files?.[0] ?? null)} /></Field>
                    <Field label="Cover"><Input type="file" accept="image/jpeg,image/png,image/webp" onChange={(e) => form.setData('cover', e.target.files?.[0] ?? null)} /></Field>
                    <Field label="Redemption PIN"><Input inputMode="numeric" maxLength={6} value={form.data.redemption_pin} onChange={(e) => form.setData('redemption_pin', e.target.value)} /></Field>
                </div>
                <p className="text-sm text-muted-foreground">
                    {merchant?.has_redemption_pin ? 'PIN configurado' : 'PIN no configurado'}
                </p>
                <Field label="Descripción corta"><Input value={form.data.short_description} onChange={(e) => form.setData('short_description', e.target.value)} /></Field>
                <Field label="Dirección"><Input value={form.data.address} onChange={(e) => form.setData('address', e.target.value)} /></Field>
                <Field label="Descripción"><textarea className="min-h-28 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm outline-none focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50" value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} /></Field>
                <div className="flex flex-wrap gap-4">
                    <Toggle label="Activo" checked={form.data.is_active} onChange={(checked) => form.setData('is_active', checked)} />
                    <Toggle label="Featured" checked={form.data.is_featured} onChange={(checked) => form.setData('is_featured', checked)} />
                </div>
                <Button disabled={form.processing}>{isEdit ? 'Guardar cambios' : 'Crear comercio'}</Button>
            </form>
        </AdminLayout>
    );
}

function Field({ label, children }: { label: string; children: ReactNode }) {
    return <label className="grid gap-2"><Label>{label}</Label>{children}</label>;
}

function Toggle({ label, checked, onChange }: { label: string; checked: boolean; onChange: (checked: boolean) => void }) {
    return <label className="flex min-h-10 items-center gap-2 text-sm font-medium"><input type="checkbox" checked={checked} onChange={(e) => onChange(e.target.checked)} />{label}</label>;
}
