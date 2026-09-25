import { usePage } from '@inertiajs/react';
import { Moon, Sun } from 'lucide-react';
import { useAppearance } from '@/hooks/use-appearance';

export default function ThemeToggle() {
    const { auth } = usePage().props;
    const { resolvedAppearance, updateAppearance } = useAppearance(Boolean(auth.user));
    const isDark = resolvedAppearance === 'dark';
    const nextAppearance = isDark ? 'light' : 'dark';

    return (
        <button
            type="button"
            onClick={() => updateAppearance(nextAppearance)}
            className="inline-flex size-11 items-center justify-center rounded-full text-foreground transition-colors hover:bg-surface-muted active:bg-surface-elevated focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand motion-safe:transition-transform motion-safe:active:scale-95"
            aria-label={isDark ? 'Cambiar a modo claro' : 'Cambiar a modo oscuro'}
        >
            {isDark ? (
                <Sun className="size-5 motion-safe:transition-transform motion-safe:duration-200" aria-hidden="true" />
            ) : (
                <Moon className="size-5 motion-safe:transition-transform motion-safe:duration-200" aria-hidden="true" />
            )}
        </button>
    );
}
