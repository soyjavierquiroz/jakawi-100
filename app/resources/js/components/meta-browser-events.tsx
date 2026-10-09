import { router, usePage } from '@inertiajs/react';
import { useLayoutEffect } from 'react';
import { metaBrowserTracker, type MetaBrowserContext } from '@/lib/meta-browser-tracker';

// Clear the gate at every SPA navigation, including pages outside marketing layouts.
router.on('start', () => metaBrowserTracker.configure(null));
router.on('navigate', ({ detail }) => {
    const context = detail.page.props.metaBrowser as MetaBrowserContext | null | undefined;
    metaBrowserTracker.configure(context);
    context?.events.forEach((event) => metaBrowserTracker.emit(event));
});

export function MetaBrowserEvents() {
    const context = usePage().props.metaBrowser as MetaBrowserContext | null | undefined;
    useLayoutEffect(() => {
        metaBrowserTracker.configure(context);
        context?.events.forEach((event) => metaBrowserTracker.emit(event));
    }, [context]);
    return null;
}
