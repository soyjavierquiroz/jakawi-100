import { fileURLToPath } from 'node:url';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import { defineConfig } from 'vite';

export default defineConfig(({ command }) => {
    if (
        command !== 'serve' ||
        process.env.VSL_DEMO !== 'jakawi-vsl-dev' ||
        process.env.APP_ENV !== 'local'
    ) {
        throw new Error(
            'VSL demo is serve-only in the isolated development container.',
        );
    }
    return {
        root: fileURLToPath(new URL('.', import.meta.url)),
        plugins: [react(), tailwindcss()],
        resolve: {
            alias: {
                '@': fileURLToPath(
                    new URL('../../resources/js', import.meta.url),
                ),
            },
        },
        server: {
            host: '0.0.0.0',
            port: 5173,
            strictPort: true,
            allowedHosts: ['localhost', '127.0.0.1'],
            fs: {
                allow: ['.', '../../resources', '../../node_modules'].map(
                    (path) => fileURLToPath(new URL(path, import.meta.url)),
                ),
            },
        },
    };
});
