export type BenefitJourney = { title: string; partner: string | null };

export const isDemoName = (name: string) => /\s[—–-]\s*DEMO$/i.test(name);
export const displayBenefitName = (name: string, demo: boolean) => demo ? name.replace(/\s*[—–-]\s*DEMO$/i, '') : name;

export default function BenefitJourneyContext({ journey, membership = false }: { journey: BenefitJourney; membership?: boolean }) {
    const demo = isDemoName(journey.title);
    return <section aria-label="Tu beneficio" className="mb-6 max-w-xl rounded-2xl border border-border bg-surface-muted p-4">
        <p className="discovery-eyebrow text-brand">{membership ? 'SIGUES CON TU BENEFICIO' : 'CONTINÚA TU BENEFICIO'}</p>
        <p className="mt-2 text-lg font-extrabold">{displayBenefitName(journey.title, demo)}</p>
        {journey.partner && <p className="mt-1 text-sm text-muted-foreground">En {displayBenefitName(journey.partner, demo)}</p>}
        {demo && <p className="mt-2 text-xs font-semibold">DEMO DE PRODUCTO · oferta simulada, no canjeable</p>}
        {membership && <p className="mt-3 text-sm leading-6">Este beneficio es para miembros JAKAWI. Activa tu membresía para continuar y acceder a los beneficios de miembro.</p>}
        {!membership && <p className="mt-2 text-sm text-muted-foreground">Beneficios, descuentos, premios y experiencias para vivir más tu ciudad.</p>}
    </section>;
}
