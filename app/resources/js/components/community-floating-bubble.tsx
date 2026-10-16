import { useEffect, useState } from 'react';
import { X } from 'lucide-react';
import { CommunityWhatsAppIcon, interact, type CityCommunityData } from '@/components/city-community-card';

const dismissedKey = 'jakawi:community-bubble:dismissed';

export function CommunityFloatingBubble({ community }: { community?: CityCommunityData | null }) {
    const [visible, setVisible] = useState(false);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState('');

    useEffect(() => {
        if (!community?.automatic) return;
        let cancelled = false;
        try {
            if (sessionStorage.getItem(dismissedKey)) return;
        } catch { /* Storage may be unavailable; backend frequency still applies. */ }
        const timer = window.setTimeout(() => {
            void interact(community, 'presented').then(result => {
                if (!cancelled && result.accepted) setVisible(true);
            }).catch(() => { /* A failed presentation stays hidden. */ });
        }, 3000 + Math.random() * 7000);
        return () => { cancelled = true; window.clearTimeout(timer); };
    }, [community]);

    useEffect(() => {
        const hide = (event: Event) => {
            if ((event as CustomEvent<string>).detail === community?.city_slug) setVisible(false);
        };
        window.addEventListener('jakawi:community-interaction', hide);
        return () => window.removeEventListener('jakawi:community-interaction', hide);
    }, [community?.city_slug]);

    if (!visible || !community) return null;

    function dismiss() {
        try { sessionStorage.setItem(dismissedKey, '1'); } catch { /* Hide this view even without storage. */ }
        setVisible(false);
    }

    async function open() {
        if (!community || busy) return;
        setBusy(true);
        setError('');
        try {
            const result = await interact(community, 'opened');
            if (result.accepted && result.url) window.location.assign(result.url);
        } catch {
            setError('No pudimos abrir la comunidad. Inténtalo de nuevo.');
        } finally { setBusy(false); }
    }

    return (
        <aside className="community-bubble" aria-label={`Comunidad ${community.city_name}`}>
            <div className="community-bubble-float">
                <button type="button" className="community-whatsapp-cta community-bubble-action" disabled={busy} onClick={() => void open()} aria-label={`Abrir comunidad de ${community.city_name}`} title={`Comunidad ${community.city_name}`}>
                    <CommunityWhatsAppIcon />
                </button>
                <button type="button" className="community-bubble-dismiss" onClick={dismiss} aria-label="Ocultar comunidad por esta sesión"><X aria-hidden="true" /></button>
            </div>
            {error && <p role="alert" className="community-bubble-error">{error}</p>}
        </aside>
    );
}
