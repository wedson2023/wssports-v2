import { useEffect, useState } from 'react';

import CashModal, { Button, GhostButton, Label } from '../CashModal';
import { situacao_padrao, situacoes_especiais } from '../../hooks/useEspeciaisPainel';
import useTelaMobile from '../../hooks/useTelaMobile';
import { Actions, Cell, ClearButton, Panel, Select } from './styles';

// etiqueta do filtro ativo, para o ActiveFilters; a situação padrão (Aguardando) não vira etiqueta
export const etiquetas_especiais = (filtros) => (filtros.aba !== 'lixeira' && filtros.situacao !== situacao_padrao
    ? [{ chave: 'situacao', texto: situacoes_especiais.find((item) => item.chave === filtros.situacao)?.rotulo ?? filtros.situacao }]
    : []);

// remover a etiqueta volta a situação para Aguardando
export const remover_filtro_especiais = () => ({ situacao: situacao_padrao });

// filtros da tela Especiais (spec 024, FR-003, contracts/paginas §1): situação num campo de seleção,
// com Limpar e Aplicar. No computador, painel abaixo do cabeçalho; no celular, modal de tela cheia
export default function SpecialsFilters({ aberto, filtros, ao_aplicar, ao_fechar }) {
    const e_mobile = useTelaMobile();
    const [situacao, definir_situacao] = useState(filtros.situacao);

    useEffect(() => {
        if (aberto) definir_situacao(filtros.situacao);
    }, [aberto]);

    const aplicar = () => {
        ao_aplicar({ situacao });
        ao_fechar();
    };

    const campos = (
        <Cell $area="situacao">
            <Label htmlFor="filtro_especial_situacao">Situação</Label>
            <Select id="filtro_especial_situacao" value={situacao} onChange={(evento) => definir_situacao(evento.target.value)}>
                {situacoes_especiais.map((item) => <option key={item.chave} value={item.chave}>{item.rotulo}</option>)}
            </Select>
        </Cell>
    );

    if (e_mobile) {
        return (
            <CashModal
                aberto={aberto}
                icone="filter_alt"
                titulo="Filtros"
                descricao="Refine a lista de especiais."
                ao_fechar={ao_fechar}
                rodape={(
                    <>
                        <GhostButton type="button" onClick={() => definir_situacao(situacao_padrao)}>Limpar</GhostButton>
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
                <ClearButton type="button" onClick={() => definir_situacao(situacao_padrao)}>
                    <i className="material-icons" aria-hidden="true">filter_alt_off</i>
                    Limpar filtros
                </ClearButton>
                <Button type="button" onClick={aplicar}>Aplicar</Button>
            </Actions>
        </Panel>
    );
}
