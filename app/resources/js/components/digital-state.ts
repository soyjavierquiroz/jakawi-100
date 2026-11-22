export const digitalState = (state: string) =>
    ({
        pending: 'Pendiente',
        confirmed: 'Confirmado',
        expired: 'Vencido',
        refunded: 'Reembolsado',
        under_review: 'Pendiente de verificación',
        rejected: 'Rechazado',
        active: 'Habilitado',
        revoked: 'Revocado',
        ready: 'PDF disponible',
        failed: 'Entrega pendiente de corrección',
    })[state] ?? state;
