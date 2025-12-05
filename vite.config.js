import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    plugins: [vue()],
    build: {
        outDir: 'public/build',
        manifest: true,
        rollupOptions: {
            input: {
                main: 'assets/js/main.js',
            },
        },
    },
    server: {
        watch: {
            usePolling: true,
        },
        hmr: {
            overlay: true, // Mostra errori overlay
        },
    },
    test: {
        globals: true,
        environment: 'jsdom', // Necessario per testare componenti Vue
    },
    publicDir: false, // ← Disabilita publicDir o usa un'altra cartella
    // Oppure se hai assets statici: publicDir: 'assets'
});