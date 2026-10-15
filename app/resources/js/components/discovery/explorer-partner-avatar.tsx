import { useState } from 'react';

export function ExplorerPartnerAvatar({ name, src, srcset = [] }: { name?: string | null; src?: string | null; srcset?: Array<{ src: string; width: number }> }) {
    const [failed, setFailed] = useState(false);
    if (!name?.trim()) return null;

    const initials = name.trim().split(/\s+/).slice(0, 2).map(word => Array.from(word)[0]).join('').toUpperCase();

    return (
        <span className="explorer-partner-avatar" aria-hidden="true">
            {src && !failed ? <img src={src} srcSet={srcset.length ? srcset.map(source => `${source.src} ${source.width}w`).join(', ') : undefined} sizes="(min-width: 640px) 48px, 42px" alt="" className="h-full w-full rounded-full object-contain" loading="lazy" decoding="async" onError={() => setFailed(true)} /> : initials}
        </span>
    );
}
