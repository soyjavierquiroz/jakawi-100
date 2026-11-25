import '../../css/mi-jakawi.css';
import { ListPagination, type ListPage } from '@/components/list-pagination';
import {
    CityCommunityCard,
    type CityCommunityData,
} from '@/components/city-community-card';
import { Head, Link, router } from '@inertiajs/react';
import {
    ChevronRight,
    BookOpen,
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
        <section className="mi-section">
            <div className="flex items-center justify-between">
                <h2 className="mi-section-title">Mis retos</h2>
                {challenges.length > 0 && (
                    <Link
                        href="/retos"
                        className="mi-text-link text-sm font-bold"
                    >
                        Ver todos los retos
                    </Link>
                )}
            </div>
            {challenges.length ? (
                <div className="mt-3 space-y-3">
                    {challenges.slice(0, 3).map((c) => (
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
    hasWelcomeGift = false,
    digitalProductCount = 0,
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
    hasWelcomeGift?: boolean;
    digitalProductCount?: number;
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
                    <div className="mi-dashboard">
                        <div className="mi-primary min-w-0">
                            <section
                                className="mi-section"
                                aria-label="Mi membresía"
                            >
                                <h2 className="mi-section-title">
                                    Mi membresía
                                </h2>
                                <PersonalMembership status={membershipStatus} />
                            </section>
                            {hasWelcomeGift && (
                                <section
                                    aria-label="Regalo de bienvenida"
                                    className="mi-gift relative mt-5 overflow-hidden rounded-[24px] border border-brand/30 bg-surface p-5"
                                >
                                    <div className="pointer-events-none absolute inset-0 bg-gradient-to-br from-brand/10 via-transparent to-transparent" />
                                    <div className="relative grid grid-cols-[1fr_76px] items-center gap-x-3 sm:grid-cols-[1fr_110px] sm:gap-x-6">
                                        <p className="text-sm font-extrabold tracking-[0.1em] text-brand">
                                            REGALO DE BIENVENIDA
                                        </p>
                                        <svg
                                            aria-hidden="true"
                                            viewBox="0 0 200 200"
                                            fill="none"
                                            className="col-start-2 row-span-3 row-start-1 w-full text-brand"
                                        >
                                            <circle
                                                cx="100"
                                                cy="104"
                                                r="83"
                                                fill="currentColor"
                                                opacity=".07"
                                            />
                                            <ellipse
                                                cx="103"
                                                cy="170"
                                                rx="57"
                                                ry="9"
                                                fill="currentColor"
                                                opacity=".12"
                                            />
                                            <path
                                                d="M48 91h105v66a10 10 0 0 1-10 10H58a10 10 0 0 1-10-10V91Z"
                                                fill="var(--color-surface)"
                                                stroke="currentColor"
                                                strokeWidth="3"
                                            />
                                            <path
                                                d="M104 94h49v63a10 10 0 0 1-10 10h-39V94Z"
                                                fill="currentColor"
                                                opacity=".14"
                                            />
                                            <rect
                                                x="41"
                                                y="76"
                                                width="119"
                                                height="26"
                                                rx="6"
                                                fill="var(--color-surface)"
                                                stroke="currentColor"
                                                strokeWidth="3"
                                            />
                                            <path
                                                d="M91 77h19v90H91z"
                                                fill="currentColor"
                                            />
                                            <path
                                                d="M100 75C65 78 56 58 69 49c13-9 25 10 31 26Zm1 0c35 3 44-17 31-26-13-9-25 10-31 26Z"
                                                fill="currentColor"
                                                fillOpacity=".14"
                                                stroke="currentColor"
                                                strokeWidth="3"
                                                strokeLinejoin="round"
                                            />
                                            <path
                                                d="m162 36 3 10 10 3-10 3-3 10-3-10-10-3 10-3 3-10ZM37 48l2 6 6 2-6 2-2 6-2-6-6-2 6-2 2-6Zm133 73 2 6 6 2-6 2-2 6-2-6-6-2 6-2 2-6Z"
                                                fill="currentColor"
                                            />
                                            <circle
                                                cx="52"
                                                cy="29"
                                                r="3"
                                                fill="currentColor"
                                                opacity=".5"
                                            />
                                        </svg>
                                        <h2 className="mt-2 text-xl leading-tight font-extrabold tracking-tight sm:text-2xl">
                                            ¡Tienes un regalo digital por
                                            activar!
                                        </h2>
                                        <p className="col-span-2 mt-3 max-w-lg leading-relaxed text-muted-foreground sm:col-span-1">
                                            Elige 1 producto digital incluido
                                            con tu membresía y agrégalo a tu
                                            biblioteca sin costo adicional.
                                        </p>
                                        <Link
                                            className="col-span-2 mt-5 inline-flex min-h-12 items-center justify-center rounded-xl bg-brand px-5 font-bold text-brand-foreground transition hover:brightness-95 sm:col-span-1 sm:justify-self-start"
                                            href="/explorar?type=digital&gifts=1"
                                        >
                                            ELEGIR MI REGALO
                                        </Link>
                                        <p className="col-span-2 mt-3 text-sm leading-relaxed text-muted-foreground">
                                            1 regalo disponible · Se guardará en
                                            tu biblioteca
                                        </p>
                                    </div>
                                </section>
                            )}
                            <section
                                className="mi-section"
                                aria-labelledby="digital-title"
                            >
                                <h2
                                    id="digital-title"
                                    className="mi-section-title"
                                >
                                    Mis productos digitales
                                </h2>
                                <div className="mi-panel mi-library">
                                    <div className="flex items-center gap-4">
                                        <span className="mi-library-icon">
                                            <BookOpen
                                                aria-hidden
                                                className="size-6"
                                            />
                                        </span>
                                        <div>
                                            <p className="text-lg font-extrabold">
                                                {digitalProductCount > 0
                                                    ? `${digitalProductCount} ${digitalProductCount === 1 ? 'producto disponible' : 'productos disponibles'}`
                                                    : 'Tu biblioteca está por comenzar'}
                                            </p>
                                            <p className="mt-1 text-sm text-muted-foreground">
                                                Tus compras y regalos digitales,
                                                siempre disponibles
                                            </p>
                                        </div>
                                    </div>
                                    <Link
                                        className="mi-action mi-button"
                                        href={
                                            digitalProductCount > 0
                                                ? '/mi-jakawi/productos-digitales'
                                                : '/explorar?type=digital'
                                        }
                                    >
                                        {digitalProductCount > 0
                                            ? 'ABRIR MI BIBLIOTECA'
                                            : 'EXPLORAR PRODUCTOS DIGITALES'}
                                    </Link>
                                </div>
                            </section>
                            <section
                                className="mi-section"
                                aria-labelledby="activity-title"
                            >
                                <h2
                                    id="activity-title"
                                    className="mi-section-title"
                                >
                                    Mi actividad y ahorro
                                </h2>
                                <div className="mi-panel">
                                    <div className="mi-stats">
                                        <Metric
                                            value={`Bs ${money(membership?.confirmed_savings ?? 0)}`}
                                            label="ahorro acumulado"
                                        />
                                        <Metric
                                            value={valueStats.benefits_used}
                                            label="beneficios utilizados"
                                        />
                                        <Metric
                                            value={valueStats.experiences_lived}
                                            label="experiencias realizadas"
                                        />
                                    </div>
                                    {membership &&
                                        Number(membership.confirmed_savings) >
                                            0 && (
                                            <p className="mt-4 text-sm text-success">
                                                {membership.has_paid_for_itself
                                                    ? 'Tu membresía ya se pagó sola.'
                                                    : `Te faltan Bs ${money(membership.remaining_to_payback)} para recuperar el valor de tu membresía.`}
                                            </p>
                                        )}
                                    <ActivityList activity={activity} />
                                </div>
                            </section>
                            <PendingReceipts receipts={pendingReceipts} />
                        </div>
                        <div className="mi-benefits min-w-0">
                            <BenefitCards benefits={featuredBenefits} />
                        </div>
                        <section
                            className="mi-referral mi-section min-w-0"
                            aria-labelledby="referral-title"
                        >
                            <h2
                                id="referral-title"
                                className="mi-section-title"
                            >
                                Invita y gana
                            </h2>
                            <div className="mi-panel">
                                <p className="font-extrabold">
                                    Comparte JAKAWI con tus amigos
                                </p>
                                <p className="mt-2 text-sm leading-6 text-muted-foreground">
                                    Recomienda JAKAWI y recibe comisiones por
                                    primeras membresías de pago elegibles y
                                    verificadas.
                                </p>
                                {memberReferral.eligible && (
                                    <InviteAndEarn referral={memberReferral} />
                                )}
                                <a
                                    href="https://jakawi.club/afiliados"
                                    className="mi-text-link mt-3 inline-flex min-h-11 items-center gap-2 text-sm font-bold"
                                >
                                    Mis referidos y ganancias en CLUB{' '}
                                    <ArrowUpRight
                                        aria-hidden
                                        className="size-4"
                                    />
                                </a>
                                <p className="text-sm leading-6 text-muted-foreground">
                                    CLUB administra tus comisiones y
                                    liquidaciones. Puedes participar con tu
                                    cuenta JAKAWI.
                                </p>
                            </div>
                        </section>
                        <div className="mi-secondary min-w-0">
                            <MyChallenges challenges={challenges} />
                            {challengePagination.total > 0 && (
                                <ListPagination page={challengePagination} />
                            )}
                            <div className="mi-community">
                                <CityCommunityCard
                                    community={community}
                                    permanent
                                    compact
                                />
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </>
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
        <div className="mt-4">
            <div className="border-y border-border py-3">
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
            {(referral.joined_count > 0 ||
                referral.jp_ledger !== 0 ||
                referral.jp_balance !== 0 ||
                !!referral.jp_held) && (
                <div className="mt-4 grid grid-cols-3 gap-3 border-t border-border pt-4">
                    <Metric
                        value={referral.joined_count}
                        label="amigos que se unieron"
                    />
                    <Metric value={referral.jp_ledger} label="Saldo JP" />
                    <Metric
                        value={referral.jp_balance}
                        label="JP disponibles"
                    />
                </div>
            )}
            {referral.jp_held ? (
                <p className="mt-3 text-sm text-muted-foreground">
                    {referral.jp_held} JP retenidos
                </p>
            ) : null}
        </div>
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
            <h3 className="text-2xl font-extrabold tracking-tight">
                {labels[status.type]}
                {status.type !== 'free' && (
                    <span className="text-lg font-semibold">
                        {' '}
                        · {states[status.status]}
                    </span>
                )}
            </h3>
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
            <div className="mt-4 flex flex-wrap gap-2">
                {!status.is_active && status.type !== 'pioneer' && (
                    <a
                        href="https://jakawi.club/membresia"
                        className="mi-action inline-flex min-h-12 items-center justify-center gap-2 rounded-xl px-5 text-sm font-bold"
                    >
                        Activar mi membresía <ArrowUpRight className="size-4" />
                    </a>
                )}
                <Link
                    href="/explorar"
                    className="mi-text-link inline-flex min-h-12 items-center justify-center gap-2 rounded-xl border border-border px-5 text-sm font-bold"
                >
                    Explorar beneficios <ChevronRight className="size-4" />
                </Link>
            </div>
        </section>
    );
}

function BenefitCards({ benefits }: { benefits: Benefit[] }) {
    return (
        <section className="mi-section" aria-label="Beneficios para ti">
            <div className="flex items-center justify-between gap-3">
                <h2 className="mi-section-title">Beneficios para ti</h2>
                <Link
                    href="/explorar"
                    className="mi-text-link inline-flex min-h-11 items-center text-sm font-bold"
                >
                    VER TODOS
                </Link>
            </div>
            {!benefits.length && (
                <div className="mi-panel text-sm text-muted-foreground">
                    Encuentra beneficios para tu cuenta en el Explorador.
                </div>
            )}
            <div className="space-y-3">
                {benefits.slice(0, 3).map((benefit) => (
                    <Link
                        key={benefit.slug}
                        href={`/beneficios/${benefit.slug}`}
                        className="mi-benefit flex gap-4 rounded-2xl border border-border bg-surface p-3 transition hover:border-brand"
                    >
                        <div className="grid size-16 shrink-0 place-items-center overflow-hidden rounded-xl bg-surface-muted">
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

function Metric({ value, label }: { value: number | string; label: string }) {
    return (
        <div>
            <p className="text-xl font-extrabold tracking-tight sm:text-2xl">
                {value}
            </p>
            <p className="mt-1 text-sm leading-5 text-muted-foreground">
                {label}
            </p>
        </div>
    );
}

function ActivityList({ activity }: { activity: Activity[] }) {
    return (
        <div className="mt-4 border-t border-border pt-3">
            {activity.length ? (
                <div className="mt-3 divide-y divide-border">
                    {activity.slice(0, 3).map((item) => (
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
                <p className="text-sm leading-6 text-muted-foreground">
                    Aquí verás tus beneficios y experiencias confirmados.
                </p>
            )}
        </div>
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
