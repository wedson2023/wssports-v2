import { useEffect, useId, useRef, useState } from 'react';

import { ModalButton } from '../ModalCard';
import Spinner from '../Spinner';
import { criar_campos } from '../../utils/mercado_pago';
import { formatar_real, para_centavos } from '../../utils/money';
import { Brand, ErrorText, Group, Input, Label, Notice, Row, SecureField, Select, Total } from './styles';

const estilo_campo = { fontSize: '15px', color: '#222', placeholderColor: '#999' };

const reais = (valor) => `R$ ${formatar_real(para_centavos(String(valor)))}`;

// pagamento com cartão (spec 007, US4): campos seguros do MercadoPago.js no visual do modal,
// bandeira, parcelas com juros pagos pelo cliente (até o máximo da banca) e total a pagar
export default function CardPayment({ valor, public_key, parcelas_maximas, enviando, erro, ao_pagar }) {
    const base = useId().replace(/:/g, '');
    const ids = { numero: `${base}_numero`, validade: `${base}_validade`, codigo: `${base}_codigo` };
    const sdk = useRef(null);
    const [pronto, definir_pronto] = useState(false);
    const [falha_sdk, definir_falha_sdk] = useState('');
    const [cartao, definir_cartao] = useState(null);
    const [parcelas, definir_parcelas] = useState(1);
    const [nome, definir_nome] = useState('');
    const [cpf, definir_cpf] = useState('');
    const [erro_local, definir_erro_local] = useState('');
    const [gerando, definir_gerando] = useState(false);

    useEffect(() => {
        let ativo = true;

        criar_campos({
            public_key,
            ids,
            estilo: estilo_campo,
            ao_mudar_bin: async (bin) => {
                if (!bin) {
                    definir_cartao(null);
                    return;
                }

                try {
                    const dados = await sdk.current.consultar(bin, valor);
                    if (ativo) {
                        definir_cartao(dados);
                        definir_parcelas(1);
                    }
                } catch {
                    if (ativo) definir_erro_local('Não reconhecemos este cartão. Confira o número.');
                }
            },
        })
            .then((criado) => {
                if (!ativo) {
                    criado.desmontar();
                    return;
                }

                sdk.current = criado;
                definir_pronto(true);
            })
            .catch((motivo) => ativo && definir_falha_sdk(motivo.message));

        return () => {
            ativo = false;
            sdk.current?.desmontar();
        };
        // os campos são montados uma vez por abertura do pagamento com cartão
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [public_key]);

    const opcoes = (cartao?.opcoes_parcelas ?? []).filter((opcao) => opcao.parcelas <= parcelas_maximas);
    const escolhida = opcoes.find((opcao) => opcao.parcelas === Number(parcelas));

    const pagar = async () => {
        definir_erro_local('');

        if (!cartao?.payment_method_id) {
            definir_erro_local('Informe o número do cartão.');
            return;
        }

        if (!nome.trim() || cpf.replace(/\D/g, '').length !== 11) {
            definir_erro_local('Informe o nome impresso no cartão e o CPF do titular.');
            return;
        }

        definir_gerando(true);

        try {
            const token = await sdk.current.gerar_token({ nome: nome.trim(), cpf });
            await ao_pagar({ token, parcelas: Number(parcelas), payment_method_id: cartao.payment_method_id, issuer_id: cartao.issuer_id });
        } catch {
            definir_erro_local('Confira os dados do cartão.');
        } finally {
            definir_gerando(false);
        }
    };

    if (falha_sdk) return <Notice>{falha_sdk}</Notice>;

    return (
        <>
            <Group>
                <Label>
                    Número do cartão
                    {cartao?.thumbnail && <Brand src={cartao.thumbnail} alt={cartao.bandeira} />}
                </Label>
                {/* o MercadoPago.js monta o iframe aqui dentro: a caixa não tem filhos do React */}
                <SecureField id={ids.numero} />
            </Group>
            <Row>
                <Group>
                    <Label>Validade</Label>
                    <SecureField id={ids.validade} />
                </Group>
                <Group>
                    <Label>Código</Label>
                    <SecureField id={ids.codigo} />
                </Group>
            </Row>
            <Group>
                <Label htmlFor={`${base}_nome`}>Nome impresso no cartão</Label>
                <Input id={`${base}_nome`} autoComplete="cc-name" value={nome} onChange={(evento) => definir_nome(evento.target.value)} />
            </Group>
            <Group>
                <Label htmlFor={`${base}_cpf`}>CPF do titular</Label>
                <Input id={`${base}_cpf`} inputMode="numeric" value={cpf} onChange={(evento) => definir_cpf(evento.target.value)} placeholder="000.000.000-00" />
            </Group>
            <Group>
                <Label htmlFor={`${base}_parcelas`}>Parcelas</Label>
                <Select id={`${base}_parcelas`} value={parcelas} onChange={(evento) => definir_parcelas(evento.target.value)} disabled={!opcoes.length}>
                    {opcoes.length
                        ? opcoes.map((opcao) => <option key={opcao.parcelas} value={opcao.parcelas}>{opcao.texto}</option>)
                        : <option value={1}>Informe o número do cartão</option>}
                </Select>
            </Group>
            <Total>
                Total a pagar
                <strong>{reais(escolhida?.total ?? valor)}</strong>
                <small>Você recebe {reais(valor)} de saldo. Juros do parcelamento por conta do titular.</small>
            </Total>
            {(erro_local || erro) && <ErrorText role="alert">{erro_local || erro}</ErrorText>}
            <ModalButton type="button" $largo onClick={pagar} disabled={!pronto || enviando || gerando}>
                {enviando || gerando ? <Spinner /> : `Pagar ${reais(escolhida?.total ?? valor)}`}
            </ModalButton>
        </>
    );
}
