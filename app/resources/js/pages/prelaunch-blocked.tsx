import { Head, Link } from '@inertiajs/react';

export default function PrelaunchBlocked({ message }: { message: string }) {
    return <main className="mx-auto max-w-xl space-y-5 px-5 py-12">
        <Head title="Operación no disponible" />
        <h1 className="text-2xl font-semibold">Operación no disponible en prelanzamiento</h1>
        <p role="alert">{message}</p>
        <Link className="inline-block rounded border px-4 py-3" href="/">Volver al inicio</Link>
    </main>;
}
