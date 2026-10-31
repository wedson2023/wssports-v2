@extends('emails.layout')

@section('titulo', 'Robô do WhatsApp desconectado')

@section('conteudo')
    <p>O robô da banca caiu, e as mensagens por WhatsApp estão paradas.</p>
    <p>Reconecte o robô para voltar a enviar.</p>
@endsection

@section('botao_url', $link)
@section('botao_texto', 'RECONECTAR')
