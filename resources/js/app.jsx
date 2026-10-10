import './bootstrap';

import { createInertiaApp, router } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';

import { ouvir_botao_voltar } from './hooks/useVoltarFecha';
import PublicLayout from './layouts/PublicLayout';
import { alerta_erro } from './utils/alerts';

const paginas = import.meta.glob('./pages/*/index.jsx', { eager: true });

// botão voltar fecha os modais; registrado antes do Inertia para tratar o voltar primeiro
ouvir_botao_voltar();

createInertiaApp({
    title: (titulo) => titulo,
    progress: false,
    resolve: (nome) => {
        const pagina = paginas[`./pages/${nome}/index.jsx`];

        // layout persistente: o cupom e o tema não reiniciam ao trocar de página
        pagina.default.layout ??= (conteudo) => <PublicLayout>{conteudo}</PublicLayout>;

        return pagina;
    },
    setup({ el, App, props }) {
        createRoot(el).render(<App {...props} />);
    },
});

// respostas que não são do Inertia (ex.: 429 do limite por IP): mostra a mensagem do backend e
// mantém a tela como está, em vez do modal de erro padrão do Inertia
router.on('invalid', (evento) => {
    const resposta = evento.detail.response;

    if (resposta?.data?.message) {
        evento.preventDefault();
        alerta_erro({ response: resposta });
    }
});

// service worker em /sw.js com escopo "/": ativa sozinho a cada versão nova (R-15)
if ('serviceWorker' in navigator && import.meta.env.PROD) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch(() => {
            // sem service worker o site funciona normalmente, só não é instalável
        });
    });
}
