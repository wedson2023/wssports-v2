import DecimalField from '../DecimalField';
import ToggleSwitch from '../ToggleSwitch';
import {
    Chip, Chips, Error, Field, Fields, Hint, Input, Label, ReadOnly, Secret, Select, SwitchRow, Textarea,
} from './styles';

// opção de seleção: texto simples ou { valor, rotulo }
const opcao = (item) => (typeof item === 'string' ? { valor: item, rotulo: item } : item);

// formulário de uma seção da tela Configurações (spec 021, research R-17): os campos vêm de uma
// definição (utils/settings_fields.js) e são desenhados pelo tipo, em duas colunas ou uma (cartões
// estreitos); no celular, sempre uma
export default function SettingsForm({ campos, valores, erros = {}, ao_mudar, somente_leitura = false, colunas = 2 }) {
    const visiveis = campos.filter((campo) => !campo.visivel || campo.visivel(valores));

    const controle = (campo) => {
        const id = `configuracao_${campo.chave}`;
        const valor = valores[campo.chave];
        const erro = erros[campo.chave];
        const desabilitado = somente_leitura || Boolean(campo.somente_leitura);
        const mudar = (novo) => ao_mudar(campo.chave, novo);

        switch (campo.tipo) {
            case 'interruptor':
                return (
                    <SwitchRow>
                        <ToggleSwitch ligado={Boolean(valor)} ao_trocar={mudar} desabilitado={desabilitado} rotulo={campo.rotulo} />
                        <span>{valor ? (campo.texto_ligado ?? 'Ligado') : (campo.texto_desligado ?? 'Desligado')}</span>
                    </SwitchRow>
                );
            // verdadeiro/falso como seleção Sim/Não (aba Operação)
            case 'sim_nao':
                return (
                    <Select id={id} value={valor ? 'sim' : 'nao'} onChange={(evento) => mudar(evento.currentTarget.value === 'sim')} disabled={desabilitado} $erro={Boolean(erro)}>
                        <option value="sim">Sim</option>
                        <option value="nao">Não</option>
                    </Select>
                );
            case 'valor':
            case 'percentual':
            case 'decimal':
                return <DecimalField id={id} valor={valor} ao_mudar={mudar} desabilitado={desabilitado} erro={Boolean(erro)} tipo={campo.tipo} />;
            case 'inteiro':
                return (
                    <Input
                        id={id}
                        inputMode="numeric"
                        value={valor ?? ''}
                        onChange={(evento) => {
                            const digitos = evento.currentTarget.value.replace(/\D/g, '');
                            mudar(digitos === '' ? null : Number(digitos));
                        }}
                        disabled={desabilitado}
                        $erro={Boolean(erro)}
                    />
                );
            case 'selecao':
                return (
                    <Select id={id} value={valor ?? ''} onChange={(evento) => mudar(evento.currentTarget.value)} disabled={desabilitado} $erro={Boolean(erro)}>
                        {campo.opcoes.map(opcao).map(({ valor: item, rotulo }) => <option key={item} value={item}>{rotulo}</option>)}
                    </Select>
                );
            case 'chips': {
                const marcados = Array.isArray(valor) ? valor : [];
                const alternar = (item) => mudar(marcados.includes(item) ? marcados.filter((atual) => atual !== item) : [...marcados, item]);

                return (
                    <Chips role="group" aria-label={campo.rotulo}>
                        {campo.opcoes.map(opcao).map(({ valor: item, rotulo }) => (
                            <Chip key={item} type="button" $marcado={marcados.includes(item)} aria-pressed={marcados.includes(item)} disabled={desabilitado} onClick={() => alternar(item)}>
                                {rotulo}
                            </Chip>
                        ))}
                    </Chips>
                );
            }
            case 'texto_longo':
                return <Textarea id={id} value={valor ?? ''} maxLength={campo.maximo} onChange={(evento) => mudar(evento.currentTarget.value)} disabled={desabilitado} $erro={Boolean(erro)} />;
            case 'telefone':
                return (
                    <Input
                        id={id}
                        inputMode="numeric"
                        value={valor ?? ''}
                        placeholder={campo.exemplo}
                        onChange={(evento) => mudar(evento.currentTarget.value.replace(/\D/g, '') || null)}
                        disabled={desabilitado}
                        $erro={Boolean(erro)}
                    />
                );
            case 'link':
                return <Input id={id} type="url" value={valor ?? ''} placeholder="https://" onChange={(evento) => mudar(evento.currentTarget.value.trim() || null)} disabled={desabilitado} $erro={Boolean(erro)} />;
            case 'data':
                return <Input id={id} type="date" value={valor ?? ''} onChange={(evento) => mudar(evento.currentTarget.value || null)} disabled={desabilitado} $erro={Boolean(erro)} />;
            case 'segredo':
                return (
                    <>
                        <Input
                            id={id}
                            type="password"
                            autoComplete="new-password"
                            value={valor ?? ''}
                            placeholder={valores[`${campo.chave}_definida`] ? 'Digite para trocar' : 'Não definida'}
                            onChange={(evento) => mudar(evento.currentTarget.value || null)}
                            disabled={desabilitado}
                            $erro={Boolean(erro)}
                        />
                        <Secret>
                            {valores[`${campo.chave}_definida`] ? `Definida (final ${valores[`${campo.chave}_final`]}). Deixe vazio para manter.` : 'Ainda não definida.'}
                        </Secret>
                    </>
                );
            case 'informacao':
                return <ReadOnly id={id}>{campo.formatar ? campo.formatar(valor, valores) : (valor ?? '—')}</ReadOnly>;
            default:
                return <Input id={id} value={valor ?? ''} maxLength={campo.maximo} placeholder={campo.exemplo} onChange={(evento) => mudar(evento.currentTarget.value)} disabled={desabilitado} $erro={Boolean(erro)} />;
        }
    };

    return (
        <Fields $colunas={colunas}>
            {visiveis.map((campo) => (
                <Field key={campo.chave} $inteiro={campo.inteiro}>
                    <Label htmlFor={`configuracao_${campo.chave}`}>{campo.rotulo}</Label>
                    {controle(campo)}
                    {campo.ajuda && <Hint>{campo.ajuda}</Hint>}
                    {erros[campo.chave] && <Error role="alert">{erros[campo.chave]}</Error>}
                </Field>
            ))}
        </Fields>
    );
}
