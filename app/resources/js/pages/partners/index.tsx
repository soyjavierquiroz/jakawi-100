import { Head, Link } from '@inertiajs/react';
import { MarketingBenefits, MarketingCTA, MarketingFAQ, MarketingHero, MarketingSocialProof } from '@/components/marketing/primitives';
import { CitySelector } from '@/components/city-selector';

type Props = { city: { name: string; slug: string }; canonical: string };

export default function PartnersIndex({ city, canonical }: Props) {
    const action = { href: '/partners/aplicar', label: 'Solicitar ser Partner' };
    const description = 'Presenta tu negocio para formar parte de JAKAWI. Revisamos cada solicitud por ciudad y conversamos contigo antes de publicar cualquier propuesta.';

    return <>
        <Head title="Partners JAKAWI | Haz que tu negocio sea parte de la ciudad">
            <meta name="description" content={description} />
            <link rel="canonical" href={canonical} />
            <meta property="og:type" content="website" />
            <meta property="og:title" content="Partners JAKAWI | Haz que tu negocio sea parte de la ciudad" />
            <meta property="og:description" content={description} />
            <meta property="og:url" content={canonical} />
        </Head>
        <main className="overflow-x-hidden">
            <div className="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
                <div className="pt-5"><span className="mr-2 text-sm font-semibold text-muted-foreground">Solicitud para</span><CitySelector /></div>
                <MarketingHero eyebrow="JAKAWI PARA NEGOCIOS" title="Tu negocio, parte de los planes de la ciudad." description="JAKAWI reúne lugares, beneficios y experiencias para ayudar a las personas a descubrir qué hacer en su ciudad. Si tienes un negocio, queremos conocer tu propuesta." action={action} aside={<div className="relative flex min-h-64 flex-col justify-between overflow-hidden rounded-[var(--radius-card)] bg-foreground p-7 text-background sm:min-h-96 sm:p-10"><img src="/jakawi-mark.svg" alt="" className="h-14 w-14" /><div><span className="block h-1 w-12 bg-brand" aria-hidden="true" /><p className="mt-5 max-w-sm text-3xl leading-tight font-extrabold tracking-[-0.04em] sm:text-4xl">MÁS RAZONES PARA SALIR. MÁS LUGARES POR DESCUBRIR.</p></div></div>} />
                <MarketingBenefits eyebrow="POR QUÉ PARTICIPAR" title="Una nueva forma de presentar lo que haces." items={[
                    { title: 'Haz visible tu propuesta', description: 'Tu negocio puede aparecer junto a los lugares y planes que las personas exploran en JAKAWI, una vez aprobado y publicado.' },
                    { title: 'Comparte beneficios y experiencias', description: 'Los Partners pueden proponer contenido para revisión editorial: beneficios, experiencias y otras oportunidades disponibles en la plataforma.' },
                    { title: 'Participa en tu ciudad', description: 'Cada solicitud se vincula a una ciudad. Así podemos conocer tu negocio en el contexto donde opera.' },
                ]} />
            </div>
            <MarketingSocialProof eyebrow="JAKAWI EN ACCIÓN" title="Conoce el espacio donde podrías participar."><p>Explora cómo se presentan los lugares, beneficios y experiencias en JAKAWI. Cada publicación pasa por revisión antes de estar disponible.</p><Link href="/explorar" className="mt-5 inline-block text-base font-extrabold text-brand underline underline-offset-4">Explorar JAKAWI</Link></MarketingSocialProof>
            <div className="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
                <section className="border-b border-border py-14 sm:py-20"><p className="text-xs font-extrabold tracking-[0.18em] text-brand uppercase">CÓMO FUNCIONA</p><h2 className="mt-3 max-w-2xl text-3xl font-extrabold tracking-[-0.04em] uppercase sm:text-5xl">Empecemos por conocernos.</h2><ol className="mt-9 grid gap-7 md:grid-cols-3">{[
                    ['01', 'Envía tu solicitud', `Cuéntanos sobre tu negocio en ${city.name} mediante el formulario de aplicación.`],
                    ['02', 'Revisamos tu propuesta', 'Nuestro equipo evalúa la información y puede ponerse en contacto contigo.'],
                    ['03', 'Preparamos tu participación', 'Si avanzamos juntos, podrás preparar contenido sujeto a revisión para JAKAWI.'],
                ].map(([number, title, body]) => <li key={number} className="border-l-2 border-brand pl-5"><span className="text-sm font-extrabold text-brand">{number}</span><h3 className="mt-3 text-xl font-extrabold">{title}</h3><p className="mt-2 leading-7 text-foreground-soft">{body}</p></li>)}</ol></section>
                <MarketingFAQ items={[
                    { question: '¿Tiene costo enviar la solicitud?', answer: 'Enviar tu solicitud no tiene costo. Si tu negocio avanza en el proceso, conversaremos contigo sobre las condiciones de participación antes de cualquier publicación.' },
                    { question: '¿Mi negocio aparece de inmediato?', answer: 'No. El equipo de JAKAWI revisa cada solicitud y el contenido antes de publicarlo.' },
                    { question: '¿Qué información necesito para aplicar?', answer: 'El nombre de tu negocio, tu nombre y una forma de contacto por WhatsApp o correo electrónico. También puedes contarnos más sobre tu propuesta.' },
                    { question: `¿La solicitud corresponde a ${city.name}?`, answer: `Sí. Usamos la ciudad seleccionada en JAKAWI para dirigir tu solicitud al flujo de ${city.name}. Si tu negocio está en otra ciudad, usa el selector para visitar su página y encontrar la solicitud correspondiente.` },
                ]} />
            </div>
            <section className="bg-foreground py-14 text-background sm:py-20"><div className="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8"><p className="text-xs font-extrabold tracking-[0.18em] text-brand uppercase">TU SIGUIENTE PASO</p><h2 className="mt-3 max-w-3xl text-3xl leading-tight font-extrabold tracking-[-0.04em] uppercase sm:text-5xl">Cuéntanos qué hace especial a tu negocio.</h2><p className="mt-4 max-w-xl leading-7 text-background/80">Tu solicitud se enviará para {city.name}. Completa el formulario y nuestro equipo revisará tu propuesta.</p><div className="mt-7"><MarketingCTA action={action} /></div></div></section>
        </main>
    </>;
}
