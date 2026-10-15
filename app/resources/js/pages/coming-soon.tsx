import { CityCommunityCard, type CityCommunityData } from '@/components/city-community-card';
import { Head } from '@inertiajs/react';

const title = 'JAKAWI — Vive más tu ciudad | Próximamente en Cochabamba';
const description = 'Beneficios, experiencias, retos y nuevos planes para vivir más Cochabamba. JAKAWI llega pronto.';

/** The official prelaunch presence has no inventory or application navigation. */
export default function ComingSoon({ community }: { community?: CityCommunityData | null }) {
    return (
        <div data-coming-soon data-marketing-theme="light-conversion" className="min-h-screen overflow-hidden bg-[var(--cream)] font-discovery text-[var(--ink)]">
            <Head title={title}>
                <meta head-key="description" name="description" content={description} />
                <meta head-key="robots" name="robots" content="index,follow" />
                <link head-key="canonical" rel="canonical" href="https://jakawi.com/" />
                <meta head-key="og:title" property="og:title" content={title} />
                <meta head-key="og:description" property="og:description" content={description} />
                <meta head-key="og:type" property="og:type" content="website" />
                <meta head-key="og:url" property="og:url" content="https://jakawi.com/" />
            </Head>
            <header className="mx-auto max-w-7xl px-6 py-7 sm:px-10 lg:py-10">
                <img src="/jakawi-logo.svg" alt="JAKAWI" className="h-9 w-auto sm:h-11" />
            </header>
            <main>
                <section className="relative mx-auto max-w-7xl px-6 pt-7 pb-14 sm:px-10 sm:pt-12 lg:pt-16 lg:pb-24">
                    <div aria-hidden="true" className="absolute top-10 right-10 hidden h-32 w-32 rotate-12 rounded-[2rem] bg-reward lg:block" />
                    <p className="relative text-[11px] font-extrabold tracking-[0.18em] sm:text-sm">
                        <span aria-hidden="true" className="mr-2 inline-block size-2 rounded-full bg-success" />
                        COCHABAMBA · PRÓXIMAMENTE
                    </p>
                    <h1 className="relative mt-7 max-w-5xl text-[clamp(2.75rem,7.7vw,7rem)] leading-[0.98] font-black tracking-[-0.055em]">
                        TU CIUDAD ESTÁ A PUNTO DE <span className="relative inline-block"><span aria-hidden="true" className="absolute inset-x-0 bottom-0 h-[0.22em] -rotate-2 bg-brand" /><span className="relative">DARTE MÁS.</span></span>
                    </h1>
                    <p className="relative mt-8 max-w-xl text-base leading-7 text-[var(--palette-light-text-secondary)] sm:text-xl sm:leading-8">
                        JAKAWI reúne beneficios, experiencias, retos y planes para ayudarte a vivir más Cochabamba y gastar mejor.
                    </p>
                    <p className="mt-9 inline-block -rotate-2 bg-reward px-4 py-3 text-sm font-black tracking-tight sm:text-lg">
                        VIVE MÁS. GASTA MENOS.
                    </p>
                </section>
                <section aria-labelledby="coming-next" className="border-y border-ink/15">
                    <div className="mx-auto max-w-7xl px-6 py-10 sm:px-10 lg:py-14">
                        <h2 id="coming-next" className="text-xs font-extrabold tracking-[0.18em]">LO QUE VIENE</h2>
                        <div className="mt-8 grid gap-8 sm:grid-cols-3 sm:gap-10">
                            {[
                                ['01', 'BENEFICIOS', 'Ventajas que sí querrás usar.', 'bg-brand'],
                                ['02', 'EXPERIENCIAS', 'Nuevos planes para salir de la rutina.', 'bg-reward'],
                                ['03', 'RETOS Y RECOMPENSAS', 'Más razones para vivir tu ciudad.', 'bg-success'],
                            ].map(([number, label, copy, color]) => (
                                <article key={number}>
                                    <span aria-hidden="true" className={`inline-flex size-9 items-center justify-center rounded-full text-xs font-extrabold ${color}`}>{number}</span>
                                    <h3 className="mt-4 text-lg font-black tracking-tight">{label}</h3>
                                    <p className="mt-2 text-sm leading-6 text-[var(--palette-light-text-secondary)]">{copy}</p>
                                </article>
                            ))}
                        </div>
                    </div>
                </section>
                <section className="mx-auto max-w-7xl px-6 py-12 sm:px-10 lg:py-16">
                    <p className="max-w-2xl text-xl leading-tight font-extrabold tracking-tight sm:text-3xl">
                        ESTAMOS PREPARANDO LA PRIMERA SELECCIÓN JAKAWI PARA COCHABAMBA.
                    </p>
                    <p className="mt-6 text-xs font-extrabold tracking-[0.18em]">PRÓXIMAMENTE.</p>
                </section>
                {community && <div className="mx-auto max-w-7xl px-6 pb-8 sm:px-10"><CityCommunityCard community={community} /></div>}
            </main>
        </div>
    );
}
