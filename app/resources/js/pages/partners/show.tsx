import { Head, Link, usePage } from '@inertiajs/react';
import { MapPin } from 'lucide-react';
import { JakawiImage } from '@/components/jakawi-image';

type Card = {
    id: number;
    slug: string;
    title: string;
    image_url?: string;
    short_description?: string;
    description?: string;
    estimated_savings?: string;
    availability_label?: string;
    regular_price?: string;
    member_price?: string;
    currency?: string;
    sessions?: { starts_at: string }[];
    ends_at?: string;
    reward_type?: string;
    reward_jp_amount?: number;
    manual_prize_description?: string;
};
type Partner = {
    name: string;
    slug: string;
    category?: string;
    description?: string;
    logo_url?: string;
    cover_url?: string;
};
type Location = {
    id: number;
    slug: string;
    name: string;
    city_name?: string;
    city?: string;
    zone?: string;
    address?: string;
    address_reference?: string;
    maps_url?: string;
    opening_hours?: Record<string, unknown>;
};
const categoryLabels: Record<string, string> = {
    food: 'Comer',
    cafe: 'Café',
    fitness: 'Entrenar',
    wellness: 'Bienestar',
    beauty: 'Belleza',
    entertainment: 'Entretenimiento',
    nightlife: 'Salir',
    shopping: 'Comprar',
    services: 'Servicios',
    experiences: 'Experiencias',
};
const date = (value: string) =>
    new Intl.DateTimeFormat('es-BO', {
        day: 'numeric',
        month: 'long',
        timeZone: 'America/La_Paz',
    }).format(new Date(value));

function Inventory({
    title,
    items,
    journey,
    cta,
}: {
    title: string;
    items: Card[];
    journey: string;
    cta: string;
}) {
    if (!items.length) return null;
    return (
        <section className="mt-8">
            <h3 className="text-lg font-extrabold">{title}</h3>
            <div className="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                {items.map((item) => (
                    <article
                        key={item.id}
                        className="min-w-0 overflow-hidden rounded-3xl border border-border bg-surface-elevated"
                    >
                        {item.image_url && (
                            <JakawiImage
                                src={item.image_url}
                                alt={item.title}
                                className="aspect-[16/10] w-full object-cover"
                            />
                        )}
                        <div className="space-y-3 p-5">
                            <h4 className="text-xl leading-tight font-extrabold break-words">
                                {item.title}
                            </h4>
                            {(item.short_description || item.description) && (
                                <p className="line-clamp-3 text-sm text-muted-foreground">
                                    {item.short_description || item.description}
                                </p>
                            )}
                            {journey === 'beneficios' && (
                                <p className="text-sm font-semibold text-success">
                                    {item.availability_label}
                                    {Number(item.estimated_savings) > 0 &&
                                        ` · Ahorro estimado Bs ${item.estimated_savings}`}
                                </p>
                            )}
                            {item.sessions?.[0] && (
                                <p className="text-sm font-semibold">
                                    {date(item.sessions[0].starts_at)}
                                </p>
                            )}
                            {item.regular_price != null && (
                                <p className="text-sm">
                                    Precio regular: {item.currency}{' '}
                                    {item.regular_price}
                                </p>
                            )}
                            {item.member_price != null && (
                                <p className="text-sm font-bold text-success">
                                    Miembros: {item.currency}{' '}
                                    {item.member_price}
                                </p>
                            )}
                            {item.reward_type === 'JP' &&
                                Number(item.reward_jp_amount) > 0 && (
                                    <p className="text-ink inline-block rounded-lg bg-reward px-3 py-2 text-sm font-bold">
                                        Recompensa: {item.reward_jp_amount} JP
                                    </p>
                                )}
                            {item.manual_prize_description && (
                                <p className="text-sm">
                                    {item.manual_prize_description}
                                </p>
                            )}
                            {journey === 'retos' && item.ends_at && (
                                <p className="text-sm">
                                    Hasta el {date(item.ends_at)}
                                </p>
                            )}
                            <Link
                                href={`/${journey}/${item.slug}`}
                                className="inline-flex min-h-11 items-center rounded-xl bg-brand px-4 text-sm font-extrabold text-brand-foreground"
                            >
                                {cta}
                            </Link>
                        </div>
                    </article>
                ))}
            </div>
        </section>
    );
}

