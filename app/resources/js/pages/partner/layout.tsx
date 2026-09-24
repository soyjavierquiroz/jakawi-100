import { Head } from '@inertiajs/react';

type Partner = { name: string; slug: string };

export default function PartnerLayout({ title, partner, children }: { title: string; partner: Partner; children: React.ReactNode }) {
    return <><Head title={`${partner.name} · ${title}`} /><p className="text-sm font-medium text-muted-foreground">{partner.name}</p><h1 className="mt-1 text-3xl font-semibold">{title}</h1><div className="mt-6">{children}</div></>;
}
