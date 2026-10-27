import { useEffect, useState } from 'react';

import CashModal, { Button, Error, GhostButton, Input, Label } from '../CashModal';
import ChampionshipPicker from '../ChampionshipPicker';
import { atalhos_dia, data_relativa } from '../../hooks/useConfrontosPainel';
import useTelaMobile from '../../hooks/useTelaMobile';
import api from '../../utils/api';
import { formatar_dia_mes } from '../../utils/dates';
import { Actions, Cell, ClearButton, Panel, Select } from './styles';

const ESCOLHER_DATA = 'data';

const situacoes = [
    { chave: '', rotulo: 'Todos' },
    { chave: 'ativos', rotulo: 'Ativos' },
    { chave: 'desativados', rotulo: 'Desativados' },
];

const dos_filtros = (filtros) => ({
    dia: filtros.dia,
    data: filtros.data,
    campeonato: filtros.campeonato,
    situacao: filtros.situacao,
    manual: filtros.manual,
    pais: filtros.pais,
    favorito: filtros.favorito,
});

const padrao = { dia: 'hoje', data: null, campeonato: null, situacao: null, manual: false, pais: null, favorito: false };

// "27/10" a partir de "2026-10-27", sem passar pelo fuso
const texto_data = (data) => formatar_dia_mes(`${data}T12:00:00`);

// etiquetas dos filtros ativos, para o ActiveFilters (chave = a removida ao tocar no x); o dia
// padrão (hoje) não vira etiqueta
export const etiquetas_confrontos = (filtros) => {
    const campeonatos = filtros.aba === 'campeonatos';
    const jogos = ['jogos', 'mais_apostados'].includes(filtros.aba);
    const dia = atalhos_dia.find((atalho) => atalho.chave === filtros.dia);

    return [
        jogos && filtros.data && { chave: 'dia', texto: `Dia ${texto_data(filtros.data)}` },
        jogos && !filtros.data && filtros.dia && filtros.dia !== 'hoje' && { chave: 'dia', texto: dia?.rotulo ?? filtros.dia },
        jogos && filtros.campeonato && { chave: 'campeonato', texto: `Campeonato: ${filtros.campeonato.nome}` },
        campeonatos && filtros.pais && { chave: 'pais', texto: `País: ${filtros.pais}` },
        filtros.aba !== 'pendentes' && filtros.situacao && { chave: 'situacao', texto: filtros.situacao === 'ativos' ? 'Ativos' : 'Desativados' },
        campeonatos && filtros.favorito && { chave: 'favorito', texto: 'Só favoritos' },
        filtros.aba !== 'pendentes' && filtros.manual && { chave: 'manual', texto: 'Só manuais' },
    ].filter(Boolean);
};

// remover uma etiqueta: o dia volta a hoje; os demais saem
export const remover_filtro_confrontos = (chave) => (chave === 'dia' ? { dia: 'hoje', data: null } : { [chave]: padrao[chave] });

