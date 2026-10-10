<?php

use App\Models\Configuracoes;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Logo da banca e texto das regras, editados pelo painel.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('configuracoes', function (Blueprint $table) {
            // caminho no disco public (logos/{sha1}.{ext}); null = logo padrão do projeto
            $table->string('logo', 255)->nullable();
            // texto das regras da banca, um parágrafo por linha
            $table->text('regras')->nullable();
        });

        // o registro que já existe recebe o texto padrão; os criados depois, pelo model
        DB::table('configuracoes')->whereNull('regras')->update(['regras' => Configuracoes::REGRAS_PADRAO]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('configuracoes', function (Blueprint $table) {
            $table->dropColumn(['logo', 'regras']);
        });
    }
};
