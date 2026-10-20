import styled from 'styled-components';

// cores do sinal do valor no cartão em destaque, legíveis no fundo claro e no escuro
const verde = '#43a047';
const vermelho = '#e53935';

// Item, Icon, Label e Text de screens/adm/dashboard/styles.js, em formato de cartão; o destaque
// (Saldo líquido) usa o fundo do cartão com borda na cor do tema, para o valor manter o contraste
export const Card = styled.div`
    display: grid;
    grid-template-columns: 40px minmax(0, 1fr);
    grid-template-rows: auto auto;
    align-items: center;
    column-gap: 10px;
    row-gap: 2px;
    padding: 10px;
    border-radius: 6px;
    border: solid 2px ${({ theme, $destaque }) => ($destaque ? theme.principal : 'transparent')};
    background-color: ${({ theme }) => theme.superficie_barra};
    color: ${({ theme }) => theme.texto_principal};
    min-width: 0;
`;

export const Icon = styled.i.attrs(() => ({ className: 'material-icons', 'aria-hidden': true }))`
    grid-row: 1 / 3;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    border-radius: 8px;
    background-color: ${({ theme }) => theme.principal};
    color: #fff;
    font-size: 22px;
    user-select: none;
`;

export const Label = styled.span`
    font-size: 0.8em;
    font-weight: 500;
    color: ${({ theme }) => theme.texto_secundario};
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
`;

export const ValueRow = styled.div`
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 6px;
    min-width: 0;
`;

export const Value = styled.strong`
    font-size: ${({ $destaque }) => ($destaque ? '1.25em' : '1.05em')};
    font-weight: 500;
    color: ${({ $negativo, $destaque }) => {
        if ($negativo) return vermelho;
        return $destaque ? verde : 'inherit';
    }};
    white-space: nowrap;
`;

// selo de dado provisório (fake) até a spec de caixa
export const Badge = styled.span`
    padding: 1px 6px;
    border-radius: 10px;
    background-color: #f9a825;
    color: #000;
    font-size: 0.7em;
    font-weight: 500;
    white-space: nowrap;
`;
