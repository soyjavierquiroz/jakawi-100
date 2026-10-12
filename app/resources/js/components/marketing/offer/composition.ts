import type { OfferPresentationV1 } from './presentation';
const words = (text: string) =>
    text
        .toLocaleLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .match(/[\p{L}\p{N}]+/gu) || [];
const grammar = new Set([
    'a',
    'al',
    'de',
    'del',
    'el',
    'en',
    'es',
    'la',
    'las',
    'lo',
    'los',
    'o',
    'para',
    'por',
    'que',
    'se',
    'su',
    'tu',
    'un',
    'una',
    'y',
]);
const information = (text: string) =>
    words(text).filter((word) => !grammar.has(word));
// Use lexical coverage only for a value title, never for conditions or answers.
const coveredBy = (text: string, sources: string[]) => {
    const covered = new Set(sources.flatMap(information));
    const tokens = information(text);
    return tokens.length > 0 && tokens.every((token) => covered.has(token));
};
export function distinctOfferFaq(
    items: OfferPresentationV1['faq'],
    covered: string[],
) {
    const normalize = (text: string) => words(text).join(' ');
    const rendered = normalize(covered.join(' '));
    return items.filter((item) => {
        const answer = normalize(item.answer);
        return !answer || !rendered.includes(answer);
    });
}
export function distinctOfferValue(p: OfferPresentationV1) {
    const value = p.value;
    if (!value) return undefined;
    if (value.tone === 'reward') {
        // Remove only complete repeated supporting sentences, retaining the actual reward title.
        const sentences = value.title.split(/(?<=[.!?])\s+/);
        const normalizedHero = words(p.hero.description).join(' ');
        const distinct = sentences.filter(
            (sentence) => !normalizedHero.includes(words(sentence).join(' ')),
        );
        return {
            ...value,
            title: distinct.length ? distinct.join(' ').trim() : value.title,
        };
    }
    if (value.note || value.description || value.detail) return value;
    return coveredBy(value.title, [
        p.hero.eyebrow,
        p.hero.headline,
        p.hero.description,
    ])
        ? undefined
        : value;
}
