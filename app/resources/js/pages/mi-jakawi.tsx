import '../../css/mi-jakawi.css';
import { ListPagination, type ListPage } from '@/components/list-pagination';
import {
    CityCommunityCard,
    type CityCommunityData,
} from '@/components/city-community-card';
import { Head, Link, router } from '@inertiajs/react';
import {
    Check,
    ChevronRight,
    Sparkles,
    ArrowUpRight,
    UserRound,
    ImageIcon,
} from 'lucide-react';

type MyChallenge = {
    title: string;
    slug: string;
    qualification_status: string;
    selection_status: string;
    progress: number | null;
    target: number | null;
    metric: string | null;
    reward: string | null;
    grant_status: string | null;
};
function MyChallenges({ challenges }: { challenges: MyChallenge[] }) {
    return (
        <section className="mt-8">
            <div className="flex items-center justify-between">
                <h2 className="text-xl font-extrabold">Mis retos</h2>
                {challenges.length > 0 && (
                    <Link
                        href="/retos"
                        className="mi-text-link text-sm font-bold"
                    >
                        Ver retos
                    </Link>
                )}
            </div>
            {challenges.length ? (
                <div className="mt-3 space-y-3">
                    {challenges.map((c) => (
                        <Link
                            key={c.slug}
                            href={`/retos/${c.slug}`}
                            className="block rounded-2xl border p-4"
                        >
                            <p className="font-extrabold">{c.title}</p>
                            <p className="mt-1 text-sm font-bold text-brand">
                                {c.grant_status === 'granted' ||
                                c.grant_status === 'fulfilled'
                                    ? 'GANADOR'
                                    : c.selection_status === 'candidate'
                                      ? 'CUMPLIDO · Esperando revisión'
                                      : c.qualification_status === 'qualified'
                                        ? 'CUMPLIDO'
                                        : 'EN PROGRESO'}
                            </p>
                            {c.progress !== null && c.target !== null && (
                                <p className="mt-1 text-sm">
                                    {c.progress} / {c.target}{' '}
                                    {c.metric === 'views'
                                        ? 'vistas'
                                        : c.metric === 'comments'
                                          ? 'comentarios'
                                          : 'likes'}
                                </p>
                            )}
                            {c.reward && (
                                <p className="mt-1 text-sm font-bold">
                                    {c.reward}
                                </p>
                            )}
                        </Link>
                    ))}
                </div>
            ) : (
                <div className="mt-3 rounded-2xl border border-border bg-surface p-5">
                    <p className="text-sm leading-6 text-muted-foreground">
                        Tu próximo reto empieza cuando tú quieras.
                    </p>
                    <Link
                        href="/retos"
                        className="mi-text-link mt-2 inline-flex min-h-11 items-center gap-2 text-sm font-bold"
                    >
                        Explorar retos <ChevronRight className="size-4" />
                    </Link>
                </div>
            )}
        </section>
    );
}
import {
    membershipExpiry,
    type MembershipStatusData,
} from '@/components/membership-status';

type Membership = {
    id: number;
    status: string;
    starts_at: string;
    ends_at: string | null;
    is_active: boolean;
    amount_paid: string;
    confirmed_savings: string;
    remaining_to_payback: string;
    has_paid_for_itself: boolean;
};
type MembershipConfig = { price_bob: number; duration_days: number };
type Activity = {
    id: string;
    type: 'redemption' | 'experience';
    title: string;
    detail: string | null;
    savings_amount?: string;
    happened_at: string;
};
type Benefit = {
    slug: string;
    title: string;
    partner_name: string;
    image_url: string | null;
    access_label: string;
    availability: { available: boolean; reason: string | null };
};
type MemberReferral = {
    eligible: boolean;
    code: string | null;
    link: string | null;
    joined_count: number;
    jp_ledger: number;
    jp_balance: number;
    jp_held?: number;
};

function formatDate(value: string) {
    return new Intl.DateTimeFormat('es-BO', {
        dateStyle: 'medium',
        timeZone: 'America/La_Paz',
    }).format(new Date(value));
}
function money(value: string | number) {
    return Number(value).toFixed(2);
}