// filtros da tela Confrontos (spec 023, FR-007 e FR-025, contracts/paginas §1). Abas de jogos: dia
// (atalhos e "Escolher data", de 30 dias atrás até depois de amanhã), campeonato (busca na lista),
// situação e só manuais. Aba Campeonatos: país, situação, só favoritos e só manuais. Campos de
// seleção numa linha com Limpar e Aplicar; no celular, modal de tela cheia
export default function MatchesFilters({ aberto, filtros, token, ao_aplicar, ao_fechar }) {
    const e_mobile = useTelaMobile();
    const [rascunho, definir_rascunho] = useState(dos_filtros(filtros));
    const [erro, definir_erro] = useState(null);
    const [paises, definir_paises] = useState([]);
    const campeonatos = filtros.aba === 'campeonatos';
    const minimo = data_relativa(-30);
    const maximo = data_relativa(2);

    useEffect(() => {
        if (!aberto) return;

        definir_rascunho(dos_filtros(filtros));
        definir_erro(null);
    }, [aberto]);

    // países só quando o filtro da aba Campeonatos abre pela primeira vez
    useEffect(() => {
        if (!aberto || !campeonatos || paises.length) return undefined;

        let ativo = true;
        api.get('/campeonatos/paises', { headers: { Authorization: `Bearer ${token}` } })
            .then(({ data }) => ativo && definir_paises(data.data))
            .catch(() => ativo && definir_paises([]));

        return () => {
            ativo = false;
        };
    }, [aberto, campeonatos]);

    const alterar = (mudanca) => definir_rascunho((atual) => ({ ...atual, ...mudanca }));

    const escolher_dia = (chave) => {
        if (chave === ESCOLHER_DATA) alterar({ dia: null, data: rascunho.data ?? data_relativa(0) });
        else alterar({ dia: chave, data: null });
    };

    const aplicar = () => {
        if (!campeonatos && rascunho.data && (rascunho.data < minimo || rascunho.data > maximo)) {
            definir_erro('A data deve estar entre os últimos 30 dias e depois de amanhã.');

            return;
        }

        ao_aplicar(rascunho);
        ao_fechar();
    };

    const limpar = () => {
        definir_rascunho(padrao);
        definir_erro(null);
    };

    const campo_situacao = (
        <Cell $area="situacao">
            <Label htmlFor="filtro_confronto_situacao">Situação</Label>
            <Select id="filtro_confronto_situacao" value={rascunho.situacao ?? ''} onChange={(evento) => alterar({ situacao: evento.target.value || null })}>
                {situacoes.map((item) => <option key={item.chave} value={item.chave}>{item.rotulo}</option>)}
            </Select>
        </Cell>
    );

    const campo_manual = (
        <Cell $area="manual">
            <Label htmlFor="filtro_confronto_manual">Só manuais</Label>
            <Select id="filtro_confronto_manual" value={rascunho.manual ? '1' : ''} onChange={(evento) => alterar({ manual: evento.target.value === '1' })}>
                <option value="">Não</option>
                <option value="1">Sim</option>
            </Select>
        </Cell>
    );

    const campos = campeonatos ? (
        <>
            <Cell $area="pais">
                <Label htmlFor="filtro_confronto_pais">País</Label>
                <Select id="filtro_confronto_pais" value={rascunho.pais ?? ''} onChange={(evento) => alterar({ pais: evento.target.value || null })}>
                    <option value="">Todos</option>
                    {rascunho.pais && !paises.includes(rascunho.pais) && <option value={rascunho.pais}>{rascunho.pais}</option>}
                    {paises.map((pais) => <option key={pais} value={pais}>{pais}</option>)}
                </Select>
            </Cell>
            {campo_situacao}
            <Cell $area="favorito">
                <Label htmlFor="filtro_confronto_favorito">Só favoritos</Label>
                <Select id="filtro_confronto_favorito" value={rascunho.favorito ? '1' : ''} onChange={(evento) => alterar({ favorito: evento.target.value === '1' })}>
                    <option value="">Não</option>
                    <option value="1">Sim</option>
                </Select>
            </Cell>
            {campo_manual}
        </>
    ) : (
        <>
            <Cell $area="dia">
                <Label htmlFor="filtro_confronto_dia">Dia</Label>
                <Select id="filtro_confronto_dia" value={rascunho.data ? ESCOLHER_DATA : (rascunho.dia ?? 'hoje')} onChange={(evento) => escolher_dia(evento.target.value)}>
                    {atalhos_dia.map((atalho) => <option key={atalho.chave} value={atalho.chave}>{atalho.rotulo}</option>)}
                    <option value={ESCOLHER_DATA}>Escolher data</option>
                </Select>
            </Cell>
            {rascunho.data && (
                <Cell $area="data">
                    <Label htmlFor="filtro_confronto_data">Data</Label>
                    <Input id="filtro_confronto_data" type="date" min={minimo} max={maximo} value={rascunho.data} $erro={Boolean(erro)} onChange={(evento) => alterar({ data: evento.target.value || data_relativa(0) })} />
                </Cell>
            )}
            <Cell $area="campeonato">
                <Label htmlFor="filtro_confronto_campeonato">Campeonato</Label>
                <ChampionshipPicker id="filtro_confronto_campeonato" valor={rascunho.campeonato} token={token} ao_escolher={(campeonato) => alterar({ campeonato })} />
            </Cell>
            {campo_situacao}
            {campo_manual}
        </>
    );

    const conteudo = (
        <>
            {campos}
            {erro && <Error role="alert">{erro}</Error>}
        </>
    );

    if (e_mobile) {
        return (
            <CashModal
                aberto={aberto}
                icone="filter_alt"
                titulo="Filtros"
                descricao={campeonatos ? 'Refine a lista de campeonatos.' : 'Refine a lista de jogos.'}
                ao_fechar={ao_fechar}
                altura_fixa
                rodape={(
                    <>
                        <GhostButton type="button" onClick={limpar}>Limpar</GhostButton>
                        <Button type="button" onClick={aplicar}>Aplicar</Button>
                    </>
                )}
            >
                {conteudo}
            </CashModal>
        );
    }

    if (!aberto) return null;

    return (
        <Panel aria-label="Filtros" $campeonatos={campeonatos} $com_data={Boolean(rascunho.data)}>
            {conteudo}
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
