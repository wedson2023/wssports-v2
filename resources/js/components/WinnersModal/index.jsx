import { useEffect, useState } from 'react';
import { useTheme } from 'styled-components';

import CashModal, { Button, GhostButton } from '../CashModal';
import Spinner from '../Spinner';
import api from '../../utils/api';
import { alerta_erro } from '../../utils/alerts';
import { gerar_imagem_premiados } from '../../utils/winners_image';
import { Empty, Preview } from './styles';

// premiados do período (spec 018, FR-020, contracts/paginas §7): busca as até 23 maiores premiadas
// (GET /apostas/premiados), mostra a prévia da imagem e permite compartilhar (quando o aparelho
// compartilha arquivos) ou baixar o PNG. parametros = filtros da tela (período, aba, vendedor)
export default function WinnersModal({ aberto, parametros, token, ao_fechar }) {
    const tema = useTheme();
    const [imagem, definir_imagem] = useState(null);
    const [vazio, definir_vazio] = useState(false);
    const [carregando, definir_carregando] = useState(false);

    useEffect(() => {
        if (!aberto) return undefined;

        let ativo = true;
        definir_carregando(true);
        definir_vazio(false);
        definir_imagem(null);

        api.get('/apostas/premiados', { params: parametros, headers: { Authorization: `Bearer ${token}` } })
            .then(async ({ data }) => {
                if (!ativo) return;

                if (!data.data.length) {
                    definir_vazio(true);
                    return;
                }

                const blob = await gerar_imagem_premiados(data, {
                    principal: tema.principal,
                    fundo: '#ffffff',
                    texto: '#222222',
                    linha_alternada: '#f1f1f1',
                });

                if (ativo) definir_imagem({ blob, endereco: URL.createObjectURL(blob), nome: `premiados-${data.periodo.de}-${data.periodo.ate}.png` });
            })
            .catch((erro) => {
                if (!ativo) return;

                alerta_erro(erro);
                ao_fechar();
            })
            .finally(() => ativo && definir_carregando(false));

        return () => {
            ativo = false;
        };
    }, [aberto]);

    // libera a imagem da memória ao trocar ou fechar
    useEffect(() => () => imagem && URL.revokeObjectURL(imagem.endereco), [imagem]);

    const arquivo = () => new File([imagem.blob], imagem.nome, { type: 'image/png' });
    const pode_compartilhar = Boolean(imagem && navigator.canShare?.({ files: [arquivo()] }));

    const compartilhar = async () => {
        try {
            await navigator.share({ files: [arquivo()] });
        } catch {
            // compartilhamento cancelado
        }
    };

    const baixar = () => {
        const link = document.createElement('a');
        link.href = imagem.endereco;
        link.download = imagem.nome;
        link.click();
    };

    return (
        <CashModal
            aberto={aberto}
            icone="emoji_events"
            titulo="Premiados do período"
            descricao="Imagem com os maiores prêmios para compartilhar."
            ao_fechar={ao_fechar}
            rodape={imagem && (
                <>
                    {pode_compartilhar && (
                        <GhostButton type="button" onClick={compartilhar}>Compartilhar</GhostButton>
                    )}
                    <Button type="button" onClick={baixar}>
                        <i className="material-icons" aria-hidden="true">download</i>
                        Baixar
                    </Button>
                </>
            )}
        >
            {carregando && <Empty><Spinner tamanho={32} cor={tema.principal} /></Empty>}
            {vazio && <Empty>Nenhuma aposta premiada no período.</Empty>}
            {imagem && <Preview src={imagem.endereco} alt="Premiados do período" />}
        </CashModal>
    );
}