export default function MiJakawi({
    pendingReceipts = [],
    community,
    membership,
    membershipStatus,
    valueStats,
    activity,
    featuredBenefits,
    memberReferral,
    challenges = [],
    challengePagination,
}: {
    pendingReceipts?: PendingReceipt[];
    community?: CityCommunityData | null;
    challengePagination: ListPage;
    challenges?: MyChallenge[];
    membership: Membership | null;
    membershipStatus: MembershipStatusData;
    membershipConfig: MembershipConfig;
    valueStats: { benefits_used: number; experiences_lived: number };
    activity: Activity[];
    featuredBenefits: Benefit[];
    memberReferral: MemberReferral;
}) {
    const savings = Number(membership?.confirmed_savings ?? 0);
    const amountPaid = Number(membership?.amount_paid ?? 0);
    const isPaidBack = membership?.has_paid_for_itself;

    return (
        <>
            <Head title="Mi JAKAWI" />
            <main className="mi-jakawi min-h-screen bg-background px-4 pt-7 pb-28 text-foreground sm:px-6 sm:pt-10">
                <div className="mx-auto w-full max-w-5xl">
                    <header className="mb-6 flex items-center justify-between gap-4">
                        <div>
                            <p className="text-xs font-extrabold tracking-[0.16em] text-brand">
                                TU ESPACIO PERSONAL
                            </p>
                            <h1 className="mt-2 text-3xl font-extrabold tracking-tight sm:text-4xl">
                                Mi JAKAWI<span className="text-brand">.</span>
                            </h1>
                            <p className="mt-2 text-sm text-muted-foreground">
                                Tu cuenta, tus beneficios y lo que ya viviste.
                            </p>
                        </div>
                        <Link
                            href="/perfil"
                            className="mi-profile inline-flex min-h-11 shrink-0 items-center gap-2 rounded-full border border-border bg-surface px-4 text-sm font-bold"
                        >
                            <UserRound className="size-4" />
                            <span>Mi perfil</span>
                        </Link>
                    </header>
                    <PendingReceipts receipts={pendingReceipts} />
                    <div className="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_320px]">
                        <div className="min-w-0">
                            <PersonalMembership status={membershipStatus} />
                            <ClubAffiliatesCard />
                            <BenefitCards benefits={featuredBenefits} />
                            {membership && (
                                <>
                                    <section
                                        className={
                                            isPaidBack
                                                ? 'mt-7 rounded-[24px] bg-success-surface p-6'
                                                : 'mt-7 border-y border-border py-6'
                                        }
                                    >
                                        <p className="text-xs font-extrabold tracking-[0.12em] uppercase">
                                            AHORRO ACUMULADO
                                        </p>
                                        <p className="mt-2 text-5xl font-extrabold tracking-[-0.05em]">
                                            Bs{' '}
                                            {money(
                                                membership.confirmed_savings,
                                            )}
                                        </p>
                                        <p className="mt-1 text-sm font-bold text-success">
                                            AHORRADOS
                                        </p>
                                        {isPaidBack ? (
                                            <div className="mt-6">
                                                <p className="flex items-center gap-2 text-2xl font-extrabold">
                                                    <span className="grid size-6 place-items-center rounded-full bg-success text-success-foreground">
                                                        <Check className="size-4 stroke-[3]" />
                                                    </span>{' '}
                                                    YA SE PAGÓ SOLA
                                                </p>
                                                <p className="mt-2 leading-6 text-muted-foreground">
                                                    Pagaste Bs{' '}
                                                    {money(
                                                        membership.amount_paid,
                                                    )}
                                                    . Ya ahorraste Bs{' '}
                                                    {money(
                                                        membership.confirmed_savings,
                                                    )}
                                                    .
                                                </p>
                                            </div>
                                        ) : (
                                            <div className="mt-6">
                                                <p className="text-sm leading-6 text-muted-foreground">
                                                    {savings === 0
                                                        ? 'Empieza a aprovechar tu JAKAWI.'
                                                        : `Te faltan Bs ${money(membership.remaining_to_payback)} para que tu JAKAWI se pague sola.`}
                                                </p>
                                                <div className="mt-4 h-2 overflow-hidden rounded-full bg-surface-muted">
                                                    <div
                                                        className="h-full rounded-full bg-brand"
                                                        style={{
                                                            width: `${Math.min(100, Math.max(0, amountPaid > 0 ? (savings / amountPaid) * 100 : 0))}%`,
                                                        }}
                                                    />
                                                </div>
                                            </div>
                                        )}
                                    </section>
                                    <section className="mt-8 flex gap-8 border-b border-border pb-7">
                                        <Metric
                                            value={valueStats.benefits_used}
                                            label="beneficios utilizados"
                                        />
                                        <Metric
                                            value={valueStats.experiences_lived}
                                            label="experiencias vividas"
                                        />
                                    </section>
                                    {memberReferral.eligible ? (
                                        <InviteAndEarn
                                            referral={memberReferral}
                                        />
                                    ) : null}
                                    <ActivityList activity={activity} />
                                </>
                            )}
                            <MyChallenges challenges={challenges} />
                            {challengePagination.total > 0 && (
                                <ListPagination page={challengePagination} />
                            )}
                        </div>
                        <aside
                            className="min-w-0 space-y-5"
                            aria-label="Más en JAKAWI"
                        >
                            <ExploreCard status={membershipStatus} />
                            <div className="mi-community">
                                <CityCommunityCard
                                    community={community}
                                    permanent
                                    compact
                                />
                            </div>
                        </aside>
                    </div>
                </div>
            </main>
        </>
    );
}

