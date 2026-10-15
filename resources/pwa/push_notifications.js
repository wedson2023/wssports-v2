/* Notificações push do aplicativo (spec 010, R-14): importado pelo service worker gerado pelo Vite
 * (workbox.importScripts) e servido em /sw-notificacoes.js, sem cache. JavaScript puro, sem build. */

// mostra o aviso; o aviso novo do mesmo jogo (ou da mesma aposta) substitui o anterior (tag)
self.addEventListener('push', (evento) => {
    if (!evento.data) return;

    let aviso;

    try {
        aviso = evento.data.json();
    } catch {
        return;
    }

    evento.waitUntil(self.registration.showNotification(aviso.titulo, {
        body: aviso.corpo ?? undefined,
        icon: aviso.icone ?? undefined,
        badge: aviso.icone ?? undefined,
        tag: aviso.tag,
        renotify: true,
        data: { url: aviso.url },
    }));
});

// ao tocar, foca a aba que já está no bilhete ou abre uma nova
self.addEventListener('notificationclick', (evento) => {
    evento.notification.close();

    const url = new URL(evento.notification.data?.url ?? '/', self.location.origin).href;

    evento.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((janelas) => {
            const aberta = janelas.find((janela) => janela.url === url);

            return aberta ? aberta.focus() : self.clients.openWindow(url);
        }),
    );
});
