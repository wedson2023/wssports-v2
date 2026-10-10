import styled from 'styled-components';

// reproduz o visual do react-responsive-carousel usado no sistema antigo (R-22)
export const Container = styled.div`
    position: relative;
    width: 100%;
    overflow: hidden;
    flex-shrink: 0;
`;

export const Track = styled.div`
    display: flex;
    transition: transform 350ms ease-in-out;
    transform: translateX(${({ $indice }) => `-${$indice * 100}%`});
`;

export const Slide = styled.a`
    display: block;
    height: auto;
    min-width: 100%;
    cursor: ${({ $com_link }) => ($com_link ? 'pointer' : 'default')};
`;

export const Image = styled.img`
    display: block;
    width: 100%;
    vertical-align: top;
    border: 0;
`;

// .control-arrow do carousel.min.css
export const Arrow = styled.button`
    position: absolute;
    top: 0;
    bottom: 0;
    ${({ $lado }) => ($lado === 'anterior' ? 'left: 0;' : 'right: 0;')}
    z-index: 2;
    padding: 5px;
    margin-top: 0;
    border: 0;
    background: none;
    font-size: 0;
    cursor: pointer;
    opacity: 0.4;
    transition: all 0.25s ease-in;

    &:hover {
        opacity: 1;
        background: rgba(0, 0, 0, 0.2);
    }

    &::before {
        content: '';
        display: inline-block;
        margin: 0 5px;
        border-top: 8px solid transparent;
        border-bottom: 8px solid transparent;
        ${({ $lado }) => ($lado === 'anterior' ? 'border-right: 8px solid #fff;' : 'border-left: 8px solid #fff;')}
    }
`;
