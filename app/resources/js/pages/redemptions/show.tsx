import { Delivery } from '@/components/marketing/delivery';
import { Head, Link } from '@inertiajs/react';
import { Clock3 } from 'lucide-react';
import QRCode from 'qrcode';
import { useEffect, useState } from 'react';

type Redemption = {
    address?: string | null;
    terms?: string | null;
    public_id: string;
    code: string;
    status: string;
    partner_name: string;
    location_name: string;
    benefit_title: string;
    benefit_slug?: string | null;
    savings_amount?: string | null;
    expires_at: string;
    confirmed_at?: string | null;
    qr_url?: string | null;
};
type Membership = {
    amount_paid?: string | null;
    confirmed_savings: string;
    has_paid_for_itself: boolean;
};
function money(value?: string | null) {
    return value ? `Bs ${Number(value).toFixed(2)}` : null;
}
function timeLeft(value: string) {
    const seconds = Math.max(
        0,
        Math.floor((new Date(value).getTime() - Date.now()) / 1000),
    );
    return `${String(Math.floor(seconds / 60)).padStart(2, '0')}:${String(seconds % 60).padStart(2, '0')}`;
}

export default function RedemptionShow({
    redemption,
    membership,
}: {
    redemption: Redemption;
    membership?: Membership | null;
}) {
    const confirmed = redemption.status === 'confirmed';
    const expired =
        !confirmed &&
        (redemption.status === 'expired' ||
            new Date(redemption.expires_at).getTime() <= Date.now());
    const savings = money(redemption.savings_amount);
    const [qr, setQr] = useState('');
    const [, tick] = useState(0);
    useEffect(() => {
        if (redemption.qr_url && !expired)
            void QRCode.toDataURL(redemption.qr_url, {
                width: 420,
                margin: 1,
            }).then(setQr);
    }, [redemption.qr_url, expired]);
    useEffect(() => {
        const timer = window.setInterval(
            () => tick((value) => value + 1),
            1000,
        );
        return () => window.clearInterval(timer);
    }, []);
    if (confirmed)
        return (
            <>
                <Head title="Canje confirmado" />
                <div>
                    <section className="mx-auto flex w-full max-w-md flex-col justify-center text-center">
                        <Delivery
                            completed
                            heading="CANJE CONFIRMADO"
                            subject={redemption.benefit_title}
                        >
                            {savings ? (
                                <>
                                    <p className="mt-8 text-sm font-bold uppercase">
                                        AHORRASTE
                                    </p>
                                    <p className="mt-1 text-5xl font-extrabold text-foreground">
                                        {savings}
                                    </p>
                                </>
                            ) : null}
                            <div className="mt-9 border-y border-border py-5 text-left">
                                <p className="font-extrabold">
                                    {redemption.partner_name}
                                </p>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    {redemption.benefit_title} ·{' '}
                                    {redemption.location_name}
                                </p>
                            </div>
                            {membership ? (
                                <div className="mt-7">
                                    <p className="text-sm font-bold">
                                        Tu ahorro acumulado
                                    </p>
                                    <p className="mt-1 text-2xl font-extrabold">
                                        Bs {membership.confirmed_savings}
                                    </p>
                                    {membership.has_paid_for_itself ? (
                                        <p className="mt-4 text-sm font-extrabold text-foreground">
                                            🎉 TU JAKAWI YA SE PAGÓ SOLA
                                        </p>
                                    ) : null}
                                </div>
                            ) : null}
                        </Delivery>
                    </section>
                </div>
            </>
        );
    return (
        <>
            <Head title="Tu código de canje" />
            <div>
                <section className="mx-auto w-full max-w-md">
                    <Delivery
                        completed={!expired}
                        heading={
                            expired
                                ? 'ESTE CÓDIGO EXPIRÓ'
                                : 'TU BENEFICIO ESTÁ LISTO'
                        }
                        subject={redemption.benefit_title}
                    >
                        {expired ? (
                            <>
                                <p className="mt-3 leading-6 text-muted-foreground">
                                    El código ya no es válido. Puedes generar
                                    uno nuevo cuando estés en el
                                    establecimiento.
                                </p>
                            </>
                        ) : (
                            <>
                                <p className="font-bold">
                                    {redemption.partner_name}
                                </p>
                                <h3 className="mt-5 font-extrabold">
                                    CÓMO USARLO
                                </h3>
                                <p className="mt-3 text-sm leading-6 text-muted-foreground">
                                    Presenta este beneficio en la sucursal
                                    seleccionada antes de consumir. El
                                    establecimiento debe validar tu código.
                                </p>
                            </>
                        )}{' '}
                        {!expired ? (
                            <>
                                <div className="mt-7 border-y border-border py-5 text-sm">
                                    <p className="font-extrabold">
                                        {redemption.location_name}
                                    </p>
                                    <p className="mt-1">{redemption.address}</p>
                                </div>
                                <div className="mt-6 rounded-[28px] bg-surface p-5">
                                    <div className="flex items-center justify-between rounded-xl bg-reward p-3 text-[var(--ink)]">
                                        <p className="text-sm font-bold">
                                            Válido hasta{' '}
                                            {new Intl.DateTimeFormat('es-BO', {
                                                hour: '2-digit',
                                                minute: '2-digit',
                                                timeZone: 'America/La_Paz',
                                            }).format(
                                                new Date(redemption.expires_at),
                                            )}
                                        </p>
                                        <p className="inline-flex items-center gap-1.5 font-extrabold">
                                            <Clock3 className="size-4" />
                                            {timeLeft(redemption.expires_at)}
                                        </p>
                                    </div>
                                    {qr ? (
                                        <img
                                            src={qr}
                                            className="mx-auto mt-5 aspect-square w-full max-w-[240px]"
                                            alt="QR de canje"
                                        />
                                    ) : (
                                        <div
                                            aria-hidden="true"
                                            className="mx-auto mt-5 aspect-square w-full max-w-[240px] animate-pulse bg-surface-muted"
                                        />
                                    )}
                                    <p className="mt-5 text-center text-sm font-semibold text-muted-foreground">
                                        Código manual
                                    </p>
                                    <p className="mt-2 rounded-2xl bg-surface-muted px-4 py-5 text-center text-4xl font-extrabold tracking-[0.22em]">
                                        {redemption.code}
                                    </p>
                                </div>
                                {redemption.terms && (
                                    <p className="mt-3 whitespace-pre-line">
                                        {redemption.terms}
                                    </p>
                                )}
                            </>
                        ) : null}
                    </Delivery>
                    {expired && redemption.benefit_slug && (
                        <Link
                            className="mt-8 inline-flex min-h-12 w-full items-center justify-center rounded-xl border border-border bg-surface px-4 text-sm font-extrabold"
                            href={`/beneficios/${redemption.benefit_slug}`}
                        >
                            GENERAR NUEVO CÓDIGO
                        </Link>
                    )}
                </section>
            </div>
        </>
    );
}
