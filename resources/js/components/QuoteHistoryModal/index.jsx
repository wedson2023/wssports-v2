import { useEffect, useState } from 'react';

import Backdrop from '../Backdrop';
import ModalHeader from '../ModalHeader';
import Pagination from '../Pagination';
import Spinner from '../Spinner';
import useVoltarFecha from '../../hooks/useVoltarFecha';
import api from '../../utils/api';
import { formatar_data_hora } from '../../utils/dates';
import { formatar_ajuste, formatar_cotacao } from '../../utils/quotes';
import { Body, Change, Changes, Container, Empty, Entry, EntryHeader, Meta } from './styles';

// texto da ação de uma linha do histórico
const descrever = (linha, nomes) => {
    const codigos = Object.keys(linha.alteracoes ?? {});
    const lista = codigos.slice(0, 3).map((codigo) => nomes?.[codigo] ?? codigo).join(', ');
    const resto = codigos.length > 3 ? ` e mais ${codigos.length - 3}` : '';

    if (linha.acao === 'trancar') return `trancou ${lista}${resto}`;
    if (linha.acao === 'destrancar') return `destrancou ${lista}${resto}`;

    return `alterou ${codigos.length} ${codigos.length === 1 ? 'cotação' : 'cotações'}`;
};

// valor de uma cotação no histórico, na unidade da camada
const valor = (camada, numero) => {
    if (numero === null || numero === undefined) return 'sem ajuste';

    if (camada === 'tetos') return formatar_cotacao(numero);
    if (camada === 'confrontos') return formatar_ajuste(numero, '');

    return formatar_ajuste(numero);
};

// histórico de uma regra (spec 016, FR-018a): autor, data e ação, da mais nova para a mais antiga;
// cada linha abre a lista "Casa: −5% → −10%". Altura fixa desde a abertura (lista que carrega depois)
export default function QuoteHistoryModal({ regra, nomes, token, ao_fechar }) {
    const aberto = Boolean(regra);
    const [pagina, definir_pagina] = useState(1);
    const [estado, definir_estado] = useState({ itens: [], ultima: 1, carregando: true, erro: null });
    const [abertos, definir_abertos] = useState([]);
    useVoltarFecha(aberto, ao_fechar);

    useEffect(() => definir_pagina(1), [regra?.chave]);

    useEffect(() => {
        if (!aberto) return undefined;

        let ativo = true;
        definir_estado((atual) => ({ ...atual, carregando: true, erro: null }));

        api.get('/cotacoes/historico', { params: { ...regra.filtros, page: pagina }, headers: { Authorization: `Bearer ${token}` } })
            .then(({ data }) => ativo && definir_estado({ itens: data.data, ultima: data.last_page, carregando: false, erro: null }))
            .catch((erro) => ativo && definir_estado({ itens: [], ultima: 1, carregando: false, erro }));

        return () => {
            ativo = false;
        };
    }, [aberto, regra?.chave, pagina]);

    const alternar = (id) => definir_abertos((atuais) => (atuais.includes(id) ? atuais.filter((item) => item !== id) : [...atuais, id]));

    return (
        <>
            <Backdrop visivel={aberto} ao_fechar={ao_fechar} camada={47} />
            <Container $visivel={aberto} role="dialog" aria-label="Histórico da regra">
                {aberto && (
                    <>
                        <ModalHeader
                            icone="history"
                            titulo={`Histórico · ${regra.titulo}`}
                            descricao="Alterações desta regra, da mais nova para a mais antiga."
                            ao_fechar={ao_fechar}
                        />
                        <Body>
                            {estado.carregando && <Empty><Spinner tamanho={30} cor="currentColor" /></Empty>}
                            {!estado.carregando && estado.erro && <Empty>{estado.erro.response?.data?.message ?? 'Não foi possível carregar o histórico.'}</Empty>}
                            {!estado.carregando && !estado.erro && !estado.itens.length && <Empty>Nenhuma alteração registrada ainda.</Empty>}
                            {!estado.carregando && estado.itens.map((linha) => (
                                <Entry key={linha.id}>
                                    <EntryHeader type="button" aria-expanded={abertos.includes(linha.id)} onClick={() => alternar(linha.id)}>
                                        <i className="material-icons" aria-hidden="true">
                                            {{ trancar: 'lock', destrancar: 'lock_open' }[linha.acao] ?? 'edit'}
                                        </i>
                                        <span>
                                            <strong>{linha.autor?.nome}</strong> {descrever(linha, nomes)}
                                            <Meta>
                                                {formatar_data_hora(linha.criado_em)}
                                                {linha.tipo ? ` · ${linha.tipo === 'ao_vivo' ? 'Ao vivo' : 'Pré-jogo'}` : ''}
                                                {linha.alvo ? ` · ${linha.alvo}` : ''}
                                            </Meta>
                                        </span>
                                        <i className="material-icons" aria-hidden="true">{abertos.includes(linha.id) ? 'expand_less' : 'expand_more'}</i>
                                    </EntryHeader>
                                    {abertos.includes(linha.id) && linha.acao === 'alterar' && (
                                        <Changes>
                                            {Object.entries(linha.alteracoes).map(([codigo, mudanca]) => (
                                                <Change key={codigo}>
                                                    <span>{nomes?.[codigo] ?? codigo}</span>
                                                    <strong>{valor(regra.filtros.camada, mudanca.antes)} → {valor(regra.filtros.camada, mudanca.depois)}</strong>
                                                </Change>
                                            ))}
                                        </Changes>
                                    )}
                                </Entry>
                            ))}
                            <Pagination pagina={pagina} ultima={estado.ultima} ao_trocar={definir_pagina} />
                        </Body>
                    </>
                )}
            </Container>
        </>
    );
}
