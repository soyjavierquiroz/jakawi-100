import { Head, Link, router, usePage } from '@inertiajs/react';
import { type Unlock } from '@/components/unlock-card';
import JourneyLayout from '@/layouts/journey-layout';
import { trackJourneyIntent } from '@/lib/journey-analytics';
import { useRef } from 'react';

type Detail = Unlock & {
    description?: string | null; terms?: string | null; jp_deposit: number;
    is_full: boolean; secret_mode: boolean; revealable: boolean;
    free_user_eligible: boolean; member_eligible: boolean;
    free_user_offer?: string | null; member_offer?: string | null;
    partner?: { name: string; slug: string } | null;
    locations?: Array<{ name: string; slug: string; city?: string | null }>;
};

export default function Show({ unlock, participation, hasActiveMembership = false, jpBalance }: {
    unlock: Detail; participation?: { status: string } | null; hasActiveMembership?: boolean;
    jpBalance?: { ledger_balance: number; held: number; available_balance: number } | null;
}) {
    const { auth } = usePage().props;
    const intentSentFor = useRef<string | null>(null);
    const membershipIntent = () => {
        if (intentSentFor.current === unlock.slug) return;
        intentSentFor.current = unlock.slug;
        trackJourneyIntent('unlock', unlock.slug);
    };
    const post = (action: string) => router.post(`/d/${unlock.slug}/${action}`);
    const status = participation?.status;
    const unavailable = unlock.is_full || unlock.status !== 'ACTIVE';
    const eligible = hasActiveMembership ? unlock.member_eligible : unlock.free_user_eligible;
    const membershipRequired = !unlock.free_user_eligible && unlock.member_eligible;
    const hidden = unlock.secret_mode && !unlock.revealable;
    const socialTitle = hidden ? '🔒 DESBLOQUEO JAKAWI' : unlock.title;
    const socialDescription = hidden ? `${unlock.committed_count} comprometidos. Haz que suceda.` : unlock.short_description || '';
    const share = async () => {
        const response = await fetch(`/d/${unlock.slug}/shared`, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '', Accept: 'application/json' },
        });
        const { url } = await response.json();
        if (navigator.share) await navigator.share({ title: 'Desbloqueo JAKAWI', url });
        else await navigator.clipboard.writeText(url);
    };
    const commit = () => {
        if (unlock.jp_deposit > 0 && !window.confirm(`¿DE VERDAD IRÍAS?\n\nGarantía: ${unlock.jp_deposit} JP`)) return;
        post('comprometer');
    };

    return <JourneyLayout>
        <Head title={socialTitle}><meta name="description" content={socialDescription} /><meta property="og:title" content={socialTitle} /><meta property="og:description" content={socialDescription} /></Head>
        <main className="mx-auto min-h-screen max-w-5xl px-4 pb-28 pt-8 text-foreground sm:px-8 sm:pt-12">
            <Link href="/explorar?type=unlocks" className="inline-flex min-h-11 items-center text-sm font-bold text-muted-foreground hover:text-foreground">← Explorar</Link>
            <div className="mt-5 grid gap-10 lg:grid-cols-[minmax(0,1fr)_minmax(300px,0.7fr)]">
                <div>
                    <p className="discovery-eyebrow text-brand">DESBLOQUEO JAKAWI</p>
                    <h1 className="mt-3 max-w-2xl text-[38px] leading-[0.98] font-extrabold tracking-tight sm:text-6xl">{unlock.title}</h1>
                    {unlock.short_description ? <p className="mt-5 max-w-2xl text-lg leading-7 font-semibold text-foreground-soft">{unlock.short_description}</p> : null}
                    {unlock.description ? <section className="mt-10 max-w-2xl"><h2 className="text-xl font-extrabold">QUÉ VAMOS A DESBLOQUEAR</h2><p className="mt-3 whitespace-pre-line leading-7 text-muted-foreground">{unlock.description}</p></section> : null}
                    {unlock.partner ? <section className="mt-9 border-t border-border pt-6"><h2 className="text-xl font-extrabold">CON QUIÉN</h2><Link href={`/partners/${unlock.partner.slug}`} className="mt-2 inline-flex min-h-11 items-center font-bold text-brand">{unlock.partner.name}</Link></section> : null}
                    {unlock.locations?.length ? <section className="mt-8 border-t border-border pt-6"><h2 className="text-xl font-extrabold">DÓNDE</h2><p className="mt-2 text-muted-foreground">{unlock.locations.map((location) => `${location.name}${location.city ? ` · ${location.city}` : ''}`).join(' · ')}</p></section> : null}
                    {unlock.terms ? <section className="mt-8 border-t border-border pt-6"><h2 className="text-xl font-extrabold">CONDICIONES</h2><p className="mt-3 whitespace-pre-line text-sm leading-6 text-muted-foreground">{unlock.terms}</p></section> : null}
                </div>
                <aside className="self-start rounded-[var(--radius-card)] border border-border bg-surface-elevated p-5 sm:p-7">
                    <p className="discovery-eyebrow text-brand">LA COMUNIDAD LO HACE POSIBLE</p>
                    <p className="mt-4 text-3xl font-extrabold">{unlock.committed_count} / {unlock.minimum_commitments}</p>
                    <p className="mt-1 text-sm font-semibold text-muted-foreground">personas comprometidas · faltan {unlock.remaining}</p>
                    <progress value={Math.min(unlock.committed_count, unlock.minimum_commitments)} max={unlock.minimum_commitments} aria-label="Progreso del desbloqueo" className="mt-5 h-3 w-full accent-brand" />
                    {unlock.status !== 'ACTIVE' ? <p className="mt-5 font-bold">{unlock.status === 'UNLOCKED' ? 'LO CONSEGUIMOS' : 'META ALCANZADA'}</p> : null}
                    <div className="mt-7 border-t border-border pt-6 text-sm leading-6">
                        <h2 className="text-lg font-extrabold">ANTES DE COMPROMETERTE</h2>
                        <p className="mt-3">Cuenta gratis: <strong>{unlock.free_user_eligible ? 'elegible' : 'no elegible'}</strong></p>
                        <p>Miembro activo: <strong>{unlock.member_eligible ? 'elegible' : 'no elegible'}</strong></p>
                        {membershipRequired ? <p className="mt-2 font-semibold">Este desbloqueo requiere membresía activa.</p> : null}
                        {unlock.jp_deposit > 0 ? <><p className="mt-3 font-semibold">Garantía: {unlock.jp_deposit} JP</p><p className="text-muted-foreground">Al comprometerte, esos JP quedan retenidos. Se liberan o aplican según el resultado de tu participación.</p>{jpBalance ? <p className="mt-2">Tu saldo disponible: <strong>{jpBalance.available_balance} JP</strong> · retenidos: {jpBalance.held} JP</p> : null}</> : <p className="mt-3 text-muted-foreground">Al comprometerte, te sumas a la meta. Si se alcanza, podrás confirmar tu participación.</p>}
                        {!hidden && (hasActiveMembership ? unlock.member_offer : unlock.free_user_offer) ? <p className="mt-3 font-semibold">{hasActiveMembership ? unlock.member_offer : unlock.free_user_offer}</p> : null}
                    </div>
                    <div className="mt-7 space-y-3">
                        {status === 'UNLOCKED_PENDING_CONFIRMATION' ? <><p className="font-bold">LO CONSEGUIMOS. ¿SIGUES DENTRO?</p><button onClick={() => post('confirmar')} className="discovery-primary-action w-full">SÍ, CONFIRMO</button><button onClick={() => post('cancelar')} className="discovery-secondary-action w-full">YA NO PUEDO</button></>
                        : status === 'CONFIRMED' ? <p className="font-bold">✓ ASISTENCIA CONFIRMADA</p>
                        : status === 'FULFILLED' ? <p className="font-bold">✓ CUMPLISTE</p>
                        : status === 'NO_SHOW' ? <p>No registramos tu participación.</p>
                        : status === 'COMMITTED' ? <><p className="font-bold">✓ ESTÁS DENTRO</p><p>Faltan {unlock.remaining} personas. Haz que suceda.</p><button onClick={share} className="discovery-primary-action w-full">INVITAR AMIGOS</button><button onClick={() => post('cancelar')} className="discovery-secondary-action w-full">CANCELAR COMPROMISO</button></>
                        : !unavailable && !auth.user ? <button onClick={commit} className="discovery-primary-action w-full">CREAR CUENTA GRATIS PARA COMPROMETERTE</button>
                        : !unavailable && !eligible && membershipRequired && !hasActiveMembership ? <Link href={`/membresia?journey=UNLOCK&action=COMMIT&resource_id=${unlock.id}`} onClick={membershipIntent} className="discovery-primary-action w-full">VER MEMBRESÍA</Link>
                        : !unavailable && eligible ? <button onClick={commit} className="discovery-primary-action w-full">SÍ, CUENTEN CONMIGO</button>
                        : !unavailable ? <p className="font-semibold">Tu estado actual no es elegible para este desbloqueo.</p> : null}
                    </div>
                    <button onClick={share} className="discovery-secondary-action mt-5 w-full">COMPARTIR DESBLOQUEO</button>
                </aside>
            </div>
        </main>
    </JourneyLayout>;
}
