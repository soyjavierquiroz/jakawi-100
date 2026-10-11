import type { LandingVideoConfig } from './LandingVideo';

export function LandingVideoFields({ value, onChange, errors }: {
    value: LandingVideoConfig | null;
    onChange: (value: LandingVideoConfig | null) => void;
    errors: Record<string, string>;
}) {
    const control = 'min-h-11 rounded border border-border bg-surface p-3 font-normal';
    return <fieldset className="col-span-full grid gap-4 sm:grid-cols-2">
        <legend className="mb-3 font-bold">Video opcional</legend>
        <label className="flex flex-col gap-1 text-sm font-bold">Fuente
            <select className={control} value={value?.source.type ?? ''} onChange={event => {
                const type = event.target.value as 'youtube' | 'mp4' | 'bunny';
                onChange(type ? { ...value, source: { type, url: value?.source.url ?? '' } } : null);
            }}>
                <option value="">Conservar imagen</option><option value="youtube">YouTube</option><option value="mp4">MP4</option><option value="bunny">Bunny (MP4/HLS)</option>
            </select>
        </label>
        {value && <>
            <label className="flex flex-col gap-1 text-sm font-bold">URL del video
                <input type="url" required className={control} value={value.source.url} onChange={event => onChange({ ...value, source: { ...value.source, url: event.target.value } })}/>
            </label>
            <label className="flex flex-col gap-1 text-sm font-bold">Encuadre
                <select className={control} value={value.framing ?? ''} onChange={event => onChange({ ...value, framing: event.target.value as LandingVideoConfig['framing'] || undefined })}>
                    <option value="">Predeterminado</option><option value="embedded-vertical">Vertical dentro de video horizontal</option><option value="native-vertical">Vertical nativo</option><option value="horizontal">Horizontal</option>
                </select>
            </label>
            <label className="flex flex-col gap-1 text-sm font-bold">Reproducción automática
                <select className={control} value={value.autoplay === 'off' ? 'off' : 'muted'} onChange={event => onChange({ ...value, autoplay: event.target.value as 'muted' | 'off' })}>
                    <option value="muted">Silenciada</option><option value="off">Desactivada</option>
                </select>
            </label>
        </>}
        {Object.entries(errors).filter(([key]) => key === 'vsl' || key.startsWith('vsl.')).map(([key, message]) => <p key={key} role="alert" className="text-sm text-danger">{message}</p>)}
    </fieldset>;
}
