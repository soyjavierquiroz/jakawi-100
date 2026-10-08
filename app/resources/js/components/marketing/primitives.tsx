import type { MouseEventHandler, ReactNode } from 'react';

type Action = { href: string; label: string; onClick?: MouseEventHandler<HTMLAnchorElement>; disabled?: boolean };

export function MarketingCTA({ action, className = '' }: { action: Action; className?: string }) {
    return <a href={action.href} aria-disabled={action.disabled || undefined} onClick={action.onClick} className={`inline-flex min-h-13 w-full items-center justify-center rounded-[var(--radius-control)] bg-brand px-6 py-3 text-center text-sm font-extrabold tracking-wide text-brand-foreground uppercase transition hover:opacity-90 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-brand sm:w-auto ${className}`}>{action.label}</a>;
}

export function MarketingHero({ eyebrow, title, description, action, aside }: { eyebrow: string; title: string; description: string; action: Action; aside?: ReactNode }) {
    return <section className="grid gap-10 py-12 sm:py-20 lg:grid-cols-[minmax(0,1.2fr)_minmax(0,0.8fr)] lg:items-center lg:gap-16">
        <div><p className="text-xs font-extrabold tracking-[0.18em] text-brand uppercase">{eyebrow}</p><h1 className="mt-4 max-w-3xl text-[clamp(2.65rem,7vw,5.5rem)] leading-[0.97] font-extrabold tracking-[-0.055em] uppercase">{title}</h1><p className="mt-6 max-w-xl text-lg leading-8 text-foreground-soft">{description}</p><div className="mt-8"><MarketingCTA action={action} /></div></div>
        {aside ? <div className="min-w-0">{aside}</div> : null}
    </section>;
}

export function MarketingBenefits({ eyebrow, title, items }: { eyebrow: string; title: string; items: { title: string; description: string }[] }) {
    return <section className="border-t border-border py-14 sm:py-20"><p className="text-xs font-extrabold tracking-[0.18em] text-brand uppercase">{eyebrow}</p><h2 className="mt-3 max-w-2xl text-3xl leading-tight font-extrabold tracking-[-0.04em] uppercase sm:text-5xl">{title}</h2><div className="mt-10 grid gap-8 md:grid-cols-3">{items.map((item, index) => <article key={item.title} className="border-t border-border pt-5"><span className="text-sm font-extrabold text-brand">0{index + 1}</span><h3 className="mt-4 text-xl font-extrabold">{item.title}</h3><p className="mt-3 leading-7 text-foreground-soft">{item.description}</p></article>)}</div></section>;
}

export function MarketingSocialProof({ eyebrow, title, children }: { eyebrow: string; title: string; children: ReactNode }) {
    return <section className="border-y border-border bg-surface-muted py-14 sm:py-20"><div className="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8"><p className="text-xs font-extrabold tracking-[0.18em] text-brand uppercase">{eyebrow}</p><h2 className="mt-3 max-w-2xl text-3xl leading-tight font-extrabold tracking-[-0.04em] uppercase sm:text-5xl">{title}</h2><div className="mt-6 max-w-2xl text-lg leading-8 text-foreground-soft">{children}</div></div></section>;
}

export function MarketingFAQ({ items }: { items: { question: string; answer: string }[] }) {
    return <section className="py-14 sm:py-20"><h2 className="text-3xl font-extrabold tracking-[-0.04em] uppercase sm:text-5xl">Preguntas frecuentes</h2><div className="mt-8 max-w-3xl divide-y divide-border border-t border-b border-border">{items.map(item => <details key={item.question} className="group py-5"><summary className="cursor-pointer list-none pr-8 text-lg font-bold marker:hidden">{item.question}<span aria-hidden="true" className="float-right text-brand group-open:rotate-45">+</span></summary><p className="mt-3 max-w-2xl leading-7 text-foreground-soft">{item.answer}</p></details>)}</div></section>;
}
