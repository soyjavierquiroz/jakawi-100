import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, MapPin, MessageCircle } from 'lucide-react';
import BenefitCard from '@/components/benefit-card';
import ExperienceCard from '@/components/experience-card';
import { JakawiImage } from '@/components/jakawi-image';

function openingHoursLabel(openingHours: unknown): string | null {
    if (typeof openingHours === 'string') return openingHours;
    if (
        !openingHours ||
        Array.isArray(openingHours) ||
        typeof openingHours !== 'object'
    )
        return null;
    const ranges = Object.values(openingHours)
        .flatMap((value) => (Array.isArray(value) ? value : [value]))
        .filter(
            (value): value is string =>
                typeof value === 'string' && value !== '',
        );
    return ranges.length ? [...new Set(ranges)].join(' · ') : null;
}

export default function PartnerShow({
    partner,
    locations = [],
    benefits = [],
    experiences = [],
}: any) {
    const location = locations[0];
    const hasOffers = benefits.length > 0 || experiences.length > 0;

    return (
        <>
            <Head title={partner.name} />
            <main className="min-h-screen bg-background pb-28 text-foreground">
                <section className="mx-auto max-w-5xl">
                    <div className="relative aspect-[16/10] bg-surface-muted sm:rounded-b-[32px]">
                        <JakawiImage
                            src={partner.cover_url}
                            srcset={partner.cover_srcset}
                            sizes="(min-width: 1024px) 960px, 100vw"
                            alt={partner.name}
                            className="h-full w-full object-cover sm:rounded-b-[32px]"
                            loading="eager"
                            priority
                        />
                        <Link
                            href="/explorar"
                            aria-label="Volver a explorar"
                            className="absolute top-4 left-4 inline-flex min-h-11 items-center gap-2 rounded-full bg-background/90 px-4 text-sm font-bold"
                        >
                            <ArrowLeft className="size-4" />
                            Explorar
                        </Link>
                    </div>
                    <div className="px-4 pt-6 sm:px-8 sm:pt-10">
                        <p className="text-xs font-extrabold tracking-[0.12em] text-brand uppercase">
                            Partner JAKAWI
                        </p>
                        <h1 className="mt-2 text-4xl leading-none font-extrabold tracking-tight sm:text-6xl">
                            {partner.name}
                        </h1>
                        <p className="mt-4 flex items-center gap-2 text-sm text-muted-foreground">
                            {partner.category ? (
                                <span className="font-semibold text-foreground-soft">
                                    {partner.category}
                                </span>
                            ) : null}
                            {partner.category && location ? (
                                <span aria-hidden="true">·</span>
                            ) : null}
                            {location ? (
                                <>
                                    <MapPin className="size-4 text-brand" />
                                    {location.zone ??
                                        location.address ??
                                        location.name}
                                </>
                            ) : null}
                        </p>
                        {hasOffers ? (
                            <section id="ofertas" className="mt-10">
                                <h2 className="text-2xl font-extrabold">
                                    ¿QUÉ PUEDO APROVECHAR AQUÍ?
                                </h2>
                                {benefits.length ? (
                                    <div className="mt-5">
                                        <h3 className="text-lg font-extrabold">
                                            BENEFICIOS JAKAWI
                                        </h3>
                                        <div className="mt-4 flex gap-4 overflow-x-auto pb-2 sm:grid sm:grid-cols-3">
                                            {benefits.map((benefit: any) => (
                                                <BenefitCard
                                                    key={benefit.id}
                                                    benefit={benefit}
                                                />
                                            ))}
                                        </div>
                                    </div>
                                ) : null}
                                {experiences.length ? (
                                    <div className={benefits.length ? 'mt-10' : 'mt-5'}>
                                        <h3 className="text-lg font-extrabold">
                                            EXPERIENCIAS
                                        </h3>
                                        <div className="mt-4 flex gap-4 overflow-x-auto pb-2 sm:grid sm:grid-cols-3">
                                            {experiences.map((experience: any) => (
                                                <ExperienceCard
                                                    key={experience.id}
                                                    experience={experience}
                                                />
                                            ))}
                                        </div>
                                    </div>
                                ) : null}
                            </section>
                        ) : null}
                        {partner.description ? (
                            <section className="mt-10 border-t border-border pt-7">
                                <h2 className="text-xl font-extrabold">
                                    SOBRE {partner.name}
                                </h2>
                                <p className="mt-3 max-w-2xl leading-7 text-muted-foreground">
                                    {partner.description}
                                </p>
                            </section>
                        ) : null}
                        {locations.length ? (
                            <section className="mt-10 border-t border-border pt-7">
                                <h2 className="text-xl font-extrabold">
                                    HORARIOS Y UBICACIONES
                                </h2>
                                <div className="mt-3 divide-y divide-border">
                                    {locations.map((item: any) => {
                                        const openingHours = openingHoursLabel(
                                            item.opening_hours,
                                        );
                                        return (
                                            <div key={item.id} className="py-4">
                                                <p className="font-bold">
                                                    {item.name}
                                                </p>
                                                {item.address || item.zone ? (
                                                    <p className="mt-1 text-sm text-muted-foreground">
                                                        {item.address ??
                                                            item.zone}
                                                    </p>
                                                ) : null}
                                                {openingHours ? (
                                                    <p className="mt-2 text-sm text-muted-foreground">
                                                        {openingHours}
                                                    </p>
                                                ) : null}
                                                {item.maps_url ? (
                                                    <a
                                                        href={`/lugares/${item.slug}/mapa`}
                                                        className="mt-4 inline-flex min-h-11 items-center gap-2 rounded-xl border border-border bg-surface px-4 text-sm font-extrabold"
                                                    >
                                                        <MapPin className="size-4" />
                                                        CÓMO LLEGAR
                                                    </a>
                                                ) : null}
                                            </div>
                                        );
                                    })}
                                </div>
                            </section>
                        ) : null}
                        {partner.whatsapp ? (
                            <a
                                href={`/partners/${partner.slug}/whatsapp`}
                                className="mt-8 inline-flex min-h-11 items-center gap-2 rounded-xl border border-border bg-surface px-4 text-sm font-extrabold"
                            >
                                <MessageCircle className="size-4" />
                                WHATSAPP
                            </a>
                        ) : null}
                    </div>
                </section>
            </main>
        </>
    );
}
