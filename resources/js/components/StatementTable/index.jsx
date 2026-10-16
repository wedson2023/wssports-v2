import { useEffect, useRef } from 'react';

import Spinner from '../Spinner';
import { cores_estado } from '../../utils/live_states';
import { formatar_real, para_centavos } from '../../utils/money';
import { Container, Empty, Head, Icon, List, Row, Sentinel, Status } from './styles';

const colunas = ['DATA', 'OP.', 'VALOR', 'PRÊMIO', 'EXPIRA EM', 'ROLLOVER', 'V. LIBERADO', 'SITUAÇÃO', 'AÇÃO'];

const sem_valor = '---';

const valor = (texto) => formatar_real(para_centavos(String(texto ?? '0').replace('-', '')));

// "2026-10-11T16:22:00-03:00" → "11/10 16:22" (horário de Brasília, como no antigo)
const data_curta = (iso) => {
    const [data, hora] = iso.split('T');
    const [, mes, dia] = data.split('-');

    return `${dia}/${mes} ${hora.slice(0, 5)}`;
};

// ícone da coluna SITUAÇÃO com os mesmos ícones e cores do antigo
const situacao = (item) => {
    if (item.aposta) {
        if (item.aposta.cancelada) return { icone: 'not_interested', cor: '#999' };
        if (item.aposta.resultado === 'Vencedor') return { icone: 'emoji_events', cor: 'gold' };
        if (item.aposta.resultado === 'Perdedor') return { icone: 'highlight_off', cor: 'red' };
        // encerrada pelo cliente (spec 010): mesma cor do bilhete em tempo real
        if (item.aposta.resultado === 'Encerrada') return { icone: 'price_check', cor: cores_estado.encerrada };

        return { icone: 'query_builder', cor: '#999' };
    }

    if (item.saque?.situacao === 'Pago') return { icone: 'attach_money', cor: 'green' };
    if (item.saque?.situacao === 'Recusado') return { icone: 'remove_red_eye', cor: 'red' };

    return item.tipo === 'Crédito' ? { icone: 'add', cor: 'green' } : { icone: 'remove', cor: 'red' };
};

const expira_em = (bonus) => {
    if (!bonus) return sem_valor;
    if (bonus.expirado) return 'Expirado';

    return bonus.expira_em_dias === null ? sem_valor : `${bonus.expira_em_dias} dia(s)`;
};

// extrato (List, Thead, Tr e Td de screens/credits): rola na horizontal no mobile e carrega a
// próxima página ao chegar no fim
export default function StatementTable({ itens, fim, carregando, ao_carregar_mais, ao_tocar_linha, acoes_bonus = null }) {
    const sentinela = useRef(null);

    useEffect(() => {
        if (fim || !sentinela.current) return undefined;

        const observador = new IntersectionObserver(([entrada]) => {
            if (entrada.isIntersecting) ao_carregar_mais();
        }, { rootMargin: '200px' });

        observador.observe(sentinela.current);

        return () => observador.disconnect();
    }, [fim, ao_carregar_mais]);

    return (
        <Container>
            <List>
                <Head>{colunas.map((coluna) => <div key={coluna}>{coluna}</div>)}</Head>
                {itens.map((item) => {
                    const { icone, cor } = situacao(item);
                    const rollover = item.rollover?.restante ?? item.bonus?.restante;

                    return (
                        <Row key={item.id} onClick={() => ao_tocar_linha(item)} $clicavel={Boolean(item.aposta || item.cassino || item.saque?.situacao === 'Recusado')}>
                            <div>{data_curta(item.data)}</div>
                            <div>{item.operacao}</div>
                            <div>{valor(item.valor)}</div>
                            <div>{item.aposta ? valor(item.aposta.premio) : item.cassino ? valor(item.cassino.total_ganho) : sem_valor}</div>
                            <div>{expira_em(item.bonus)}</div>
                            <div>{rollover !== undefined ? valor(rollover) : sem_valor}</div>
                            <div>{item.bonus ? valor(item.bonus.liberado) : sem_valor}</div>
                            <div><Status className="material-icons" $cor={cor}>{icone}</Status></div>
                            <div onClick={(evento) => evento.stopPropagation()}>{item.bonus && acoes_bonus ? acoes_bonus(item) : null}</div>
                        </Row>
                    );
                })}
                {!itens.length && !carregando && <Empty>Nenhum registro encontrado</Empty>}
                {carregando && <Empty><Spinner tamanho={24} cor="#999" /></Empty>}
                {!fim && <Sentinel ref={sentinela} />}
            </List>
        </Container>
    );
}

export { Icon };
