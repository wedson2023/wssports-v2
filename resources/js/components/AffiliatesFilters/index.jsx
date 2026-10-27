import { useEffect, useState } from 'react';

import CashModal, { Button, Error, GhostButton, Hint, Input, Label } from '../CashModal';
import { carregar_gerentes, situacoes_por_aba } from '../../hooks/useAfiliadosPainel';
import useTelaMobile from '../../hooks/useTelaMobile';
import { atalho_do_periodo, atalhos_periodo, periodo_do_atalho, texto_periodo, validar_periodo } from '../../utils/periods';
import { Actions, Cell, ClearButton, Panel, Select } from './styles';

const dos_filtros = (filtros) => ({
    situacao: filtros.situacao,
    gerente: filtros.gerente,
    sem_gerente: filtros.sem_gerente,
    data_inicial: filtros.data_inicial,
    data_final: filtros.data_final,
});

// etiquetas dos filtros ativos, para o ActiveFilters (chave = a removida ao tocar no x); o período
// padrão (este mês) e a situação padrão da aba não viram etiqueta
export const etiquetas_afiliados = (filtros) => {
    const mes = periodo_do_atalho('mes');
    const padrao = situacoes_por_aba[filtros.aba]?.[0]?.chave ?? null;
    const outro_periodo = filtros.data_inicial !== mes.de || filtros.data_final !== mes.ate;

    return [
        filtros.situacao && filtros.situacao !== padrao && { chave: 'situacao', texto: filtros.situacao },
        filtros.gerente && { chave: 'gerente', texto: `Gerente: ${filtros.gerente.nome}` },
        filtros.sem_gerente && { chave: 'sem_gerente', texto: 'Sem gerente' },
        outro_periodo && { chave: 'periodo', texto: texto_periodo({ de: filtros.data_inicial, ate: filtros.data_final }) },
    ].filter(Boolean);
};

// remover uma etiqueta: o período volta a este mês e a situação ao padrão da aba
export const remover_filtro_afiliados = (filtros, chave) => {
    if (chave === 'periodo') {
        const mes = periodo_do_atalho('mes');

        return { data_inicial: mes.de, data_final: mes.ate };
    }

    if (chave === 'situacao') return { situacao: situacoes_por_aba[filtros.aba]?.[0]?.chave ?? null };

    return { [chave]: chave === 'sem_gerente' ? false : null };
};

