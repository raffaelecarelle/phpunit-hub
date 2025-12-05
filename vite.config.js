import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    plugins: [vue()],
    build: {
        outDir: 'public/build',
        manifest: true, // Genera manifest.json
        rollupOptions: {
            input: {
                main: 'public/js/main.js',
            },
        },
    },
    server: {
        watch: {
            usePolling: true, // Questo può aiutare in alcuni ambienti (es. WSL)
        },
        hmr: true, // Abilita Hot Module Replacement
    },
    publicDir: 'public',
});