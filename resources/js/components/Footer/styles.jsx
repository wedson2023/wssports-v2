import styled, { css } from 'styled-components';

// imagens brancas (jogo responsável e redes sociais): no modo claro ganham um contorno escuro para
// aparecer no fundo claro; no modo escuro ficam iguais ao sistema antigo
const contorno_no_modo_claro = css`
    ${({ theme }) => theme.modo === 'claro' && css`
        filter: drop-shadow(0 0 1px #555) drop-shadow(0 0 1px #555);
    `}
`;

// Footer, FooterLogo, FooterText, FooterImage, FooterIcons, FooterIcon, FooterLinks, FooterLink e
// FooterCopy de main/styles.js
export const Container = styled.div`
    padding-top: 15px;
    background-color: ${({ theme }) => theme.superficie_barra};
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: space-between;
    color: ${({ theme }) => theme.texto_principal};
`;

export const Logo = styled.img`
    width: 75px;
    margin-bottom: 10px;
`;

export const Text = styled.span`
    font-size: 0.9em;
    margin-bottom: 20px;
`;

export const ResponsibleImage = styled.img`
    width: 300px;
    max-width: 90%;
    margin-bottom: 20px;
    cursor: pointer;
    ${contorno_no_modo_claro}
`;

export const Icons = styled.div`
    display: flex;
    width: 200px;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
`;

export const SocialIcon = styled.img`
    width: 28px;
    cursor: pointer;
    ${contorno_no_modo_claro}
`;

export const Links = styled.div`
    display: flex;
    width: 280px;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
`;

const estilo_link = `
    text-decoration: none;
    font-size: 0.9em;
    cursor: pointer;

    &:hover {
        text-decoration: underline;
    }
`;

export const TextLink = styled.a`
    ${estilo_link}
    color: ${({ theme }) => theme.texto_principal};
`;

export const Copy = styled.div`
    font-size: 0.9em;
    background-color: ${({ theme }) => theme.superficie_titulo};
    padding: 10px 5px 75px 5px;
    text-align: center;
    width: 100%;
`;

export const Version = styled.span`
    font-size: 0.8em;
    color: ${({ theme }) => theme.texto_apagado};
    margin-top: 5px;
    display: block;
`;
