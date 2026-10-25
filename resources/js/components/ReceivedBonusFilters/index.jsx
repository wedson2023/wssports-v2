import { useEffect, useState } from 'react';

import CashModal, { Button, Error, GhostButton, Input, Label } from '../CashModal';
import PromotionPicker from '../PromotionPicker';
import { modalidades } from '../../hooks/useBonusPainel';
import useTelaMobile from '../../hooks/useTelaMobile';
import { atalho_do_periodo, atalhos_periodo, periodo_do_atalho } from '../../utils/periods';
import { Actions, Cell, ClearButton, Panel, Select } from './styles';

// período máximo do recebimento, em dias (FR-023)
const DIAS_MAXIMOS = 366;

// sem período = desde sempre (o padrão da aba)
const SEMPRE = 'sempre';

const opcoes_periodo = [{ chave: SEMPRE, rotulo: 'Desde sempre' }, ...atalhos_periodo];

const vazio = { promocao: null, modalidade: null, data_inicial: null, data_final: null };

const dos_filtros = (filtros) => ({
    promocao: filtros.promocao,
    modalidade: filtros.modalidade,
    data_inicial: filtros.data_inicial,
    data_final: filtros.data_final,
});

// atalho que corresponde às datas (ou "desde sempre" sem datas)
const atalho_das_datas = ({ data_inicial, data_final }) => (
    data_inicial && data_final ? atalho_do_periodo({ de: data_inicial, ate: data_final }) : SEMPRE
);

const formatar_data = (texto) => texto.split('-').reverse().join('/');

// etiquetas dos filtros ativos, para o ActiveFilters (chave = a removida ao tocar no x)
export const etiquetas_recebidos = (filtros) => [
    filtros.promocao && { chave: 'promocao', texto: `Promoção: ${filtros.promocao.nome}` },
    filtros.modalidade && { chave: 'modalidade', texto: filtros.modalidade },
    filtros.data_inicial && filtros.data_final && { chave: 'data_inicial', texto: `Recebidos de ${formatar_data(filtros.data_inicial)} a ${formatar_data(filtros.data_final)}` },
].filter(Boolean);

// remover uma etiqueta: o período sai inteiro
export const remover_filtro_recebidos = (chave) => (chave === 'data_inicial' ? { data_inicial: null, data_final: null } : { [chave]: null });

// filtros da aba Bônus recebidos (spec 020, FR-023, contracts/paginas §7): promoção escolhida da
// lista e modalidade e período em campos de seleção, numa linha; as datas De/Até só no período
// Personalizado (até 1 ano). No computador, painel abaixo do cabeçalho; no celular, modal de tela cheia
export default function ReceivedBonusFilters({ aberto, filtros, token, ao_aplicar, ao_fechar }) {
    const e_mobile = useTelaMobile();
    const [rascunho, definir_rascunho] = useState(dos_filtros(filtros));
    const [atalho, definir_atalho] = useState(() => atalho_das_datas(filtros));
    const [erro, definir_erro] = useState(null);

    useEffect(() => {
        if (!aberto) return;

        definir_rascunho(dos_filtros(filtros));
        definir_atalho(atalho_das_datas(filtros));
        definir_erro(null);
    }, [aberto]);

    const alterar = (mudanca) => definir_rascunho((atual) => ({ ...atual, ...mudanca }));

    // atalho escolhido preenche as datas; Personalizado mantém as que houver para editar
    const escolher_periodo = (chave) => {
        definir_atalho(chave);

        if (chave === SEMPRE) alterar({ data_inicial: null, data_final: null });
        else if (chave !== 'personalizado') {
            const periodo = periodo_do_atalho(chave);
            alterar({ data_inicial: periodo.de, data_final: periodo.ate });
        }
    };

    const aplicar = () => {
        const { data_inicial: de, data_final: ate } = rascunho;

        if (Boolean(de) !== Boolean(ate) || (de && de > ate)) {
            definir_erro('Informe as duas datas do período, com o início antes do fim.');
            return;
        }

        if (de && (new Date(ate) - new Date(de)) / 86400000 >= DIAS_MAXIMOS) {
            definir_erro('O período deve ter no máximo 1 ano.');
            return;
        }

        definir_erro(null);
        ao_aplicar(rascunho);
        ao_fechar();
    };

    const limpar = () => {
        definir_rascunho(vazio);
        definir_atalho(SEMPRE);
        definir_erro(null);
    };

    const campos = (
        <>
            <Cell $area="promocao">
                <Label htmlFor="filtro_bonus_promocao">Promoção</Label>
                <PromotionPicker id="filtro_bonus_promocao" valor={rascunho.promocao} token={token} ao_escolher={(promocao) => alterar({ promocao })} />
            </Cell>
            <Cell $area="modalidade">
                <Label htmlFor="filtro_bonus_modalidade">Modalidade</Label>
                <Select id="filtro_bonus_modalidade" value={rascunho.modalidade ?? ''} onChange={(evento) => alterar({ modalidade: evento.target.value || null })}>
                    <option value="">Todas</option>
                    {modalidades.map((modalidade) => <option key={modalidade} value={modalidade}>{modalidade}</option>)}
                </Select>
            </Cell>
            <Cell $area="periodo">
                <Label htmlFor="filtro_bonus_periodo">Recebidos no período</Label>
                <Select id="filtro_bonus_periodo" value={atalho} onChange={(evento) => escolher_periodo(evento.target.value)}>
                    {opcoes_periodo.map((opcao) => <option key={opcao.chave} value={opcao.chave}>{opcao.rotulo}</option>)}
                </Select>
            </Cell>
            {atalho === 'personalizado' && (
                <>
                    <Cell $area="de">
                        <Label htmlFor="filtro_bonus_de">De</Label>
                        <Input id="filtro_bonus_de" type="date" value={rascunho.data_inicial ?? ''} onChange={(evento) => alterar({ data_inicial: evento.target.value || null })} />
                    </Cell>
                    <Cell $area="ate">
                        <Label htmlFor="filtro_bonus_ate">Até</Label>
                        <Input id="filtro_bonus_ate" type="date" value={rascunho.data_final ?? ''} onChange={(evento) => alterar({ data_final: evento.target.value || null })} />
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
                descricao="Refine a lista de bônus recebidos."
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
