import { usePage } from '@inertiajs/react';

export type Partner = { name: string; slug: string };
export function useOperationalAccess() {
    const { partnerAccess } = usePage<{
        partnerAccess?: { can_operate: boolean } | null;
    }>().props;
    return {
        blocked: partnerAccess?.can_operate === false,
    };
}

export function OperationFeedback({
    error,
    success,
}: {
    error?: string | null;
    success?: string | null;
}) {
    const { blocked } = useOperationalAccess();
    return (
        <div className="space-y-3">
            {blocked && (
                <p
                    role="note"
                    className="rounded-md border border-border bg-muted p-4 text-sm"
                >
                    Este establecimiento no está habilitado para operar. Puedes consultar su historial.
                </p>
            )}
            {error && (
                <p
                    role="alert"
                    className="rounded-md border border-destructive/40 bg-destructive/10 p-4 text-sm text-foreground"
                >
                    {error}
                </p>
            )}
            {success && (
                <p
                    role="status"
                    className="rounded-md border border-success/40 bg-success/10 p-4 text-sm text-foreground"
                >
                    {success}
                </p>
            )}
        </div>
    );
}

export function sessionDate(value: string, timezone: string) {
    return new Intl.DateTimeFormat('es-BO', {
        dateStyle: 'medium',
        timeStyle: 'short',
        timeZone: timezone,
    }).format(new Date(value));
}
