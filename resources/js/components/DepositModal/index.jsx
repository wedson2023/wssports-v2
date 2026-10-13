import { useCallback, useEffect, useRef, useState } from 'react';

import CardPayment from '../CardPayment';
import ModalCard, { ModalButton } from '../ModalCard';
import PixCharge from '../PixCharge';
import Spinner from '../Spinner';
import api from '../../utils/api';
import { alerta_erro } from '../../utils/alerts';
import { centavos_para_texto, formatar_real, para_centavos } from '../../utils/money';
import {
    BonusBox, Check, Chip, Chips, ErrorText, Field, Hint, Input, Label, Method, Methods, Notice, Prefix, Success, SuccessMark,
} from './styles';

const icones_meio = { Pix: 'qr_code_2', 'Cartão de crédito': 'credit_card' };

const reais = (texto) => `R$ ${formatar_real(para_centavos(texto))}`;

// depósito em etapas (spec 007, FR-019): valor e aceite de bônus; meio; cobrança Pix ou cartão;
// sucesso. Cadastro sem CPF/e-mail chama ao_completar_cadastro e retoma depois; opcoes_iniciais
// são as opções já carregadas pela tela, para abrir sem esperar a API
export default function DepositModal({ aberto, opcoes_iniciais = null, ao_fechar, ao_concluir, ao_completar_cadastro }) {
    const [opcoes, definir_opcoes] = useState(null);
    const [etapa, definir_etapa] = useState('valor'); // valor | pix | cartao | sucesso
    const [valor, definir_valor] = useState('');
    const [aceita_bonus, definir_aceita_bonus] = useState(true);
    const [meio, definir_meio] = useState(null);
    const [deposito, definir_deposito] = useState(null);
    const [mensagem, definir_mensagem] = useState('');
    const [erro, definir_erro] = useState('');
    const [enviando, definir_enviando] = useState(false);
    const consulta = useRef(null);

    // cada abertura recomeça do valor, com as opções atuais da banca
    useEffect(() => {
        if (!aberto) return;

        definir_etapa('valor');
        definir_deposito(null);
        definir_erro('');

        const aplicar = (dados) => {
            definir_opcoes(dados);
            definir_aceita_bonus(dados.aceita_bonus);
            definir_meio(dados.meios[0] ?? null);
        };

        // com as opções da tela, abre pronto; sem elas, busca na API
        if (opcoes_iniciais) {
            aplicar(opcoes_iniciais);
            return;
        }

        definir_opcoes(null);

        api.get('/area-cliente/depositos/opcoes')
            .then(({ data }) => aplicar(data.data))
            .catch((falha) => {
                alerta_erro(falha);
                ao_fechar();
            });
        // as opções só são aplicadas ao abrir: a recarga da tela não reinicia um depósito em andamento
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [aberto, ao_fechar]);

    const parar_consulta = () => clearInterval(consulta.current);

    useEffect(() => parar_consulta, []);
    useEffect(() => { if (!aberto) parar_consulta(); }, [aberto]);

    const concluir = useCallback((texto) => {
        parar_consulta();
        definir_mensagem(texto);
        definir_etapa('sucesso');
        ao_concluir();
    }, [ao_concluir]);

    // acompanha a situação enquanto o modal está aberto (a confirmação chega pelo Mercado Pago)
    const acompanhar = useCallback((codigo) => {
        parar_consulta();
        consulta.current = setInterval(async () => {
            try {
                const { data } = await api.get(`/area-cliente/depositos/${codigo}`);

                if (data.data.situacao === 'Aprovado') concluir('Depósito realizado com sucesso!');
                else if (['Expirado', 'Cancelado', 'Recusado'].includes(data.data.situacao)) {
                    parar_consulta();
                    definir_deposito((atual) => ({ ...atual, situacao: data.data.situacao }));
                }
            } catch {
                // sem conexão: tenta de novo na próxima volta
            }
        }, 3000);
    }, [concluir]);

    const validar_valor = () => {
        const centavos = para_centavos(valor);

        if (!centavos) return 'Informe o valor do depósito.';
        if (centavos < para_centavos(opcoes.minimo)) return `O depósito mínimo é de ${reais(opcoes.minimo)}.`;
        if (centavos > para_centavos(opcoes.maximo)) return `O depósito máximo é de ${reais(opcoes.maximo)}.`;

        return '';
    };

    // envia o depósito; cartao (token, parcelas, bandeira) chega do CardPayment
    const enviar = async (cartao = null) => {
        definir_enviando(true);
        definir_erro('');

        try {
            const { data } = await api.post('/area-cliente/depositos', {
                valor: centavos_para_texto(para_centavos(valor)),
                meio,
                aceita_bonus,
                ...(cartao ? { cartao } : {}),
            });

            definir_deposito(data.data);

            if (data.data.situacao === 'Aprovado') concluir('Depósito realizado com sucesso!');
            else if (meio === 'Pix') {
                definir_etapa('pix');
                acompanhar(data.data.codigo);
            } else {
                definir_mensagem('Pagamento em análise. Você verá o depósito assim que for aprovado.');
                definir_etapa('sucesso');
                acompanhar(data.data.codigo);
            }
        } catch (falha) {
            const resposta = falha.response;

            if (resposta?.status === 422 && resposta.data?.faltando) {
                ao_completar_cadastro(resposta.data.faltando, resposta.data.message, () => enviar(cartao));
            } else if (resposta?.status === 422) {
                definir_erro(Object.values(resposta.data.errors ?? {})[0]?.[0] ?? resposta.data.message);
            } else {
                alerta_erro(falha);
            }
        } finally {
            definir_enviando(false);
        }
    };

    const continuar = () => {
        const problema = validar_valor();

        if (problema) {
            definir_erro(problema);
            return;
        }

        if (meio === 'Cartão de crédito') definir_etapa('cartao');
        else enviar();
    };

    const conteudo = () => {
        if (!opcoes) return <Notice><Spinner tamanho={28} cor="#999" /></Notice>;

        if (!opcoes.disponivel) {
            return (
                <Notice>
                    <i className="material-icons" aria-hidden="true">info</i>
                    {opcoes.motivo}
                </Notice>
            );
        }

        if (etapa === 'sucesso') {
            return (
                <Success>
                    {/* check animado: o círculo se desenha, enche de verde e o check é traçado */}
                    <SuccessMark viewBox="0 0 52 52" aria-hidden="true">
                        <circle className="circulo" cx="26" cy="26" r="24" />
                        <path className="check" d="M15 27l7 7 15-15" />
                    </SuccessMark>
                    <p>{mensagem}</p>
                </Success>
            );
        }

        if (etapa === 'pix' && deposito?.pix) {
            return <PixCharge pix={deposito.pix} expirado={deposito.situacao && deposito.situacao !== 'Pendente'} ao_gerar_novo={() => enviar()} />;
        }

        if (etapa === 'cartao') {
            return (
                <CardPayment
                    valor={centavos_para_texto(para_centavos(valor))}
                    public_key={opcoes.public_key}
                    parcelas_maximas={opcoes.parcelas_maximas}
                    enviando={enviando}
                    erro={erro}
                    ao_pagar={enviar}
                />
            );
        }

        return (
            <>
                <Label htmlFor="valor_deposito">Digite o valor que deseja depositar</Label>
                <Field $erro={Boolean(erro)}>
                    <Prefix>R$</Prefix>
                    <Input
                        id="valor_deposito"
                        inputMode="decimal"
                        placeholder="0,00"
                        value={valor}
                        onChange={(evento) => { definir_valor(evento.target.value); definir_erro(''); }}
                    />
                </Field>
                <Hint>Mínimo {reais(opcoes.minimo)} · máximo {reais(opcoes.maximo)}</Hint>
                {erro && <ErrorText role="alert">{erro}</ErrorText>}
                <Chips>
                    {opcoes.valores_rapidos.map((rapido) => (
                        <Chip key={rapido} type="button" $ativo={para_centavos(valor) === para_centavos(rapido)} onClick={() => { definir_valor(rapido.replace('.', ',')); definir_erro(''); }}>
                            {reais(rapido)}
                        </Chip>
                    ))}
                </Chips>

                <BonusBox>
                    <Check>
                        <input type="checkbox" checked={aceita_bonus} onChange={() => definir_aceita_bonus(!aceita_bonus)} />
                        Aceito receber bônus
                    </Check>
                    <small>Marcando esta opção você aceita os termos do rollover do bônus.</small>
                </BonusBox>

                <Label as="p">Como você quer pagar?</Label>
                <Methods>
                    {opcoes.meios.map((nome) => (
                        <Method key={nome} type="button" $ativo={meio === nome} onClick={() => definir_meio(nome)} aria-pressed={meio === nome}>
                            <i className="material-icons" aria-hidden="true">{icones_meio[nome] ?? 'payments'}</i>
                            {nome}
                        </Method>
                    ))}
                </Methods>
            </>
        );
    };

    const rodape = () => {
        if (!opcoes || !opcoes.disponivel || etapa === 'sucesso') {
            return <ModalButton type="button" onClick={ao_fechar}>Fechar</ModalButton>;
        }

        if (etapa === 'valor') {
            return (
                <ModalButton type="button" onClick={continuar} disabled={enviando}>
                    {enviando ? <Spinner /> : 'Continuar'}
                </ModalButton>
            );
        }

        return (
            <ModalButton type="button" $secundario onClick={() => { parar_consulta(); definir_etapa('valor'); }} disabled={enviando}>
                Voltar
            </ModalButton>
        );
    };

    return (
        <ModalCard aberto={aberto} titulo="Realizar depósito" subtitulo={etapa === 'valor' ? null : `Valor: ${reais(valor || '0')}`} ao_fechar={ao_fechar} rodape={rodape()}>
            {conteudo()}
        </ModalCard>
    );
}
