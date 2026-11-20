import { usePage } from '@inertiajs/react';
import { type Appearance, useAppearance } from '@/hooks/use-appearance';

export default function ThemeToggle() {
    const { auth } = usePage().props;
    const { appearance, updateAppearance } = useAppearance(Boolean(auth?.user));
    return (
        <select
            aria-label="Apariencia"
            value={appearance}
            onChange={(event) => updateAppearance(event.target.value as Appearance)}
            className="min-h-11 max-w-full rounded-xl border border-input bg-surface px-2 text-sm font-semibold text-foreground focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
        >
            <option value="light">Claro</option>
            <option value="dark">Oscuro</option>
            <option value="system">Sistema</option>
        </select>
    );
}
