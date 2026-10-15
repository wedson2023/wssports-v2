// estados do bilhete em tempo real (spec 010, FR-012): cores próprias, distintas da cor principal do
// tema, sempre acompanhadas de texto ou ícone (nunca só a cor)
export const cores_estado = {
    ganhando: '#1fa45b',
    perdendo: '#e5484d',
    aguardando: '#d99a00',
    nao_iniciado: '#8a8f98',
    devolvido: '#4a90d9',
    encerrada: '#7c5cd6',
};

// cor e ícone (Material Icons) pelo selo ou pelo resultado do palpite/aposta
export const aparencia_estado = (texto) => {
    switch (texto) {
        case 'Ganhando agora':
        case 'Vencedor':
        case 'ganhando':
            return { cor: cores_estado.ganhando, icone: 'trending_up' };
        case 'Perdendo agora':
        case 'Perdedor':
        case 'perdendo':
            return { cor: cores_estado.perdendo, icone: 'trending_down' };
        case 'Devolvido':
        case 'Cancelado':
            return { cor: cores_estado.devolvido, icone: 'undo' };
        case 'Encerrada':
            return { cor: cores_estado.encerrada, icone: 'price_check' };
        case 'nao_iniciado':
            return { cor: cores_estado.nao_iniciado, icone: 'schedule' };
        default:
            return { cor: cores_estado.aguardando, icone: 'hourglass_empty' };
    }
};

// rótulo do resultado da aposta no resumo
export const rotulo_resultado = (bilhete) => {
    if (bilhete.situacao === 'Cancelada') return 'Cancelada';
    if (bilhete.resultado === 'Vencedor') return 'Ganhou';
    if (bilhete.resultado === 'Perdedor') return 'Perdeu';
    if (bilhete.resultado === 'Encerrada') return 'Encerrada';

    return 'Em andamento';
};
