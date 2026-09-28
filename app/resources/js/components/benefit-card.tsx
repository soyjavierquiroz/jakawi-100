import { Link } from '@inertiajs/react';
import { JakawiImage } from '@/components/jakawi-image';
import { LockKeyhole, MapPin } from 'lucide-react';
import type { BenefitSummary } from '@/types';

export default function BenefitCard({ benefit, variant = 'default' }: { benefit: BenefitSummary; variant?: 'default' | 'discovery' | 'list' }) {
    const list = variant === 'list';
    return <Link href={`/beneficios/${benefit.slug}`} className={`group overflow-hidden border border-border bg-surface-elevated transition duration-200 active:scale-[.98] ${list ? 'grid min-h-36 grid-cols-[38%_1fr] rounded-[var(--radius-card)]' : `block w-[78vw] max-w-80 shrink-0 ${variant === 'discovery' ? 'rounded-[var(--radius-card)]' : 'rounded-[22px]'} sm:w-full`}`}>
        <div className={`${list ? 'min-h-full' : 'aspect-[4/3]'} bg-surface-muted`}><JakawiImage src={benefit.image_url} srcset={benefit.image_srcset} sizes={list ? '180px' : '(min-width: 768px) 33vw, 84vw'} alt={benefit.title} className="h-full w-full object-cover" /></div>
        <div className={`${list ? 'flex min-w-0 flex-col justify-center p-[var(--space-card)]' : 'space-y-3 p-4'}`}><div><p className="discovery-eyebrow text-brand">Beneficio JAKAWI</p><h3 className="mt-1 text-xl leading-tight font-extrabold">{benefit.title}</h3></div>{benefit.partner?.name ? <p className="mt-1 text-sm font-semibold text-foreground-soft">{benefit.partner.name}</p> : null}{benefit.short_description ? <p className={`${list ? 'mt-1 line-clamp-2' : 'mt-3 line-clamp-2'} text-sm text-muted-foreground`}>{benefit.short_description}</p> : null}<div className={`${list ? 'mt-3' : 'mt-3'} flex items-center gap-2 text-xs font-semibold text-foreground-soft`}><LockKeyhole className="size-3.5 text-brand" aria-hidden="true" /> Beneficio JAKAWI {benefit.partner?.name ? <><span aria-hidden="true">·</span><MapPin className="size-3.5" aria-hidden="true" /></> : null}</div></div>
    </Link>;
}
