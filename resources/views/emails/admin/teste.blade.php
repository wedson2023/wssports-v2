@extends('emails.layout')

@section('titulo', 'Teste de e-mail')

@section('conteudo')
    <p>Tudo certo: o e-mail da {{ $nome_sistema }} está funcionando.</p>
@endsection
