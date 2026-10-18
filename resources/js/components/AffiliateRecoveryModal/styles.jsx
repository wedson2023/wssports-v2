import styled from 'styled-components';
import { mobile } from '../../theme/tokens';
import { Container as ContainerCadastro, Form as FormCadastro } from '../AffiliateSignupModal/styles';

// mesmo visual do modal de cadastro de afiliado, mais estreito e com uma coluna
export {
    Caption, CloseButton, EyeButton, Field, FieldError, Header, Hint, InputIcon, Label, List, SubmitButton, Title,
} from '../AffiliateSignupModal/styles';

export const Container = styled(ContainerCadastro)`
    width: 420px;
    max-width: 94vw;

    ${mobile} {
        width: 100%;
        max-width: none;
    }
`;

export const Form = styled(FormCadastro)`
    grid-template-columns: 100%;
`;

export const Text = styled.p`
    margin: 0;
    font-size: 14px;
    color: #555;
`;

export const LinkButton = styled.button`
    justify-self: center;
    padding: 0;
    border: none;
    background: transparent;
    color: #666;
    font-size: 13px;
    text-decoration: underline;
    cursor: pointer;
`;
