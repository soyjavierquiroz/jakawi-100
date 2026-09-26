import type { ReactNode } from 'react';
import { AlertCircle, Inbox, LoaderCircle } from 'lucide-react';

type StatePanelProps = { title: string; description?: string; action?: ReactNode; className?: string };

export function EmptyState({ title, description, action, className = '' }: StatePanelProps) {
    return <section className={`rounded-2xl border border-border bg-surface p-6 text-center ${className}`}><Inbox aria-hidden="true" className="mx-auto size-7 text-brand" /><h2 className="mt-3 text-xl font-extrabold">{title}</h2>{description ? <p className="mx-auto mt-2 max-w-sm text-sm leading-6 text-muted-foreground">{description}</p> : null}{action ? <div className="mt-5">{action}</div> : null}</section>;
}

export function ErrorState({ title = 'NO PUDIMOS CONECTAR', description = 'Intenta nuevamente.', action, className = '' }: StatePanelProps) {
    return <section role="alert" className={`rounded-2xl border border-danger bg-surface p-6 text-center ${className}`}><AlertCircle aria-hidden="true" className="mx-auto size-7 text-danger" /><h2 className="mt-3 text-xl font-extrabold">{title}</h2><p className="mx-auto mt-2 max-w-sm text-sm leading-6 text-muted-foreground">{description}</p>{action ? <div className="mt-5">{action}</div> : null}</section>;
}

export function LoadingSkeleton({ className = 'h-24' }: { className?: string }) {
    return <div aria-hidden="true" className={`animate-pulse rounded-2xl bg-surface-muted ${className}`}><span className="sr-only">Cargando…</span></div>;
}

export function StatusBanner({ title, description, tone = 'neutral', className = '' }: { title: string; description?: string; tone?: 'neutral' | 'danger' | 'success'; className?: string }) {
    const tones = { neutral: 'border-border bg-surface-muted', danger: 'border-danger bg-surface', success: 'border-success bg-success-surface' };
    return <section role="status" className={`rounded-2xl border p-5 ${tones[tone]} ${className}`}><div className="flex gap-3"><LoaderCircle aria-hidden="true" className={`mt-0.5 size-5 shrink-0 ${tone === 'danger' ? 'text-danger' : tone === 'success' ? 'text-success' : 'text-brand'}`} /><div><h2 className="font-extrabold">{title}</h2>{description ? <p className="mt-1 text-sm leading-6 text-muted-foreground">{description}</p> : null}</div></div></section>;
}