function ClubAffiliatesCard() {
    return (
        <section
            aria-labelledby="club-affiliates-title"
            className="mi-affiliates mt-5 rounded-3xl border border-border bg-surface p-5"
        >
            <p className="flex items-center gap-2 text-xs font-extrabold tracking-[0.12em] uppercase">
                <span aria-hidden className="size-2 rounded-full bg-[#ffd166]" />
                Gana con JAKAWI
            </p>
            <h2 id="club-affiliates-title" className="mt-2 text-xl font-extrabold">
                Invita amigos. Gana comisiones.
            </h2>
            <p className="mt-2 text-sm leading-6 text-muted-foreground">
                Recomienda JAKAWI y recibe comisiones por primeras membresías de
                pago elegibles y verificadas. Puedes participar con tu cuenta
                gratuita, Estándar o Pionero, sin comprar una membresía.
            </p>
            <a
                href="https://jakawi.club/afiliados"
                className="mi-action mt-4 inline-flex min-h-11 items-center justify-center gap-2 rounded-full px-5 py-3 text-sm font-extrabold"
            >
                Mis referidos y ganancias <ArrowUpRight aria-hidden className="size-4 shrink-0" />
            </a>
            <p className="mt-3 text-xs leading-5 text-muted-foreground">
                JAKAWI.CLUB gestiona el acceso de afiliado, la elegibilidad, tus
                enlaces y referidos, las comisiones, tarifas y liquidaciones.
            </p>
        </section>
    );
}

