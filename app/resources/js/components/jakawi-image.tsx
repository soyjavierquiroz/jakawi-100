import { useState } from 'react';

type Source = { src: string; width: number };

export function JakawiImage({ src, srcset = [], sizes, alt, className = '', loading = 'lazy', priority = false, fallbackClassName = '', fallbackContent = true }: {
    src?: string | null; srcset?: Source[]; sizes?: string; alt: string; className?: string; loading?: 'lazy' | 'eager'; priority?: boolean; fallbackClassName?: string; fallbackContent?: boolean;
}) {
    const [failed, setFailed] = useState(false);
    if (!src || failed) return <div role="img" aria-label={alt} className={`relative flex h-full w-full overflow-hidden border border-border bg-surface-muted p-4 text-foreground ${fallbackClassName}`}><div aria-hidden="true" className="absolute -right-7 -top-9 size-32 rounded-full border-[14px] border-brand/25" /><div aria-hidden="true" className="absolute -bottom-11 left-7 size-28 rounded-full bg-action/35" />{fallbackContent ? <span aria-hidden="true" className="relative mt-auto grid size-9 place-items-center rounded-full border-2 border-foreground/20 bg-surface text-sm font-extrabold">{alt.trim().slice(0, 1).toUpperCase()}</span> : null}</div>;
    return <img src={src} srcSet={srcset.length ? srcset.map(source => `${source.src} ${source.width}w`).join(', ') : undefined} sizes={sizes} alt={alt} className={className} loading={loading} decoding="async" fetchPriority={priority ? 'high' : 'auto'} onError={() => setFailed(true)} />;
}
