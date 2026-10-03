import { Link } from '@inertiajs/react';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

export default function AuthSimpleLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    return (
        <div className="flex min-h-svh flex-col items-center justify-center bg-background px-5 py-8 pb-[calc(2rem+var(--pwa-install-clearance,0px))] font-discovery sm:px-6 md:px-10 md:py-10 md:pb-[calc(2.5rem+var(--pwa-install-clearance,0px))]">
            <div className="w-full max-w-[25rem]">
                <div className="flex flex-col gap-8 sm:gap-10">
                    <div className="flex flex-col items-center gap-5 text-center">
                        <Link
                            href={home()}
                            className="flex items-center"
                        >
                            <img
                                src="/jakawi-logo.svg"
                                alt="JAKAWI"
                                className="h-10 w-auto"
                            />
                        </Link>

                        <div className="space-y-3">
                            <h1 className="text-[2rem] leading-[1.02] font-extrabold tracking-[-0.055em] text-foreground uppercase sm:text-[2.25rem]">
                                {title}
                            </h1>
                            <p className="mx-auto max-w-sm text-base leading-6 text-muted-foreground">
                                {description}
                            </p>
                        </div>
                    </div>
                    {children}
                </div>
            </div>
        </div>
    );
}
