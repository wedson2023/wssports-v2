<?php

namespace App\Http\Controllers;

use App\Enums\AcaoConfiguracao;
use App\Http\Controllers\Concerns\GarantirPermissaoCliente;
use App\Http\Requests\ConfiguracoesLogoRequest;
use App\Models\Configuracoes;
use App\Services\ArmazenamentoImagens;
use App\Services\HistoricoConfiguracoes;
use App\Services\IconesAplicativo;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Logo da banca (spec 006, FR-032d): salva com o hash do conteúdo no nome, para a logo nova não
 * ficar presa no cache do navegador. Desde a spec 021 também gera os ícones do aplicativo (FR-011) e
 * entra no histórico. Só Admin e Supervisor.
 */
class ConfiguracoesLogoController extends Controller implements HasMiddleware
{
    use GarantirPermissaoCliente;

    public function __construct(private ArmazenamentoImagens $imagens, private IconesAplicativo $icones) {}

    public static function middleware(): array
    {
        return [self::permissao_cliente('configuracoes.editar')];
    }

    public function update(ConfiguracoesLogoRequest $request, HistoricoConfiguracoes $historico): JsonResponse
    {
        $configuracoes = Configuracoes::atual();
        $logo_anterior = $configuracoes->logo;
        $pasta_anterior = $configuracoes->pasta_icones;
        $arquivo = $request->file('logo');
        $conteudo = (string) file_get_contents($arquivo->getRealPath());

        // ícones primeiro: se a imagem não puder ser lida, a logo e os ícones atuais continuam
        try {
            $pasta = $this->icones->gerar($conteudo, $configuracoes->tema()['cor_fundo']);
        } catch (RuntimeException) {
            throw ValidationException::withMessages(['logo' => 'Envie uma imagem png, jpg ou webp de até 1 MB.']);
        }

        $configuracoes->forceFill([
            'logo' => $this->imagens->salvar($arquivo, 'logos'),
            'pasta_icones' => $pasta,
        ])->save();

        // logo trocada: a anterior e os ícones dela saem do disco (a mesma imagem de novo mantém o endereço)
        if ($configuracoes->logo !== $logo_anterior) {
            $this->imagens->remover($logo_anterior);
            $historico->registrar($request->user(), 'logo', AcaoConfiguracao::Alteração, null, [
                'logo' => ['antes' => $logo_anterior, 'depois' => $configuracoes->logo],
            ]);
        }

        if ($pasta !== $pasta_anterior) {
            $this->icones->remover($pasta_anterior);
        }

        return response()->json(['data' => [
            'logo' => $configuracoes->url_logo(),
            'icones' => $configuracoes->icones(),
            'aviso' => $this->icones->pequena($conteudo)
                ? 'A imagem tem menos de 192x192 pixels; os ícones grandes podem ficar borrados.'
                : null,
        ]]);
    }
}
