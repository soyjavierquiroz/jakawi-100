import { useForm } from '@inertiajs/react';

type Assignment = {
    owner_user_id: number;
    owner: { id: number; name: string };
    source: string;
    assigned_at: string;
};

type Props = {
    targetType: 'PARTNER_APPLICATION' | 'PROGRAM_APPLICATION';
    targetId: number;
    ownership: Assignment | null;
    candidates: { id: number; name: string }[];
};

export default function OwnershipBlock({ targetType, targetId, ownership, candidates }: Props) {
    const assign = useForm<{ owner_user_id: number | ''; reason: string }>({ owner_user_id: ownership?.owner_user_id ?? '', reason: '' });
    const remove = useForm({ reason: '' });
    const path = `/admin/ownership/${targetType}/${targetId}`;

    return <section className="mt-8 max-w-2xl rounded border border-border p-4">
        <h2 className="font-semibold">RESPONSABLE</h2>
        <p className="mt-2">{ownership?.owner.name ?? 'Sin asignar'}</p>
        {ownership && <p className="text-sm text-muted-foreground">{ownership.source} · {ownership.assigned_at}</p>}
        <form className="mt-4 flex flex-wrap items-end gap-2" onSubmit={event => { event.preventDefault(); assign.post(path, { onSuccess: () => assign.setData('reason', '') }); }}>
            <label className="grid gap-1 text-sm">Responsable
                <select className="rounded border border-border bg-background p-2" value={assign.data.owner_user_id} onChange={event => assign.setData('owner_user_id', event.target.value ? Number(event.target.value) : '')} required>
                    <option value="">Seleccionar</option>
                    {candidates.map(candidate => <option key={candidate.id} value={candidate.id}>{candidate.name} · #{candidate.id}</option>)}
                </select>
            </label>
            <label className="grid gap-1 text-sm">Motivo
                <input className="rounded border border-border bg-background p-2" value={assign.data.reason} onChange={event => assign.setData('reason', event.target.value)} maxLength={2000} required />
            </label>
            <button className="rounded bg-brand px-3 py-2 font-semibold text-brand-foreground" disabled={assign.processing}>{ownership ? 'Reasignar' : 'Asignar'}</button>
            {assign.errors.owner_user_id && <p className="w-full text-sm text-red-600">{assign.errors.owner_user_id}</p>}
        </form>
        {ownership && <form className="mt-4 flex flex-wrap items-end gap-2" onSubmit={event => { event.preventDefault(); remove.delete(path, { onSuccess: () => remove.reset() }); }}>
            <label className="grid gap-1 text-sm">Motivo para dejar sin asignar
                <input className="rounded border border-border bg-background p-2" value={remove.data.reason} onChange={event => remove.setData('reason', event.target.value)} maxLength={2000} required />
            </label>
            <button className="rounded border border-border px-3 py-2" disabled={remove.processing}>Dejar sin asignar</button>
        </form>}
    </section>;
}
