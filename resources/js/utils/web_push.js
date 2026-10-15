// suporte às notificações push do aplicativo (spec 010, R-15)

// o navegador tem tudo o que o Web Push precisa
export const suporta_push = () => typeof window !== 'undefined'
    && 'serviceWorker' in navigator
    && 'PushManager' in window
    && 'Notification' in window;

export const e_iphone = () => /iPhone|iPad|iPod/.test(navigator.userAgent);

// aberto como aplicativo instalado (tela de início), e não numa aba do navegador
export const aplicativo_instalado = () => window.navigator.standalone === true
    || window.matchMedia?.('(display-mode: standalone)').matches === true;

// no iPhone, as notificações só funcionam com o aplicativo instalado (FR-047)
export const precisa_instalar = () => e_iphone() && !aplicativo_instalado();

// chave pública VAPID (base64url) para o formato pedido pelo pushManager.subscribe
export const chave_para_bytes = (base64url) => {
    const preenchida = `${base64url}${'='.repeat((4 - (base64url.length % 4)) % 4)}`.replace(/-/g, '+').replace(/_/g, '/');
    const binario = window.atob(preenchida);

    return Uint8Array.from(binario, (caractere) => caractere.charCodeAt(0));
};

// inscrição atual do aparelho (ou null), sem pedir permissão
export const inscricao_atual = async () => {
    if (!suporta_push()) return null;

    const registro = await navigator.serviceWorker.getRegistration('/');

    return registro ? registro.pushManager.getSubscription() : null;
};
