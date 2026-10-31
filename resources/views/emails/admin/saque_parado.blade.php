@extends('emails.layout')

@section('titulo', 'Saque aguardando há 30 minutos')

@section('conteudo')
    <p><strong>Cliente:</strong> {{ $nome }}</p>
    <p><strong>Valor:</strong> {{ $valor }}</p>
    <p><strong>Chave Pix ({{ $tipo_chave }}):</strong> {{ $chave }}</p>
    <p>Confira e dê baixa no painel.</p>
@endsection

@section('botao_url', $link)
@section('botao_texto', 'VER SAQUE')
