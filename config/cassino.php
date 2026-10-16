<?php

/**
 * Regras do catálogo e da carteira do cassino (spec 011). As credenciais do provedor ficam em
 * config/services.php (provedor_cassino), lidas do .env.
 */
return [

    // provedores com grafias diferentes no campo "title" do provedor, já normalizadas (minúsculas,
    // sem espaço, "_" nem "-"), unificados no mesmo código (R-02). A normalização já junta
    // "NetEnt"/"net ent", "micro-gaming"/"microgaming" e "nolimit_city"/"nolimitcity"
    'apelidos_provedores' => [
        'netentp' => 'netent',
    ],

    // sufixos do canal (label) dos canais pré-pagos, que exigem crédito do hall: entram desativados e
    // marcados "bônus primeiro" (R-06)
    'sufixos_pre_pagos' => ['_sr', '_sz'],

    // prioridade de tentativa na abertura pelo sufixo do canal (menor primeiro); "" = sem sufixo
    'prioridade_sufixos' => [
        '' => 10,
        '_op' => 10,
        '_kn' => 20,
        '_sl' => 30,
        '_xg' => 30,
        '_asia' => 40,
        '_premium' => 40,
    ],

    // prioridade de canal sem sufixo conhecido e de canal pré-pago
    'prioridade_padrao' => 50,
    'prioridade_pre_pago' => 90,

    // continente enviado na abertura do jogo
    'continente' => 'eur',

    // horas em que um canal recusado pelo provedor fica indisponível antes de ser tentado de novo (R-08)
    'horas_indisponibilidade_canal' => 24,

    // minutos de cache da tela inicial do cassino (R-07)
    'minutos_cache_inicio' => 5,

    // horas em que a sessão de jogo ainda vale para a consulta de saldo e para reaproveitar a sessão (R-11, R-17)
    'horas_sessao_saldo' => 12,

    // dias em que as chamadas recusadas ou com erro ficam guardadas (R-15)
    'dias_retencao_recusadas' => 90,

];
