import { Delivery } from '@/components/marketing/delivery';

export type ChallengeDeliveryContent = {
    title: string;
    partner: string | null;
    mission: string;
    steps: { heading: string; body: string }[];
    deadline: string | null;
    platforms: string[];
    hashtags: string[];
    mentions: string[];
    verificationBoundary: string;
};

export function ChallengeDelivery({
    content,
    reward,
    selection,
    slots,
    state,
    primaryAction,
}: {
    content: ChallengeDeliveryContent;
    reward: string | null;
    selection: string;
    slots?: { awarded: number; reserved: number; limit: number } | null;
    state: string;
    primaryAction: 'evidence' | 'instructions' | null;
}) {
    return (
        <Delivery
            heading="YA ESTÁS DENTRO DEL RETO"
            subject={content.title}
            successCue="PARTICIPACIÓN ACTIVADA"
            accountAccess={false}
        >
            {content.partner && (
                <p className="font-bold text-muted-foreground">
                    {content.partner}
                </p>
            )}
            <section className="border-l-4 border-brand pl-4">
                <h3 className="text-xs font-extrabold tracking-wider">
                    TU MISIÓN
                </h3>
                <p className="mt-2 font-semibold">{content.mission}</p>
            </section>
            <section id="challenge-instructions" className="scroll-mt-6 pt-4">
                <h3 className="text-xl font-extrabold">QUÉ TIENES QUE HACER</h3>
                <ol className="mt-5 space-y-5">
                    {content.steps.map((step, index) => (
                        <li key={step.heading} className="flex gap-3">
                            <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-foreground text-sm font-extrabold text-background">
                                {index + 1}
                            </span>
                            <div className="min-w-0">
                                <h4 className="font-extrabold">
                                    {step.heading}
                                </h4>
                                <p className="mt-1 text-muted-foreground">
                                    {step.body}
                                </p>
                            </div>
                        </li>
                    ))}
                </ol>
                {(content.platforms.length > 0 ||
                    content.hashtags.length > 0 ||
                    content.mentions.length > 0) && (
                    <dl className="mt-5 rounded-2xl border border-border bg-surface p-4">
                        {content.platforms.length > 0 && (
                            <div>
                                <dt className="font-bold">
                                    Plataformas permitidas
                                </dt>
                                <dd>{content.platforms.join(', ')}</dd>
                            </div>
                        )}
                        {content.hashtags.length > 0 && (
                            <div className="mt-2">
                                <dt className="font-bold">
                                    Etiquetas requeridas
                                </dt>
                                <dd className="break-words">
                                    {content.hashtags.join(' ')}
                                </dd>
                            </div>
                        )}
                        {content.mentions.length > 0 && (
                            <div className="mt-2">
                                <dt className="font-bold">
                                    Menciones requeridas
                                </dt>
                                <dd className="break-words">
                                    {content.mentions.join(' ')}
                                </dd>
                            </div>
                        )}
                    </dl>
                )}
            </section>
            {content.deadline && (
                <section className="rounded-2xl bg-reward p-4 text-[var(--ink)]">
                    <h3 className="text-xs font-extrabold tracking-wider">
                        TIENES HASTA
                    </h3>
                    <p className="mt-2 text-xl font-extrabold">
                        {new Intl.DateTimeFormat('es-BO', {
                            day: 'numeric',
                            month: 'short',
                            hour: '2-digit',
                            minute: '2-digit',
                            timeZone: 'America/La_Paz',
                        })
                            .format(new Date(content.deadline))
                            .toUpperCase()}
                    </p>
                </section>
            )}
            <section className="rounded-2xl border border-reward bg-surface p-4">
                <h3 className="inline-flex rounded-lg bg-reward px-2 py-1 text-xs font-extrabold tracking-wider text-[var(--ink)]">
                    QUÉ PUEDES GANAR
                </h3>
                <p className="mt-2 font-bold">
                    {reward ?? 'Premio por anunciar'}
                </p>
                <p className="mt-2 text-muted-foreground">{selection}</p>
                {slots && (
                    <p className="mt-3 text-sm font-semibold">
                        {Math.max(0, slots.limit - slots.reserved)} premios
                        disponibles{' '}
                        <span className="font-normal text-muted-foreground">
                            · {slots.awarded} otorgados de {slots.limit}
                        </span>
                    </p>
                )}
                <p className="mt-2 text-xs text-muted-foreground">
                    Participar no significa haber ganado el premio.
                </p>
            </section>
            <section
                className="rounded-2xl border border-border p-4"
                aria-live="polite"
            >
                <h3 className="text-xs font-extrabold tracking-wider">
                    TU PARTICIPACIÓN
                </h3>
                <p className="mt-2 font-extrabold">{state}</p>
            </section>
            {primaryAction && (
                <a
                    href={
                        primaryAction === 'evidence'
                            ? '#challenge-evidence'
                            : '#challenge-instructions'
                    }
                    className="funnel-primary-action flex min-h-14 items-center justify-center rounded-xl px-4 text-center font-extrabold text-white"
                >
                    {primaryAction === 'evidence'
                        ? 'ENVIAR MI EVIDENCIA'
                        : 'VER MIS INSTRUCCIONES'}
                </a>
            )}
        </Delivery>
    );
}