export default function PartnerShow({
    partner,
    canonicalUrl,
    locations = [],
    benefits = [],
    experiences = [],
    challenges = [],
}: {
    partner: Partner;
    canonicalUrl: string;
    locations: Location[];
    benefits: Card[];
    experiences: Card[];
    challenges: Card[];
}) {
    const { auth } = usePage().props;
    const hasInventory =
        benefits.length + experiences.length + challenges.length > 0;
    return (
        <>
            <Head title={`${partner.name} en JAKAWI`}>
                <link
                    head-key="canonical"
                    rel="canonical"
                    href={canonicalUrl}
                />
            </Head>
            <main className="mx-auto max-w-6xl px-4 pt-6 pb-28 text-foreground sm:px-8">
                <section className="overflow-hidden rounded-[32px] border border-border bg-surface">
                    {partner.cover_url && (
                        <JakawiImage
                            src={partner.cover_url}
                            alt={partner.name}
                            className="max-h-80 w-full object-cover"
                            loading="eager"
                            priority
                        />
                    )}
                    <div className="p-6 sm:p-10">
                        {partner.logo_url && (
                            <JakawiImage
                                src={partner.logo_url}
                                alt=""
                                className="mb-5 size-20 rounded-2xl object-contain"
                            />
                        )}
                        <p className="text-xs font-extrabold tracking-widest text-brand">
                            DENTRO DE JAKAWI
                        </p>
                        <h1 className="mt-3 text-4xl leading-tight font-extrabold break-words sm:text-6xl">
                            {partner.name}
                        </h1>
                        {partner.category && (
                            <p className="mt-3 text-sm font-semibold">
                                {categoryLabels[partner.category] ||
                                    partner.category}
                            </p>
                        )}
                        {partner.description && (
                            <p className="mt-4 max-w-2xl leading-7 text-muted-foreground">
                                {partner.description}
                            </p>
                        )}
                        {locations[0] && (
                            <a
                                href="#ubicaciones"
                                className="mt-5 inline-flex min-h-11 items-center gap-2 text-sm font-bold"
                            >
                                <MapPin className="size-4 shrink-0 text-brand" />
                                {[
                                    locations[0].city_name || locations[0].city,
                                    locations[0].zone || locations[0].name,
                                ]
                                    .filter(Boolean)
                                    .join(' · ')}
                            </a>
                        )}
                    </div>
                </section>
                {hasInventory ? (
                    <section className="mt-10">
                        <h2 className="text-2xl font-extrabold">
                            LO QUE PUEDES VIVIR AQUÍ
                        </h2>
                        <Inventory
                            title="BENEFICIOS"
                            items={benefits}
                            journey="beneficios"
                            cta="VER BENEFICIO"
                        />
                        <Inventory
                            title="EXPERIENCIAS"
                            items={experiences}
                            journey="experiencias"
                            cta="VER EXPERIENCIA"
                        />
                        <Inventory
                            title="RETOS"
                            items={challenges}
                            journey="retos"
                            cta="VER RETO"
                        />
                    </section>
                ) : (
                    <p className="mt-10 rounded-3xl border border-border bg-surface p-6 font-bold">
                        PRÓXIMAMENTE HABRÁ MÁS PARA VIVIR AQUÍ.
                    </p>
                )}
                {locations.length > 0 && (
                    <section id="ubicaciones" className="mt-10">
                        <h2 className="text-2xl font-extrabold">
                            DÓNDE ENCONTRARLOS
                        </h2>
                        <div className="mt-4 grid gap-4 sm:grid-cols-2">
                            {locations.map((location) => {
                                const hours = Object.entries(
                                    location.opening_hours || {},
                                ).flatMap(([day, ranges]) => {
                                    const values = (
                                        Array.isArray(ranges)
                                            ? ranges
                                            : [ranges]
                                    ).filter(
                                        (value) =>
                                            typeof value === 'string' && value,
                                    );
                                    return values.length
                                        ? [`${day}: ${values.join(', ')}`]
                                        : [];
                                });
                                return (
                                    <div
                                        key={location.id}
                                        className="rounded-3xl border border-border bg-surface p-6"
                                    >
                                        <h3 className="font-extrabold">
                                            {location.name}
                                        </h3>
                                        <p className="mt-2 text-sm">
                                            {[
                                                location.city_name ||
                                                    location.city,
                                                location.zone,
                                                location.address,
                                            ]
                                                .filter(Boolean)
                                                .join(' · ')}
                                        </p>
                                        {location.address_reference && (
                                            <p className="mt-2 text-sm text-muted-foreground">
                                                {location.address_reference}
                                            </p>
                                        )}
                                        {hours.length > 0 && (
                                            <p className="mt-2 text-sm">
                                                {hours.join(' · ')}
                                            </p>
                                        )}
                                        {location.maps_url && (
                                            <a
                                                href={`/lugares/${location.slug}/mapa`}
                                                className="mt-4 inline-flex min-h-11 items-center gap-2 font-bold text-brand"
                                            >
                                                <MapPin className="size-4" />
                                                CÓMO LLEGAR
                                            </a>
                                        )}
                                    </div>
                                );
                            })}
                        </div>
                    </section>
                )}
                {!auth.user && (
                    <section
                        data-partner-account
                        className="mt-10 rounded-3xl border border-border bg-surface p-6 sm:p-8"
                    >
                        <h2 className="text-xl font-extrabold break-words">
                            LLEVA {partner.name} CONTIGO EN JAKAWI
                        </h2>
                        <Link
                            href={`/p/${partner.slug}/auth/register`}
                            className="mt-5 inline-flex min-h-12 items-center rounded-xl bg-brand px-5 font-extrabold text-brand-foreground"
                        >
                            CREAR MI CUENTA
                        </Link>
                        <p className="mt-5 text-sm">
                            ¿Ya tienes cuenta?{' '}
                            <Link
                                href={`/p/${partner.slug}/auth/login`}
                                className="inline-flex min-h-11 items-center font-extrabold text-brand underline"
                            >
                                INICIA SESIÓN
                            </Link>
                        </p>
                    </section>
                )}
            </main>
        </>
    );
}
