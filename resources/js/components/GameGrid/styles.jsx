import styled from 'styled-components';

export const Games = styled.div`
    display: grid;
    grid-template-columns: calc(33.33% - 10px) calc(33.33% - 10px) calc(33.33% - 10px);
    grid-gap: 15px;
    width: 90%;
    margin: 25px auto;
`;

export const Message = styled.p`
    color: #999;
    font-size: 12px;
    text-align: center;
    display: block;
    width: 70%;
    margin: 100px auto 200px auto;
`;
