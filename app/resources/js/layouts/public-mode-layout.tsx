import { Head, usePage } from '@inertiajs/react';
import PrelaunchNotice from '@/components/prelaunch-notice';
import { PwaInstall } from '@/components/pwa-install';

/** Runs inside Inertia, so public policy follows client navigation and partial reloads. */
export default function PublicModeLayout({ children }: { children: React.ReactNode }) {
    const page = usePage();
    const videoLanding = ['landing-presentations/experience', 'landing-presentations/benefit', 'landing-presentations/challenge', 'landing-presentations/unlock'].includes(page.component) && Boolean(page.props.vsl);
    return (
        <>
            {page.props.prelaunchPublic && (
                <>
                    <Head><meta head-key="robots" name="robots" content="noindex,nofollow" /></Head>
                    <PrelaunchNotice />
                </>
            )}
            {children}
            {!videoLanding && page.component !== 'coming-soon' && page.component !== 'redemptions/show' &&
                !(page.component === 'landing-presentations/conversion' && page.props.conversionStage === 'result') &&
                <PwaInstall preview={page.props.preview === true} />}
        </>
    );
}
