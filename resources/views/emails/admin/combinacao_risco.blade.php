@extends('emails.layout')

@section('titulo', 'Combinação de risco nova')

@section('conteudo')
    <p style="text-align: left; display: inline-block;">{!! nl2br(e($texto)) !!}</p>
@endsection

@section('botao_url', $link)
@section('botao_texto', 'VER NO PAINEL')
