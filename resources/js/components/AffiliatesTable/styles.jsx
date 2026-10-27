import styled from 'styled-components';

// mesma tabela da lista de apostas (spec 018) e das promoções (spec 020)
export {
    Actions, Cell, Code, Detail, Details, DetailsRow, Empty, Expand, HeadCell, Retry, Row, Scroll, Skeleton, Sub, Table,
} from '../BetsTable/styles';

// cor do selo de cada situação do afiliado, legível nos dois temas com texto branco
const cores_situacao = {
    Ativo: '#2e7d32',
    Inativo: '#757575',
    'Em análise': '#1565c0',
    Recusado: '#c62828',
};

export const Status = styled.span`
    display: inline-block;
    padding: 4px 8px;
    border-radius: 0.3em;
    background-color: ${({ $situacao }) => cores_situacao[$situacao] ?? '#757575'};
    color: #fff;
    font-size: 0.85em;
    white-space: nowrap;
`;

// a receber negativo (saldo líquido com mais saques que depósitos) em vermelho
export const Money = styled.span`
    color: ${({ $negativo }) => ($negativo ? '#c62828' : 'inherit')};
    font-weight: bold;
    white-space: nowrap;
`;

// afiliado sem gerente responsável, em destaque para o Admin escolher um (spec 019)
export const Alert = styled.span`
    display: inline-flex;
    padding: 2px 6px;
    border-radius: 0.3em;
    background-color: #ef6c00;
    color: #fff;
    font-size: 0.85em;
    white-space: nowrap;
`;

export const ProfileLink = styled.a`
    color: ${({ theme }) => theme.principal};
    font-weight: 500;
    text-decoration: underline;
    white-space: nowrap;
`;
