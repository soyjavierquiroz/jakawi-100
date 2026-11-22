import { Link } from '@inertiajs/react';
import type { ReactNode, Ref } from 'react';

export function ExplorerCard({
    href,
    image,
    children,
    cardRef,
    onOpen,
}: {
    href: string;
    image: ReactNode;
    children: ReactNode;
    cardRef?: Ref<HTMLAnchorElement>;
    onOpen?: () => void;
}) {
    return (
        <Link
            ref={cardRef}
            href={href}
            onClick={onOpen}
            className="explorer-card group grid min-h-40 grid-cols-[42%_1fr] overflow-hidden rounded-[var(--radius-card)] bg-surface-elevated shadow-editorial transition duration-200 hover:-translate-y-0.5 hover:shadow-featured focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand active:scale-[.99] sm:block"
        >
            <div className="overflow-hidden bg-surface-muted sm:aspect-[5/4]">
                {image}
            </div>
            {children}
        </Link>
    );
}
