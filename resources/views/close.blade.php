<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Voltando ao cassino</title>
    <style>
        body { margin: 0; background: #222; color: #ccc; font-family: Roboto, Arial, sans-serif; display: flex; align-items: center; justify-content: center; height: 100vh; }
    </style>
</head>
<body>
    <p>Voltando ao cassino...</p>
    <script>
        // saída do jogo do cassino (spec 011, R-09): dentro da moldura, avisa a página para fechar o
        // jogo; aberto na própria aba, volta para o cassino
        if (window.parent !== window) {
            window.parent.postMessage('closeGame', window.location.origin);
        } else {
            window.location.replace('/cassino');
        }
    </script>
</body>
</html>
