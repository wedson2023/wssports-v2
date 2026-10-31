@extends('emails.layout')

@section('titulo', 'Olá, seja bem vindo :)')

@section('conteudo')
    <p>Olá, {{ $nome }}!</p>
    <p>Falta só um passo: confirme seu e-mail para entrar na sua conta.</p>
    <p>O link vale por 24 horas. Se não foi você que se cadastrou, ignore este e-mail.</p>
@endsection

@section('botao_url', $link)
@section('botao_texto', 'CONFIRMAR E-MAIL')
