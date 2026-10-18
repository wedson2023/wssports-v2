@extends('emails.layout')

@section('titulo', 'Olá, seja bem vindo :)')

@section('conteudo')
    <p>Olá, {{ $nome }}!</p>
    <p>Seu cadastro foi realizado com sucesso.</p>
    <p>Agora é só entrar e aproveitar a nossa plataforma.</p>
@endsection

@section('botao_url', $link)
@section('botao_texto', 'ACESSAR O SITE')
