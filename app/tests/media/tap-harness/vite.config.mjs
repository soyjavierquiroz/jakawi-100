import { defineConfig } from 'vite-plus';
import react from '@vitejs/plugin-react';
import path from 'node:path';
export default defineConfig({ plugins: [react()], resolve: { alias: {'@': path.resolve('resources/js')} }, server: { host: '0.0.0.0', port: 8096, strictPort: true } });
