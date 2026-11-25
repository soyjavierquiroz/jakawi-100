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
    sharedQrs?: {id:number; amount:string; currency:string; bank_evidence:string; valid_until:string|null; enabled:boolean; current:boolean}[];
}) {
    const form = useForm({
        bank: s?.bank ?? '',
        account: s?.account ?? '',
        holder: s?.holder ?? '',
        currency: s?.currency ?? 'BOB',
        enabled: s?.enabled ?? false,
    });
    const shared = useForm({amount:'49', currency:s?.currency ?? 'BOB', shared_qr:null as File|null, bank_document:null as File|null, bank_evidence:'', bank_reuse_confirmed:false, no_expiry:false, valid_until:''});
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
                <p>Sube una vez el QR Banco Ganadero de BOB 49 y después el de BOB 29. Confirma con el banco que cada QR admite varios clientes y productos, y documenta su vigencia. Sin esa confirmación, utiliza QR específicos.</p>
                {sharedQrs.map(qr => <div key={qr.id} className="grid gap-2 rounded border border-border p-3"><p>{qr.currency} {qr.amount} · {qr.current ? 'Verificado y disponible' : 'No disponible: revisar cuenta, vigencia o autorización'}</p><p>{qr.bank_evidence}</p><p>Vigencia: {qr.valid_until ?? 'Sin vencimiento según constancia bancaria'}</p><div className="flex flex-wrap gap-3"><a className="underline" href={`/admin/digital/cobro/qr-compartido/${qr.id}/qr`} target="_blank" rel="noreferrer">Ver QR</a><a className="underline" href={`/admin/digital/cobro/qr-compartido/${qr.id}/evidence`} target="_blank" rel="noreferrer">Constancia bancaria</a>{qr.enabled && <button type="button" className="underline" onClick={() => router.post(`/admin/digital/cobro/qr-compartido/${qr.id}/desactivar`)}>Desactivar</button>}</div></div>)}
                <form className="grid gap-4" onSubmit={e => {e.preventDefault(); shared.transform(data => ({...data, bank_reuse_confirmed:data.bank_reuse_confirmed ? '1':'0', no_expiry:data.no_expiry ? '1':'0'})); shared.post('/admin/digital/cobro/qr-compartido', {forceFormData:true, preserveScroll:true, onSuccess:()=>shared.reset('shared_qr','bank_document','bank_reuse_confirmed')});}}>
                    <label className="grid gap-2">Importe fijo<input required type="number" step="0.01" min="0.01" className="min-h-12 rounded border bg-surface p-3" value={shared.data.amount} onChange={e=>shared.setData('amount',e.target.value)}/></label>
                    <label className="grid gap-2">Moneda<select className="min-h-12 rounded border bg-surface p-3" value={shared.data.currency} onChange={e=>shared.setData('currency',e.target.value)}><option>BOB</option><option>USD</option></select></label>
                    <label className="grid gap-2">QR bancario (PNG/JPG)<input required type="file" accept="image/png,image/jpeg" onChange={e=>shared.setData('shared_qr',e.target.files?.[0] ?? null)}/></label>
                    <label className="grid gap-2">Constancia del banco (PDF)<input required type="file" accept="application/pdf" onChange={e=>shared.setData('bank_document',e.target.files?.[0] ?? null)}/></label>
                    <label className="grid gap-2">Referencia y condiciones confirmadas por el banco<textarea required minLength={20} className="min-h-28 rounded border bg-surface p-3" value={shared.data.bank_evidence} onChange={e=>shared.setData('bank_evidence',e.target.value)}/></label>
                    <label className="flex gap-3"><input type="checkbox" checked={shared.data.no_expiry} onChange={e=>shared.setData('no_expiry',e.target.checked)}/>El banco confirma que este QR no vence.</label>
                    {!shared.data.no_expiry && <label className="grid gap-2">Válido hasta (hora de Bolivia)<input required type="datetime-local" className="min-h-12 rounded border bg-surface p-3" value={shared.data.valid_until} onChange={e=>shared.setData('valid_until',e.target.value)}/></label>}
                    <label className="flex gap-3"><input required type="checkbox" checked={shared.data.bank_reuse_confirmed} onChange={e=>shared.setData('bank_reuse_confirmed',e.target.checked)}/>Verifiqué con el banco que este QR corresponde a la cuenta autorizada y cobra el importe y moneda indicados; permite reutilización entre clientes y productos bajo las condiciones documentadas.</label>
                    {Object.values(shared.errors).map((message,i)=><p role="alert" className="text-destructive" key={i}>{message}</p>)}
                    <button disabled={shared.processing || !ready} className="min-h-12 rounded-xl bg-brand px-5 font-bold text-brand-foreground">Guardar y verificar QR compartido</button>
                </form>
            </section>
        </AdminLayout>
    );
}
