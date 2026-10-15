/** Shared public notice: in document flow, above the journey and below modal layers. */
export default function PrelaunchNotice() {
    return (
        <aside
            data-prelaunch-notice
            aria-label="Modo pre-lanzamiento"
            className="prelaunch-notice sticky top-0 z-40 border-b border-ink/15 bg-reward px-4 py-2 text-ink"
        >
            <div className="mx-auto max-w-6xl gap-x-5 gap-y-1 sm:flex sm:items-center">
                <p className="shrink-0 text-[10px] font-extrabold tracking-widest sm:text-xs">
                    MODO PRE-LANZAMIENTO
                </p>
                <p className="text-xs leading-4 sm:leading-5">
                    JAKAWI está en modo DEMO. Las experiencias, beneficios, retos o premios son ilustrativos y no corresponden a items reales.
                </p>
            </div>
        </aside>
    );
}
