import { Monitor, Moon, Sun } from 'lucide-react';
import { useAppearance } from '@/hooks/use-appearance';
import {
    DropdownMenu,
    DropdownMenuTrigger,
    DropdownMenuContent,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
} from '@/components/ui/dropdown-menu';

const modes = [
    { value: 'light', label: 'Claro', icon: Sun },
    { value: 'dark', label: 'Oscuro', icon: Moon },
    { value: 'system', label: 'Sistema', icon: Monitor },
] as const;

// Shared by Inertia headers and Blade. The appearance store remains the only authority.
export function AppearanceMenu({
    authenticated = false,
}: {
    authenticated?: boolean;
}) {
    const { appearance, updateAppearance } = useAppearance(authenticated);
    const active = modes.find((mode) => mode.value === appearance) ?? modes[2];
    const Icon = active.icon;
    // Keep icon dimensions independent of legacy Admin control padding.
    return (
        <DropdownMenu modal={false}>
            <DropdownMenuTrigger asChild>
                <button
                    type="button"
                    style={{ padding: 0, borderRadius: '50%' }}
                    aria-label={`Apariencia: ${active.label}`}
                    title={`Apariencia: ${active.label}`}
                    className="inline-flex size-11 shrink-0 items-center justify-center rounded-full text-muted-foreground transition hover:bg-surface-muted hover:text-foreground focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                >
                    <Icon className="size-5 shrink-0" aria-hidden="true" />
                </button>
            </DropdownMenuTrigger>
            <DropdownMenuContent
                align="end"
                sideOffset={6}
                className="w-40 rounded-xl border-border bg-surface-elevated p-1.5 text-foreground shadow-editorial"
            >
                <DropdownMenuRadioGroup
                    aria-label="Apariencia"
                    value={appearance}
                    onValueChange={(value) => {
                        const mode = modes.find((mode) => mode.value === value);
                        if (mode) updateAppearance(mode.value);
                    }}
                >
                    {modes.map(({ value, label, icon: ModeIcon }) => (
                        <DropdownMenuRadioItem
                            key={value}
                            value={value}
                            className="min-h-11 gap-3 rounded-lg text-sm font-medium focus:bg-surface-muted focus:text-foreground data-[state=checked]:bg-brand-subtle data-[state=checked]:font-semibold"
                        >
                            <ModeIcon className="size-4" aria-hidden="true" />
                            {label}
                        </DropdownMenuRadioItem>
                    ))}
                </DropdownMenuRadioGroup>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
