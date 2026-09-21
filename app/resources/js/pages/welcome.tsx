import { Head, Link, usePage } from '@inertiajs/react';
import { dashboard, login } from '@/routes';
/* @chisel-registration */
import { register } from '@/routes';
/* @end-chisel-registration */

export default function Welcome() {
    const { auth } = usePage().props;

    return (
        <>
            <Head title="JAKAWI" />
            <main className="flex min-h-screen items-center bg-background px-6 py-10 text-foreground">
                <section className="mx-auto flex w-full max-w-4xl flex-col gap-10">
                    <div className="space-y-5">
                        <p className="text-sm font-semibold tracking-[0.28em] text-muted-foreground">
                            JAKAWI
                        </p>
                        <div className="space-y-3">
                            <h1 className="text-5xl font-semibold leading-none sm:text-7xl">
                                Vive más.
                                <br />
                                Gasta menos.
                            </h1>
                            <p className="max-w-md text-lg text-muted-foreground">
                                Tu ciudad tiene más para ti.
                            </p>
                        </div>
                    </div>

                    <nav className="flex flex-col gap-3 sm:flex-row">
                        {auth.user ? (
                            <Link
                                href={dashboard()}
                                className="inline-flex min-h-12 items-center justify-center rounded-md bg-brand px-6 text-sm font-semibold text-brand-foreground transition hover:bg-brand/90"
                            >
                                Mi JAKAWI
                            </Link>
                        ) : (
                            <>
                                {/* @chisel-registration */}
                                <Link
                                    href={register()}
                                    className="inline-flex min-h-12 items-center justify-center rounded-md bg-brand px-6 text-sm font-semibold text-brand-foreground transition hover:bg-brand/90"
                                >
                                    Crear cuenta
                                </Link>
                                {/* @end-chisel-registration */}
                                <Link
                                    href={login()}
                                    className="inline-flex min-h-12 items-center justify-center rounded-md border border-border bg-surface/70 px-6 text-sm font-semibold text-foreground transition hover:bg-surface"
                                >
                                    Entrar
                                </Link>
                            </>
                        )}
                    </nav>
                </section>
            </main>
        </>
    );
}