function InviteAndEarn({ referral }: { referral: MemberReferral }) {
    const share = () => {
        router.post(
            '/mi-jakawi/referral-shared',
            {},
            {
                preserveState: true,
                preserveScroll: true,
                onSuccess: () => undefined,
            },
        );
        const text = 'Invita a tus amigos a vivir más la ciudad.';
        if (navigator.share)
            void navigator
                .share({
                    title: 'JAKAWI',
                    text,
                    url: referral.link ?? undefined,
                })
                .catch(() => undefined);
        else void navigator.clipboard?.writeText(referral.link ?? '');
    };
    const copy = () => {
        void navigator.clipboard?.writeText(referral.link ?? '');
        router.post(
            '/mi-jakawi/referral-shared',
            {},
            { preserveState: true, preserveScroll: true },
        );
    };
    const whatsapp = () => {
        router.post(
            '/mi-jakawi/referral-shared',
            {},
            { preserveState: true, preserveScroll: true },
        );
        window.open(
            `https://wa.me/?text=${encodeURIComponent(`Invita a tus amigos a vivir más la ciudad. ${referral.link ?? ''}`)}`,
            '_blank',
            'noopener,noreferrer',
        );
    };
    return (
        <section className="mt-8 rounded-[28px] bg-surface p-6">
            <p className="text-xs font-extrabold tracking-[0.12em] text-brand uppercase">
                INVITA Y GANA
            </p>
            <h2 className="mt-2 text-2xl font-extrabold">
                Invita a tus amigos a vivir más la ciudad.
            </h2>
            <div className="mt-5 rounded-2xl border border-border bg-background p-4">
                <p className="text-xs font-bold text-muted-foreground uppercase">
                    Tu código
                </p>
                <p className="mt-1 text-xl font-extrabold tracking-[0.12em]">
                    {referral.code}
                </p>
                <p className="mt-3 text-sm break-all text-muted-foreground">
                    {referral.link}
                </p>
            </div>
            <div className="mt-4 grid grid-cols-3 gap-2">
                <button
                    onClick={copy}
                    className="min-h-11 rounded-xl border border-border px-2 text-xs font-extrabold"
                >
                    COPIAR ENLACE
                </button>
                <button
                    onClick={whatsapp}
                    className="min-h-11 rounded-xl border border-border px-2 text-xs font-extrabold"
                >
                    WHATSAPP
                </button>
                <button
                    onClick={share}
                    className="min-h-11 rounded-xl bg-brand px-2 text-xs font-extrabold text-brand-foreground"
                >
                    COMPARTIR
                </button>
            </div>
            <div className="mt-6 grid grid-cols-3 gap-3 border-t border-border pt-5">
                <Metric
                    value={referral.joined_count}
                    label="amigos que se unieron"
                />
                <Metric value={referral.jp_ledger} label="Saldo JP" />
                <Metric value={referral.jp_balance} label="JP disponibles" />
            </div>
            {referral.jp_held ? (
                <p className="mt-3 text-sm text-muted-foreground">
                    {referral.jp_held} JP retenidos
                </p>
            ) : null}
        </section>
    );
}

function PersonalMembership({ status }: { status: MembershipStatusData }) {
    const labels = {
        free: 'Cuenta gratuita',
        pioneer: 'Pionero',
        standard: 'Estándar',
    };
    const states = {
        free: 'Gratuita',
        active: 'Activa',
        expired: 'Vencida',
        cancelled: 'Cancelada',
        scheduled: 'Programada',
    };
    return (
        <section
            aria-label="Tu membresía"
            className="mi-membership relative overflow-hidden rounded-3xl border border-border bg-surface p-5 sm:p-6"
        >
            <div className="mb-5 flex items-center justify-between gap-3">
                <span className="text-xs font-extrabold tracking-[0.14em] text-muted-foreground uppercase">
                    Tu membresía
                </span>
                <Sparkles aria-hidden className="size-5 text-brand" />
            </div>
            <h2 className="text-2xl font-extrabold tracking-tight">
                {labels[status.type]}
                {status.type !== 'free' && (
                    <span className="text-lg font-semibold">
                        {' '}
                        · {states[status.status]}
                    </span>
                )}
            </h2>
            <p className="mt-3 flex items-start gap-2 text-sm leading-6">
                <span
                    className={`mt-2 size-2 shrink-0 rounded-full ${status.is_active ? 'bg-success' : 'bg-reward'}`}
                />
                {status.benefits.exclusive_member_benefits
                    ? 'Acceso a beneficios de membresía desde ahora.'
                    : 'Explora el catálogo y descubre los beneficios para tu cuenta.'}
            </p>
            {status.type !== 'free' && (
                <p className="mt-3 border-t border-border pt-3 text-sm leading-6 text-muted-foreground">
                    {status.type === 'pioneer'
                        ? 'Tu vigencia: '
                        : 'Vencimiento efectivo: '}
                    {membershipExpiry(status)}
                </p>
            )}
            <div className="mt-5 rounded-2xl bg-surface-muted p-4 text-sm leading-6">
                <p className="font-bold">Con tu cuenta</p>
                <p className="mt-1 text-muted-foreground">
                    {status.benefits.public_catalog &&
                        'Catálogo público y beneficios que no requieren membresía, según sus condiciones.'}
                    {status.benefits.club_access &&
                        ' Acceso a CLUB con tu cuenta JAKAWI.'}
                </p>
                {!status.benefits.exclusive_member_benefits && (
                    <p className="mt-3 text-muted-foreground">
                        <strong className="text-foreground">
                            Por activar:
                        </strong>{' '}
                        beneficios y canjes exclusivos de membresía.
                    </p>
                )}
            </div>
            <div className="mt-5 flex flex-col gap-3 sm:flex-row">
                {!status.is_active && status.type !== 'pioneer' && (
                    <a
                        href="https://jakawi.club/membresia"
                        className="mi-action inline-flex min-h-12 items-center justify-center gap-2 rounded-xl px-5 text-sm font-bold"
                    >
                        Activar mi membresía <ArrowUpRight className="size-4" />
                    </a>
                )}
                <Link
                    href="/beneficios"
                    className="mi-text-link inline-flex min-h-12 items-center justify-center gap-2 rounded-xl border border-border px-5 text-sm font-bold"
                >
                    Explorar beneficios <ChevronRight className="size-4" />
                </Link>
            </div>
        </section>
    );
}

