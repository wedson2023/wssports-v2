// endereço do escudo do time: o provedor envia o número do escudo (ou "escudo" quando o time não
// tem um próprio); mesma regra do MatchCard
export const url_escudo = (escudo) => (/^https?:\/\//.test(escudo ?? '') ? escudo : `https://api.oddbrasil.com/img/mini/m_${escudo || 'escudo'}.png`);

// imagem que não carregou some, mantendo o espaço reservado
export const esconder_escudo = (evento) => {
    evento.currentTarget.style.visibility = 'hidden';
};
