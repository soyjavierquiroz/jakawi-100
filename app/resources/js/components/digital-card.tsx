import { Link } from '@inertiajs/react';
import { FileText } from 'lucide-react';
export type DigitalProductData = {
    id: number;
    name: string;
    slug: string;
    short_description: string | null;
    description: string | null;
    commercial_content: string | null;
    public_price: string | null;
    member_price: string | null;
    currency: string;
    cover_url?: string | null;
    partner: { id: number; name: string };
    support_information: string | null;
    access_terms: string | null;
    url: string;
};
export const digitalMoney = (amount: string | null, currency: string) =>
    amount === null
        ? 'Precio por configurar'
        : new Intl.NumberFormat('es-BO', {
              style: 'currency',
              currency,
          }).format(Number(amount));
export function DigitalCard({ product: p }: { product: DigitalProductData }) {
    return (
        <Link
            href={p.url}
            className="block overflow-hidden rounded-2xl border border-border bg-surface shadow-sm transition hover:border-brand focus-visible:outline-2 focus-visible:outline-brand"
        >
            <div className="flex aspect-[16/9] items-center justify-center bg-surface-muted">
                {p.cover_url ? (
                    <img
                        src={p.cover_url}
                        alt=""
                        loading="lazy"
                        className="h-full w-full object-cover"
                    />
                ) : (
                    <FileText
                        className="size-12 text-brand"
                        aria-hidden="true"
                    />
                )}
            </div>
            <div className="p-5">
                <p className="text-xs font-bold text-brand">
                    Digital · disponible online
                </p>
                <h3 className="mt-2 text-xl font-extrabold">{p.name}</h3>
                <p className="mt-1 text-sm text-muted-foreground">
                    {p.partner.name}
                </p>
                <p className="mt-3 text-sm">{p.short_description}</p>
                <p className="mt-4 font-bold">
                    {digitalMoney(p.public_price, p.currency)}
                </p>
                {p.member_price ? (
                    <p className="mt-1 text-sm text-muted-foreground">
                        Con membresía activa:{' '}
                        {digitalMoney(p.member_price, p.currency)}
                    </p>
                ) : null}
                <span className="mt-4 inline-flex min-h-11 items-center font-semibold text-brand underline">
                    Ver producto
                </span>
            </div>
        </Link>
    );
}
