import { Head, Link } from '@inertiajs/react';
import { Check, ArrowLeft } from 'lucide-react';
import { JakawiImage } from '@/components/jakawi-image';
import { login, register } from '@/routes';

type Paywall = {
    triggerBenefit: { id: number; slug: string; title: string; partner: { name: string; slug: string } | null; estimated_savings: string | null; image_url: string | null; image_srcset: string | null };
    membership: { price_bob: number; duration_days: number; benefits_copy: string[] };
    state: 'guest' | 'free' | 'expired';
    can_create_account: boolean;
};

function money(value: number | string) {
    return new Intl.NumberFormat('es-BO', { style: 'currency', currency: 'BOB', maximumFractionDigits: 0 }).format(Number(value));
}

export default function MembershipPaywall({ paywall }: { paywall: Paywall }) {
    const benefit = paywall.triggerBenefit;
    const price = money(paywall.membership.price_bob);
    const savings = benefit.estimated_savings === null ? null : money(benefit.estimated_savings);
    const authAction = paywall.can_create_account ? register() : null;

    return <><Head title="Desbloquea JAKAWI" /><main className="min-h-screen bg-background text-foreground"><section className="mx-auto max-w-5xl px-4 py-5 sm:px-8 sm:py-10"><Link href={`/beneficios/${benefit.slug}`} className="inline-flex min-h-11 items-center gap-2 text-sm font-bold text-muted-foreground hover:text-foreground"><ArrowLeft className="size-4" />Volver al beneficio</Link><div className="mt-6 grid overflow-hidden rounded-[28px] border border-border bg-surface shadow-sm lg:grid-cols-[1.05fr_.95fr]"><div className="min-h-72 bg-surface-muted lg:min-h-full"><JakawiImage src={benefit.image_url} srcset={benefit.image_srcset} sizes="(min-width: 1024px) 560px, 100vw" alt={benefit.title} className="h-full w-full object-cover" loading="eager" priority /></div><div className="p-6 sm:p-10"><p className="text-xs font-extrabold tracking-[.14em] text-brand uppercase">Esto es para miembros JAKAWI</p><h1 className="mt-3 text-4xl leading-[.96] font-extrabold tracking-tight sm:text-5xl">Con tu membresía desbloqueas este beneficio y muchos más.</h1><div className="mt-8 border-y border-border py-5"><p className="text-xs font-extrabold tracking-[.12em] text-muted-foreground uppercase">{benefit.partner?.name ?? 'Partner JAKAWI'}</p><p className="mt-2 text-xl font-extrabold">{benefit.title}</p>{savings ? <p className="mt-2 text-sm font-semibold text-foreground">Ahorras aproximadamente {savings}</p> : null}</div><div className="mt-8"><p className="text-xs font-extrabold tracking-[.12em] text-brand uppercase">JAKAWI</p><p className="mt-2 text-3xl font-extrabold">{price} <span className="text-base text-muted-foreground">· {paywall.membership.duration_days} días</span></p><ul className="mt-5 space-y-3">{paywall.membership.benefits_copy.map(item => <li key={item} className="flex items-center gap-3 text-sm font-semibold"><Check className="size-5 text-brand" />{item}</li>)}</ul></div>{authAction ? <div className="mt-8 space-y-3"><Link href={authAction} className="benefit-primary-action">ACTIVAR JAKAWI — {price}</Link><Link href={login()} className="flex min-h-11 items-center justify-center text-sm font-bold text-muted-foreground hover:text-foreground">Ya tengo cuenta</Link></div> : <div className="mt-8"><p className="text-sm leading-6 text-muted-foreground">Este beneficio queda asociado a tu intención de membresía.</p></div>}</div></div></section></main></>;
}
