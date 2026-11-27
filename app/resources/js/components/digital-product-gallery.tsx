import type { DigitalProductData } from '@/components/digital-card';
import { JakawiImage } from '@/components/jakawi-image';
import { ChevronLeft, ChevronRight, Pause, Play } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

const slideStep = (element: HTMLDivElement) => {
    const [first, second] = element.children;
    return first && second ? second.getBoundingClientRect().left - first.getBoundingClientRect().left : 0;
};

export function DigitalProductGallery({ product: p }: { product: DigitalProductData }) {
    const images = (p.gallery_urls ?? []).filter(Boolean);
    const count = images.length;
    const loop = count > 1;
    const track = useRef<HTMLDivElement>(null);
    const active = useRef(0);
    const pointerDown = useRef(false);
    const touching = useRef(false);
    const resumeAfter = useRef(0);
    const settleTimer = useRef<ReturnType<typeof setTimeout> | null>(null);
    const [index, setIndex] = useState(0);
    const [inView, setInView] = useState(false);
    const [mobile, setMobile] = useState(false);
    const [reducedMotion, setReducedMotion] = useState(true);
    const [documentVisible, setDocumentVisible] = useState(true);
    const [paused, setPaused] = useState(false);
    const [focused, setFocused] = useState(false);
    const mod = (value: number) => ((value % count) + count) % count;

    const stepWidth = () => {
        const el = track.current;
        if (!el || el.children.length < 2) return 0;
        return slideStep(el);
    };
    const interact = () => { resumeAfter.current = Date.now() + 6000; };
    const move = (next: number, manual = true) => {
        const el = track.current;
        const step = stepWidth();
        if (!el || !step) return;
        if (manual) interact();
        // Use the nearest neighbouring copy, including across the loop boundary.
        const physical = Math.round(el.scrollLeft / step);
        const target = physical + next - active.current;
        el.scrollTo({ left: target * step, behavior: reducedMotion ? 'instant' : 'smooth' });
    };

    useEffect(() => {
        const el = track.current;
        if (!el || !loop) return;
        const reset = () => {
            const step = slideStep(el);
            el.scrollTo({ left: (count + active.current) * step, behavior: 'instant' });
        };
        reset();
        const resize = new ResizeObserver(reset);
        resize.observe(el);
        const intersection = new IntersectionObserver(([entry]) => setInView(entry.isIntersecting), { threshold: 0.25 });
        intersection.observe(el);
        return () => {
            resize.disconnect();
            intersection.disconnect();
            if (settleTimer.current) clearTimeout(settleTimer.current);
        };
    }, [count, loop]);

    useEffect(() => {
        const phone = window.matchMedia('(max-width: 767px)');
        const motion = window.matchMedia('(prefers-reduced-motion: reduce)');
        const update = () => { setMobile(phone.matches); setReducedMotion(motion.matches); };
        const visibility = () => setDocumentVisible(!document.hidden);
        update(); visibility();
        phone.addEventListener('change', update);
        motion.addEventListener('change', update);
        document.addEventListener('visibilitychange', visibility);
        const release = () => { if (pointerDown.current && !touching.current) { pointerDown.current = false; interact(); } };
        window.addEventListener('pointerup', release);
        return () => {
            phone.removeEventListener('change', update);
            motion.removeEventListener('change', update);
            document.removeEventListener('visibilitychange', visibility);
            window.removeEventListener('pointerup', release);
        };
    }, []);

    useEffect(() => {
        if (!loop || !mobile || reducedMotion || !inView || !documentVisible || paused || focused) return;
        const timer = setInterval(() => {
            if (pointerDown.current || Date.now() < resumeAfter.current) return;
            const el = track.current;
            if (!el) return;
            const step = slideStep(el);
            el.scrollTo({ left: (Math.round(el.scrollLeft / step) + 1) * step, behavior: 'smooth' });
        }, 2000);
        return () => clearInterval(timer);
    }, [loop, mobile, reducedMotion, inView, documentVisible, paused, focused]);

    if (!count) return null;
    const slides = loop ? [...images, ...images, ...images] : images;
    const automatic = loop && mobile && !reducedMotion && !paused && !focused;
    const controlClass = 'grid size-11 place-items-center rounded-full border border-border bg-surface transition-colors hover:border-brand/40 hover:text-brand focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-brand motion-reduce:transition-none';
    return (
        <section aria-labelledby="gallery-heading" className="mt-14 border-t border-border pt-10 sm:mt-20"
            onFocusCapture={event => setFocused(event.target.matches(':focus-visible'))}
            onBlurCapture={event => { if (!event.currentTarget.contains(event.relatedTarget)) { setFocused(false); interact(); } }}>
            <div className="mb-6 flex items-center justify-between gap-4">
                <h2 id="gallery-heading" className="text-2xl font-extrabold tracking-tight sm:text-3xl">Un vistazo a la experiencia</h2>
                {loop && <div className="hidden shrink-0 gap-2 md:flex">
                    <button type="button" aria-label="Imagen anterior" aria-controls="product-gallery" onClick={() => move(active.current - 1)} className={controlClass}><ChevronLeft aria-hidden="true" size={20} /></button>
                    <button type="button" aria-label="Imagen siguiente" aria-controls="product-gallery" onClick={() => move(active.current + 1)} className={controlClass}><ChevronRight aria-hidden="true" size={20} /></button>
                </div>}
            </div>
            <div id="product-gallery" ref={track} role="region" aria-label={`Imágenes de ${p.name}`} aria-roledescription={loop ? 'carrusel' : undefined} tabIndex={loop ? 0 : undefined}
                onPointerDown={() => { pointerDown.current = true; setFocused(false); interact(); }}
                onPointerUp={() => { if (!touching.current) pointerDown.current = false; interact(); }}
                onPointerCancel={() => { if (!touching.current) pointerDown.current = false; interact(); }}
                onTouchStart={() => { touching.current = true; pointerDown.current = true; interact(); }}
                onTouchEnd={event => { touching.current = event.touches.length > 0; pointerDown.current = touching.current; interact(); }}
                onTouchCancel={() => { touching.current = false; pointerDown.current = false; interact(); }}
                onWheel={interact}
                onKeyDown={event => {
                    if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
                    event.preventDefault();
                    setFocused(true);
                    move(event.key === 'Home' ? 0 : event.key === 'End' ? count - 1 : active.current + (event.key === 'ArrowRight' ? 1 : -1));
                }}
                onScroll={() => {
                    const el = track.current;
                    const step = stepWidth();
                    if (!el || !step) return;
                    const next = mod(Math.round(el.scrollLeft / step));
                    active.current = next;
                    setIndex(next);
                    if (settleTimer.current) clearTimeout(settleTimer.current);
                    settleTimer.current = setTimeout(() => {
                        const physical = el.scrollLeft / step;
                        const settled = Math.round(physical);
                        if (Math.abs(physical - settled) < 0.02 && (settled < count || settled >= count * 2)) {
                            // Identical buffer images make this reset invisible; swipe stays native.
                            el.scrollTo({ left: (count + mod(settled)) * step, behavior: 'instant' });
                        }
                    }, 180);
                }}
                className={`relative grid snap-x snap-mandatory grid-flow-col gap-4 overflow-x-auto overscroll-x-contain rounded-2xl pb-2 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-brand ${!loop ? 'auto-cols-[100%] md:auto-cols-[minmax(0,400px)]' : count === 2 ? 'auto-cols-[86%] md:auto-cols-[calc((100%-16px)/2)]' : 'auto-cols-[86%] md:auto-cols-[calc((100%-16px)/2)] lg:auto-cols-[calc((100%-32px)/3)]'}`}>
                {slides.map((url, physical) => {
                    const logical = loop ? mod(physical) : physical;
                    const clone = loop && (physical < count || physical >= count * 2);
                    return <figure key={physical} role={clone ? undefined : 'group'} aria-hidden={clone || undefined} aria-label={clone ? undefined : `${logical + 1} de ${count}`} className="min-w-0 snap-start rounded-2xl border border-border/60 bg-surface-muted p-1.5 shadow-sm">
                        <JakawiImage src={url} srcset={p.gallery_srcsets?.[logical]} sizes="(min-width: 1024px) 340px, (min-width: 768px) 45vw, 78vw" alt={clone ? '' : `${p.name} · Imagen de apoyo ${logical + 1}`} loading="lazy" className="h-[min(110vw,420px)] w-full rounded-xl object-contain md:h-[400px]" />
                    </figure>;
                })}
            </div>
            {loop && <div className="mt-3 flex flex-wrap items-center justify-center gap-2">
                <div className="flex flex-wrap justify-center" aria-label="Elegir imagen">
                    {images.map((_, item) => <button type="button" key={item} aria-label={`Ver imagen ${item + 1}`} aria-current={index === item ? 'true' : undefined} aria-controls="product-gallery" onClick={() => move(item)} className="grid size-11 place-items-center rounded-full focus-visible:outline-2 focus-visible:outline-brand"><span className={`h-1.5 rounded-full transition-[width,background-color] motion-reduce:transition-none ${index === item ? 'w-5 bg-brand' : 'w-1.5 bg-foreground/25'}`} /></button>)}
                </div>
                {mobile && !reducedMotion && <button type="button" aria-label={paused ? 'Reproducir galería' : 'Pausar galería'} aria-controls="product-gallery" aria-pressed={paused} onClick={() => { setPaused(!paused); if (paused) { resumeAfter.current = 0; setFocused(false); } }} className={controlClass}>{paused ? <Play size={16} aria-hidden="true" /> : <Pause size={16} aria-hidden="true" />}</button>}
                <span className="sr-only" aria-live={automatic ? 'off' : 'polite'} aria-atomic="true">Imagen {index + 1} de {count}</span>
            </div>}
        </section>
    );
}
