import type { JakawiVslPlayerProps } from './types';

/** Parses one complete shortcode only. Never evaluates or renders input. */
export function parseVideoShortcode(input: string): JakawiVslPlayerProps | null {
    if (input.length > 4096) return null;
    const match = /^\[jakawi_video\s+([^\[\]]+)\]$/.exec(input.trim());
    if (!match) return null;
    const attributes: Record<string, string> = {};
    const allowed = new Set(['source', 'id', 'url', 'mode', 'framing', 'autoplay', 'poster', 'theme', 'accent', 'text', 'overlay']);
    let remaining = match[1];
    while (remaining.trim()) {
        const attribute = /^\s*([a-z]+)="([^"<>\r\n]*)"(?:\s+|$)/.exec(remaining);
        if (!attribute || !allowed.has(attribute[1]) || attribute[1] in attributes) return null;
        attributes[attribute[1]] = attribute[2];
        remaining = remaining.slice(attribute[0].length);
    }
    const { source, id, url, mode, framing, autoplay, poster, theme, accent, text, overlay } = attributes;
    const https = (value: string) => {
        try { const parsed = new URL(value); return parsed.protocol === 'https:' && !parsed.username && !parsed.password && !/\s/.test(value); }
        catch { return false; }
    };
    if (!['youtube', 'mp4', 'bunny'].includes(source)) return null;
    if (mode !== undefined && !['vsl', 'course'].includes(mode)) return null;
    if (framing !== undefined && !['embedded-vertical', 'native-vertical', 'horizontal'].includes(framing)) return null;
    if (autoplay !== undefined && !['muted', 'off'].includes(autoplay)) return null;
    if (theme !== undefined && !['inherit', 'custom'].includes(theme)) return null;
    if (poster !== undefined && !https(poster)) return null;
    if ([accent, text, overlay].some(color => color !== undefined && !/^#[\da-f]{6}$/i.test(color))) return null;
    if (source === 'youtube' ? !id || !/^[\w-]{11}$/.test(id) || url !== undefined : id !== undefined || !url || !https(url)) return null;
    return {
        source: source === 'youtube' ? { type: 'youtube', url: `https://www.youtube.com/watch?v=${id}` } : { type: source as 'mp4' | 'bunny', url: url! },
        ...(mode ? { mode: mode as 'vsl' | 'course' } : {}),
        ...(framing ? { framing: framing as 'embedded-vertical' | 'native-vertical' | 'horizontal' } : {}),
        ...(autoplay ? { autoplay: autoplay as 'muted' | 'off' } : {}),
        ...(theme ? { theme: theme as 'inherit' | 'custom' } : {}),
        ...(poster ? { poster } : {}),
        ...([accent, text, overlay].some(Boolean) ? { colors: { accent, text, overlay } } : {}),
    };
}
