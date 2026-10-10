import { useEffect, useRef } from 'react';

// modais abertos, do mais antigo ao mais recente; cada um ocupa uma entrada no histórico
const abertos = [];
// voltas no histórico feitas por aqui (modal fechado pelo X, Esc ou fundo) que ainda vão chegar
let voltas_pendentes = 0;

// cria a entrada do modal no histórico, com o mesmo estado da página (o Inertia segue íntegro)
const empilhar = (modal) => {
    window.history.pushState(window.history.state, '');
    modal.empilhado = true;
};

// interrompe o popstate antes do Inertia, que restauraria a página (e remontaria a tela)
const ao_voltar = (evento) => {
    if (voltas_pendentes > 0) {
        voltas_pendentes -= 1;
        evento.stopImmediatePropagation();

        // modal aberto enquanto a volta estava a caminho ganha a entrada dele agora
        if (voltas_pendentes === 0) abertos.filter((modal) => !modal.empilhado).forEach(empilhar);
        return;
    }

    const modal = abertos.pop();
    if (!modal) return;

    evento.stopImmediatePropagation();
    modal.fechar.current();
};

// registra o ouvinte do botão voltar; precisa rodar antes do createInertiaApp para ser o primeiro
export const ouvir_botao_voltar = () => window.addEventListener('popstate', ao_voltar);

// botão voltar do aparelho (ou do navegador) fecha o modal aberto em vez de sair da página,
// como o browser-back-button do sistema antigo
export default function useVoltarFecha(visivel, ao_fechar) {
    const fechar = useRef(ao_fechar);
    fechar.current = ao_fechar;

    useEffect(() => {
        if (!visivel) return undefined;

        const modal = { fechar, empilhado: false };
        abertos.push(modal);
        if (voltas_pendentes === 0) empilhar(modal);

        return () => {
            const posicao = abertos.indexOf(modal);
            if (posicao === -1) return; // já saiu pelo botão voltar

            // fechado por outro meio: remove a entrada que o modal criou no histórico
            abertos.splice(posicao, 1);
            if (modal.empilhado) {
                voltas_pendentes += 1;
                window.history.back();
            }
        };
    }, [visivel]);
}
