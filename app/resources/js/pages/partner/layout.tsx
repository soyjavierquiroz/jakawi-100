import { Head } from '@inertiajs/react';
import type { ReactNode } from 'react';

type Partner = { name: string; slug: string };

export default function PartnerHeading({
    title,
    partner,
    children,
}: {
    title: string;
    partner: Partner;
    children: ReactNode;
}) {
    return (
        <>
            <Head title={`${partner.name} · ${title}`} />
            <h1 className="text-2xl font-semibold sm:text-3xl">{title}</h1>
            <div className="mt-5">{children}</div>
        </>
    );
}
