// impressão na impressora térmica Bluetooth pelo Web Bluetooth (Chrome no Android e no computador),
// como o connect_printer do sistema antigo (spec 006, R-06)

// serviço das impressoras térmicas usado pelo sistema antigo
const servico_impressora = 'e7810a71-73ae-499d-8c15-faa9aef0c3f2';

// tamanho de cada envio: as impressoras aceitam blocos pequenos
const tamanho_bloco = 20;

// característica de escrita da impressora conectada; fica só em memória enquanto a conexão durar
let caracteristica = null;

export const bluetooth_disponivel = () => typeof navigator !== 'undefined' && 'bluetooth' in navigator;

// primeira característica que aceita escrita, procurando em todos os serviços do aparelho
const procurar_caracteristica = async (servidor) => {
    const servicos = await servidor.getPrimaryServices();

    for (const servico of servicos) {
        const caracteristicas = await servico.getCharacteristics();
        const de_escrita = caracteristicas.find(({ properties }) => properties.write || properties.writeWithoutResponse);

        if (de_escrita) return de_escrita;
    }

    throw new Error('A impressora escolhida não aceita impressão.');
};

// pede ao vendedor para escolher a impressora (só na primeira vez) e guarda a característica
const conectar = async () => {
    const aparelho = await navigator.bluetooth.requestDevice({ acceptAllDevices: true, optionalServices: [servico_impressora] });

    aparelho.addEventListener('gattserverdisconnected', () => { caracteristica = null; });

    const servidor = await aparelho.gatt.connect();
    caracteristica = await procurar_caracteristica(servidor);

    return caracteristica;
};

// envia os bytes em blocos, um depois do outro; em erro, esquece a impressora (a próxima impressão
// pede para escolher de novo). Cancelar a escolha marca o erro com cancelado = true.
export const escrever = async (bytes) => {
    try {
        const destino = caracteristica ?? await conectar();

        for (let inicio = 0; inicio < bytes.length; inicio += tamanho_bloco) {
            const bloco = bytes.slice(inicio, inicio + tamanho_bloco);

            if (destino.properties.writeWithoutResponse && destino.writeValueWithoutResponse) await destino.writeValueWithoutResponse(bloco);
            else await destino.writeValue(bloco);
        }
    } catch (erro) {
        caracteristica = null;

        if (erro?.name === 'NotFoundError') erro.cancelado = true;

        throw erro;
    }
};
