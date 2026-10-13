import { useState, type MouseEventHandler, type ReactNode } from 'react';

export type MarketingAction = { href: string; label: string; onClick?: MouseEventHandler<HTMLAnchorElement>; disabled?: boolean; kind?: string };

export function MarketingCTA({ action, className = '', location = 'final' }: { action: MarketingAction; className?: string; location?: 'hero' | 'body' | 'final' }) {
    return <a data-growth-kind={action.kind} data-growth-location={location} href={action.href} aria-disabled={action.disabled || undefined} onClick={action.onClick} className={`funnel-primary-action inline-flex min-h-13 w-full items-center justify-center rounded-[var(--radius-control)] bg-[var(--marketing-action)] px-6 py-3 text-center text-sm font-extrabold tracking-wide text-[var(--marketing-action-text)] uppercase transition hover:opacity-90 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-brand sm:w-auto ${className}`}>{action.label}</a>;
}

export function MarketingHero({ eyebrow, title, description, action, aside }: { eyebrow: string; title: string; description: string; action: MarketingAction; aside?: ReactNode }) {
    return <section className="grid gap-10 py-12 sm:py-20 lg:grid-cols-[minmax(0,1.2fr)_minmax(0,0.8fr)] lg:items-center lg:gap-16">
        <div><p className="text-xs font-extrabold tracking-[0.18em] text-foreground uppercase">{eyebrow}</p><h1 className="mt-4 max-w-3xl text-[clamp(2.65rem,7vw,5.5rem)] leading-[0.97] font-extrabold tracking-[-0.055em] uppercase">{title}</h1><p className="mt-6 max-w-xl text-lg leading-8 text-foreground-soft">{description}</p><div className="mt-8"><MarketingCTA action={action} location="hero" /></div></div>
        {aside ? <div className="min-w-0">{aside}</div> : null}
    </section>;
}

export function MarketingBenefits({ eyebrow, title, items }: { eyebrow: string; title: string; items: { title: string; description: string }[] }) {
    return <section className="border-t border-border py-14 sm:py-20"><p className="text-xs font-extrabold tracking-[0.18em] text-foreground uppercase">{eyebrow}</p><h2 className="mt-3 max-w-2xl text-3xl leading-tight font-extrabold tracking-[-0.04em] uppercase sm:text-5xl">{title}</h2><div className="mt-10 grid gap-8 md:grid-cols-3">{items.map((item, index) => <article key={item.title} className="border-t border-border pt-5"><span className="text-sm font-extrabold text-foreground">0{index + 1}</span><h3 className="mt-4 text-xl font-extrabold">{item.title}</h3><p className="mt-3 leading-7 text-foreground-soft">{item.description}</p></article>)}</div></section>;
}

export function MarketingSocialProof({ eyebrow, title, children }: { eyebrow: string; title: string; children: ReactNode }) {
    return <section className="border-y border-border bg-surface-muted py-14 sm:py-20"><div className="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8"><p className="text-xs font-extrabold tracking-[0.18em] text-foreground uppercase">{eyebrow}</p><h2 className="mt-3 max-w-2xl text-3xl leading-tight font-extrabold tracking-[-0.04em] uppercase sm:text-5xl">{title}</h2><div className="mt-6 max-w-2xl text-lg leading-8 text-foreground-soft">{children}</div></div></section>;
}

export function MarketingFAQ({
    items,
    treatment = 'standard',
}: {
    items: { question: string; answer: string }[];
    treatment?: 'standard' | 'compact';
}) {
    const [openFaq, setOpenFaq] = useState<Record<string, boolean>>({});
    const compact = treatment === 'compact';
    return (
        <section
            data-offer-chapter={compact ? 'faq' : undefined}
            className={
                compact
                    ? 'border-t border-border py-12 sm:py-20'
                    : 'py-14 sm:py-20'
            }
        >
            {compact && <p className="discovery-eyebrow">SIN DUDAS</p>}
            <h2
                className={
                    compact
                        ? 'mt-3 text-4xl leading-none font-extrabold tracking-tight uppercase sm:text-6xl'
                        : 'text-3xl font-extrabold tracking-[-0.04em] uppercase sm:text-5xl'
                }
            >
                Preguntas frecuentes
            </h2>
            <div
                className={
                    compact
                        ? 'mt-8 divide-y divide-border border-y border-border'
                        : 'mt-8 max-w-3xl divide-y divide-border border-t border-b border-border'
                }
            >
                {items.map((item) => (
                    <details
                        key={item.question}
                        className={compact ? 'group py-6' : 'group py-5'}
                        onToggle={(event) => {
                            const open = event.currentTarget.open;
                            setOpenFaq((previous) => ({
                                ...previous,
                                [item.question]: open,
                            }));
                        }}
                    >
                        <summary
                            aria-expanded={Boolean(openFaq[item.question])}
                            className={
                                compact
                                    ? 'flex cursor-pointer list-none items-start justify-between gap-6 text-lg font-bold marker:hidden'
                                    : 'cursor-pointer list-none pr-8 text-lg font-bold marker:hidden'
                            }
                        >
                            {item.question}
                            <span
                                aria-hidden="true"
                                className={
                                    compact
                                        ? 'flex size-8 shrink-0 items-center justify-center rounded-full bg-surface-muted text-xl text-foreground'
                                        : 'float-right text-foreground group-open:rotate-45'
                                }
                            >
                                {compact
                                    ? openFaq[item.question]
                                        ? '−'
                                        : '+'
                                    : '+'}
                            </span>
                        </summary>
                        <p
                            className={
                                compact
                                    ? 'mt-4 max-w-3xl leading-7 text-[var(--marketing-text-secondary)]'
                                    : 'text-foreground-soft mt-3 max-w-2xl leading-7'
                            }
                        >
                            {item.answer}
                        </p>
                    </details>
                ))}
            </div>
        </section>
    );
}
