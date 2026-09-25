import { Head, Link } from '@inertiajs/react';
import QRCode from 'qrcode';
import { useEffect, useState } from 'react';

type Redemption = {
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

function money(value?: string | null) {
    if (!value) return null;
    return `Bs ${Number(value).toFixed(2)}`;
}

function timeLeft(value: string) { const seconds = Math.max(0, Math.floor((new Date(value).getTime() - Date.now()) / 1000)); return `${String(Math.floor(seconds / 60)).padStart(2, '0')}:${String(seconds % 60).padStart(2, '0')}`; }

export default function RedemptionShow({
    redemption,
}: {
    redemption: Redemption;
}) {
    const confirmed = redemption.status === 'confirmed';
    const expired = redemption.status === 'expired' && !confirmed;
    const savings = money(redemption.savings_amount);
    const [qr, setQr] = useState('');
    const [, tick] = useState(0);
    useEffect(() => { if (redemption.qr_url) QRCode.toDataURL(redemption.qr_url, { width: 360, margin: 1 }).then(setQr); }, [redemption.qr_url]);
    useEffect(() => { const timer = window.setInterval(() => tick(value => value + 1), 1000); return () => window.clearInterval(timer); }, []);

    return (
        <main className="min-h-screen bg-background px-4 py-6 text-foreground">
            <Head title="Tu código de canje" />
            <section className="mx-auto flex w-full max-w-md flex-col gap-5">
                <p className="text-sm font-bold tracking-wide text-brand uppercase">JAKAWI</p><h1 className="text-3xl font-extrabold">{confirmed ? 'CANJE CONFIRMADO' : 'MUESTRA ESTE CÓDIGO'}</h1>
                <div className="rounded-md border border-border bg-surface p-5">
                    {confirmed ? (
                        <><p className="text-lg font-semibold text-success">✓ Valor recibido</p>{savings ? <><p className="mt-5 text-sm font-bold tracking-wide text-success uppercase">Ahorraste</p><p className="text-4xl font-extrabold text-success">{savings}</p></> : null}</>
                    ) : null}
                    {expired ? (
                        <p className="text-lg font-semibold">
                            Este código venció.
                        </p>
                    ) : null}
                    {!confirmed && !expired ? (
                        <p className="text-sm text-muted-foreground">
                            Muéstrale este QR al Partner.
                        </p>
                    ) : null}
                    {qr ? <img src={qr} className="mx-auto mt-4 w-full max-w-[300px]" alt="QR de canje" /> : null}
                    <p className="mt-4 rounded-md bg-background px-4 py-5 text-center text-5xl font-bold tracking-[0.25em]">
                        {redemption.code}
                    </p>
                    <dl className="mt-5 grid gap-3 text-sm">
                        <div>
                            <dt className="font-semibold">Beneficio</dt>
                            <dd>{redemption.benefit_title}</dd>
                        </div>
                        <div>
                            <dt className="font-semibold">Partner</dt>
                            <dd>{redemption.partner_name}</dd>
                        </div>
                        <div>
                            <dt className="font-semibold">Lugar</dt>
                            <dd>{redemption.location_name}</dd>
                        </div>
                        {!confirmed && !expired ? (
                            <div>
                                <dt className="font-semibold">Expira en</dt>
                                <dd>
                                    {timeLeft(redemption.expires_at)}
                                </dd>
                            </div>
                        ) : null}
                        {confirmed ? (
                            <div>
                                <dt className="font-semibold">Confirmado</dt>
                                <dd>
                                    {redemption.confirmed_at
                                        ? new Date(
                                              redemption.confirmed_at,
                                          ).toLocaleString('es-BO')
                                        : ''}
                                </dd>
                            </div>
                        ) : null}
                        {savings ? (
                            <div>
                                <dt className="font-semibold">
                                    Ahorro estimado
                                </dt>
                                <dd>{savings}</dd>
                            </div>
                        ) : null}
                    </dl>
                    {!confirmed && !expired ? (
                        <p className="mt-5 text-sm text-muted-foreground">
                            También puedes usar el código: {redemption.code}
                        </p>
                    ) : null}
                </div>
                <Link
                    className="inline-flex min-h-11 items-center justify-center rounded-md bg-brand px-4 text-sm font-semibold text-brand-foreground"
                    href={
                        redemption.benefit_slug
                            ? `/beneficios/${redemption.benefit_slug}`
                            : '/beneficios'
                    }
                >
                    Regresar al beneficio
                </Link>
            </section>
        </main>
    );
}
