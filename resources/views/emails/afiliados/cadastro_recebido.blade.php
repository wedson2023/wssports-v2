@extends('emails.layout')

@section('titulo', 'Olá, seja bem vindo :)')

@section('conteudo')
    <p>Olá, {{ $nome }}!</p>
    <p>Seu cadastro está em análise.</p>
    <p>Assim que possível lhe enviaremos um e-mail com a confirmação.</p>
@endsection
