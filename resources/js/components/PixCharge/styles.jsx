import styled from 'styled-components';

export const Title = styled.h3`
    text-align: center;
    font-size: 17px;
    font-weight: 500;
    color: #222;
    margin-bottom: 12px;
`;

export const QrCode = styled.img`
    display: block;
    width: 180px;
    height: 180px;
    margin: 0 auto 12px;
    padding: 8px;
    border: 1px solid #e6e6e6;
    border-radius: 12px;
`;

export const Hint = styled.p`
    text-align: center;
    color: #666;
    font-size: 13px;
    margin: 8px 0;
`;

export const CodeBox = styled.div`
    position: relative;
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin: 12px 0;
`;

export const Code = styled.p`
    padding: 10px 12px;
    background: #f6f7f9;
    border: 1px dashed #c5c5c5;
    border-radius: 10px;
    font-family: monospace;
    font-size: 12px;
    color: #444;
    word-break: break-all;
    max-height: 72px;
    overflow-y: auto;
    user-select: all;
`;

export const Copied = styled.span`
    position: absolute;
    top: -8px;
    right: 8px;
    padding: 4px 10px;
    border-radius: 8px;
    background: #222;
    color: #fff;
    font-size: 12px;
`;

export const Timer = styled.p`
    text-align: center;
    font-size: 13px;
    font-weight: 500;
    color: ${({ theme }) => theme.principal};
`;

export const Status = styled.div`
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 12px;
    text-align: center;

    i {
        font-size: 48px;
        color: #999;
    }
`;
