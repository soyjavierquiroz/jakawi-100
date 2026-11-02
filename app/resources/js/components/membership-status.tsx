export type MembershipStatusData = {
    type: 'free' | 'standard' | 'pioneer';
    status: 'free' | 'active' | 'cancelled' | 'scheduled' | 'expired';
    is_active: boolean;
    effective_ends_at: string | null;
    expiry_policy: string | null;
    benefits: { public_catalog: boolean; club_access: boolean; exclusive_member_benefits: boolean };
};

const plans = { free: 'Gratuito', standard: 'Estándar', pioneer: 'Pionero' };
const states = { free: 'Sin membresía de pago', active: 'Activa', cancelled: 'Cancelada', scheduled: 'Programada', expired: 'Vencida' };
export function membershipExpiry(status: MembershipStatusData): string {
    if (status.type === 'pioneer') {
        const date = status.effective_ends_at ? ` (${formatDate(status.effective_ends_at)})` : '. Fecha oficial de lanzamiento aún no definida';
        return `15 meses después del lanzamiento${date}`;
    }
    return status.effective_ends_at ? formatDate(status.effective_ends_at) : 'Sin vencimiento de membresía de pago';
}
function formatDate(value: string): string {
    return new Date(value).toLocaleDateString('es-BO', { timeZone: 'America/La_Paz', day: 'numeric', month: 'short', year: 'numeric' });
}
export default function MembershipStatus({ status }: { status: MembershipStatusData }) {
    return <section aria-label="Tu membresía" className="mt-5 rounded-3xl border border-border bg-surface-elevated p-5 text-foreground">
        <h2 className="text-lg font-bold">Tu membresía JAKAWI</h2>
        <p className="mt-2">Tipo: <strong>{plans[status.type]}</strong></p>
        <p>Estado: <strong>{states[status.status]}</strong></p>
        {status.type !== 'free' && <p className="mt-2 text-sm">Vencimiento: {membershipExpiry(status)}</p>}
        <p className="mt-3 text-sm">Puedes explorar el catálogo público y acceder a CLUB con tu cuenta JAKAWI.</p>
        <p className="mt-2 text-sm">{status.benefits.exclusive_member_benefits ? 'Disponibles: beneficios exclusivos de membresía, sujetos a las condiciones de cada beneficio.' : 'Los beneficios y canjes exclusivos de membresía requieren un plan activo; tu cuenta actual no los tiene disponibles.'}</p>
        <div className="mt-3 flex flex-wrap gap-x-5 gap-y-2 text-sm font-bold">
            <a href="https://jakawi.club" className="inline-flex min-h-11 items-center text-brand underline underline-offset-4">Ir a CLUB</a>
            {!status.is_active && <a href="https://jakawi.club/membresia" className="inline-flex min-h-11 items-center text-brand underline underline-offset-4">Conocer la membresía en CLUB</a>}
            <a href="/perfil" className="inline-flex min-h-11 items-center text-brand underline underline-offset-4">Mi perfil JAKAWI</a>
        </div>
    </section>;
}
