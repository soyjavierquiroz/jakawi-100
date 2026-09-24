import { Head, Link } from '@inertiajs/react';

type Partner = {
    id: number;
    name: string;
    slug: string;
    role: 'owner' | 'manager' | 'staff';
};

export default function PartnerIndex({ partners }: { partners: Partner[] }) {
    return (
        <>
            <Head title="Portal Partner" />
            <section className="w-full max-w-2xl">
                <h1 className="text-3xl font-semibold">Portal Partner</h1>
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
            </section>
        </>
    );
}
