import { readdirSync } from 'node:fs';

// Match @inertiajs/vite's React resolver: ./pages and ./Pages, recursively.
export function inertiaPages(root = process.cwd()) {
    function walk(directory) {
        return readdirSync(`${root}/${directory}`, { withFileTypes: true })
            .flatMap((entry) => {
                const path = `${directory}/${entry.name}`;
                return entry.isDirectory() ? walk(path)
                    : /\.(tsx|jsx)$/.test(entry.name) ? [path] : [];
            });
    }
    return readdirSync(`${root}/resources/js`)
        .filter((name) => name === 'pages' || name === 'Pages')
        .flatMap((name) => walk(`resources/js/${name}`)).sort();
}
