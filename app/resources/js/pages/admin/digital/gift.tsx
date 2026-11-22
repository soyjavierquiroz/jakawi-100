import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AdminLayout from '../layout';

export default function Gift({ product, users, query, enabled, ready }: {
    product: {name: string; slug: string}; users: {id: number; name: string; email: string}[];
    query: string; enabled: boolean; ready: boolean;
}) {
    const [invitation, setInvitation] = useState<{url: string; expires_at: string} | null>(null);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState('');
    const [term, setTerm] = useState(query);
    const [copied, setCopied] = useState(false);
    const form = useForm({user_id: '' as string | number, issuance_key: crypto.randomUUID()});
    const path = `/admin/productos-digitales/${product.slug}/regalar`;
    return <AdminLayout title="Enviar como regalo"><Head title="Enviar como regalo"><meta name="robots" content="noindex,nofollow"/><meta name="referrer" content="no-referrer"/></Head>
        <h2 className="text-2xl font-bold">{product.name}</h2>
        {!enabled && <p role="status" className="my-5 rounded-xl border p-4">Los regalos reales están desactivados.</p>}
        {!ready && <p role="status" className="my-5 rounded-xl border p-4">Autoriza el regalo promocional y confirma el PDF definitivo en el editor del producto.</p>}
        {invitation ? <section className="my-5 grid max-w-2xl gap-4 rounded-xl border p-5"><h3 className="text-xl font-bold">Enlace de regalo creado</h3><p>Comparte este enlace individual con el destinatario. Solo se muestra en esta entrega; guárdalo antes de salir.</p><input aria-label="Enlace de regalo" readOnly value={invitation.url} className="min-h-12 w-full rounded border bg-surface p-3" onFocus={e => e.target.select()}/><button className="min-h-12 rounded-xl bg-brand px-5 font-bold text-brand-foreground" onClick={async () => {await navigator.clipboard.writeText(invitation.url); setCopied(true);}}>{copied ? 'ENLACE COPIADO' : 'COPIAR ENLACE'}</button><p>Vence: {new Date(invitation.expires_at).toLocaleString('es-BO', {timeZone: 'America/La_Paz'})}</p><p>No se envían correos automáticamente.</p></section> : <>
        <form className="my-5 flex max-w-2xl gap-3" onSubmit={e => {e.preventDefault(); router.get(path, {q: term}, {preserveState: true});}}><input aria-label="Buscar usuario existente" value={term} onChange={e => setTerm(e.target.value)} placeholder="Nombre o correo del usuario" className="min-h-12 min-w-0 flex-1 rounded border bg-surface p-3" maxLength={200}/><button className="min-h-12 rounded border px-4">Buscar</button></form>
        <form className="grid max-w-2xl gap-5" onSubmit={async e => {e.preventDefault(); if (busy) return; setBusy(true); setError(''); try {const xsrf = document.cookie.split('; ').find(cookie => cookie.startsWith('XSRF-TOKEN='))?.slice('XSRF-TOKEN='.length); const csrf = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content; const response = await fetch(path, {method: 'POST', headers: {'Content-Type': 'application/json', Accept: 'application/json', ...(xsrf ? {'X-XSRF-TOKEN': decodeURIComponent(xsrf)} : csrf ? {'X-CSRF-TOKEN': csrf} : {})}, body: JSON.stringify(form.data)}); const result = await response.json(); if (!response.ok) throw new Error(result.message || 'No se pudo crear la invitación.'); setInvitation(result);} catch (reason) {setError(reason instanceof Error ? reason.message : 'No se pudo crear la invitación.');} finally {setBusy(false);}}}>
            <label className="grid gap-2">Destinatario<select aria-label="Destinatario" value={form.data.user_id} onChange={e => form.setData('user_id', e.target.value)} className="min-h-12 rounded border bg-surface p-3"><option value="">Generar invitación individual para quien reciba el enlace</option>{users.map(user => <option key={user.id} value={user.id}>{user.name} · {user.email}</option>)}</select></label>
            <p>{form.data.user_id ? 'Solo el usuario seleccionado podrá reclamarlo después de iniciar sesión.' : 'Quien tenga el enlace podrá reclamarlo al iniciar sesión o registrarse. Compártelo de forma privada con una sola persona.'}</p>
            {error && <p role="alert">{error}</p>}
            {Object.values(form.errors).map((error, i) => <p role="alert" key={i}>{error}</p>)}
            <button disabled={!enabled || !ready || busy} className="min-h-12 rounded-xl bg-brand px-6 font-bold text-brand-foreground disabled:opacity-50">GENERAR ENLACE DE REGALO</button>
        </form></>}
        <Link className="mt-6 inline-flex min-h-12 items-center text-brand underline" href={`/admin/productos-digitales/${product.slug}/editar`}>Volver al producto</Link>
    </AdminLayout>;
}
