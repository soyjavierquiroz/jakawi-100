import { metaBrowserTracker } from '@/lib/meta-browser-tracker';
import { Head } from '@inertiajs/react';
import { MarketingBenefits, MarketingCTA, MarketingFAQ, MarketingHero, MarketingSocialProof } from '@/components/marketing/primitives';
import MarketingLayout from '@/layouts/marketing-layout';

type Block =
    | { type: 'benefits'; eyebrow: string; title: string; items: { title: string; description: string }[] }
    | { type: 'image_text'; title: string; description: string; image: string; image_alt?: string }
    | { type: 'social_proof'; eyebrow: string; title: string; text: string }
    | { type: 'faq'; items: { question: string; answer: string }[] }
    | { type: 'rich_text'; title?: string; paragraphs: string[] };

type Landing = {
    key: string; path: string; title: string; description: string; eyebrow: string; hero_image: string | null;
    blocks: Block[]; primary_cta: { type: 'internal' | 'external'; href: string; label: string };
    seo: { title?: string; description?: string };
};

function ContentBlock({ block }: { block: Block }) {
    switch (block.type) {
        case 'benefits': return <div className="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8"><MarketingBenefits eyebrow={block.eyebrow} title={block.title} items={block.items} /></div>;
        case 'social_proof': return <MarketingSocialProof eyebrow={block.eyebrow} title={block.title}><p>{block.text}</p></MarketingSocialProof>;
        case 'faq': return <div className="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8"><MarketingFAQ items={block.items} /></div>;
        case 'image_text': return <section className="mx-auto grid max-w-6xl gap-8 border-t border-border px-4 py-14 sm:px-6 sm:py-20 lg:grid-cols-2 lg:items-center lg:px-8"><img src={block.image} alt={block.image_alt ?? ''} className="aspect-[4/3] w-full rounded-[var(--radius-card)] object-cover" loading="lazy" /><div><h2 className="text-3xl font-extrabold uppercase sm:text-5xl">{block.title}</h2><p className="mt-5 whitespace-pre-line text-lg leading-8 text-foreground-soft">{block.description}</p></div></section>;
        case 'rich_text': return <section className="mx-auto max-w-6xl border-t border-border px-4 py-14 sm:px-6 sm:py-20 lg:px-8">{block.title && <h2 className="max-w-3xl text-3xl font-extrabold uppercase sm:text-5xl">{block.title}</h2>}<div className="mt-6 max-w-3xl space-y-4 text-lg leading-8 text-foreground-soft">{block.paragraphs.map((paragraph, index) => <p key={index}>{paragraph}</p>)}</div></section>;
    }
}

export default function PublicJourneyShow({ landing, canonical }: { landing: Landing; canonical: string }) {
    const trackClick = () => {
        const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        if (!csrf) return;
        try {
            const eventId = globalThis.crypto?.randomUUID?.();
            if (eventId) metaBrowserTracker.cta(eventId, landing.primary_cta.type === 'external' ? 'external' : (landing.primary_cta.href === '/register' ? 'signup' : 'product_detail'), 'other');
            void fetch(`/analytics/public-landings/${encodeURIComponent(landing.key)}/cta`, {
                method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json', 'Content-Type': 'application/json' },
                credentials: 'same-origin', keepalive: true, body: JSON.stringify({ event_id: eventId }),
            }).catch(() => undefined);
        } catch { /* Tracking never interrupts the CTA. */ }
    };
    const action = { href: landing.primary_cta.href, label: landing.primary_cta.label, onClick: trackClick };
    const title = landing.seo.title ?? landing.title;
    const description = landing.seo.description ?? landing.description;

    return <MarketingLayout>
        <Head title={title}><meta name="description" content={description} /><link rel="canonical" href={canonical} /><meta property="og:type" content="website" /><meta property="og:title" content={title} /><meta property="og:description" content={description} /><meta property="og:url" content={canonical} /></Head>
        <main className="overflow-x-hidden">
            <div className="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8"><MarketingHero eyebrow={landing.eyebrow} title={landing.title} description={landing.description} action={action} aside={landing.hero_image ? <img src={landing.hero_image} alt="" className="aspect-[4/3] w-full rounded-[var(--radius-card)] object-cover" /> : undefined} /></div>
            {landing.blocks.map((block, index) => <ContentBlock key={`${block.type}-${index}`} block={block} />)}
            <section className="bg-foreground py-14 text-background sm:py-20"><div className="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8"><h2 className="max-w-3xl text-3xl font-extrabold uppercase sm:text-5xl">{landing.title}</h2><div className="mt-7"><MarketingCTA action={action} /></div></div></section>
        </main>
    </MarketingLayout>;
}
