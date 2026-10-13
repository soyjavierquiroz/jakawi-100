// Inertia's query-string parser drops the synthetic origin for relative links.
// This is a parsing base, never an app/API/asset origin. Keep it non-routable.
export function replaceInertInertiaBase(code) {
    const before =
        'new URL(href.toString(), typeof window === "undefined" ? "http://localhost" : window.location.toString())';
    if (code.split(before).length !== 2) {
        throw new Error(
            'Inertia parsing base changed; review its semantics before release',
        );
    }
    return code.replace(
        before,
        before.replace('http://localhost', 'https://url-parser.invalid'),
    );
}
export function inertInertiaUrlBase() {
    return {
        name: 'inert-inertia-url-base',
        apply: 'build',
        enforce: 'pre',
        transform(code, id) {
            if (
                !id
                    .replaceAll('\\', '/')
                    .split('?')[0]
                    .endsWith('/node_modules/@inertiajs/core/dist/index.js')
            )
                return;
            return { code: replaceInertInertiaBase(code), map: null };
        },
    };
}