function ExploreCard({ status }: { status: MembershipStatusData }) {
    return (
        <section className="rounded-3xl border border-border bg-surface p-5">
            <span className="inline-flex rounded-full bg-reward/20 p-2">
                <Sparkles className="size-5" />
            </span>
            <h2 className="mt-4 text-xl font-extrabold">
                Más razones para salir
            </h2>
            <p className="mt-2 text-sm leading-6 text-muted-foreground">
                Encuentra tu próximo plan. Cada beneficio indica sus requisitos
                y condiciones.
            </p>
            <Link
                href="/beneficios"
                className="mi-text-link mt-4 flex min-h-11 items-center justify-between gap-2 text-sm font-bold"
            >
                Explorar beneficios <ChevronRight className="size-4" />
            </Link>
            {!status.is_active && status.type !== 'pioneer' && (
                <div className="mt-3 border-t border-border pt-4">
                    <p className="text-sm leading-6 text-muted-foreground">
                        Los beneficios exclusivos requieren una membresía
                        activa. Consulta la oferta vigente en CLUB.
                    </p>
                    <a
                        href="https://jakawi.club/membresia"
                        className="mi-action mt-4 flex min-h-12 items-center justify-center gap-2 rounded-xl px-3 text-sm font-bold"
                    >
                        Activar mi membresía <ArrowUpRight className="size-4" />
                    </a>
                </div>
            )}
        </section>
    );
}

