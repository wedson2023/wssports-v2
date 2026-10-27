import { Error, Field, Fields, Input, Label, Textarea } from '../CashModal';
import { redes_sociais } from '../../hooks/useAfiliadosPainel';
import { Counter, Select } from './styles';

export const MAXIMO_SOBRE = 1000;

// seguidores digitados: só os dígitos, até 999.999.999
export const so_digitos_seguidores = (texto) => String(texto ?? '').replace(/\D/g, '').slice(0, 9);

// perfil do afiliado na rede social (spec 022, FR-016, R-08) na grade dos modais do painel: rede
// principal (seleção), link do perfil, seguidores (só números) e "sobre você" com contador.
// valores = { rede_social, link_perfil, quantidade_seguidores, sobre }; obrigatorios marca os três
// primeiros com "*"
export default function AffiliateProfileFields({ id = 'afiliado', valores, ao_mudar, erros = {}, obrigatorios = true }) {
    const marca = obrigatorios ? ' *' : '';

    return (
        <Fields>
            <Field>
                <Label htmlFor={`${id}_rede_social`}>{`Rede social principal${marca}`}</Label>
                <Select id={`${id}_rede_social`} value={valores.rede_social ?? ''} $erro={Boolean(erros.rede_social)} onChange={(evento) => ao_mudar({ rede_social: evento.target.value })}>
                    <option value="">Selecione</option>
                    {redes_sociais.map((rede) => <option key={rede} value={rede}>{rede}</option>)}
                </Select>
                {erros.rede_social && <Error role="alert">{erros.rede_social}</Error>}
            </Field>
            <Field>
                <Label htmlFor={`${id}_quantidade_seguidores`}>{`Quantidade de seguidores${marca}`}</Label>
                <Input
                    id={`${id}_quantidade_seguidores`}
                    inputMode="numeric"
                    placeholder="Ex.: 12000"
                    value={valores.quantidade_seguidores ?? ''}
                    $erro={Boolean(erros.quantidade_seguidores)}
                    onChange={(evento) => ao_mudar({ quantidade_seguidores: so_digitos_seguidores(evento.target.value) })}
                />
                {erros.quantidade_seguidores && <Error role="alert">{erros.quantidade_seguidores}</Error>}
            </Field>
            <Field $inteiro>
                <Label htmlFor={`${id}_link_perfil`}>{`Link do perfil${marca}`}</Label>
                <Input
                    id={`${id}_link_perfil`}
                    type="url"
                    placeholder="https://"
                    maxLength={255}
                    value={valores.link_perfil ?? ''}
                    $erro={Boolean(erros.link_perfil)}
                    onChange={(evento) => ao_mudar({ link_perfil: evento.target.value })}
                />
                {erros.link_perfil && <Error role="alert">{erros.link_perfil}</Error>}
            </Field>
            <Field $inteiro>
                <Label htmlFor={`${id}_sobre`}>Sobre você</Label>
                <Textarea
                    id={`${id}_sobre`}
                    maxLength={MAXIMO_SOBRE}
                    placeholder="Conte um pouco sobre o seu público e como vai divulgar."
                    value={valores.sobre ?? ''}
                    $erro={Boolean(erros.sobre)}
                    onChange={(evento) => ao_mudar({ sobre: evento.target.value })}
                />
                <Counter>{`${(valores.sobre ?? '').length}/${MAXIMO_SOBRE}`}</Counter>
                {erros.sobre && <Error role="alert">{erros.sobre}</Error>}
            </Field>
        </Fields>
    );
}
