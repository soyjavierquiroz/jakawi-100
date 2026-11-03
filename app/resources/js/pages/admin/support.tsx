import { Link, useForm } from '@inertiajs/react';
import AdminLayout from './layout';
import { ListPagination, type ListPage } from '@/components/list-pagination';

type User = { id: number; name: string; email: string };
export default function Support({ users, partner, fresh, mfa }: { users: ListPage & { data: User[] }; partner: { id: number; name: string } | null; fresh: boolean; mfa: boolean }) {
    const auth = useForm({ password: '', code: '' });
    const start = useForm({ user_id: users.data.length === 1 ? String(users.data[0].id) : '', partner_id: partner?.id ?? '', reason: '', mode: 'view' });
    return <AdminLayout title={partner ? `Soporte Partner — ${partner.name}` : 'Consulta de usuarios · Ver como usuario'}>
        <p>Observa la experiencia COM con los permisos reales de la cuenta. Ver como conserva sólo lectura; Gestionar Partner habilita sus formularios editoriales. Máximo 15 minutos. No inicia sesión en CLUB.</p>
        {!fresh && <form className="my-5 grid max-w-lg gap-3" onSubmit={e => { e.preventDefault(); auth.post('/admin/soporte/reautenticar', { onFinish: () => auth.reset() }); }}>
            <h2 className="font-bold">Confirma tu identidad administrativa</h2>
            <label>Tu contraseña<input className="block w-full rounded-xl border p-3" required type="password" autoComplete="current-password" value={auth.data.password} onChange={e => auth.setData('password', e.target.value)} /></label>
            {mfa && <label>Código MFA vigente<input className="block w-full rounded-xl border p-3" required autoComplete="one-time-code" inputMode="numeric" value={auth.data.code} onChange={e => auth.setData('code', e.target.value)} /></label>}
            {Object.values(auth.errors).map(error => <p role="alert" key={error}>{error}</p>)}
            <button disabled={auth.processing} className="rounded-xl bg-brand p-3 text-white">Reautenticar</button>
        </form>}
        <form method="get" className="my-5 flex flex-wrap gap-3"><label>Buscar usuario<input name="q" placeholder="Nombre o correo" className="block rounded-xl border p-3" /></label>{partner && <input type="hidden" name="partner" value={partner.id} />}<button className="rounded-xl border p-3">Buscar</button></form>
        <form className="grid max-w-3xl gap-4" onSubmit={e => { e.preventDefault(); const mode = ((e.nativeEvent as SubmitEvent).submitter as HTMLButtonElement)?.value || 'view'; start.transform(data => ({ ...data, mode })); start.post('/admin/soporte'); }}>
            <label>Motivo obligatorio<textarea required minLength={10} maxLength={1000} className="block w-full rounded-xl border p-3" value={start.data.reason} onChange={e => start.setData('reason', e.target.value)} /></label>
            <fieldset disabled={!fresh || start.processing} className="grid gap-3"><legend className="font-bold">{partner ? 'Representantes autorizados existentes' : 'Usuarios COM'}</legend>
                {users.data.map(user => <label key={user.id} className="flex min-w-0 items-center gap-3 rounded-xl border p-3"><input required type="radio" name="user_id" value={user.id} checked={start.data.user_id === String(user.id)} onChange={e => start.setData('user_id', e.target.value)} /><span className="min-w-0 break-words">{user.name} · #{user.id}<small className="block break-all">{user.email}</small></span></label>)}
                {!users.data.length && <p>No hay cuentas elegibles. No se crearán asociaciones.</p>}
                <button type="submit" value="view" className="rounded-xl bg-brand p-3 text-white" disabled={!users.data.length}>{partner ? 'Ver como Partner' : 'Ver como usuario'}</button>
                {partner && <button type="submit" value="manage" className="rounded-xl bg-brand p-3 text-white" disabled={!users.data.length}>Gestionar Partner</button>}
            </fieldset>
            {Object.values(start.errors).map(error => <p role="alert" key={error}>{error}</p>)}
        </form><ListPagination page={users} /><Link href="/admin">Volver a administración</Link>
    </AdminLayout>;
}
