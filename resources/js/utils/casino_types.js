// rótulos dos tipos de jogo do cassino (types de screens/casino do sistema antigo), mais os tipos que
// a API do provedor passou a enviar (crash_games) e os jogos sem tipo (spec 011)
export const rotulos_tipos = {
    populares: 'Populares',
    slots: 'Slots',
    fast_games: 'Jogo rápido',
    live_dealers: 'Ao vivo',
    lottery: 'Loterias',
    arcade: 'Arcade',
    sport: 'Esportes',
    card: 'Cartas',
    roulette: 'Roleta',
    video_poker: 'Video Poker',
    crash_games: 'Crash',
    outros: 'Outros',
};

// tipo desconhecido aparece como veio do provedor
export const rotulo_tipo = (tipo) => rotulos_tipos[tipo] ?? tipo;
