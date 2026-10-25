import { useEffect, useState } from 'react';

import { ler_valor, valor_para_campo } from '../CashModal';
import { Affix, Input } from './styles';

// prefixo ou sufixo de cada tipo
const afixos = { valor: { texto: 'R$' }, percentual: { texto: '%', sufixo: true } };

// número com até 2 casas digitado com vírgula ou ponto (spec 021): o campo guarda o texto digitado e
// avisa o valor da API ("50.00"); texto inválido vai como está, para o servidor recusar com a
// mensagem do campo. tipo: valor (R$), percentual (%) ou decimal (sem afixo)
export default function DecimalField({ id, valor, ao_mudar, desabilitado = false, erro = false, tipo = 'decimal' }) {
    const [texto, definir_texto] = useState(() => valor_para_campo(valor));

    // valor trocado de fora (Desfazer, recarga): o campo acompanha
    useEffect(() => {
        const atual = valor === null || valor === undefined || valor === '' ? null : ler_valor(valor);

        if (ler_valor(texto) !== atual) definir_texto(valor_para_campo(valor));
        // só reage ao valor recebido; o texto digitado não dispara a sincronização
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [valor]);

    const mudar = (evento) => {
        const digitado = evento.currentTarget.value;
        definir_texto(digitado);
        ao_mudar(digitado.trim() === '' ? null : (ler_valor(digitado) ?? digitado));
    };

    const campo = <Input id={id} inputMode="decimal" value={texto} onChange={mudar} disabled={desabilitado} $erro={erro} />;
    const afixo = afixos[tipo];

    if (!afixo) return campo;

    return (
        <Affix $sufixo={afixo.sufixo}>
            {campo}
            <span aria-hidden="true">{afixo.texto}</span>
        </Affix>
    );
}
