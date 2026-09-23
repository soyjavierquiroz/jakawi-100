import { Link } from '@inertiajs/react';
import AdminLayout from '../layout';
export default function ResourceIndex({ title, resource, items = [] }: any) {
    return (
        <AdminLayout title={title}>
            <Link
                className="rounded bg-brand px-4 py-2 text-brand-foreground"
                href={`/admin/${resource}/create`}
            >
                Crear
            </Link>
            <div className="mt-5 divide-y rounded border border-border">
                {items.map((item: any) => (
                    <div className="flex justify-between p-4" key={item.id}>
                        <span>
                            {item.name || item.title}{' '}
                            <small className="text-muted-foreground">
                                {item.status}
                            </small>
                        </span>
                        <Link href={`/admin/${resource}/${item.id}/edit`}>
                            Editar
                        </Link>
                    </div>
                ))}
                {!items.length ? (
                    <p className="p-4 text-muted-foreground">
                        Aún no hay registros.
                    </p>
                ) : null}
            </div>
        </AdminLayout>
    );
}
