import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';

export default function AdminLayout({ children }: { children: ReactNode }) {
    return (
        <main className="min-h-screen bg-background px-4 py-6 text-foreground sm:px-6 sm:py-10">
            <section className="mx-auto flex w-full max-w-5xl flex-col gap-6">
                <nav className="flex flex-wrap gap-2">
                    <Link className="rounded-md px-3 py-2 text-sm font-medium hover:bg-muted" href="/admin">
                        Admin
                    </Link>
                    <Link className="rounded-md px-3 py-2 text-sm font-medium hover:bg-muted" href="/admin/merchants">
                        Comercios
                    </Link>
                    <Link className="rounded-md px-3 py-2 text-sm font-medium hover:bg-muted" href="/admin/benefits">
                        Beneficios
                    </Link>
                    <Link className="rounded-md px-3 py-2 text-sm font-medium hover:bg-muted" href="/admin/memberships">
                        Membresias
                    </Link>
                    <Link className="rounded-md px-3 py-2 text-sm font-medium hover:bg-muted" href="/">
                        Home
                    </Link>
                </nav>
                {children}
            </section>
        </main>
    );
}
