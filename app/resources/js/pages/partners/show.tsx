import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowDown, ArrowUpRight, MapPin } from 'lucide-react';
import { PartnerSocialLinks } from '@/components/partner-social-links';
import { JakawiImage } from '@/components/jakawi-image';

type Source = { src: string; width: number };
type Card = {
    id: number;
    slug: string;
    title: string;
    image_url?: string;
    image_srcset?: Source[];
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
    instagram?: string | null;
    tiktok?: string | null;
    facebook?: string | null;
    website?: string | null;
    whatsapp?: string | null;
    logo_srcset?: Source[];
    cover_srcset?: Source[];
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
    phone?: string;
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
    items,
    journey,
    label,
    cta,
}: {
    items: Card[];
    journey: string;
    label: string;
    cta: string;
}) {
    return items.map((item) => (
        <article
            key={`${journey}-${item.id}`}
            data-partner-offer={journey}
            className="partner-offer"
        >
            <div className="partner-offer-media">
                <JakawiImage
                    src={item.image_url}
                    srcset={item.image_srcset}
                    sizes="(min-width: 1024px) 390px, (min-width: 640px) 45vw, 90vw"
                    alt={item.title}
                    className="h-full w-full object-cover"
                />
                <span className="partner-offer-label">{label}</span>
            </div>
            <div className="flex flex-1 flex-col p-6">
                <h3 className="text-2xl leading-tight font-extrabold break-words">
                    {item.title}
                </h3>
                {(item.short_description || item.description) && (
                    <p className="mt-3 line-clamp-3 text-sm leading-relaxed text-muted-foreground">
                        {item.short_description || item.description}
                    </p>
                )}
                <div className="mt-5 mb-6 space-y-2">
                    {journey === 'beneficios' && (
                        <>
                            {Number(item.estimated_savings) > 0 && (
                                <p className="partner-opportunity">
                                    Ahorro estimado Bs {item.estimated_savings}
                                </p>
                            )}
                            {item.availability_label && (
                                <p className="text-sm text-muted-foreground">
                                    {item.availability_label}
                                </p>
                            )}
                        </>
                    )}
                    {item.sessions?.[0] && (
                        <p className="partner-opportunity">
                            {date(item.sessions[0].starts_at)}
                        </p>
                    )}
                    {item.regular_price != null && (
                        <p className="text-sm text-muted-foreground">
                            Precio regular: {item.currency} {item.regular_price}
                        </p>
                    )}
                    {item.member_price != null && (
                        <p className="text-xl font-extrabold">
                            Miembros: {item.currency} {item.member_price}
                        </p>
                    )}
                    {item.reward_type === 'JP' &&
                        Number(item.reward_jp_amount) > 0 && (
                            <p className="partner-opportunity">
                                Recompensa: {item.reward_jp_amount} JP
                            </p>
                        )}
                    {item.manual_prize_description && (
                        <p className="text-sm font-semibold">
                            {item.manual_prize_description}
                        </p>
                    )}
                    {journey === 'retos' && item.ends_at && (
                        <p className="text-sm font-bold">
                            Hasta el {date(item.ends_at)}
                        </p>
                    )}
                </div>
                <Link
                    href={`/${journey}/${item.slug}`}
                    className="partner-primary mt-auto"
                >
                    {cta}
                    <ArrowUpRight
                        className="size-5 shrink-0"
                        aria-hidden="true"
                    />
                </Link>
            </div>
        </article>
    ));
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
    const locality =
        locations[0] &&
        [locations[0].city_name || locations[0].city, locations[0].zone]
            .filter(Boolean)
            .join(' · ');
    // Presentation only: promote the first stored sentence, keep the remaining copy intact.
    const description = partner.description?.trim() || '';
    const sentence = description.match(/^(.+?[.!?])(?:\s+|$)([\s\S]*)$/);
    const headline = sentence?.[1] || description;
    const supporting = sentence?.[2];
    const initials = partner.name
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((word) => word[0])
        .join('')
        .toUpperCase();
    return (
        <>
            <Head title={`${partner.name} en JAKAWI`}>
                <link
                    head-key="canonical"
                    rel="canonical"
                    href={canonicalUrl}
                />
            </Head>
            <main className="partner-profile mx-auto max-w-[1280px] px-5 pt-5 pb-28 text-foreground sm:px-8 lg:pt-8">
                <section
                    className="partner-hero"
                    aria-labelledby="partner-name"
                >
                    <div data-partner-cover className="partner-cover">
                        {partner.cover_url ? (
                            <JakawiImage
                                src={partner.cover_url}
                                srcset={partner.cover_srcset}
                                sizes="(min-width: 1280px) 560px, (min-width: 1024px) 46vw, 100vw"
                                alt={`Portada de ${partner.name}`}
                                className="h-full w-full object-cover"
                                loading="eager"
                                priority
                            />
                        ) : (
                            <div
                                data-partner-cover-fallback
                                className="partner-cover-fallback"
                                aria-hidden="true"
                            >
                                <span className="partner-cover-ring" />
                                <span className="partner-cover-dot" />
                                <span className="partner-cover-word">
                                    {categoryLabels[partner.category || ''] ||
                                        partner.name}
                                </span>
                                <span className="partner-cover-signature">
                                    JAKAWI / LOCAL
                                </span>
                            </div>
                        )}
                    </div>
                    <div className="partner-hero-copy">
                        <div className="flex items-center gap-4">
                            <div data-partner-logo className="partner-avatar">
                                {partner.logo_url ? (
                                    <JakawiImage
                                        src={partner.logo_url}
                                        srcset={partner.logo_srcset}
                                        sizes="112px"
                                        alt={`Logo de ${partner.name}`}
                                        className="h-full w-full object-contain p-3"
                                        loading="eager"
                                    />
                                ) : (
                                    <span
                                        data-partner-logo-fallback
                                        className="text-3xl font-extrabold"
                                    >
                                        {initials}
                                    </span>
                                )}
                            </div>
                            <p className="min-w-0 text-xs leading-relaxed font-extrabold tracking-widest uppercase">
                                {(partner.category || locality) && (
                                    <span
                                        aria-hidden="true"
                                        className="mr-2 inline-block size-1.5 rounded-full bg-success align-middle"
                                    />
                                )}
                                {[
                                    categoryLabels[partner.category || ''] ||
                                        partner.category,
                                    locality,
                                ]
                                    .filter(Boolean)
                                    .join(' · ')}
                            </p>
                        </div>
                        <h1
                            id="partner-name"
                            className={`partner-name ${headline ? '' : 'partner-name-solo'}`}
                        >
                            {partner.name}
                        </h1>
                        {headline && (
                            <h2 className="partner-headline">{headline}</h2>
                        )}
                        {supporting && (
                            <p className="mt-5 max-w-lg leading-relaxed text-muted-foreground">
                                {supporting}
                            </p>
                        )}
                        <PartnerSocialLinks partner={partner} />
                        <div className="mt-7 flex flex-wrap items-center gap-x-6 gap-y-3">
                            <a
                                href={
                                    hasInventory ? '#propuestas' : '#el-lugar'
                                }
                                className="partner-primary"
                            >
                                {hasInventory
                                    ? 'VER LO QUE HAY EN JAKAWI'
                                    : 'CONOCER EL LUGAR'}
                                <ArrowDown
                                    className="size-5 shrink-0"
                                    aria-hidden="true"
                                />
                            </a>
                            {locations.length > 0 && (
                                <a
                                    href="#ubicaciones"
                                    className="inline-flex min-h-12 items-center gap-2 text-sm font-extrabold"
                                >
                                    <MapPin
                                        className="size-4 text-success"
                                        aria-hidden="true"
                                    />
                                    CÓMO LLEGAR
                                </a>
                            )}
                        </div>
                    </div>
                </section>
                {hasInventory ? (
                    <section
                        id="propuestas"
                        className="partner-section scroll-mt-8"
                    >
                        <p className="partner-kicker">
                            {partner.name} / JAKAWI
                        </p>
                        <h2 className="partner-section-title">
                            LO QUE PUEDES
                            <br />
                            VIVIR AQUÍ<span className="text-brand">.</span>
                        </h2>
                        <div className="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                            <Inventory
                                items={benefits}
                                journey="beneficios"
                                label="BENEFICIO"
                                cta="VER BENEFICIO"
                            />
                            <Inventory
                                items={experiences}
                                journey="experiencias"
                                label="EXPERIENCIA"
                                cta="VER EXPERIENCIA"
                            />
                            <Inventory
                                items={challenges}
                                journey="retos"
                                label="RETO"
                                cta="VER RETO"
                            />
                        </div>
                    </section>
                ) : (
                    <section
                        id="el-lugar"
                        data-partner-empty
                        className="mt-10 flex scroll-mt-8 gap-4 border-l-4 border-brand pl-5"
                    >
                        <div>
                            <h2 className="text-lg font-extrabold">
                                {partner.name} ya está en JAKAWI.
                            </h2>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Estamos preparando sus primeras propuestas.
                            </p>
                        </div>
                    </section>
                )}
                {locations.length > 0 && (
                    <section
                        id="ubicaciones"
                        className="partner-section scroll-mt-8"
                    >
                        <div className="lg:grid lg:grid-cols-[1fr_1.6fr] lg:gap-16">
                            <div>
                                <p className="partner-kicker">NOS VEMOS AQUÍ</p>
                                <h2 className="partner-section-title">
                                    HAZLO TU
                                    <br />
                                    PRÓXIMA PARADA
                                    <span className="text-brand">.</span>
                                </h2>
                            </div>
                            <div className="mt-8 divide-y divide-border lg:mt-0">
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
                                                typeof value === 'string' &&
                                                value,
                                        );
                                        return values.length
                                            ? [`${day}: ${values.join(', ')}`]
                                            : [];
                                    });
                                    return (
                                        <div
                                            key={location.id}
                                            className="py-6 first:pt-0"
                                        >
                                            <div className="flex items-start gap-3">
                                                <MapPin
                                                    className="mt-1 size-6 shrink-0 text-brand"
                                                    aria-hidden="true"
                                                />
                                                <h3 className="text-2xl font-extrabold">
                                                    {location.name}
                                                </h3>
                                            </div>
                                            <p className="mt-3 leading-relaxed">
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
                                                <p className="mt-3 text-sm">
                                                    {hours.join(' · ')}
                                                </p>
                                            )}
                                            {location.phone && (
                                                <p className="mt-2 text-sm">
                                                    {location.phone}
                                                </p>
                                            )}
                                            {location.maps_url && (
                                                <a
                                                    href={`/lugares/${location.slug}/mapa`}
                                                    className="mt-4 inline-flex min-h-12 items-center gap-3 border-b-2 border-brand text-sm font-extrabold"
                                                >
                                                    CÓMO LLEGAR
                                                    <ArrowUpRight
                                                        className="size-5"
                                                        aria-hidden="true"
                                                    />
                                                </a>
                                            )}
                                        </div>
                                    );
                                })}
                            </div>
                        </div>
                    </section>
                )}
                {!auth.user && (
                    <section
                        data-partner-account
                        className="partner-account partner-section"
                    >
                        <p className="partner-kicker">SIGUE DESCUBRIENDO</p>
                        <h2 className="max-w-2xl text-3xl leading-tight font-extrabold break-words sm:text-4xl">
                            DESCUBRE MÁS DE {partner.name} EN JAKAWI.
                        </h2>
                        <p className="mt-4 max-w-xl leading-relaxed">
                            Crea tu cuenta para explorar beneficios,
                            experiencias y retos en JAKAWI.
                        </p>
                        <Link
                            href={`/p/${partner.slug}/auth/register`}
                            className="partner-primary mt-6"
                        >
                            CREAR MI CUENTA
                            <ArrowUpRight
                                className="size-5"
                                aria-hidden="true"
                            />
                        </Link>
                        <p className="mt-4 text-sm">
                            ¿Ya tienes cuenta?{' '}
                            <Link
                                href={`/p/${partner.slug}/auth/login`}
                                className="inline-flex min-h-11 items-center font-extrabold underline underline-offset-4"
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
