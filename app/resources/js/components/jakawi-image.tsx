import { useState } from 'react';

type Source = { src: string; width: number };

export function JakawiImage({ src, srcset = [], sizes, alt, className = '', loading = 'lazy', priority = false, fallbackClassName = '' }: {
    src?: string | null; srcset?: Source[]; sizes?: string; alt: string; className?: string; loading?: 'lazy' | 'eager'; priority?: boolean; fallbackClassName?: string;
}) {
    const [failed, setFailed] = useState(false);
    if (!src || failed) return <div role="img" aria-label={alt} className={`flex h-full w-full items-end bg-brand p-5 text-lg font-bold text-brand-foreground ${fallbackClassName}`}>JAKAWI</div>;
    return <img src={src} srcSet={srcset.length ? srcset.map(source => `${source.src} ${source.width}w`).join(', ') : undefined} sizes={sizes} alt={alt} className={className} loading={loading} decoding="async" fetchPriority={priority ? 'high' : 'auto'} onError={() => setFailed(true)} />;
}
