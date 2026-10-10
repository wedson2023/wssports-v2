import { createHash } from 'node:crypto';
import { existsSync, readdirSync, readFileSync } from 'node:fs';
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';
import { VitePWA } from 'vite-plugin-pwa';

// revisão de um arquivo de public/ para o pré-cache (muda quando o conteúdo muda)
const revisao = (caminho) => createHash('md5').update(readFileSync(caminho)).digest('hex');

// página offline e imagens fixas de public/ que entram no pré-cache do service worker (R-15)
const arquivos_publicos = () => {
    const entradas = [];

    if (existsSync('public/offline.html')) {
        entradas.push({ url: '/offline.html', revision: revisao('public/offline.html') });
    }

    if (existsSync('public/images')) {
        readdirSync('public/images', { withFileTypes: true })
            .filter((arquivo) => arquivo.isFile() && /\.(png|svg|ico)$/.test(arquivo.name))
            .forEach((arquivo) => entradas.push({
                url: `/images/${arquivo.name}`,
                revision: revisao(`public/images/${arquivo.name}`),
            }));
    }

    return entradas;
};

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/js/app.jsx'],
            refresh: true,
        }),
        react(),
        VitePWA({
            strategies: 'generateSW',
            registerType: 'autoUpdate',
            injectRegister: false,
            // o manifest é montado pelo servidor (rota /manifest.webmanifest)
            manifest: false,
            filename: 'sw.js',
            workbox: {
                globPatterns: ['**/*.{js,css,woff2,png,svg,ico}'],
                // o service worker é servido em /sw.js: endereços absolutos e runtime embutido
                modifyURLPrefix: { '': '/build/' },
                inlineWorkboxRuntime: true,
                additionalManifestEntries: arquivos_publicos(),
                navigateFallback: null,
                skipWaiting: true,
                clientsClaim: true,
                cleanupOutdatedCaches: true,
                runtimeCaching: [
                    {
                        // a página nunca vem do cache; sem conexão, mostra a página offline
                        urlPattern: ({ request }) => request.mode === 'navigate',
                        handler: 'NetworkOnly',
                        options: { precacheFallback: { fallbackURL: '/offline.html' } },
                    },
                    {
                        urlPattern: ({ url }) => url.pathname.startsWith('/api/') || url.pathname.startsWith('/fakes/'),
                        handler: 'NetworkOnly',
                    },
                    {
                        // escudos e bandeiras de outros domínios
                        urlPattern: ({ sameOrigin }) => !sameOrigin,
                        handler: 'NetworkOnly',
                    },
                ],
            },
        }),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
