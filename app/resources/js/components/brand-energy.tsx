export function BrandEnergy({ className = '' }: { className?: string }) {
    return (
        <span
            aria-hidden="true"
            className={`pointer-events-none inline-flex items-center gap-1.5 ${className}`}
        >
            <span className="h-1 w-9 -rotate-3 rounded-full bg-action" />
            <span className="relative size-2">
                <span className="absolute inset-0 rotate-45 rounded-sm bg-brand" />
            </span>
        </span>
    );
}
