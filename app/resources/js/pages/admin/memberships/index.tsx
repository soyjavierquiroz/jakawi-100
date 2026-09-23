import { Head, router, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import AdminLayout from '../layout';

type Membership = {
    id: number;
    status: string;
    starts_at: string | null;
    ends_at: string | null;
    amount_paid: string | null;
    is_active: boolean;
};

type UserRow = {
    id: number;
    name: string;
    email: string;
    membership: Membership | null;
};

type PaginatedUsers = {
    data: UserRow[];
};

type MembershipConfig = {
    price_bob: number;
    duration_days: number;
};

type Flash = {
    success?: string;
    error?: string;
};

function formatDate(value?: string | null) {
    if (!value) {
        return 'Sin fecha';
    }

    return new Intl.DateTimeFormat('es-BO', {
        dateStyle: 'medium',
    }).format(new Date(value));
}

function ActivateForm({
    user,
    membershipConfig,
    paymentMethods,
}: {
    user: UserRow;
    membershipConfig: MembershipConfig;
    paymentMethods: string[];
}) {
    const { data, setData, post, processing, errors, reset } = useForm({
        user_id: user.id,
        amount_paid: String(membershipConfig.price_bob),
        payment_method: 'cash',
        payment_reference: '',
        notes: '',
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        post('/admin/memberships', {
            preserveScroll: true,
            onSuccess: () => reset('payment_reference', 'notes'),
        });
    }

    if (user.membership?.is_active) {
        return null;
    }

    return (
        <form
            onSubmit={submit}
            className="grid gap-3 border-t border-border pt-4 sm:grid-cols-2"
        >
            <label className="grid gap-1 text-sm">
                <span className="font-medium">Amount paid</span>
                <input
                    className="min-h-10 rounded-md border border-border bg-background px-3"
                    type="number"
                    min="0"
                    step="0.01"
                    value={data.amount_paid}
                    onChange={(event) =>
                        setData('amount_paid', event.target.value)
                    }
                />
                {errors.amount_paid ? (
                    <span className="text-xs text-destructive">
                        {errors.amount_paid}
                    </span>
                ) : null}
            </label>
            <label className="grid gap-1 text-sm">
                <span className="font-medium">Payment method</span>
                <select
                    className="min-h-10 rounded-md border border-border bg-background px-3"
                    value={data.payment_method}
                    onChange={(event) =>
                        setData('payment_method', event.target.value)
                    }
                >
                    {paymentMethods.map((method) => (
                        <option key={method} value={method}>
                            {method}
                        </option>
                    ))}
                </select>
            </label>
            <label className="grid gap-1 text-sm sm:col-span-2">
                <span className="font-medium">Reference</span>
                <input
                    className="min-h-10 rounded-md border border-border bg-background px-3"
                    value={data.payment_reference}
                    onChange={(event) =>
                        setData('payment_reference', event.target.value)
                    }
                />
            </label>
            <label className="grid gap-1 text-sm sm:col-span-2">
                <span className="font-medium">Notes</span>
                <textarea
                    className="min-h-20 rounded-md border border-border bg-background px-3 py-2"
                    value={data.notes}
                    onChange={(event) => setData('notes', event.target.value)}
                />
            </label>
            <button
                className="inline-flex min-h-10 items-center justify-center rounded-md bg-brand px-3 text-sm font-semibold text-brand-foreground disabled:opacity-60"
                disabled={processing}
                type="submit"
            >
                Activar {membershipConfig.duration_days} dias
            </button>
        </form>
    );
}

export default function AdminMembershipsIndex({
    users,
    filters,
    membershipConfig,
    paymentMethods,
}: {
    users: PaginatedUsers;
    filters: { search: string };
    membershipConfig: MembershipConfig;
    paymentMethods: string[];
}) {
    const { flash } = usePage().props as { flash?: Flash };

    function cancelMembership(id: number) {
        router.patch(
            `/admin/memberships/${id}/cancel`,
            {},
            { preserveScroll: true },
        );
    }

    return (
        <AdminLayout title="Membresias">
            <Head title="Membresias" />
            <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h1 className="text-3xl font-semibold">Membresias</h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Bs{membershipConfig.price_bob} /{' '}
                        {membershipConfig.duration_days} dias
                    </p>
                </div>
                <form className="flex gap-2" method="get">
                    <input
                        className="min-h-10 rounded-md border border-border bg-background px-3 text-sm"
                        defaultValue={filters.search}
                        name="search"
                        placeholder="Buscar name/email"
                    />
                    <button
                        className="rounded-md border border-border px-3 text-sm font-medium"
                        type="submit"
                    >
                        Buscar
                    </button>
                </form>
            </div>

            {flash?.success ? (
                <div className="rounded-md border border-border bg-surface p-3 text-sm font-medium text-success">
                    {flash.success}
                </div>
            ) : null}
            {flash?.error ? (
                <div className="rounded-md border border-border bg-surface p-3 text-sm font-medium text-destructive">
                    {flash.error}
                </div>
            ) : null}

            <div className="grid gap-4">
                {users.data.map((user) => (
                    <div
                        key={user.id}
                        className="rounded-md border border-border bg-surface p-4"
                    >
                        <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <p className="font-semibold">{user.name}</p>
                                <p className="text-sm text-muted-foreground">
                                    {user.email}
                                </p>
                            </div>
                            <div className="flex flex-wrap items-center gap-2">
                                <span className="rounded-md border border-border px-2 py-1 text-xs font-medium text-muted-foreground">
                                    {user.membership?.is_active
                                        ? 'active'
                                        : (user.membership?.status ??
                                          'inactive')}
                                </span>
                                {user.membership?.is_active ? (
                                    <button
                                        className="min-h-9 rounded-md border border-border px-3 text-sm font-medium hover:bg-muted"
                                        type="button"
                                        onClick={() =>
                                            cancelMembership(
                                                user.membership!.id,
                                            )
                                        }
                                    >
                                        Cancelar
                                    </button>
                                ) : null}
                            </div>
                        </div>

                        <dl className="mt-4 grid gap-3 text-sm sm:grid-cols-3">
                            <div>
                                <dt className="text-xs font-semibold text-muted-foreground uppercase">
                                    Inicio
                                </dt>
                                <dd className="mt-1">
                                    {formatDate(user.membership?.starts_at)}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-xs font-semibold text-muted-foreground uppercase">
                                    Vencimiento
                                </dt>
                                <dd className="mt-1">
                                    {formatDate(user.membership?.ends_at)}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-xs font-semibold text-muted-foreground uppercase">
                                    Amount paid
                                </dt>
                                <dd className="mt-1">
                                    {user.membership?.amount_paid
                                        ? `Bs${user.membership.amount_paid}`
                                        : 'Sin pago'}
                                </dd>
                            </div>
                        </dl>

                        <ActivateForm
                            user={user}
                            membershipConfig={membershipConfig}
                            paymentMethods={paymentMethods}
                        />
                    </div>
                ))}
            </div>
        </AdminLayout>
    );
}
