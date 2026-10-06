import { Head, Link, useForm } from '@inertiajs/react';
import { MarketingBenefits, MarketingCTA, MarketingFAQ, MarketingHero, MarketingSocialProof } from '@/components/marketing/primitives';

type Program = 'afiliados' | 'creadores' | 'promotores';
type Props = {
    program: Program;
    authenticated: boolean;
    canonical: string;
    application: { status: string; created_at: string } | null;
    enrollment: { status: string } | null;
    active: boolean;
    profileCity: string | null;
};

const copy = {
    afiliados: {
        title: 'Recomienda JAKAWI. Comparte planes que valen la pena.',
        description: 'Si te gusta recomendar lugares, beneficios y experiencias, puedes solicitar participar en el programa de afiliados de JAKAWI y compartir tu enlace de referidos.',
        eyebrow: 'AFILIADOS JAKAWI',
        benefits: [
            { title: 'Comparte lo que descubres', description: 'Recomienda JAKAWI a personas que buscan planes y lugares en su ciudad.' },
            { title: 'Usa tu código de referido', description: 'Cuando tu participación esté activa, tendrás un código para compartir JAKAWI.' },
            { title: 'Sigue tu actividad', description: 'Consulta el desempeño de tus referidos desde tu panel de afiliado.' },
        ],
        faq: '¿Necesito tener una audiencia grande?',
        answer: 'No pedimos un tamaño de audiencia para enviar tu solicitud. Queremos conocer cómo recomendarías JAKAWI.',
        field: '¿Dónde compartes recomendaciones? (opcional)',
    },
    creadores: {
        title: 'Crea contenido sobre los planes de tu ciudad.',
        description: 'Si compartes ideas, historias o recomendaciones, solicita participar como creador y conecta lugares, beneficios y experiencias de JAKAWI con tu audiencia.',
        eyebrow: 'CREADORES JAKAWI',
        benefits: [
            { title: 'Inspira nuevos planes', description: 'Comparte contenido que ayude a descubrir qué hacer en la ciudad.' },
            { title: 'Conecta con tu comunidad', description: 'Presenta propuestas de JAKAWI en tus propios canales y con tu estilo.' },
            { title: 'Sigue tu actividad', description: 'Consulta la actividad de tus enlaces y referidos desde tu panel de creador.' },
        ],
        faq: '¿Puedo solicitar participar si recién empiezo a crear contenido?',
        answer: 'Sí. Puedes enviarnos el enlace de tu canal y una breve idea de cómo compartirías los planes de JAKAWI.',
        field: 'Enlace a tu canal o perfil (opcional)',
    },
    promotores: {
        title: 'Haz crecer JAKAWI desde tu comunidad.',
        description: 'Si te interesa conectar personas y oportunidades en tu ciudad, solicita participar como promotor y ayúdanos a activar JAKAWI en el territorio.',
        eyebrow: 'PROMOTORES JAKAWI',
        benefits: [
            { title: 'Impulsa el crecimiento local', description: 'Ayuda a que más personas conozcan JAKAWI y sus planes.' },
            { title: 'Activa conexiones', description: 'Comparte JAKAWI en tu comunidad y participa en acciones locales.' },
            { title: 'Sigue tu actividad', description: 'Consulta tu actividad de promotor desde las herramientas disponibles para el programa.' },
        ],
        faq: '¿En qué ciudad puedo participar?',
        answer: 'Cuéntanos tu ciudad en el mensaje si aún no está en tu perfil. Revisaremos tu solicitud según el contexto local.',
        field: 'Enlace a tu perfil público (opcional)',
    },
};

const panel = { afiliados: '/affiliate', creadores: '/creator', promotores: '/promoter/sales' };
const statusLabels: Record<string, string> = {
    SUBMITTED: 'Solicitud recibida', CONTACTED: 'Estamos en contacto', QUALIFIED: 'Solicitud en evaluación',
    APPROVED: 'Solicitud aprobada', REJECTED: 'Solicitud cerrada',
};

