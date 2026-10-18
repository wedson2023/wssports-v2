@extends('emails.layout')

@section('titulo', 'Cadastro de afiliado')

@section('conteudo')
    <p>Olá, {{ $nome }}!</p>
    <p>Infelizmente seu cadastro de afiliado não foi aprovado.</p>
    @if ($motivo)
        <p>Motivo: {{ $motivo }}</p>
    @endif
@endsection
