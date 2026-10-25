import { useEffect, useState } from 'react';

import AffiliatePicker from '../AffiliatePicker';
import CashModal, { Button, GhostButton, Label } from '../CashModal';
import { categorias, modalidades, situacoes_promocao } from '../../hooks/useBonusPainel';
import useTelaMobile from '../../hooks/useTelaMobile';
import {
    Actions, AffiliateRow, AffiliateSearch, Cell, Chip, Chips, ClearButton, Panel, Select,
} from './styles';

const vazio = { situacao_promocao: null, modalidade: null, categoria: null, afiliado: null };

const dos_filtros = (filtros) => ({
    situacao_promocao: filtros.situacao_promocao,
    modalidade: filtros.modalidade,
    categoria: filtros.categoria,
    afiliado: filtros.afiliado,
});

// escolha do afiliado: todos, só as gerais ou as de um afiliado (escolhido da lista)
const modo_do_afiliado = (afiliado) => {
    if (!afiliado) return 'todos';

    return afiliado === 'geral' ? 'geral' : 'um';
};

// etiquetas dos filtros ativos, para o ActiveFilters (chave = a removida ao tocar no x)
export const etiquetas_promocoes = (filtros) => [
    filtros.situacao_promocao && { chave: 'situacao_promocao', texto: filtros.situacao_promocao },
    filtros.modalidade && { chave: 'modalidade', texto: filtros.modalidade },
    filtros.categoria && { chave: 'categoria', texto: filtros.categoria },
    filtros.afiliado && { chave: 'afiliado', texto: filtros.afiliado === 'geral' ? 'Promoções gerais' : `Afiliado: ${filtros.afiliado.nome}` },
].filter(Boolean);

// filtros da aba Promoções (spec 020, FR-008, contracts/paginas §3): situação e modalidade em campos de
// seleção, na linha dos botões; embaixo, categoria e afiliado em chips, um por linha; em "De um
// afiliado", a busca do afiliado (só da lista) ao lado dos chips. No computador, painel abaixo do
// cabeçalho; no celular, modal de tela cheia
export default function PromotionsFilters({ aberto, filtros, token, ao_aplicar, ao_fechar }) {
    const e_mobile = useTelaMobile();
    const [rascunho, definir_rascunho] = useState(dos_filtros(filtros));
    const [modo_afiliado, definir_modo_afiliado] = useState(() => modo_do_afiliado(filtros.afiliado));

    // ao abrir, o rascunho parte dos filtros em vigor
    useEffect(() => {
        if (!aberto) return;

        definir_rascunho(dos_filtros(filtros));
        definir_modo_afiliado(modo_do_afiliado(filtros.afiliado));
    }, [aberto]);

    const alterar = (mudanca) => definir_rascunho((atual) => ({ ...atual, ...mudanca }));

    const escolher_modo_afiliado = (modo) => {
        definir_modo_afiliado(modo);
        alterar({ afiliado: modo === 'geral' ? 'geral' : null });
    };

    // "De um afiliado" sem escolher nenhum vale como todos
    const aplicar = () => {
        ao_aplicar(rascunho);
        ao_fechar();
    };

    const limpar = () => {
        definir_rascunho(vazio);
        definir_modo_afiliado('todos');
    };

    const selecao = (chave, rotulo, opcoes, todos) => (
        <Cell $area={chave}>
            <Label htmlFor={`filtro_promocao_${chave}`}>{rotulo}</Label>
            <Select id={`filtro_promocao_${chave}`} value={rascunho[chave] ?? ''} onChange={(evento) => alterar({ [chave]: evento.target.value || null })}>
                <option value="">{todos}</option>
                {opcoes.map((opcao) => <option key={opcao} value={opcao}>{opcao}</option>)}
            </Select>
        </Cell>
    );

    const campos = (
        <>
            {selecao('situacao_promocao', 'Situação', situacoes_promocao, 'Todas')}
            {selecao('modalidade', 'Modalidade', modalidades, 'Todas')}
            <Cell $area="categoria">
                <Label as="span">Categoria</Label>
                <Chips role="group" aria-label="Categoria">
                    <Chip type="button" aria-pressed={!rascunho.categoria} onClick={() => alterar({ categoria: null })}>Todas</Chip>
                    {categorias.map((categoria) => (
                        <Chip key={categoria} type="button" aria-pressed={rascunho.categoria === categoria} onClick={() => alterar({ categoria })}>{categoria}</Chip>
                    ))}
                </Chips>
            </Cell>
            <Cell $area="afiliado">
                <Label as="span">Afiliado</Label>
                <AffiliateRow>
                    <Chips role="group" aria-label="Afiliado">
                        <Chip type="button" aria-pressed={modo_afiliado === 'todos'} onClick={() => escolher_modo_afiliado('todos')}>Todos</Chip>
                        <Chip type="button" aria-pressed={modo_afiliado === 'geral'} onClick={() => escolher_modo_afiliado('geral')}>Geral (sem afiliado)</Chip>
                        <Chip type="button" aria-pressed={modo_afiliado === 'um'} onClick={() => escolher_modo_afiliado('um')}>De um afiliado</Chip>
                    </Chips>
                    {modo_afiliado === 'um' && (
                        <AffiliateSearch>
                            <AffiliatePicker
                                id="filtro_promocao_afiliado"
                                valor={rascunho.afiliado === 'geral' ? null : rascunho.afiliado}
                                token={token}
                                ao_escolher={(afiliado) => alterar({ afiliado })}
                            />
                        </AffiliateSearch>
                    )}
                </AffiliateRow>
            </Cell>
        </>
    );

    if (e_mobile) {
        return (
            <CashModal
                aberto={aberto}
                icone="filter_alt"
                titulo="Filtros"
                descricao="Refine a lista de promoções."
                ao_fechar={ao_fechar}
                altura_fixa
                rodape={(
                    <>
                        <GhostButton type="button" onClick={limpar}>Limpar</GhostButton>
                        <Button type="button" onClick={aplicar}>Aplicar</Button>
                    </>
                )}
            >
                {campos}
            </CashModal>
        );
    }

    if (!aberto) return null;

    return (
        <Panel aria-label="Filtros">
            {campos}
            <Actions>
                <ClearButton type="button" onClick={limpar}>
                    <i className="material-icons" aria-hidden="true">filter_alt_off</i>
                    Limpar filtros
                </ClearButton>
                <Button type="button" onClick={aplicar}>Aplicar</Button>
            </Actions>
        </Panel>
    );
}
