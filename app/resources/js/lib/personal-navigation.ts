export type PersonalSection = 'home' | 'explore' | 'account' | 'profile';

/** Segment boundaries keep similarly named staff and unrelated routes inactive. */
export function personalSection(url: string): PersonalSection | null {
    const path = url.split(/[?#]/, 1)[0].replace(/\/+$/, '') || '/';
    const within = (root: string) => path === root || path.startsWith(`${root}/`);

    // The old personal editor lives below /mi-jakawi and redirects to /perfil.
    if (within('/perfil') || within('/mi-jakawi/perfil') || path === '/settings/profile') return 'profile';
    if (within('/mi-jakawi')) return 'account';
    if (path === '/explorar' || ['/beneficios', '/experiencias', '/lugares'].some(within) || /^\/p\/[^/]+$/.test(path)) return 'explore';
    return path === '/' ? 'home' : null;
}

export function personalLinkClass(active: boolean): string {
    return [
        'transition-colors hover:text-[#111111] dark:hover:text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#ff5a4f]',
        active
            ? 'font-semibold text-[#111111] dark:text-white underline decoration-[#ff5a4f] decoration-2 underline-offset-8'
            : 'font-medium text-muted-foreground',
    ].join(' ');
}
