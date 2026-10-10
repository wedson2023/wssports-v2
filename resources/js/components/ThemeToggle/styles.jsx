import styled from 'styled-components';

// botão dia/noite (novo, aprovado pelo responsável): ícone na cor do tema, no padrão dos ícones do
// cabeçalho
export const Button = styled.i.attrs(() => ({ className: 'material-icons' }))`
    color: ${({ theme }) => theme.principal};
    font-size: 30px;
    cursor: pointer;
    user-select: none;
    justify-self: end;
    transition-duration: 0.3s;

    &:hover {
        opacity: 0.8;
    }
`;
