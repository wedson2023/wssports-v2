<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\GarantirPermissaoCliente;
use App\Http\Requests\ConfiguracoesLogoRequest;
use App\Models\Configuracoes;
use App\Services\ArmazenamentoImagens;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;

/**
 * Logo da banca (spec 006, FR-032d): salva com o hash do conteúdo no nome, para a logo nova não
 * ficar presa no cache do navegador. Só Admin e Supervisor.
 */
class ConfiguracoesLogoController extends Controller implements HasMiddleware
{
    use GarantirPermissaoCliente;

    public function __construct(private ArmazenamentoImagens $imagens) {}

    public static function middleware(): array
    {
        return [self::permissao_cliente('configuracoes.editar')];
    }

    public function update(ConfiguracoesLogoRequest $request): JsonResponse
    {
        $configuracoes = Configuracoes::atual();
        $anterior = $configuracoes->logo;

        $configuracoes->logo = $this->imagens->salvar($request->file('logo'), 'logos');
        $configuracoes->save();

        // logo trocada: a anterior sai do disco (a mesma imagem de novo mantém o endereço)
        if ($configuracoes->logo !== $anterior) {
            $this->imagens->remover($anterior);
        }

        return response()->json(['data' => ['logo' => $configuracoes->url_logo()]]);
    }
}
