@extends('emails.layout')

@section('titulo', 'Recuperação de senha')

@section('conteudo')
    <p>Olá, {{ $nome }}!</p>
    <p>Use o código abaixo para criar uma senha nova. Ele vale por {{ $validade_minutos }} minutos.</p>
    <p style="font-size: 2em; font-weight: bold; letter-spacing: 6px; margin: 20px 0;">{{ $codigo }}</p>
    <p>Se não foi você que pediu, ignore este e-mail.</p>
@endsection
