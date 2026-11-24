import { ExplorerCard } from '@/components/discovery/explorer-card';
import { JakawiImage } from '@/components/jakawi-image';
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
    gallery_urls?: string[];
    category?: string | null;
    coming_soon?: boolean;
};
export const digitalMoney = (amount: string | null, currency: string) =>
    amount === null
        ? 'Precio por configurar'
        : new Intl.NumberFormat('es-BO', {
              style: 'currency',
              currency,
          }).format(Number(amount));
export function DigitalCard({ product: p, giftContext = false }: { product: DigitalProductData; giftContext?: boolean }) {
    return (
        <ExplorerCard
            href={p.url}
            image={
                p.cover_url ? (
                    <JakawiImage
                        src={p.cover_url}
                        alt={p.name}
                        sizes="(min-width: 1024px) 29vw, (min-width: 640px) 44vw, 42vw"
                        className="h-full w-full object-cover transition duration-500 group-hover:scale-[1.03]"
                    />
                ) : (
                    <div className="flex h-full min-h-40 items-center justify-center">
                        <FileText
                            className="size-10 text-brand"
                            aria-hidden="true"
                        />
                    </div>
                )
            }
        >
            <div className="explorer-card-copy min-w-0 p-4 sm:p-[var(--space-card)]">
                <p className="explorer-card-type discovery-eyebrow text-muted-foreground">
                    {giftContext ? 'INCLUIDO CON TU MEMBRESÍA' : 'Digital · disponible online'}
                </p>
                <h3 className="mt-2 text-lg leading-tight font-extrabold tracking-tight">
                    {p.name}
                </h3>
                {p.short_description ? (
                    <p className="mt-2 line-clamp-3 text-sm leading-relaxed font-medium text-muted-foreground">
                        {p.short_description}
                    </p>
                ) : null}
                <p className="mt-2 text-sm font-medium text-muted-foreground">
                    {p.partner.name}
                </p>
                <p className="mt-3 text-base font-extrabold">
                    {giftContext ? 'Sin costo adicional' : p.coming_soon ? 'Disponible próximamente' : digitalMoney(p.public_price, p.currency)}
                </p>
                {!giftContext && p.member_price ? (
                    <p className="mt-1 text-xs font-semibold text-muted-foreground">
                        Con membresía activa:{' '}
                        {digitalMoney(p.member_price, p.currency)}
                    </p>
                ) : null}
                <span className="mt-3 block text-xs font-extrabold tracking-[0.08em] text-brand">
                    {giftContext ? 'VER REGALO' : 'VER PRODUCTO ↗'}
                </span>
            </div>
        </ExplorerCard>
    );
}
