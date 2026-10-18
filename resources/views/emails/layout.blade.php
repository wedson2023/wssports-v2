{{-- base dos e-mails no visual do antigo (wssports.bet/resources/views/emails/filiado/cadastro.blade.php) --}}
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $nome_sistema }}</title>
</head>
<body style="margin: 0; padding: 0; background: #ffffff;">
    <div style="display: block; text-align: center; font-family: 'Trebuchet MS', Arial, sans-serif; padding: 20px;">
        <h3 style="text-transform: uppercase;">@yield('titulo')</h3>
        <img src="{{ $logo }}" alt="{{ $nome_sistema }}" style="margin: 25px auto; width: 150px;" />

        <div style="font-size: 0.9em; font-family: Tahoma, Arial, sans-serif;">
            @yield('conteudo')
        </div>

        @hasSection('botao_url')
            <p style="margin-top: 30px;">
                <a href="@yield('botao_url')" style="padding: 15px; color: #ffffff !important; background: {{ $cor_principal }}; text-decoration: none; font-weight: 500;">@yield('botao_texto')</a>
            </p>
        @endif
    </div>
</body>
</html>
