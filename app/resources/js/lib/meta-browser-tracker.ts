export type MetaBrowserEvent = {
    event_id: string;
    event_name: string;
    standard: boolean;
    custom_data: Record<string, string | string[]>;
};
export type MetaBrowserContext = {
    provider: 'META';
    pixel_id: string;
    events: MetaBrowserEvent[];
    cta: { name: string; standard: boolean };
};
type Pixel = ((...args: unknown[]) => void) & {
    callMethod?: (...args: unknown[]) => void;
    queue: unknown[][];
    push?: Pixel;
    loaded: boolean;
    version: string;
};
declare global {
    interface Window { fbq?: Pixel; _fbq?: Pixel }
}

/** No business decisions: every emission requires the current server-provided context. */
export class MetaBrowserTracker {
    private context: MetaBrowserContext | null = null;
    private revision = 0;
    private initialized: string | null = null;
    private sent = new Set<string>();
    private loading: Promise<Pixel> | null = null;
    private pixel: Pixel | null = null;

    constructor(private readonly load: () => Promise<Pixel> = loadPixel) {}

    configure(context: MetaBrowserContext | null | undefined): void {
        this.revision++;
        this.context = context?.provider === 'META' && /^[0-9]+$/.test(context.pixel_id) ? context : null;
    }

    emit(event: MetaBrowserEvent): void {
        const context = this.context;
        const revision = this.revision;
        if (!context || !/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i.test(event.event_id)) return;
        const key = `${context.pixel_id}:${event.event_name}:${event.event_id}`;
        if (this.sent.has(key)) return;
        const deliver = (pixel: Pixel) => {
            if (this.revision !== revision || this.context?.pixel_id !== context.pixel_id || this.sent.has(key)) return;
            try {
                if (this.initialized !== context.pixel_id) {
                    pixel('set', 'autoConfig', false, context.pixel_id);
                    pixel('init', context.pixel_id);
                    this.initialized = context.pixel_id;
                }
                pixel(event.standard ? 'trackSingle' : 'trackSingleCustom', context.pixel_id,
                    event.event_name, event.custom_data, { eventID: event.event_id });
                this.sent.add(key);
            } catch { /* Browser delivery is always best effort. */ }
        };
        // A loaded adapter emits during click capture, before an Inertia action clears the gate.
        if (this.pixel) { deliver(this.pixel); return; }
        try {
            this.loading ??= this.load().catch((error: unknown) => { this.loading = null; throw error; });
        } catch { return; }
        void this.loading.then((pixel) => {
            this.pixel = pixel;
            // A navigation/provider switch during load invalidates the pending emission.
            deliver(pixel);
        }).catch(() => undefined);
    }

    cta(eventId: string, kind: string, location: string): void {
        if (!this.context || !['signup', 'membership', 'product_detail', 'redeem', 'reserve', 'participate', 'commit', 'external', 'other'].includes(kind)
            || !['hero', 'body', 'final', 'other'].includes(location)) return;
        this.emit({ event_id: eventId, event_name: this.context.cta.name, standard: this.context.cta.standard,
            custom_data: { cta_kind: kind, cta_location: location } });
    }
}

function loadPixel(): Promise<Pixel> {
    return new Promise((resolve, reject) => {
        // Do not adopt a foreign integration that may have enabled automatic collection.
        if (window.fbq) { reject(new Error('Pixel already owned by another integration')); return; }
        const pixel: Pixel = Object.assign((...args: unknown[]) => {
            if (pixel.callMethod) pixel.callMethod(...args);
            else pixel.queue.push(args);
        }, { queue: [] as unknown[][], loaded: true, version: '2.0' });
        pixel.push = pixel;
        window.fbq = pixel;
        window._fbq = pixel;
        const script = document.createElement('script');
        script.async = true;
        script.src = 'https://connect.facebook.net/en_US/fbevents.js';
        script.onload = () => resolve(pixel);
        script.onerror = () => { script.remove(); delete window.fbq; delete window._fbq; reject(new Error('Pixel unavailable')); };
        document.head.appendChild(script);
    });
}

export const metaBrowserTracker = new MetaBrowserTracker();
