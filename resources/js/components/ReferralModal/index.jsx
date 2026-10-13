import { useEffect, useState } from 'react';

import ModalCard, { ModalButton } from '../ModalCard';
import Spinner from '../Spinner';
import api from '../../utils/api';
import { alerta_erro } from '../../utils/alerts';
import { formatar_real, para_centavos } from '../../utils/money';
import { Big, Center, Copied, Link, Progress, Text } from './styles';

// "Indique um amigo" (modals/indication do antigo, com visual novo): indicações válidas, quantas
// faltam para o próximo bônus e o link de cadastro para compartilhar pelo WhatsApp; resumo_inicial
// é o resumo já carregado pela tela, para abrir sem esperar a API
export default function ReferralModal({ aberto, resumo_inicial = null, ao_fechar, ao_ver_indicacoes }) {
    const [resumo, definir_resumo] = useState(null);
    const [copiado, definir_copiado] = useState(false);

    useEffect(() => {
        if (!aberto) return;

        if (resumo_inicial) {
            definir_resumo(resumo_inicial);
            return;
        }

        definir_resumo(null);
        api.get('/area-cliente/indicacoes', { params: { por_pagina: 1 } })
            .then(({ data }) => definir_resumo(data.resumo))
            .catch((erro) => {
                alerta_erro(erro);
                ao_fechar();
            });
    }, [aberto, resumo_inicial, ao_fechar]);

    const indicar = () => {
        const texto = `Olá! Estou te mandando o link para fazer seu cadastro: ${resumo.link}`;
        window.open(`https://api.whatsapp.com/send?text=${encodeURIComponent(texto)}`, '_blank', 'noopener');
    };

    const copiar = async () => {
        try {
            await navigator.clipboard.writeText(resumo.link);
            definir_copiado(true);
            setTimeout(() => definir_copiado(false), 1500);
        } catch {
            // sem acesso à área de transferência: o link continua visível para copiar à mão
        }
    };

    const rodape = (
        <>
            <ModalButton type="button" $secundario onClick={ao_ver_indicacoes}>Minhas indicações</ModalButton>
            <ModalButton type="button" onClick={indicar} disabled={!resumo}>
                <i className="material-icons" aria-hidden="true">share</i>
                Indicar
            </ModalButton>
        </>
    );

    return (
        <ModalCard aberto={aberto} titulo="Indique um amigo" ao_fechar={ao_fechar} rodape={rodape} largura={440}>
            {!resumo ? <Center><Spinner tamanho={28} cor="#999" /></Center> : (
                <Center>
                    <Text>Você tem</Text>
                    <Big>{resumo.validas}</Big>
                    <Text>indicação(ões) válida(s)</Text>
                    <Progress $porcentagem={Math.round(((resumo.meta - resumo.faltam_para_proximo) / resumo.meta) * 100)} />
                    <Text>
                        Faltam <strong>{resumo.faltam_para_proximo}</strong> para ganhar{' '}
                        <strong>R$ {formatar_real(para_centavos(resumo.valor_bonus ?? '0'))}</strong> de bônus
                    </Text>
                    <Text $pequeno>A indicação vale quando o amigo faz o primeiro depósito.</Text>
                    <Link type="button" onClick={copiar} title="Copiar link">
                        <span>{resumo.link}</span>
                        <i className="material-icons" aria-hidden="true">content_copy</i>
                    </Link>
                    {copiado && <Copied role="status">Link copiado</Copied>}
                </Center>
            )}
        </ModalCard>
    );
}
