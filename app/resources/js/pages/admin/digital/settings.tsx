import { useEffect, useState } from 'react';
import { Head, router, useForm } from '@inertiajs/react';
import AdminLayout from '../layout';
type Settings = {
    bank: string;
    account: string;
    holder: string;
    currency: string;
    enabled: boolean;
};
export default function Settings({
    settings: s,
    ready,
    sharedQrs = [],
}: {
    settings: Settings | null;
    ready: boolean;
    sharedQrs?: {id:number; tier:'public'|'member'; amount:string; currency:string; enabled:boolean; current:boolean; preview_url:string}[];
}) {
    const form = useForm({
        bank: s?.bank ?? '',
        account: s?.account ?? '',
        holder: s?.holder ?? '',
        currency: s?.currency ?? 'BOB',
        enabled: s?.enabled ?? false,
    });
    const shared = useForm({public_qr:null as File|null, member_qr:null as File|null, reuse_confirmed:false});
    const [saved, setSaved] = useState(false);
    const [previews, setPreviews] = useState<Record<string,string>>({});
    useEffect(() => {
        const urls: Record<string,string> = {};
        for (const tier of ['public','member'] as const) {
            const file = shared.data[`${tier}_qr`];
            if (file) urls[tier] = URL.createObjectURL(file);
        }
        setPreviews(urls);
        return () => Object.values(urls).forEach(url => URL.revokeObjectURL(url));
    }, [shared.data.public_qr, shared.data.member_qr]);
    return (
        <AdminLayout title="Cuenta autorizada para JAKAWI Digital">
            <Head title="Cobro digital" />
            <p
                role="status"
                className="mb-5 rounded-xl border border-border p-4 font-semibold"
            >
                {ready
                    ? 'Cuenta autorizada para cobro digital'
                    : 'Cobro pendiente de configurar'}
            </p>
            <p className="mb-5 text-muted-foreground">
                La cuenta autorizada y los QR compartidos por importe sirven para los productos compatibles. Cada orden conserva su comprobante y exige verificación del ingreso bancario real.
            </p>
            <form
                className="grid max-w-2xl gap-5"
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post('/admin/digital/cobro', { forceFormData: true });
                }}
            >
                {(
                    [
                        ['bank', 'Banco'],
                        ['account', 'Cuenta receptora'],
                        ['holder', 'Titular'],
                    ] as const
                ).map(([key, label]) => (
                    <label key={key} className="grid gap-2 font-semibold">
                        {label}
                        <input
                            required
                            className="min-h-12 rounded-xl border border-border bg-surface px-3"
                            value={form.data[key]}
                            onChange={(e) => form.setData(key, e.target.value)}
                        />
                    </label>
                ))}
                <label className="grid gap-2">
                    Moneda
                    <select
                        className="min-h-12 rounded-xl border border-border bg-surface px-3"
                        value={form.data.currency}
                        onChange={(e) =>
                            form.setData('currency', e.target.value)
                        }
                    >
                        <option>BOB</option>
                        <option>USD</option>
                    </select>
                </label>
                {(
                    [
                        [
                            'enabled',
                            'Autorizar esta cuenta para cobros de JAKAWI Digital.',
                        ],
                    ] as const
                ).map(([key, label]) => (
                    <label className="flex items-start gap-3" key={key}>
                        <input
                            type="checkbox"
                            className="mt-1 size-5 shrink-0"
                            checked={form.data[key]}
                            onChange={(e) =>
                                form.setData(key, e.target.checked)
                            }
                        />
                        {label}
                    </label>
                ))}
                {Object.values(form.errors).map((e, i) => (
                    <p role="alert" className="text-destructive" key={i}>
                        {e}
                    </p>
                ))}
                <button
                    disabled={form.processing}
                    className="min-h-12 rounded-xl bg-brand px-5 font-bold text-brand-foreground"
                >
                    Guardar configuración
                </button>
            </form>
            <section className="mt-10 grid max-w-2xl gap-5 rounded-xl border border-border p-5">
                <h2 className="text-xl font-bold">QR compartidos por importe</h2>
                <p>Deben ser QR reutilizables emitidos para los importes exactos y la cuenta configurada. Se utilizarán automáticamente en los productos compatibles y seguirán vigentes hasta que los reemplaces o desactives.</p>
                <form className="grid gap-5" onSubmit={e => {
                    e.preventDefault();
                    setSaved(false);
                    shared.transform(data => ({...data, reuse_confirmed:data.reuse_confirmed ? '1':'0'}));
                    shared.post('/admin/digital/cobro/qr-compartido', {forceFormData:true, preserveScroll:true, onSuccess:()=>{
                        shared.reset('public_qr','member_qr','reuse_confirmed');
                        setSaved(true);
                    }});
                }}>
                    {(['public','member'] as const).map(tier => {
                        const qr = sharedQrs.find(item => item.tier === tier);
                        const preview = previews[tier] || qr?.preview_url;
                        return <div key={tier} className="grid gap-3 rounded-xl border border-border p-4">
                            <h3 className="text-lg font-bold">{tier === 'public' ? 'QR PÚBLICO — Bs 49' : 'QR MIEMBRO — Bs 29'}</h3>
                            {preview && <img src={preview} alt={`QR ${tier === 'public' ? 'público de Bs 49' : 'miembro de Bs 29'}`} className="max-h-64 max-w-full justify-self-start rounded-lg object-contain" />}
                            <label className="grid gap-2 font-semibold">
                                {qr ? 'Reemplazar QR' : 'Subir imagen PNG/JPG'}
                                <input key={`${tier}-${qr?.preview_url ?? ''}-${saved}`} type="file" accept="image/png,image/jpeg" onChange={e=>{
                                    setSaved(false);
                                    shared.setData(`${tier}_qr`, e.target.files?.[0] ?? null);
                                }}/>
                            </label>
                            <p role="status">{shared.errors[`${tier}_qr`] ? <span className="text-destructive">{shared.errors[`${tier}_qr`]}</span> : shared.data[`${tier}_qr`] ? 'Imagen seleccionada. Pendiente de guardar.' : qr?.current ? (saved ? 'QR guardado y disponible.' : 'QR guardado. Disponible para productos compatibles.') : qr ? 'QR no disponible. Reemplázalo para la cuenta autorizada actual.' : 'QR pendiente de cargar.'}</p>
                            {qr?.enabled && <button type="button" className="justify-self-start underline" onClick={()=>router.post(`/admin/digital/cobro/qr-compartido/${qr.id}/desactivar`, {}, {preserveScroll:true, onSuccess:()=>setSaved(false)})}>Desactivar QR</button>}
                        </div>;
                    })}
                    <label className="flex items-start gap-3"><input required type="checkbox" className="mt-1 size-5 shrink-0" checked={shared.data.reuse_confirmed} onChange={e=>shared.setData('reuse_confirmed',e.target.checked)}/>Confirmo que estos QR son reutilizables y corresponden a los importes y la cuenta configurada.</label>
                    {shared.errors.reuse_confirmed && <p role="alert" className="text-destructive">{shared.errors.reuse_confirmed}</p>}
                    {!ready && <p role="status">Autoriza primero la cuenta receptora en BOB.</p>}
                    <button disabled={shared.processing || !ready} className="min-h-12 rounded-xl bg-brand px-5 font-bold text-brand-foreground disabled:opacity-50">GUARDAR QR COMPARTIDOS</button>
                </form>
            </section>
        </AdminLayout>
    );
}
