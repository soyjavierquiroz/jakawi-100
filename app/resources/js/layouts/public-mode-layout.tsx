import { useEffect } from 'react';
import { synchronizeAppearanceAccount } from '@/hooks/use-appearance';
import SupportBanner from '@/components/support-banner';
import { usePage } from '@inertiajs/react';
import { PwaInstall } from '@/components/pwa-install';

/** Runs inside Inertia, so public policy follows client navigation and partial reloads. */
export default function PublicModeLayout({ children }: { children: React.ReactNode }) {
    const page = usePage();
    const user = page.props.auth?.user ?? null;
    useEffect(() => synchronizeAppearanceAccount(user), [user?.id]);
    const videoLanding = ['landing-presentations/experience', 'landing-presentations/benefit', 'landing-presentations/challenge', 'landing-presentations/unlock'].includes(page.component) && Boolean(page.props.vsl);
    return (
        <>
            {children}
            <SupportBanner />
            {!videoLanding && page.component !== 'redemptions/show' &&
                !(page.component === 'landing-presentations/conversion' && page.props.conversionStage === 'result') &&
                <PwaInstall preview={page.props.preview === true} />}
        </>
    );
}