// filtros da tela Afiliados (spec 022, FR-007, contracts/paginas §1): situação, gerente (lista da
// rede, R-15; Admin e Supervisor), sem gerente (Admin) e período (atalhos; De/Até no Personalizado,
// até 1 ano) em campos de seleção, numa linha com Limpar e Aplicar. No computador, painel abaixo
// do cabeçalho; no celular, modal de tela cheia
export default function AffiliatesFilters({ aberto, filtros, token, funcao, ao_aplicar, ao_fechar }) {
    const e_mobile = useTelaMobile();
    const [rascunho, definir_rascunho] = useState(dos_filtros(filtros));
    const [atalho, definir_atalho] = useState(() => atalho_do_periodo({ de: filtros.data_inicial, ate: filtros.data_final }));
    const [erro, definir_erro] = useState(null);
    const [gerentes, definir_gerentes] = useState({ lista: [], sem_acesso: false });
    const escolhe_gerente = funcao === 'Admin' || funcao === 'Supervisor';
    const situacoes = situacoes_por_aba[filtros.aba] ?? [];

    useEffect(() => {
        if (!aberto) return;

        definir_rascunho(dos_filtros(filtros));
        definir_atalho(atalho_do_periodo({ de: filtros.data_inicial, ate: filtros.data_final }));
        definir_erro(null);
    }, [aberto]);

    // a lista de gerentes só é buscada quando o filtro abre pela primeira vez
    useEffect(() => {
        if (!aberto || !escolhe_gerente || gerentes.lista.length || gerentes.sem_acesso) return undefined;

        let ativo = true;
        carregar_gerentes(token)
            .then((lista) => ativo && definir_gerentes({ lista, sem_acesso: false }))
            .catch(() => ativo && definir_gerentes({ lista: [], sem_acesso: true }));

        return () => {
            ativo = false;
        };
    }, [aberto]);

    const alterar = (mudanca) => definir_rascunho((atual) => ({ ...atual, ...mudanca }));

    const escolher_periodo = (chave) => {
        definir_atalho(chave);

        if (chave !== 'personalizado') {
            const periodo = periodo_do_atalho(chave);
            alterar({ data_inicial: periodo.de, data_final: periodo.ate });
        }
    };

    const aplicar = () => {
        const mensagem = validar_periodo({ de: rascunho.data_inicial, ate: rascunho.data_final });
        definir_erro(mensagem);

        if (mensagem) return;

        ao_aplicar(rascunho);
        ao_fechar();
    };

    const limpar = () => {
        const mes = periodo_do_atalho('mes');

        definir_rascunho({ situacao: situacoes[0]?.chave ?? null, gerente: null, sem_gerente: false, data_inicial: mes.de, data_final: mes.ate });
        definir_atalho('mes');
        definir_erro(null);
    };

    const campos = (
        <>
            {situacoes.length > 0 && (
                <Cell $area="situacao">
                    <Label htmlFor="filtro_afiliado_situacao">Situação</Label>
                    <Select id="filtro_afiliado_situacao" value={rascunho.situacao ?? ''} onChange={(evento) => alterar({ situacao: evento.target.value || null })}>
                        {situacoes.map((item) => <option key={item.rotulo} value={item.chave ?? ''}>{item.rotulo}</option>)}
                    </Select>
                </Cell>
            )}
            {escolhe_gerente && (
                <Cell $area="gerente">
                    <Label htmlFor="filtro_afiliado_gerente">Gerente</Label>
                    <Select
                        id="filtro_afiliado_gerente"
                        value={rascunho.gerente?.id ?? ''}
                        disabled={gerentes.sem_acesso}
                        onChange={(evento) => alterar({ gerente: gerentes.lista.find((gerente) => String(gerente.id) === evento.target.value) ?? null })}
                    >
                        <option value="">Todos</option>
                        {/* o escolhido continua na lista enquanto ela carrega */}
                        {rascunho.gerente && !gerentes.lista.some((gerente) => gerente.id === rascunho.gerente.id) && <option value={rascunho.gerente.id}>{rascunho.gerente.nome}</option>}
                        {gerentes.lista.map((gerente) => <option key={gerente.id} value={gerente.id}>{gerente.nome}</option>)}
                    </Select>
                    {gerentes.sem_acesso && <Hint>Você não tem acesso à lista de gerentes.</Hint>}
                </Cell>
            )}
            {funcao === 'Admin' && filtros.aba !== 'analise' && (
                <Cell $area="sem_gerente">
                    <Label htmlFor="filtro_afiliado_sem_gerente">Sem gerente</Label>
                    <Select id="filtro_afiliado_sem_gerente" value={rascunho.sem_gerente ? '1' : ''} onChange={(evento) => alterar({ sem_gerente: evento.target.value === '1' })}>
                        <option value="">Todos</option>
                        <option value="1">Só os sem gerente</option>
                    </Select>
                </Cell>
            )}
            <Cell $area="periodo">
                <Label htmlFor="filtro_afiliado_periodo">Período</Label>
                <Select id="filtro_afiliado_periodo" value={atalho} onChange={(evento) => escolher_periodo(evento.target.value)}>
                    {atalhos_periodo.map((item) => <option key={item.chave} value={item.chave}>{item.rotulo}</option>)}
                </Select>
            </Cell>
            {atalho === 'personalizado' && (
                <>
                    <Cell $area="de">
                        <Label htmlFor="filtro_afiliado_de">De</Label>
                        <Input id="filtro_afiliado_de" type="date" value={rascunho.data_inicial ?? ''} $erro={Boolean(erro)} onChange={(evento) => alterar({ data_inicial: evento.target.value })} />
                    </Cell>
                    <Cell $area="ate">
                        <Label htmlFor="filtro_afiliado_ate">Até</Label>
                        <Input id="filtro_afiliado_ate" type="date" value={rascunho.data_final ?? ''} $erro={Boolean(erro)} onChange={(evento) => alterar({ data_final: evento.target.value })} />
                    </Cell>
                </>
            )}
            {erro && <Error role="alert">{erro}</Error>}
        </>
    );

    if (e_mobile) {
        return (
            <CashModal
                aberto={aberto}
                icone="filter_alt"
                titulo="Filtros"
                descricao="Refine a lista de afiliados."
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
        <Panel aria-label="Filtros" $personalizado={atalho === 'personalizado'}>
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
