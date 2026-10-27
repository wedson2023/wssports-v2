import styled from 'styled-components';

import { Field as FieldBase, Input } from '../ProfileModal/styles';

// mesmos campos do "Meus dados" do cliente (ProfileModal), no visual novo dos modais (ModalCard)
export { ErrorText, Grid, Hint, Input, Lock, Select } from '../ProfileModal/styles';

export const Loading = styled.div`
    display: flex;
    justify-content: center;
    padding: 30px 0;
`;

// campo da grade; $inteiro ocupa as duas colunas (link do perfil e "sobre você", spec 022)
export const Field = styled(FieldBase)`
    grid-column: ${({ $inteiro }) => ($inteiro ? '1 / -1' : 'auto')};
`;

// "Sobre você": mesmo campo, com altura para o texto
export const Textarea = styled(Input).attrs({ as: 'textarea' })`
    height: auto;
    min-height: 90px;
    padding: 10px 12px;
    font-family: inherit;
    resize: vertical;
`;
