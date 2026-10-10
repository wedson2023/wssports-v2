// compartilha um texto como no sistema antigo: no mobile, pelo compartilhamento do aparelho; no
// desktop (ou sem esse recurso), abrindo o WhatsApp
export const compartilhar_texto = async (texto, e_mobile) => {
    if (e_mobile && navigator.share) {
        try {
            await navigator.share({ text: texto });
        } catch {
            // compartilhamento cancelado pelo visitante
        }
        return;
    }

    window.open(`https://api.whatsapp.com/send?text=${encodeURIComponent(texto)}`, '_blank', 'noopener');
};
