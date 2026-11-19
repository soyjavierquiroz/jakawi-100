import {
    CityCommunityCard,
    type CityCommunityData,
} from '@/components/city-community-card';
import { Head, Link } from '@inertiajs/react';

type Partner = {
    id: number;
    name: string;
    slug: string;
    role: 'owner' | 'manager' | 'staff';
};

export default function PartnerIndex({
    partners,
    community,
}: {
    partners: Partner[];
    community?: CityCommunityData | null;
}) {
    return (
        <>
            <Head title="Portal Partner" />
            <section className="w-full max-w-2xl">
                <h1 className="text-3xl font-semibold">Portal Partner</h1>
                <div className="mt-6 rounded-md border border-border bg-surface p-5">
                    <h2 className="text-lg font-semibold">
                        Elige un establecimiento
                    </h2>
                    <ul className="mt-3 divide-y divide-border">
                        {partners.map((partner) => (
                            <li
                                key={partner.id}
                                className="flex flex-wrap items-center justify-between gap-3 py-3"
                            >
                                <Link
                                    href={`/partner/${partner.slug}`}
                                    className="min-h-11 py-3 font-medium underline"
                                >
                                    {partner.name}
                                </Link>
                                <span className="text-sm text-muted-foreground">
                                    {{
                                        owner: 'Propietario',
                                        manager: 'Manager',
                                        staff: 'Personal operativo',
                                    }[partner.role] ?? partner.role}
                                </span>
                            </li>
                        ))}
                    </ul>
                </div>
                <CityCommunityCard community={community} permanent />
            </section>
        </>
    );
}
