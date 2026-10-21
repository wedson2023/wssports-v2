import { useEffect, useRef, useState } from 'react';

import { Item, Menu, Trigger, Wrapper } from './styles';

// espera antes de fechar quando o mouse sai, para dar tempo de chegar ao menu
const ATRASO_FECHAR = 150;

// altura reservada para decidir se o menu abre para baixo ou para cima
const ALTURA_MENU = 220;

// botão de três pontinhos que abre a lista de ações (ao clicar, ou ao passar o mouse em aparelhos
// com mouse); acoes = [{ chave, icone, titulo, tom, ao_clicar }]. O menu fica em posição fixa para
// não ser cortado pela rolagem da tabela
export default function ActionsMenu({ acoes, rotulo = 'Ações' }) {
    const [posicao, definir_posicao] = useState(null);
    const gatilho = useRef(null);
    const caixa = useRef(null);
    const espera = useRef(null);

    const tem_mouse = () => window.matchMedia('(hover: hover)').matches;

    const abrir = () => {
        window.clearTimeout(espera.current);

        const retangulo = gatilho.current.getBoundingClientRect();
        const para_cima = window.innerHeight - retangulo.bottom < ALTURA_MENU;

        definir_posicao({
            right: window.innerWidth - retangulo.right,
            ...(para_cima ? { bottom: window.innerHeight - retangulo.top + 4 } : { top: retangulo.bottom + 4 }),
        });
    };

    const fechar = () => {
        window.clearTimeout(espera.current);
        definir_posicao(null);
    };

    // fecha ao clicar fora, ao rolar, ao redimensionar ou com Esc
    useEffect(() => {
        if (!posicao) return undefined;

        const fora = (evento) => !caixa.current?.contains(evento.target) && fechar();
        const tecla = (evento) => evento.key === 'Escape' && fechar();

        document.addEventListener('pointerdown', fora);
        document.addEventListener('keydown', tecla);
        window.addEventListener('scroll', fechar, true);
        window.addEventListener('resize', fechar);

        return () => {
            document.removeEventListener('pointerdown', fora);
            document.removeEventListener('keydown', tecla);
            window.removeEventListener('scroll', fechar, true);
            window.removeEventListener('resize', fechar);
        };
    }, [posicao]);

    useEffect(() => () => window.clearTimeout(espera.current), []);

    if (!acoes.length) return null;

    return (
        <Wrapper
            ref={caixa}
            onMouseEnter={() => tem_mouse() && abrir()}
            onMouseLeave={() => {
                if (tem_mouse()) espera.current = window.setTimeout(fechar, ATRASO_FECHAR);
            }}
        >
            <Trigger
                ref={gatilho}
                type="button"
                title={rotulo}
                aria-label={rotulo}
                aria-haspopup="menu"
                aria-expanded={Boolean(posicao)}
                $aberto={Boolean(posicao)}
                onClick={() => (posicao ? fechar() : abrir())}
            >
                <i className="material-icons" aria-hidden="true">more_vert</i>
            </Trigger>
            {posicao && (
                <Menu role="menu" style={posicao}>
                    {acoes.map((acao) => (
                        <Item
                            key={acao.chave}
                            type="button"
                            role="menuitem"
                            $tom={acao.tom}
                            onClick={() => {
                                fechar();
                                acao.ao_clicar();
                            }}
                        >
                            <i className="material-icons" aria-hidden="true">{acao.icone}</i>
                            {acao.titulo}
                        </Item>
                    ))}
                </Menu>
            )}
        </Wrapper>
    );
}
