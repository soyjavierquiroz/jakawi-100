import { useEffect, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';

export type CityCommunityData = {
    city_slug: string;
    city_name: string;
    name: string;
    status: string;
    automatic: boolean;
    source: 'onboarding' | 'home' | 'mi-jakawi';
};
type Action =
    | 'presented'
    | 'opened'
    | 'snoozed'
    | 'declined'
    | 'self_reported_joined'
    | 'reset';
type Result = { accepted: boolean; status: string; url?: string };

async function interact(
    community: CityCommunityData,
    action: Action,
): Promise<Result> {
    const csrf =
        document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
            ?.content ?? '';
    const response = await fetch(
        `/comunidades/${encodeURIComponent(community.city_slug)}/interaccion`,
        {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrf,
                Accept: 'application/json',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ action, source: community.source }),
        },
    );
    if (!response.ok)
        throw new Error('No pudimos guardar tu elección. Inténtalo de nuevo.');
    return response.json();
}

export function CityCommunityCard({
    community,
    permanent = false,
}: {
    community?: CityCommunityData | null;
    permanent?: boolean;
}) {
    if (!community || (!permanent && !community.automatic)) return null;
    return (
        <CommunityCard
            key={`${community.city_slug}-${community.source}`}
            community={community}
            permanent={permanent}
        />
    );
}

function CommunityCard({
    community,
    permanent,
}: {
    community: CityCommunityData;
    permanent: boolean;
}) {
    const section = useRef<HTMLElement>(null);
    const started = useRef(false);
    const [ready, setReady] = useState(!community.automatic);
    const [hidden, setHidden] = useState(false);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState('');
    const [status, setStatus] = useState(community.status);
    const automatic = community.automatic && !hidden;

    async function present() {
        if (started.current) return;
        started.current = true;
        setError('');
        try {
            const result = await interact(community, 'presented');
            setStatus(result.status);
            setHidden(!result.accepted);
            setReady(true);
        } catch {
            started.current = false;
            setError('No pudimos cargar la invitación. Puedes reintentar.');
        }
    }

    useEffect(() => {
        if (!community.automatic || !section.current) return;
        const observer = new IntersectionObserver(
            (entries) => {
                if (
                    entries.some(
                        (entry) =>
                            entry.isIntersecting &&
                            entry.intersectionRatio >= 0.5,
                    )
                ) {
                    observer.disconnect();
                    void present();
                }
            },
            { threshold: 0.5 },
        );
        observer.observe(section.current);
        return () => observer.disconnect();
        // The keyed component keeps one exposure request per rendered invitation.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    async function act(action: Action) {
        setBusy(true);
        setError('');
        try {
            const result = await interact(community, action);
            setStatus(result.status);
            if (action === 'opened' && result.url) {
                window.location.assign(result.url);
            } else if (action !== 'reset') {
                setHidden(true);
            }
        } catch (failure) {
            setError(
                failure instanceof Error
                    ? failure.message
                    : 'Inténtalo de nuevo.',
            );
        } finally {
            setBusy(false);
        }
    }

    if (hidden && !permanent) return null;
    const joined = status === 'self_reported_joined';
    const declined = status === 'declined';

    return (
        <section
            ref={section}
            aria-label="Tu comunidad JAKAWI"
            className="mt-8 max-w-xl rounded-[var(--radius-card)] border border-border bg-surface p-5 sm:p-6"
        >
            <p className="text-xs font-extrabold tracking-wide text-brand uppercase">
                {community.name}
            </p>
            <h2 className="mt-2 text-xl font-extrabold">
                {permanent
                    ? 'Tu comunidad JAKAWI'
                    : `No te pierdas lo que pasa en ${community.city_name}.`}
            </h2>
            <p className="mt-2 text-sm leading-relaxed text-muted-foreground">
                {permanent
                    ? 'Descubre las novedades de tu ciudad en WhatsApp.'
                    : `Nuevos lugares, beneficios y experiencias. Entérate primero en la comunidad JAKAWI de ${community.city_name}.`}
            </p>
            <p className="mt-3 text-sm font-medium">
                Gratis · Opcional · Solo anuncios de JAKAWI
            </p>
            <p className="mt-2 text-xs leading-relaxed text-muted-foreground">
                WhatsApp es una plataforma externa. Según el tipo de grupo y su
                configuración, otros participantes pueden ver tu información de
                contacto. Participar es voluntario.
            </p>
            <div className="mt-4 flex flex-col gap-2">
                <Button
                    disabled={busy || (!ready && !permanent)}
                    onClick={() => void act('opened')}
                    className="h-auto min-h-11 bg-brand px-4 py-3 whitespace-normal text-brand-foreground focus-visible:ring-brand"
                >
                    {busy
                        ? 'Guardando…'
                        : permanent
                          ? `Abrir comunidad de ${community.city_name}`
                          : 'Unirme a la comunidad'}
                </Button>
                {ready && !joined && !declined && (
                    <Button
                        variant="outline"
                        disabled={busy}
                        onClick={() => void act('self_reported_joined')}
                        className="min-h-11"
                    >
                        Ya me uní
                    </Button>
                )}
                {automatic && ready && (
                    <>
                        <Button
                            variant="ghost"
                            disabled={busy}
                            onClick={() => void act('snoozed')}
                            className="min-h-11"
                        >
                            Ahora no
                        </Button>
                        <Button
                            variant="ghost"
                            disabled={busy}
                            onClick={() => void act('declined')}
                            className="h-auto min-h-11 whitespace-normal"
                        >
                            No recibir más invitaciones
                        </Button>
                    </>
                )}
                {permanent && (joined || declined) && (
                    <>
                        <p
                            role="status"
                            className="text-sm text-muted-foreground"
                        >
                            {joined
                                ? 'Indicaste que ya te uniste. Es una declaración tuya, sin verificación de WhatsApp.'
                                : 'Desactivaste las invitaciones automáticas.'}
                        </p>
                        <Button
                            variant="ghost"
                            disabled={busy}
                            onClick={() => void act('reset')}
                            className="h-auto min-h-11 whitespace-normal"
                        >
                            {joined
                                ? 'Corregir: aún no me uní'
                                : 'Volver a permitir invitaciones'}
                        </Button>
                    </>
                )}
            </div>
            {error && (
                <div role="alert" className="mt-3 text-sm">
                    <p>{error}</p>
                    {!ready && (
                        <Button
                            variant="outline"
                            onClick={() => void present()}
                            className="mt-2 min-h-11"
                        >
                            Reintentar
                        </Button>
                    )}
                </div>
            )}
        </section>
    );
}
