<?php

namespace App\Http\Controllers;

use App\Http\Requests\AlterarSenhaAfiliadoRequest;
use App\Http\Requests\UpdateMeusDadosAfiliadoRequest;
use App\Http\Resources\MeusDadosAfiliadoResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Meus dados e troca de senha do afiliado (spec 012, FR-056 e FR-057).
 */
class AreaAfiliadoMeusDadosController extends Controller
{
    public function show(Request $request): MeusDadosAfiliadoResource
    {
        return new MeusDadosAfiliadoResource($request->user('afiliados')->load('meio_pagamento'));
    }

    /**
     * Contato e endereço no afiliado; tipo e chave Pix no meio de pagamento dele.
     */
    public function update(UpdateMeusDadosAfiliadoRequest $request): MeusDadosAfiliadoResource
    {
        $afiliado = $request->user('afiliados');
        $dados = $request->validated();

        DB::transaction(function () use ($afiliado, $dados) {
            $afiliado->update(Arr::except($dados, ['pix_tipo_chave', 'pix_chave']));

            if (isset($dados['pix_tipo_chave'])) {
                $afiliado->meio_pagamento()->updateOrCreate([], Arr::only($dados, ['pix_tipo_chave', 'pix_chave']));
            }
        });

        return new MeusDadosAfiliadoResource($afiliado->refresh()->load('meio_pagamento'));
    }

    /**
     * Troca a senha, faz os outros tokens deixarem de valer e devolve um token novo para esta sessão.
     */
    public function alterar_senha(AlterarSenhaAfiliadoRequest $request): JsonResponse
    {
        $afiliado = $request->user('afiliados');

        $afiliado->update(['password' => $request->validated('password')]);
        $afiliado->invalidar_tokens();

        // o token novo sai no mesmo segundo da data de corte e continua valendo (o middleware só
        // recusa iat anterior a ela)
        $token = auth('afiliados')->login($afiliado);

        return response()->json([
            'token' => $token,
            'tipo' => 'bearer',
            'expira_em' => auth('afiliados')->factory()->getTTL() * 60,
        ]);
    }
}
