import { useEffect, useState } from 'react';

import ModalCard, { ModalButton } from '../ModalCard';
import Spinner from '../Spinner';
import api from '../../utils/api';
import { alerta_erro } from '../../utils/alerts';
import { centavos_para_texto, formatar_real, para_centavos } from '../../utils/money';
import { Available, ErrorText, Field, Input, Key, Label, Prefix, Row, Success, Summary } from './styles';

const reais = (centavos) => `R$ ${formatar_real(Math.max(0, centavos))}`;

// saque (spec 007, US3; no antigo era um alerta com o campo de valor): saldo, valor, chave Pix
// de destino e o saldo depois; cadastro incompleto chama ao_completar_cadastro e retoma depois
export default function WithdrawModal({ aberto, saldo, ao_fechar, ao_concluir, ao_completar_cadastro }) {
    const [valor, definir_valor] = useState('');
    const [pix, definir_pix] = useState(undefined);
    const [erro, definir_erro] = useState('');
    const [enviando, definir_enviando] = useState(false);
    const [mensagem, definir_mensagem] = useState('');
    const saldo_centavos = para_centavos(String(saldo ?? '0').replace('-', '')) * (String(saldo).startsWith('-') ? -1 : 1);

    useEffect(() => {
        if (!aberto) return;

        definir_valor('');
        definir_erro('');
        definir_mensagem('');
        definir_pix(undefined);

        api.get('/area-cliente/meios-pagamento')
            .then(({ data }) => {
                const chaves = data.data.filter((meio) => meio.tipo === 'Pix' && meio.pix_chave);
                definir_pix(chaves.find((meio) => meio.principal) ?? chaves[0] ?? null);
            })
            .catch(() => definir_pix(null));
    }, [aberto]);

    const sacar = async () => {
        if (!para_centavos(valor)) {
            definir_erro('Informe o valor do saque.');
            return;
        }

        definir_enviando(true);
        definir_erro('');

        try {
            const { data } = await api.post('/area-cliente/saques', { valor: centavos_para_texto(para_centavos(valor)) });
            definir_mensagem(data.message);
            ao_concluir();
        } catch (falha) {
            const resposta = falha.response;

            if (resposta?.status === 422 && resposta.data?.faltando) ao_completar_cadastro(resposta.data.faltando, resposta.data.message, sacar);
            else if (resposta?.status === 422) definir_erro(Object.values(resposta.data.errors ?? {})[0]?.[0] ?? resposta.data.message);
            else alerta_erro(falha);
        } finally {
            definir_enviando(false);
        }
    };

    const rodape = mensagem
        ? <ModalButton type="button" onClick={ao_fechar}>Fechar</ModalButton>
        : (
            <>
                <ModalButton type="button" $secundario onClick={ao_fechar} disabled={enviando}>Cancelar</ModalButton>
                <ModalButton type="button" onClick={sacar} disabled={enviando}>{enviando ? <Spinner /> : 'Sacar'}</ModalButton>
            </>
        );

    return (
        <ModalCard aberto={aberto} titulo="Solicitar saque" ao_fechar={ao_fechar} rodape={rodape} largura={460}>
            {mensagem ? (
                <Success>
                    <i className="material-icons" aria-hidden="true">check_circle</i>
                    <p>{mensagem}</p>
                </Success>
            ) : (
                <>
                    <Available>
                        Saldo disponível
                        <strong>{saldo_centavos < 0 ? `R$ -${formatar_real(-saldo_centavos)}` : reais(saldo_centavos)}</strong>
                    </Available>

                    <Label htmlFor="valor_saque">Digite o valor que deseja sacar</Label>
                    <Field $erro={Boolean(erro)}>
                        <Prefix>R$</Prefix>
                        <Input id="valor_saque" inputMode="decimal" placeholder="0,00" value={valor} onChange={(evento) => { definir_valor(evento.target.value); definir_erro(''); }} />
                    </Field>
                    {erro && <ErrorText role="alert">{erro}</ErrorText>}

                    <Label as="p">Chave Pix de destino</Label>
                    <Key>
                        {pix === undefined && <Spinner tamanho={20} cor="#999" />}
                        {pix === null && <span>Nenhuma chave Pix cadastrada. Cadastre em Meus dados.</span>}
                        {pix && (
                            <>
                                <i className="material-icons" aria-hidden="true">pix</i>
                                <div>
                                    <strong>{pix.pix_tipo_chave}: {pix.pix_chave}</strong>
                                    <span>{pix.pix_nome_titular}</span>
                                </div>
                            </>
                        )}
                    </Key>

                    <Summary>
                        <Row><span>Saldo atual</span><span>{reais(saldo_centavos)}</span></Row>
                        <Row><span>Saque</span><span>- {reais(para_centavos(valor))}</span></Row>
                        <Row $total><span>Saldo depois do saque</span><span>{reais(saldo_centavos - para_centavos(valor))}</span></Row>
                    </Summary>
                </>
            )}
        </ModalCard>
    );
}
