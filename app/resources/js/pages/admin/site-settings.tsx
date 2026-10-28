import { useForm } from '@inertiajs/react';
import AdminLayout from './layout';

export default function SiteSettings({ showDemoBanner }: { showDemoBanner: boolean }) {
    const form = useForm({ show_demo_banner: showDemoBanner });
    return <AdminLayout title="Ajustes">
        <form className="max-w-xl rounded-[var(--radius-card)] border border-border bg-surface p-5" onSubmit={event => { event.preventDefault(); form.put('/admin/settings', { preserveScroll: true }); }}>
            <label className="flex min-h-11 cursor-pointer items-center justify-between gap-5 font-semibold">
                Mostrar franja superior de DEMO
                <input type="checkbox" role="switch" checked={form.data.show_demo_banner} onChange={event => form.setData('show_demo_banner', event.target.checked)} className="size-6 shrink-0 accent-brand focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-brand" />
            </label>
            <p className="mt-3 text-sm text-muted-foreground">Controla la franja informativa en todo el sitio. El modo DEMO, las restricciones comerciales y la política de publicación se mantienen.</p>
            {form.errors.show_demo_banner && <p role="alert" className="mt-3 text-sm text-destructive">{form.errors.show_demo_banner}</p>}
            <button disabled={form.processing} className="mt-5 min-h-11 rounded-[var(--radius-control)] bg-brand px-5 font-semibold text-brand-foreground focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-brand disabled:opacity-50">Guardar ajustes</button>
            {form.recentlySuccessful && <p role="status" className="mt-3 text-sm">Ajustes guardados.</p>}
        </form>
    </AdminLayout>;
}
