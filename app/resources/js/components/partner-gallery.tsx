import { useEffect, useRef } from 'react';
import { JakawiImage } from '@/components/jakawi-image';
import './partner-gallery.css';

type GalleryImage = {
    image_url: string;
    image_srcset: { src: string; width: number }[];
};

/** The original desktop grid, with a mobile-only looping track. */
export function PartnerGallery({
    images,
    name,
}: {
    images: GalleryImage[];
    name: string;
}) {
    const viewportRef = useRef<HTMLDivElement>(null);
    const trackRef = useRef<HTMLDivElement>(null);
    const count = images.length;

    useEffect(() => {
        const viewport = viewportRef.current;
        const track = trackRef.current;
        if (!viewport || !track || !count) return;
        const mobile = window.matchMedia('(max-width: 767px)');
        const reduced = window.matchMedia('(prefers-reduced-motion: reduce)');
        let position = count > 1 ? count : 0;
        let step = 0;
        let visible = false;
        let moving = false;
        let held = false;
        let pausedUntil = 0;
        let timer: ReturnType<typeof setTimeout> | undefined;
        let settleTimer: ReturnType<typeof setTimeout> | undefined;
        let gesture:
            | {
                  id: number;
                  x: number;
                  y: number;
                  dx: number;
                  offset: number;
                  axis?: 'x' | 'y';
              }
            | undefined;

        function paint(animate = false, offset = 0) {
            track!.style.transition =
                animate && !reduced.matches ? 'transform 400ms ease' : 'none';
            track!.style.transform = mobile.matches
                ? `translate3d(${-position * step + offset}px, 0, 0)`
                : '';
            viewport!.dataset.galleryIndex = String(
                ((position % count) + count) % count,
            );
        }
        function settle() {
            clearTimeout(settleTimer);
            moving = false;
            // Equivalent copies occupy the exact same pixels at either boundary.
            if (position < count || position >= count * 2) {
                position = count + (((position % count) + count) % count);
                paint();
            }
        }
        function move(direction: number) {
            if (count < 2 || moving || !mobile.matches) return;
            position += direction;
            moving = true;
            paint(true);
            settleTimer = setTimeout(settle, reduced.matches ? 0 : 420);
        }
        function schedule(delay = 2000) {
            clearTimeout(timer);
            if (
                !mobile.matches ||
                reduced.matches ||
                !visible ||
                document.hidden ||
                held ||
                count < 2
            )
                return;
            timer = setTimeout(
                () => {
                    if (Date.now() < pausedUntil) {
                        schedule(pausedUntil - Date.now());
                        return;
                    }
                    move(1);
                    schedule();
                },
                Math.max(delay, pausedUntil - Date.now()),
            );
        }
        function interact() {
            pausedUntil = Date.now() + 4000;
            schedule(4000);
        }
        function resize() {
            clearTimeout(settleTimer);
            moving = false;
            gesture = undefined;
            held = false;
            viewport!.tabIndex = mobile.matches && count > 1 ? 0 : -1;
            const slide = track!.querySelector<HTMLElement>('figure');
            step = (slide?.getBoundingClientRect().width || 0) + 12;
            position =
                count > 1 ? count + (((position % count) + count) % count) : 0;
            paint();
            schedule();
        }
        function down(event: PointerEvent) {
            if (
                !mobile.matches ||
                count < 2 ||
                !event.isPrimary ||
                event.button !== 0
            )
                return;
            // Freeze an in-flight slide at its current pixel before dragging.
            const currentX = new DOMMatrixReadOnly(
                getComputedStyle(track!).transform,
            ).m41;
            clearTimeout(settleTimer);
            moving = false;
            position = Math.round(-currentX / step);
            const offset = currentX + position * step;
            paint(false, offset);
            held = true;
            interact();
            gesture = {
                id: event.pointerId,
                x: event.clientX,
                y: event.clientY,
                dx: 0,
                offset,
            };
        }
        function drag(event: PointerEvent) {
            if (!gesture || event.pointerId !== gesture.id) return;
            const dx = event.clientX - gesture.x;
            const dy = event.clientY - gesture.y;
            if (!gesture.axis && Math.max(Math.abs(dx), Math.abs(dy)) > 6) {
                gesture.axis = Math.abs(dx) > Math.abs(dy) ? 'x' : 'y';
                if (gesture.axis === 'x')
                    viewport!.setPointerCapture(event.pointerId);
            }
            if (gesture.axis !== 'x') return;
            gesture.dx = Math.max(-step, Math.min(step, dx));
            paint(false, gesture.offset + gesture.dx);
        }
        function up(event: PointerEvent) {
            if (!gesture || event.pointerId !== gesture.id) return;
            const finished = gesture;
            gesture = undefined;
            held = false;
            if (viewport!.hasPointerCapture(event.pointerId))
                viewport!.releasePointerCapture(event.pointerId);
            if (finished.axis === 'x' || finished.offset !== 0) {
                if (
                    event.type !== 'pointercancel' &&
                    Math.abs(finished.dx) > Math.min(50, step * 0.18)
                )
                    move(finished.dx < 0 ? 1 : -1);
                else move(0);
            }
            interact();
        }
        function key(event: KeyboardEvent) {
            if (!mobile.matches) return;
            interact();
            if (event.key === 'ArrowRight' || event.key === 'ArrowLeft') {
                event.preventDefault();
                move(event.key === 'ArrowRight' ? 1 : -1);
            }
        }
        function visibility() {
            schedule();
        }
        const observer = new IntersectionObserver(
            ([entry]) => {
                visible = entry.isIntersecting;
                schedule();
            },
            { threshold: 0 },
        );
        const sizeObserver = new ResizeObserver(resize);
        observer.observe(viewport);
        sizeObserver.observe(viewport);
        mobile.addEventListener('change', resize);
        reduced.addEventListener('change', resize);
        document.addEventListener('visibilitychange', visibility);
        viewport.addEventListener('pointerdown', down);
        window.addEventListener('pointermove', drag);
        window.addEventListener('pointerup', up);
        window.addEventListener('pointercancel', up);
        viewport.addEventListener('keydown', key);
        viewport.addEventListener('focusin', interact);
        track.addEventListener('transitionend', settle);
        resize();
        return () => {
            clearTimeout(timer);
            clearTimeout(settleTimer);
            observer.disconnect();
            sizeObserver.disconnect();
            mobile.removeEventListener('change', resize);
            reduced.removeEventListener('change', resize);
            document.removeEventListener('visibilitychange', visibility);
            viewport.removeEventListener('pointerdown', down);
            window.removeEventListener('pointermove', drag);
            window.removeEventListener('pointerup', up);
            window.removeEventListener('pointercancel', up);
            viewport.removeEventListener('keydown', key);
            viewport.removeEventListener('focusin', interact);
            track.removeEventListener('transitionend', settle);
        };
    }, [count]);

    const copies = count > 1 ? [-1, 0, 1] : [0];
    return (
        <div
            ref={viewportRef}
            data-partner-gallery
            className={`partner-gallery mt-8 ${count === 2 ? 'max-w-4xl' : count === 1 ? 'max-w-lg' : ''}`}
            role="region"
            aria-label={`Fotografías de ${name}`}
            aria-roledescription="carrusel"
        >
            <div
                ref={trackRef}
                className={`partner-gallery-track grid gap-5 ${count >= 3 ? 'md:grid-cols-3' : count === 2 ? 'md:grid-cols-2' : ''}`}
            >
                {copies.flatMap((copy) =>
                    images.map((image, index) => (
                        <figure
                            key={`${copy}-${index}-${image.image_url}`}
                            className={`aspect-[4/5] overflow-hidden rounded-2xl ${copy ? 'partner-gallery-copy' : ''}`}
                            aria-hidden={copy ? true : undefined}
                        >
                            <JakawiImage
                                src={image.image_url}
                                srcset={image.image_srcset}
                                sizes="(min-width: 1024px) 440px, (min-width: 768px) 45vw, 90vw"
                                alt={`${name} · imagen ${index + 1}`}
                                className="h-full w-full object-cover"
                            />
                        </figure>
                    )),
                )}
            </div>
        </div>
    );
}
