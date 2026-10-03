import { Link } from '@inertiajs/react';

export function PublicFooter() {
    return (
        <footer className="border-t border-border/70 bg-surface">
            <div className="mx-auto flex w-full max-w-6xl flex-col gap-6 px-4 py-8 pb-[calc(7rem+env(safe-area-inset-bottom))] sm:px-6 lg:flex-row lg:items-end lg:justify-between lg:px-8 lg:py-9">
                <div className="max-w-sm">
                    <Link
                        href="/"
                        aria-label="Ir al inicio de JAKAWI"
                        className="inline-flex focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-brand"
                    >
                        <img
                            src="/jakawi-wordmark.svg"
                            alt=""
                            className="h-8 w-auto"
                        />
                    </Link>
                    <p className="mt-3 text-sm leading-6 text-muted-foreground">
                        La app para vivir más tu ciudad.
                    </p>
                </div>
                <nav
                    aria-label="Enlaces de JAKAWI"
                    className="flex flex-wrap gap-x-5 gap-y-3 text-sm font-semibold text-muted-foreground"
                >
                    <Link
                        href="/explorar"
                        className="transition hover:text-foreground focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-brand"
                    >
                        Explorar
                    </Link>
                    <Link
                        href="/mi-jakawi"
                        className="transition hover:text-foreground focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-brand"
                    >
                        Mi JAKAWI
                    </Link>
                    <Link
                        href="/perfil"
                        className="transition hover:text-foreground focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-brand"
                    >
                        Perfil
                    </Link>
                </nav>
            </div>
        </footer>
    );
}