function BenefitCards({ benefits }: { benefits: Benefit[] }) {
    if (!benefits.length) return null;
    return (
        <section className="mt-8" aria-label="Beneficios para explorar">
            <div className="flex items-center justify-between gap-3">
                <h2 className="text-xl font-extrabold">Lo que te espera</h2>
                <Link
                    href="/beneficios"
                    className="mi-text-link inline-flex min-h-11 items-center text-sm font-bold"
                >
                    Ver todos
                </Link>
            </div>
            <p className="mb-4 text-sm leading-6 text-muted-foreground">
                Revisa el acceso de tu cuenta antes de elegir.
            </p>
            <div className="space-y-3">
                {benefits.map((benefit) => (
                    <Link
                        key={benefit.slug}
                        href={`/beneficios/${benefit.slug}`}
                        className="mi-benefit flex gap-4 rounded-2xl border border-border bg-surface p-3 transition hover:border-brand"
                    >
                        <div className="grid size-20 shrink-0 place-items-center overflow-hidden rounded-xl bg-surface-muted sm:size-24">
                            {benefit.image_url ? (
                                <img
                                    src={benefit.image_url}
                                    alt=""
                                    loading="lazy"
                                    className="size-full object-cover"
                                />
                            ) : (
                                <ImageIcon
                                    className="size-6 text-muted-foreground"
                                    aria-hidden
                                />
                            )}
                        </div>
                        <div className="min-w-0 flex-1 self-center">
                            <p className="text-xs text-muted-foreground">
                                {benefit.partner_name}
                            </p>
                            <h3 className="mt-1 text-sm leading-5 font-extrabold sm:text-base">
                                {benefit.title}
                            </h3>
                            <p className="mt-2 text-xs leading-5 text-muted-foreground">
                                {benefit.availability.available
                                    ? benefit.access_label
                                    : benefit.availability.reason}
                            </p>
                            <span className="mi-text-link mt-1 inline-flex min-h-7 items-center gap-1 text-xs font-bold">
                                Ver detalle <ChevronRight className="size-3" />
                            </span>
                        </div>
                    </Link>
                ))}
            </div>
        </section>
    );
}

function Metric({ value, label }: { value: number; label: string }) {
    return (
        <div>
            <p className="text-2xl font-extrabold">{value}</p>
            <p className="mt-1 text-xs leading-4 font-bold text-muted-foreground">
                {label}
            </p>
        </div>
    );
}

function ActivityList({ activity }: { activity: Activity[] }) {
    return (
        <section className="mt-9">
            <h2 className="text-xl font-extrabold">Tu actividad</h2>
            {activity.length ? (
                <div className="mt-3 divide-y divide-border">
                    {activity.map((item) => (
                        <div
                            key={item.id}
                            className="flex items-center justify-between gap-4 py-4"
                        >
                            <div className="min-w-0">
                                <p className="truncate font-extrabold">
                                    {item.title}
                                </p>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    {item.detail}
                                    {item.detail ? ' · ' : ''}
                                    {formatDate(item.happened_at)}
                                </p>
                            </div>
                            {item.type === 'redemption' &&
                            item.savings_amount ? (
                                <p className="shrink-0 text-sm font-extrabold text-success">
                                    +Bs {money(item.savings_amount)} ahorrados
                                </p>
                            ) : (
                                <p className="shrink-0 text-sm font-extrabold text-success">
                                    VIVIDA
                                </p>
                            )}
                        </div>
                    ))}
                </div>
            ) : (
                <div className="mt-4 border-l-2 border-brand pl-4">
                    <p className="font-extrabold">
                        Tu primera experiencia JAKAWI empieza aquí.
                    </p>
                    <p className="mt-1 text-sm leading-6 text-muted-foreground">
                        Cuando recibas un beneficio confirmado, tu valor
                        aparecerá aquí.
                    </p>
                    <Link
                        href="/explorar"
                        className="mt-4 inline-flex min-h-11 items-center gap-2 text-sm font-extrabold text-brand"
                    >
                        EXPLORAR <ChevronRight className="size-4" />
                    </Link>
                </div>
            )}
        </section>
    );
}

type PendingReceipt = { reference: string; upload_url: string };
function PendingReceipts({ receipts }: { receipts: PendingReceipt[] }) {
    return receipts.length ? (
        <section
            className="mt-5 space-y-3"
            aria-label="Comprobantes pendientes"
        >
            {receipts.map((receipt) => (
                <div
                    key={receipt.reference}
                    className="rounded-2xl border border-brand bg-surface p-5"
                >
                    <h2 className="text-lg font-extrabold">
                        Falta adjuntar comprobante
                    </h2>
                    <p className="mt-2 text-sm text-muted-foreground">
                        Tu aviso está registrado. Puedes adjuntar el archivo
                        ahora o volver después.
                    </p>
                    <a
                        href={receipt.upload_url}
                        className="mt-3 inline-flex min-h-11 items-center font-bold text-brand"
                    >
                        Adjunta tu comprobante
                    </a>
                </div>
            ))}
        </section>
    ) : null;
}
