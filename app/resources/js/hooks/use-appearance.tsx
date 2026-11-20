import { useSyncExternalStore } from 'react';
import { toast } from 'sonner';

export type ResolvedAppearance = 'light' | 'dark';
export type Appearance = ResolvedAppearance | 'system';
export type UseAppearanceReturn = {
    readonly appearance: Appearance;
    readonly resolvedAppearance: ResolvedAppearance;
    readonly updateAppearance: (mode: Appearance) => void;
};
const listeners = new Set<() => void>();
let currentAppearance: Appearance = 'system';
let initialized = false;
let persistence = Promise.resolve();
const valid = (value: unknown): value is Appearance => ['light', 'dark', 'system'].includes(value as string);
const prefersDark = () => typeof window !== 'undefined' && window.matchMedia('(prefers-color-scheme: dark)').matches;
const resolved = (): ResolvedAppearance => currentAppearance === 'dark' || (currentAppearance === 'system' && prefersDark()) ? 'dark' : 'light';
const storageKey = () => document.documentElement.dataset.appearanceUser ? `appearance:${document.documentElement.dataset.appearanceUser}` : 'appearance';
const notify = () => listeners.forEach((listener) => listener());
const subscribe = (callback: () => void) => { listeners.add(callback); return () => { listeners.delete(callback); }; };
const apply = () => {
    const root = document.documentElement;
    root.classList.toggle('dark', resolved() === 'dark');
    root.style.colorScheme = resolved();
    root.dataset.appearance = currentAppearance;
    notify();
    document.dispatchEvent(new Event('appearancechange'));
};
const remember = () => {
    try { window.localStorage.setItem(storageKey(), currentAppearance); } catch { /* Cookie remains available when browser storage is restricted. */ }
    // Host-only cookie: COM never sets a Domain attribute or shares with CLUB.
    document.cookie = `appearance=${currentAppearance};path=/;max-age=31536000;SameSite=Lax${location.protocol === 'https:' ? ';Secure' : ''}`;
};

export function initializeTheme(): void {
    if (typeof window === 'undefined' || initialized) return;
    initialized = true;
    const bootstrap = document.documentElement.dataset.appearance;
    currentAppearance = valid(bootstrap) ? bootstrap : 'system';
    apply();
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', apply);
    window.addEventListener('storage', (event) => {
        if (event.key !== storageKey()) return;
        currentAppearance = valid(event.newValue) ? event.newValue : 'system';
        document.cookie = `appearance=${currentAppearance};path=/;max-age=31536000;SameSite=Lax${location.protocol === 'https:' ? ';Secure' : ''}`;
        apply();
    });
}

// Inertia login/logout does not reload the document. Account state must follow it.
export function synchronizeAppearanceAccount(user: { id: number; theme_preference?: string } | null): void {
    const root = document.documentElement;
    const id = user ? String(user.id) : '';
    if ((root.dataset.appearanceUser ?? '') === id) return;
    root.dataset.appearanceUser = id;
    root.dataset.appearanceAuthenticated = user ? 'true' : 'false';
    let stored: unknown;
    try { stored = window.localStorage.getItem('appearance'); } catch { /* Use system fallback. */ }
    currentAppearance = user && valid(user.theme_preference) ? user.theme_preference : valid(stored) ? stored : 'system';
    apply();
}

export function useAppearance(persistForAuthenticatedUser = false): UseAppearanceReturn {
    const appearance = useSyncExternalStore(subscribe, () => currentAppearance, () => 'system' as Appearance);
    const resolvedAppearance = useSyncExternalStore(subscribe, resolved, () => 'light' as ResolvedAppearance);
    return { appearance, resolvedAppearance, updateAppearance: (mode) => updateAppearance(mode, persistForAuthenticatedUser) };
}

export const updateAppearance = (mode: Appearance, persistForAuthenticatedUser = false): void => {
        if (!valid(mode)) return;
        currentAppearance = mode;
        remember();
        apply();
        if (persistForAuthenticatedUser) {
            // Serialize quick changes so the final account preference matches the UI.
            persistence = persistence.then(async () => {
                const token = document.cookie.split('; ').find((cookie) => cookie.startsWith('XSRF-TOKEN='))?.slice('XSRF-TOKEN='.length);
                const response = await fetch('/settings/appearance', {
                    method: 'PATCH', credentials: 'same-origin',
                    headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', ...(token ? { 'X-XSRF-TOKEN': decodeURIComponent(token) } : {}) },
                    body: JSON.stringify({ theme_preference: mode }),
                });
                if (!response.ok) throw new Error('Appearance persistence failed');
            }).catch(() => { const message = 'La apariencia se aplicó en este navegador. No se pudo guardar en tu cuenta.'; toast.error(message); document.querySelectorAll('[data-appearance-status]').forEach((node) => { node.textContent = message; }); });
        }
    };
