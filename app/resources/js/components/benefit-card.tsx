import { Link } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import type { BenefitSummary } from '@/types';

function formatSavings(value: BenefitSummary['estimated_savings']) {
    if (value === null || value === undefined || value === '') {
        return null;
    }

    const amount = Number(value);

    if (Number.isNaN(amount)) {
        return null;
    }

    return new Intl.NumberFormat('es-MX', {
        style: 'currency',
        currency: 'MXN',
        maximumFractionDigits: 0,
    }).format(amount);
}

export default function BenefitCard({ benefit }: { benefit: BenefitSummary }) {
    const savings = formatSavings(benefit.estimated_savings);

    return (
        <article className="overflow-hidden rounded-md border border-border bg-surface shadow-xs">
            <div className="aspect-[16/10] bg-muted">
                {benefit.image_url ? (
                    <img
                        src={benefit.image_url}
                        alt=""
                        className="h-full w-full object-cover"
                        loading="lazy"
                    />
                ) : (
                    <div className="flex h-full items-center justify-center px-4 text-sm font-medium text-muted-foreground">
                        JAKAWI
                    </div>
                )}
            </div>
            <div className="flex min-h-56 flex-col gap-3 p-4">
                <div className="space-y-1">
                    <p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                        {benefit.merchant.name}
                    </p>
                    <h2 className="text-lg leading-tight font-semibold text-foreground">
                        {benefit.title}
                    </h2>
                </div>

                {benefit.short_description ? (
                    <p className="line-clamp-3 text-sm leading-6 text-muted-foreground">
                        {benefit.short_description}
                    </p>
                ) : null}

                <div className="mt-auto flex flex-col gap-3">
                    {savings ? (
                        <p className="text-sm font-semibold text-success">
                            Ahorro estimado {savings}
                        </p>
                    ) : null}
                    <Link
                        href={`/beneficios/${benefit.slug}`}
                        className="inline-flex min-h-11 items-center justify-center gap-2 rounded-md bg-brand px-4 text-sm font-semibold text-brand-foreground transition hover:bg-brand/90"
                    >
                        Ver beneficio
                        <ArrowRight className="size-4" aria-hidden="true" />
                    </Link>
                </div>
            </div>
        </article>
    );
}
