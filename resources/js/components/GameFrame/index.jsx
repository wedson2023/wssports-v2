import { useEffect, useRef, useState } from 'react';

import useVoltarFecha from '../../hooks/useVoltarFecha';
import { Bar, Button, Container, Frame, Title } from './styles';

// mensagens com que os jogos pedem para fechar (closeGame, close e o lobby da Evolution)
const pedidos_de_fechar = ['closeGame', 'close', 'GAME_MODE:LOBBY'];

const pediu_fechar = (dados) => {
    if (typeof dados === 'string') return pedidos_de_fechar.some((pedido) => dados.includes(pedido));

    return pedidos_de_fechar.includes(dados?.action) || pedidos_de_fechar.includes(dados?.type) || pedidos_de_fechar.includes(dados?.name);
};

// jogo do cassino dentro do site (spec 011, R-09): voltar, tela cheia e fechamento pelas mensagens do
// provedor, pela página de saída (/close.php) e pelo voltar do aparelho
export default function GameFrame({ url, nome, ao_fechar }) {
    const container = useRef(null);
    const quadro = useRef(null);
    const [tela_cheia, definir_tela_cheia] = useState(false);

    useVoltarFecha(true, ao_fechar);

    useEffect(() => {
        const ao_receber = (evento) => {
            if (pediu_fechar(evento.data)) ao_fechar();
        };
        const ao_mudar_tela_cheia = () => definir_tela_cheia(Boolean(document.fullscreenElement));

        window.addEventListener('message', ao_receber);
        document.addEventListener('fullscreenchange', ao_mudar_tela_cheia);

        return () => {
            window.removeEventListener('message', ao_receber);
            document.removeEventListener('fullscreenchange', ao_mudar_tela_cheia);
            if (document.fullscreenElement) document.exitFullscreen().catch(() => {});
        };
    }, [ao_fechar]);

    // o jogo voltou para a página de saída do site (mesma origem): fecha
    const ao_carregar = () => {
        try {
            if (quadro.current?.contentWindow?.location.pathname === '/close.php') ao_fechar();
        } catch {
            // página do provedor (outra origem): continua aberto
        }
    };

    const alternar_tela_cheia = () => {
        if (document.fullscreenElement) document.exitFullscreen().catch(() => {});
        else container.current?.requestFullscreen?.().catch(() => {});
    };

    return (
        <Container ref={container}>
            <Bar>
                <Button onClick={ao_fechar} title="Voltar">arrow_back</Button>
                <Title>{nome}</Title>
                <Button onClick={alternar_tela_cheia} title="Tela cheia">{tela_cheia ? 'fullscreen_exit' : 'fullscreen'}</Button>
            </Bar>
            <Frame ref={quadro} src={url} title={nome} allow="fullscreen; autoplay" allowFullScreen onLoad={ao_carregar} />
        </Container>
    );
}
