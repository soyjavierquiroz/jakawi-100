import { Link } from '@inertiajs/react';
import { JakawiImage } from '@/components/jakawi-image';
import { LockKeyhole, MapPin } from 'lucide-react';
import type { BenefitSummary } from '@/types';

export default function BenefitCard({ benefit }: { benefit: BenefitSummary }) {
    return <Link href={`/beneficios/${benefit.slug}`} className="group block w-[78vw] max-w-80 shrink-0 overflow-hidden rounded-[22px] border border-border bg-surface-elevated transition duration-200 active:scale-[.98] sm:w-full">
        <div className="aspect-[4/3] bg-surface-muted"><JakawiImage src={benefit.image_url} srcset={benefit.image_srcset} sizes="(min-width: 768px) 33vw, 84vw" alt={benefit.title} className="h-full w-full object-cover" fallbackClassName="bg-brand" /></div>
        <div className="space-y-3 p-4"><div><p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">{benefit.partner?.name ?? 'JAKAWI'}</p><h3 className="mt-1 text-xl leading-tight font-bold">{benefit.title}</h3></div>{benefit.short_description ? <p className="line-clamp-2 text-sm text-muted-foreground">{benefit.short_description}</p> : null}<div className="flex items-center gap-2 text-xs font-semibold text-foreground-soft"><LockKeyhole className="size-3.5 text-brand" aria-hidden="true" /> Beneficio JAKAWI {benefit.partner?.name ? <><span aria-hidden="true">·</span><MapPin className="size-3.5" aria-hidden="true" /></> : null}</div></div>
    </Link>;
}
