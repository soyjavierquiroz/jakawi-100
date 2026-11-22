export type PersonalSection = 'home' | 'explore' | 'account' | 'profile';

/** Segment boundaries keep similarly named staff and unrelated routes inactive. */
export function personalSection(url: string): PersonalSection | null {
    const path = url.split(/[?#]/, 1)[0].replace(/\/+$/, '') || '/';
    const within = (root: string) => path === root || path.startsWith(`${root}/`);

    // The old personal editor lives below /mi-jakawi and redirects to /perfil.
    if (within('/perfil') || within('/mi-jakawi/perfil') || path === '/settings/profile') return 'profile';
    if (within('/mi-jakawi')) return 'account';
    if (path === '/explorar' || ['/beneficios', '/experiencias', '/lugares', '/productos-digitales'].some(within) || /^\/p\/[^/]+$/.test(path)) return 'explore';
    return path === '/' ? 'home' : null;
}

export function personalLinkClass(active: boolean): string {
    return [
        'transition-colors hover:text-foreground focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring',
        active
            ? 'font-semibold text-foreground underline decoration-brand decoration-2 underline-offset-8'
            : 'font-medium text-muted-foreground',
    ].join(' ');
}
