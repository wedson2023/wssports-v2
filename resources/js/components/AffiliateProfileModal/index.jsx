import { useEffect, useState } from 'react';

import ModalCard, { ModalButton } from '../ModalCard';
import Spinner from '../Spinner';
import { alerta_erro, alerta_sucesso } from '../../utils/alerts';
import {
    chave_pix_para_api, erros_por_campo, estados, formatar_chave_pix, formatar_telefone, somente_digitos, tipos_chave_pix,
} from '../../utils/forms';
import { ErrorText, Field, Grid, Hint, Input, Lock, Loading, Select } from './styles';

const aviso_travado = 'Para alterar, fale com a banca.';

// Meus dados do afiliado (spec 012, FR-056): nome, login, e-mail e CPF só leitura; contato,
// endereço e chave Pix editáveis
export default function AffiliateProfileModal({ aberto, requisitar, ao_fechar }) {
    const [dados, definir_dados] = useState(null);
    const [erros, definir_erros] = useState({});
    const [salvando, definir_salvando] = useState(false);

    useEffect(() => {
        if (!aberto) return;

        definir_dados(null);
        definir_erros({});

        requisitar({ url: '/area-afiliado/meus-dados' })
            .then(({ data }) => definir_dados({
                ...data,
                telefone: formatar_telefone(data.telefone ?? ''),
                pix_tipo_chave: data.pix_tipo_chave ?? '',
                pix_chave: formatar_chave_pix(data.pix_tipo_chave, data.pix_chave ?? ''),
            }))
            .catch((erro) => {
                if (![401, 403].includes(erro.response?.status)) alerta_erro(erro);
                ao_fechar();
            });
    }, [aberto, requisitar, ao_fechar]);

    const mudar = (campo) => (evento) => {
        const valor = evento.currentTarget.value;

        definir_dados((atuais) => {
            if (campo === 'pix_tipo_chave') return { ...atuais, pix_tipo_chave: valor, pix_chave: '' };
            if (campo === 'telefone') return { ...atuais, telefone: formatar_telefone(valor) };
            if (campo === 'pix_chave') return { ...atuais, pix_chave: formatar_chave_pix(atuais.pix_tipo_chave, valor) };

            return { ...atuais, [campo]: valor };
        });
        definir_erros((atuais) => ({ ...atuais, [campo]: undefined }));
    };

    const salvar = async () => {
        definir_salvando(true);

        try {
            await requisitar({
                url: '/area-afiliado/meus-dados',
                method: 'patch',
                data: {
                    telefone: somente_digitos(dados.telefone),
                    cidade: dados.cidade ?? '',
                    estado: dados.estado ?? '',
                    endereco: dados.endereco ?? '',
                    pix_tipo_chave: dados.pix_tipo_chave,
                    pix_chave: chave_pix_para_api(dados.pix_tipo_chave, dados.pix_chave),
                },
            });

            ao_fechar();
            alerta_sucesso('Dados atualizados!');
        } catch (erro) {
            if (erro.response?.status === 422) definir_erros(erros_por_campo(erro));
            else if (![401, 403].includes(erro.response?.status)) alerta_erro(erro);
        } finally {
            definir_salvando(false);
        }
    };

    const travado = (rotulo, valor) => (
        <Field>
            <span>{rotulo} <Lock className="material-icons" aria-hidden="true">lock</Lock></span>
            <Input value={valor ?? ''} disabled title={aviso_travado} />
        </Field>
    );

    const campo = (nome, rotulo, controle) => (
        <Field>
            <span>{rotulo}</span>
            {controle}
            {erros[nome] && <ErrorText>{erros[nome]}</ErrorText>}
        </Field>
    );

    const rodape = (
        <>
            <ModalButton type="button" $secundario onClick={ao_fechar} disabled={salvando}>Cancelar</ModalButton>
            <ModalButton type="button" onClick={salvar} disabled={salvando || !dados}>{salvando ? <Spinner /> : 'Salvar'}</ModalButton>
        </>
    );

    return (
        <ModalCard aberto={aberto} titulo="Meus dados" ao_fechar={ao_fechar} rodape={rodape} largura={560}>
            {!dados ? (
                <Loading><Spinner tamanho={28} cor="#999" /></Loading>
            ) : (
                <>
                    <Grid>
                        {travado('Nome', dados.nome)}
                        {travado('Login', dados.login)}
                        {travado('E-mail', dados.email)}
                        {travado('CPF', dados.cpf)}
                        {campo('telefone', 'Telefone', <Input type="tel" inputMode="numeric" value={dados.telefone} onChange={mudar('telefone')} />)}
                        {campo('cidade', 'Cidade', <Input value={dados.cidade ?? ''} onChange={mudar('cidade')} />)}
                        {campo('estado', 'Estado', (
                            <Select value={dados.estado ?? ''} onChange={mudar('estado')}>
                                <option value="">Selecione</option>
                                {estados.map(([uf, nome]) => <option key={uf} value={uf}>{nome}</option>)}
                            </Select>
                        ))}
                        {campo('endereco', 'Endereço', <Input value={dados.endereco ?? ''} onChange={mudar('endereco')} />)}
                        {campo('pix_tipo_chave', 'Tipo de chave Pix', (
                            <Select value={dados.pix_tipo_chave} onChange={mudar('pix_tipo_chave')}>
                                <option value="">Selecione</option>
                                {tipos_chave_pix.map((tipo) => <option key={tipo} value={tipo}>{tipo}</option>)}
                            </Select>
                        ))}
                        {campo('pix_chave', 'Chave Pix', <Input value={dados.pix_chave} onChange={mudar('pix_chave')} disabled={!dados.pix_tipo_chave} />)}
                    </Grid>
                    <Hint>Para alterar nome, login, e-mail ou CPF, fale com a banca.</Hint>
                </>
            )}
        </ModalCard>
    );
}
