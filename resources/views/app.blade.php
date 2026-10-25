<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <meta name="robots" content="noindex">
    <meta name="apple-mobile-web-app-capable" content="yes">

    <link rel="manifest" href="/manifest.webmanifest">
    {{-- ícones gerados da logo da banca (spec 021, research R-06) --}}
    @php($icones = $page['props']['tema']['icones'])
    <link rel="icon" href="{{ $icones['favicon'] }}">
    <link rel="apple-touch-icon" href="{{ $icones['apple'][180] }}">
    <link rel="apple-touch-icon" sizes="152x152" href="{{ $icones['apple'][152] }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ $icones['apple'][180] }}">
    <link rel="apple-touch-icon" sizes="167x167" href="{{ $icones['apple'][167] }}">

    <script>
        // aplica o modo salvo (claro/escuro) antes do React desenhar, para não piscar o modo errado
        (function () {
            try {
                var modo = localStorage.getItem('wssports.modo');

                if (modo === 'claro' || modo === 'escuro') {
                    document.documentElement.dataset.modo = modo;
                    document.documentElement.style.backgroundColor = modo === 'claro' ? '#ffffff' : '#000000';
                }
            } catch (erro) {
                // sem acesso ao armazenamento: o React usa o modo configurado
            }
        })();
    </script>

    @viteReactRefresh
    @vite('resources/js/app.jsx')
    @inertiaHead
</head>

<body>
    @inertia
</body>

</html>
