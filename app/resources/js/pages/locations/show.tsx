import { Head, Link } from '@inertiajs/react';
export default function LocationShow({ location, benefits = [] }: any) {
    return (
        <main className="min-h-screen bg-background p-5 text-foreground">
            <Head title={location.name} />
            <Link href="/">JAKAWI</Link>
            <h1 className="mt-6 text-4xl font-semibold">{location.name}</h1>
            {location.partner ? (
                <Link href={`/partners/${location.partner.slug}`}>
                    {location.partner.name}
                </Link>
            ) : null}
            <p className="mt-3 text-muted-foreground">
                {[location.address, location.zone, location.city]
                    .filter(Boolean)
                    .join(', ')}
            </p>
            {location.maps_url ? (
                <a
                    className="mt-4 inline-block text-brand"
                    href={`/lugares/${location.slug}/mapa`}
                >
                    Cómo llegar
                </a>
            ) : null}
            {location.whatsapp ? (
                <a
                    className="ml-4 text-brand"
                    href={`/lugares/${location.slug}/whatsapp`}
                >
                    WhatsApp
                </a>
            ) : null}
            <section className="mt-8">
                <h2 className="text-xl font-semibold">
                    Beneficios disponibles
                </h2>
                {benefits.map((x: any) => (
                    <Link
                        className="block py-2"
                        key={x.id}
                        href={`/beneficios/${x.slug}`}
                    >
                        {x.title}
                    </Link>
                ))}
            </section>
        </main>
    );
}
