import type { ReactNode } from 'react';

export type Partner = { name: string; slug: string };
export const primaryAction =
    'inline-flex min-h-11 items-center justify-center rounded-md bg-brand px-4 py-3 font-medium text-brand-foreground disabled:opacity-50';
export const secondaryAction =
    'inline-flex min-h-11 items-center justify-center rounded-md border border-border px-4 py-3 text-sm font-medium disabled:opacity-50';
export const unlockStates: Record<string, string> = {
    DRAFT: 'Borrador',
    PENDING_REVIEW: 'En revisión',
    APPROVED: 'Aprobado',
    REJECTED: 'Rechazado',
    SCHEDULED: 'Programado',
    ACTIVE: 'Activo',
    GOAL_REACHED: 'Meta alcanzada',
    UNLOCKED: 'Desbloqueado',
    FULFILLMENT_ACTIVE: 'En cumplimiento',
    COMPLETED: 'Completado',
    GOAL_NOT_REACHED: 'Meta no alcanzada',
    CANCELLED: 'Cancelado',
};
export const reviewStates: Record<string, string> = {
    DRAFT: 'Borrador',
    SUBMITTED: 'En revisión',
    CHANGES_REQUESTED: 'Cambios solicitados',
    APPROVED: 'Aprobado',
    REJECTED: 'Rechazado',
};
export const challengeStates: Record<string, string> = {
    draft: 'Borrador operativo',
    open: 'Abierto o programado',
    closed: 'Cerrado',
    cancelled: 'Cancelado',
};
export function Field({
    name,
    label,
    help,
    error,
    value,
    onChange,
    options,
    multiline = false,
    type = 'text',
    required = false,
    min,
    maxLength,
}: {
    name: string;
    label: string;
    help?: string;
    error?: string;
    value: string | number;
    onChange: (value: string) => void;
    options?: [string, string][];
    multiline?: boolean;
    type?: string;
    required?: boolean;
    min?: number;
    maxLength?: number;
}) {
    const cls =
        'w-full min-w-0 rounded-md border border-border bg-background p-3 text-foreground disabled:opacity-70';
    const described =
        [help && `${name}-help`, error && `${name}-error`]
            .filter(Boolean)
            .join(' ') || undefined;
    const common = {
        id: name,
        name,
        value,
        required,
        'aria-describedby': described,
        'aria-invalid': !!error,
        className: cls,
        onChange: (
            e: React.ChangeEvent<
                HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement
            >,
        ) => onChange(e.target.value),
    };
    return (
        <div className="min-w-0 space-y-2">
            <label className="block text-sm font-medium" htmlFor={name}>
                {label}
                {required && ' *'}
            </label>
            {options ? (
                <select {...common}>
                    {options.map(([key, text]) => (
                        <option key={key} value={key}>
                            {text}
                        </option>
                    ))}
                </select>
            ) : multiline ? (
                <textarea {...common} rows={4} maxLength={maxLength} />
            ) : (
                <input
                    {...common}
                    type={type}
                    min={min}
                    maxLength={maxLength}
                />
            )}
            {help && (
                <p
                    id={`${name}-help`}
                    className="text-sm text-muted-foreground"
                >
                    {help}
                </p>
            )}
            {error && (
                <p
                    id={`${name}-error`}
                    role="alert"
                    className="text-sm font-medium text-destructive"
                >
                    {error}
                </p>
            )}
        </div>
    );
}
export function ProposalSection({
    title,
    description,
    children,
}: {
    title: string;
    description?: string;
    children: ReactNode;
}) {
    return (
        <section className="space-y-4 rounded-md border border-border bg-surface p-4 sm:p-6">
            <h2 className="text-xl font-semibold">{title}</h2>
            {description && (
                <p className="text-sm text-muted-foreground">{description}</p>
            )}
            <div className="grid min-w-0 gap-5 sm:grid-cols-2">{children}</div>
        </section>
    );
}
export function EditorialState({
    label,
    editable,
    notes,
}: {
    label: string;
    editable: boolean;
    notes?: string | null;
}) {
    return (
        <div className="space-y-3">
            <p className="inline-flex rounded-md bg-muted px-3 py-2 text-sm font-semibold">
                {label}
            </p>
            {!editable && (
                <p className="text-sm text-muted-foreground">
                    Esta propuesta se consulta sin edición desde su estado
                    actual. JAKAWI gestiona la revisión y cualquier activación.
                </p>
            )}
            {notes && (
                <aside
                    role="note"
                    className="rounded-md border border-brand/40 bg-muted p-4"
                >
                    <p className="font-semibold">Observaciones de JAKAWI</p>
                    <p className="mt-2 text-sm break-words whitespace-pre-wrap">
                        {notes}
                    </p>
                </aside>
            )}
        </div>
    );
}