export default function ProgramLanding({ program, canonical, application, enrollment, active, authenticated, profileCity }: Props) {
    const c = copy[program];
    const form = useForm({ channel_url: '', message: '' });
    const open = application && !['REJECTED', 'APPROVED'].includes(application.status);
    const action = active ? { href: panel[program], label: 'Ir a mi programa' }
        : open || application?.status === 'APPROVED' ? { href: '#solicitud', label: 'Ver mi estado' }
            : { href: `/${program}/aplicar`, label: `Quiero ser ${program === 'afiliados' ? 'afiliado' : program === 'creadores' ? 'creador' : 'promotor'}` };
    const title = `${program[0].toUpperCase()}${program.slice(1)} JAKAWI | Participa en tu ciudad`;
    const description = c.description;
    return <>
        <Head title={title}><meta name="description" content={description} /><link rel="canonical" href={canonical} /><meta property="og:type" content="website" /><meta property="og:title" content={title} /><meta property="og:description" content={description} /><meta property="og:url" content={canonical} /></Head>
        <main className="overflow-x-hidden">
            <div className="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
                <MarketingHero eyebrow={c.eyebrow} title={c.title} description={c.description} action={action} aside={<div className="flex min-h-64 flex-col justify-between rounded-[var(--radius-card)] bg-foreground p-8 text-background sm:min-h-96"><img src="/jakawi-mark.svg" alt="" className="size-14" /><p className="max-w-xs text-3xl font-extrabold leading-tight uppercase">Más razones para salir. Más planes para compartir.</p></div>} />
                <MarketingBenefits eyebrow="POR QUÉ PARTICIPAR" title="Comparte JAKAWI a tu manera." items={c.benefits} />
            </div>
            <MarketingSocialProof eyebrow="CONOCE JAKAWI" title="Explora los planes que puedes compartir."><p>Descubre lugares, beneficios y experiencias disponibles en JAKAWI antes de enviar tu solicitud.</p><Link href="/explorar" className="mt-5 inline-block font-extrabold text-brand underline">Explorar JAKAWI</Link></MarketingSocialProof>
            <div className="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
                <section className="border-b border-border py-14 sm:py-20"><h2 className="text-3xl font-extrabold uppercase sm:text-5xl">Así puedes participar.</h2><ol className="mt-8 grid gap-8 md:grid-cols-3">{[
                    ['01', 'Crea tu cuenta gratis', 'Usa tu nombre, correo y WhatsApp para crear tu cuenta JAKAWI.'],
                    ['02', 'Envía tu solicitud', 'Cuéntanos brevemente cómo te gustaría participar.'],
                    ['03', 'Esperamos la revisión', 'El equipo de JAKAWI revisa tu solicitud antes de activar el programa.'],
                ].map(([number, heading, body]) => <li key={number} className="border-l-2 border-brand pl-5"><span className="font-extrabold text-brand">{number}</span><h3 className="mt-3 text-xl font-extrabold">{heading}</h3><p className="mt-2 text-foreground-soft">{body}</p></li>)}</ol></section>
                <MarketingFAQ items={[{ question: '¿Se activa el programa al enviar mi solicitud?', answer: 'No. El equipo de JAKAWI revisa y aprueba cada solicitud antes de activar la participación.' }, { question: c.faq, answer: c.answer }, { question: '¿Qué información necesito?', answer: 'Tu cuenta gratuita ya incluye nombre, correo y WhatsApp. Puedes añadir un enlace y un mensaje breve para que conozcamos tu propuesta.' }]} />
                <section id="solicitud" className="border-t border-border py-14 sm:py-20"><p className="text-xs font-extrabold tracking-[0.18em] text-brand uppercase">TU SIGUIENTE PASO</p>
                    {active ? <div><h2 className="mt-3 text-3xl font-extrabold uppercase">Ya participas en este programa</h2><p className="mt-4">Tu participación está activa.</p><div className="mt-6"><MarketingCTA action={action} /></div></div>
                    : open || application?.status === 'APPROVED' ? <div><h2 className="mt-3 text-3xl font-extrabold uppercase">{statusLabels[application?.status ?? '']}</h2><p className="mt-4 max-w-xl text-foreground-soft">JAKAWI revisará tu solicitud. Puedes volver aquí para consultar su estado.</p></div>
                    : enrollment ? <div><h2 className="mt-3 text-3xl font-extrabold uppercase">Participación no activa</h2><p className="mt-4 max-w-xl text-foreground-soft">Tu participación actual no está activa. Puedes enviar una nueva solicitud para revisión.</p></div> : null}
                    {!active && !open && application?.status !== 'APPROVED' && <div className="mt-6 max-w-xl"><h2 className="text-3xl font-extrabold uppercase">{application?.status === 'REJECTED' ? 'Enviar una nueva solicitud' : 'Solicita participar'}</h2><p className="mt-3 text-foreground-soft">{profileCity ? `Tu perfil indica ${profileCity}. ` : ''}Usaremos el nombre y los datos de contacto de tu cuenta.</p>
                        {authenticated && <form className="mt-7 space-y-5" onSubmit={e => { e.preventDefault(); form.post(`/${program}/solicitudes`); }}><label className="block font-semibold">{c.field}<input type="url" value={form.data.channel_url} onChange={e => form.setData('channel_url', e.target.value)} className="mt-2 block w-full rounded-[var(--radius-control)] border border-border bg-background p-3" placeholder="https://" /></label>{form.errors.channel_url && <p className="text-sm text-brand">{form.errors.channel_url}</p>}<label className="block font-semibold">¿Cómo te gustaría participar? (opcional)<textarea value={form.data.message} onChange={e => form.setData('message', e.target.value)} maxLength={1000} rows={3} className="mt-2 block w-full rounded-[var(--radius-control)] border border-border bg-background p-3" /></label>{form.errors.message && <p className="text-sm text-brand">{form.errors.message}</p>}<button disabled={form.processing} className="rounded-[var(--radius-control)] bg-brand px-6 py-3 font-extrabold text-brand-foreground uppercase disabled:opacity-60">Enviar solicitud</button></form>}
                        {!authenticated && <div className="mt-6"><MarketingCTA action={action} /></div>}
                    </div>}
                </section>
            </div>
        </main>
    </>;
}
