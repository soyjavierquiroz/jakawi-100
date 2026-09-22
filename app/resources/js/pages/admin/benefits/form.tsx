import { Head, Link, router, useForm } from '@inertiajs/react';
import { FormEvent, type ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AdminLayout from '../layout';

type MerchantOption = { id: number; name: string };
type Benefit = {
    id: number;
    merchant_id: number;
    title: string;
    short_description?: string | null;
    description?: string | null;
    terms?: string | null;
    benefit_type?: string | null;
    estimated_savings?: string | number | null;
    redemption_limit_per_member?: number | null;
    is_active: boolean;
    is_featured: boolean;
    starts_at?: string | null;
    ends_at?: string | null;
    sort_order: number;
} | null;

export default function BenefitForm({
    benefit,
    merchants,
}: {
    benefit: Benefit;
    merchants: MerchantOption[];
}) {
    const isEdit = Boolean(benefit);
    const form = useForm({
        merchant_id: benefit?.merchant_id ?? merchants[0]?.id ?? '',
        title: benefit?.title ?? '',
        short_description: benefit?.short_description ?? '',
        description: benefit?.description ?? '',
        terms: benefit?.terms ?? '',
        benefit_type: benefit?.benefit_type ?? '',
        estimated_savings: benefit?.estimated_savings ?? '',
        redemption_limit_per_member: (benefit?.redemption_limit_per_member ?? 1) as number | null,
        image: null as File | null,
        is_active: benefit?.is_active ?? true,
        is_featured: benefit?.is_featured ?? false,
        starts_at: benefit?.starts_at ? benefit.starts_at.slice(0, 16) : '',
        ends_at: benefit?.ends_at ? benefit.ends_at.slice(0, 16) : '',
        sort_order: benefit?.sort_order ?? 0,
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        if (isEdit && benefit) {
            router.post(`/admin/benefits/${benefit.id}`, {
                ...form.data,
                _method: 'put',
            }, {
                forceFormData: true,
            });
        } else {
            form.post('/admin/benefits', { forceFormData: true });
        }
    }

    return (
        <AdminLayout>
            <Head title={isEdit ? 'Editar beneficio' : 'Crear beneficio'} />
            <form onSubmit={submit} className="space-y-5 rounded-md border border-border bg-surface p-4">
                <div className="flex items-center justify-between gap-3">
                    <h1 className="text-2xl font-semibold">{isEdit ? 'Editar beneficio' : 'Crear beneficio'}</h1>
                    <Link href="/admin/benefits" className="text-sm font-medium text-muted-foreground hover:text-foreground">Volver</Link>
                </div>
                <div className="grid gap-4 sm:grid-cols-2">
                    <Field label="Comercio">
                        <select className="h-9 rounded-md border border-input bg-transparent px-3 text-sm" value={form.data.merchant_id} onChange={(e) => form.setData('merchant_id', Number(e.target.value))} required>
                            {merchants.map((merchant) => (
                                <option key={merchant.id} value={merchant.id}>{merchant.name}</option>
                            ))}
                        </select>
                    </Field>
                    <Field label="Título"><Input value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} required /></Field>
                    <Field label="Tipo"><Input placeholder="2x1, discount, free_item..." value={form.data.benefit_type} onChange={(e) => form.setData('benefit_type', e.target.value)} /></Field>
                    <Field label="Ahorro estimado"><Input type="number" min="0" step="0.01" value={form.data.estimated_savings} onChange={(e) => form.setData('estimated_savings', e.target.value)} /></Field>
                    <Field label="Límite de canjes por miembro"><Input type="number" min="1" value={form.data.redemption_limit_per_member ?? ''} onChange={(e) => form.setData('redemption_limit_per_member', e.target.value === '' ? null : Number(e.target.value))} placeholder="Sin límite" /></Field>
                    <Field label="Inicio"><Input type="datetime-local" value={form.data.starts_at} onChange={(e) => form.setData('starts_at', e.target.value)} /></Field>
                    <Field label="Fin"><Input type="datetime-local" value={form.data.ends_at} onChange={(e) => form.setData('ends_at', e.target.value)} /></Field>
                    <Field label="Orden"><Input type="number" min="0" value={form.data.sort_order} onChange={(e) => form.setData('sort_order', Number(e.target.value))} /></Field>
                    <Field label="Imagen"><Input type="file" accept="image/jpeg,image/png,image/webp" onChange={(e) => form.setData('image', e.target.files?.[0] ?? null)} /></Field>
                </div>
                <Field label="Descripción corta"><Input value={form.data.short_description} onChange={(e) => form.setData('short_description', e.target.value)} /></Field>
                <Field label="Descripción"><textarea className="min-h-28 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm outline-none focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50" value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} /></Field>
                <Field label="Condiciones"><textarea className="min-h-24 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm outline-none focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50" value={form.data.terms} onChange={(e) => form.setData('terms', e.target.value)} /></Field>
                <div className="flex flex-wrap gap-4">
                    <Toggle label="Activo" checked={form.data.is_active} onChange={(checked) => form.setData('is_active', checked)} />
                    <Toggle label="Featured" checked={form.data.is_featured} onChange={(checked) => form.setData('is_featured', checked)} />
                </div>
                <Button disabled={form.processing || merchants.length === 0}>{isEdit ? 'Guardar cambios' : 'Crear beneficio'}</Button>
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
