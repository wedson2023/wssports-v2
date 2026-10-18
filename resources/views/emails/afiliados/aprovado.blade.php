@extends('emails.layout')

@section('titulo', 'Olá, seja bem vindo :)')

@section('conteudo')
    <p>Olá, {{ $nome }}!</p>
    <p>Parabéns, seu cadastro foi confirmado.</p>
    <p>Entre com o seu login e senha para acompanhar suas comissões.</p>
@endsection

@section('botao_url', $link)
@section('botao_texto', 'ACESSAR')
