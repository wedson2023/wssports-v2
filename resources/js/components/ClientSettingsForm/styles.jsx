import styled from 'styled-components';

// mesmos grupos, campos e chips de esportes das regras de aposta do vendedor (spec 015)
export { Chip, Chips, Error, Field, Grid, Group, GroupTitle, Input, Label, Select } from '../BettingRulesFields/styles';

export const Loading = styled.div`
    display: flex;
    justify-content: center;
    padding: 24px 0;
    color: ${({ theme }) => theme.principal};
`;

export const Footer = styled.div`
    display: flex;
    justify-content: flex-end;
    padding-top: 12px;
`;
