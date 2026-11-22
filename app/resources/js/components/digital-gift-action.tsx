import { router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog';

export type GiftAction = { kind: 'welcome' | 'promotional' | 'owned' | null; url?: string; authenticated?: boolean };

export function useDigitalGiftAction(action?: GiftAction) {
    const [open, setOpen] = useState(false);
    const [busy, setBusy] = useState(false);
    const { errors } = usePage().props;
    const label = action?.kind === 'owned' ? 'ABRIR / DESCARGAR' : action?.kind === 'welcome' ? 'ACTIVAR MI REGALO' : 'RECLAMAR MI REGALO';
    const click = () => {
        if (!action?.url) return;
        if (action.kind === 'owned') { window.location.assign(action.url); return; }
        if (action.kind === 'promotional' && !action.authenticated) { router.visit(action.url); return; }
        setOpen(true);
    };
    const confirmation = <Dialog open={open} onOpenChange={(value) => { if (!busy) setOpen(value); }}><DialogContent>
        <DialogHeader><DialogTitle>{label}</DialogTitle><DialogDescription>{action?.kind === 'welcome'
            ? 'Vas a elegir este producto como tu regalo de bienvenida. Solo puedes elegir uno.'
            : 'Vas a reclamar este producto para tu biblioteca. La invitación solo se puede utilizar una vez.'}</DialogDescription></DialogHeader>
        {Object.values(errors ?? {}).map((error, i) => <p role="alert" key={i}>{String(error)}</p>)}
        <DialogFooter><button className="min-h-12 rounded-xl border border-border px-5" disabled={busy} onClick={() => setOpen(false)}>Cancelar</button><button className="min-h-12 rounded-xl bg-brand px-5 font-bold text-brand-foreground" disabled={busy} onClick={() => {
            if (busy || !action?.url) return;
            setBusy(true);
            router.post(action.url, {confirm: true}, {onFinish: () => setBusy(false)});
        }}>{busy ? 'Activando…' : 'CONFIRMAR REGALO'}</button></DialogFooter>
    </DialogContent></Dialog>;
    return { label, click, confirmation };
}
