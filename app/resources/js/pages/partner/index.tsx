import { Head, Link } from '@inertiajs/react';

type Partner = {
    id: number;
    name: string;
    slug: string;
    role: 'owner' | 'manager' | 'staff';
};

export default function PartnerIndex({ partners }: { partners: Partner[] }) {
    return (
        <main className="min-h-screen bg-background px-4 py-6 text-foreground sm:px-6">
            <Head title="Portal Partner" />
            <section className="mx-auto w-full max-w-2xl">
                <p className="text-sm font-semibold tracking-wide text-muted-foreground uppercase">
                    JAKAWI Partner
                </p>
                <h1 className="mt-2 text-3xl font-semibold">Portal Partner</h1>
                <div className="mt-6 rounded-md border border-border bg-surface p-5">
                    <h2 className="text-lg font-semibold">Partners gestionados</h2>
                    <ul className="mt-3 divide-y divide-border">
                        {partners.map((partner) => (
                            <li key={partner.id} className="flex items-center justify-between py-3">
                                <Link href={`/partner/${partner.slug}`} className="font-medium">{partner.name}</Link>
                                <span className="text-sm text-muted-foreground">{partner.role}</span>
                            </li>
                        ))}
                    </ul>
                </div>
                <Link href="/" className="mt-4 inline-block text-sm underline">Ver JAKAWI</Link>
            </section>
        </main>
    );
}
