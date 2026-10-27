import { useEffect, useState } from 'react';

import CashModal, {
    Button, Error, Field, Fields, GhostButton, Hint, Input, Label, Summary, SummaryRow,
} from '../CashModal';
import { carregar_gerentes, tipos_ganho } from '../../hooks/useAfiliadosPainel';
import api from '../../utils/api';
import { alerta_erro } from '../../utils/alerts';
import { erros_por_campo } from '../../utils/forms';
import { ProfileLink, Select } from './styles';

// aprovação do cadastro público (spec 022, FR-022, contracts/paginas §2): resumo do perfil, gerente
// (lista da rede, R-15) e regra de ganho, os três obrigatórios; a regra que o cadastro já tiver
// (anterior à spec 022) vem preenchida
export default function AffiliateApprovalModal({ afiliado, token, ao_fechar, ao_concluir }) {
    const aberto = Boolean(afiliado);
    const [dados, definir_dados] = useState({ usuarios_id: '', tipo_ganho: '', percentual: '' });
    const [gerentes, definir_gerentes] = useState({ lista: [], sem_acesso: false });
    const [erros, definir_erros] = useState({});
    const [salvando, definir_salvando] = useState(false);

    useEffect(() => {
        if (!aberto) return undefined;

        let ativo = true;
        definir_erros({});
        definir_dados({
            usuarios_id: afiliado.gerente ? String(afiliado.gerente.id) : '',
            tipo_ganho: afiliado.comissao?.tipo_ganho ?? '',
            percentual: afiliado.comissao ? String(Number(afiliado.comissao.percentual)).replace('.', ',') : '',
        });

        carregar_gerentes(token)
            .then((lista) => ativo && definir_gerentes({ lista, sem_acesso: false }))
            .catch(() => ativo && definir_gerentes({ lista: [], sem_acesso: true }));

        return () => {
            ativo = false;
        };
    }, [afiliado?.id]);

    const alterar = (mudanca) => definir_dados((atual) => ({ ...atual, ...mudanca }));

    const aprovar = async () => {
        definir_salvando(true);
        definir_erros({});

        try {
            await api.post(`/afiliados/${afiliado.id}/aprovar`, {
                usuarios_id: dados.usuarios_id ? Number(dados.usuarios_id) : null,
                tipo_ganho: dados.tipo_ganho,
                percentual: dados.percentual.trim().replace(',', '.'),
            }, { headers: { Authorization: `Bearer ${token}` } });
            ao_concluir('Cadastro aprovado. O afiliado recebe o aviso por e-mail.');
        } catch (falha) {
            if (falha.response?.status === 422) definir_erros(erros_por_campo(falha));
            else alerta_erro(falha);
        } finally {
            definir_salvando(false);
        }
    };

    const completo = dados.usuarios_id && dados.tipo_ganho && dados.percentual.trim();

    return (
        <CashModal
            aberto={aberto}
            icone="how_to_reg"
            titulo="Aprovar cadastro"
            descricao="Escolha o gerente e a regra de ganho para liberar o afiliado."
            ao_fechar={ao_fechar}
            rodape={(
                <>
                    <GhostButton type="button" onClick={ao_fechar}>Cancelar</GhostButton>
                    <Button type="button" disabled={salvando || !completo} onClick={aprovar}>{salvando ? 'Aprovando…' : 'Aprovar'}</Button>
                </>
            )}
        >
            {afiliado && (
                <>
                    <Summary>
                        <SummaryRow>
                            <dt>Afiliado</dt>
                            <dd>{`${afiliado.nome} (${afiliado.login})`}</dd>
                        </SummaryRow>
                        <SummaryRow>
                            <dt>Rede social</dt>
                            <dd>{afiliado.rede_social ?? 'Não informado'}</dd>
                        </SummaryRow>
                        <SummaryRow>
                            <dt>Seguidores</dt>
                            <dd>{afiliado.quantidade_seguidores?.toLocaleString('pt-BR') ?? 'Não informado'}</dd>
                        </SummaryRow>
                        <SummaryRow>
                            <dt>Perfil</dt>
                            <dd>{afiliado.link_perfil ? <ProfileLink href={afiliado.link_perfil} target="_blank" rel="noopener noreferrer">Abrir perfil</ProfileLink> : 'Não informado'}</dd>
                        </SummaryRow>
                    </Summary>
                    <Fields>
                        <Field $inteiro>
                            <Label htmlFor="aprovar_gerente">Gerente responsável *</Label>
                            <Select id="aprovar_gerente" value={dados.usuarios_id} disabled={gerentes.sem_acesso} $erro={Boolean(erros.usuarios_id)} onChange={(evento) => alterar({ usuarios_id: evento.target.value })}>
                                <option value="">Selecione</option>
                                {gerentes.lista.map((gerente) => <option key={gerente.id} value={gerente.id}>{gerente.nome}</option>)}
                            </Select>
                            {gerentes.sem_acesso && <Hint>Você não tem acesso à lista de gerentes.</Hint>}
                            {erros.usuarios_id && <Error role="alert">{erros.usuarios_id}</Error>}
                        </Field>
                        <Field>
                            <Label htmlFor="aprovar_tipo_ganho">Tipo de ganho *</Label>
                            <Select id="aprovar_tipo_ganho" value={dados.tipo_ganho} $erro={Boolean(erros.tipo_ganho)} onChange={(evento) => alterar({ tipo_ganho: evento.target.value })}>
                                <option value="">Selecione</option>
                                {tipos_ganho.map((tipo) => <option key={tipo} value={tipo}>{tipo}</option>)}
                            </Select>
                            {erros.tipo_ganho && <Error role="alert">{erros.tipo_ganho}</Error>}
                        </Field>
                        <Field>
                            <Label htmlFor="aprovar_percentual">Percentual (%) *</Label>
                            <Input id="aprovar_percentual" inputMode="decimal" placeholder="De 0 a 100" value={dados.percentual} $erro={Boolean(erros.percentual)} onChange={(evento) => alterar({ percentual: evento.target.value })} />
                            {erros.percentual && <Error role="alert">{erros.percentual}</Error>}
                        </Field>
                    </Fields>
                    {erros.situacao && <Error role="alert">{erros.situacao}</Error>}
                </>
            )}
        </CashModal>
    );
}
