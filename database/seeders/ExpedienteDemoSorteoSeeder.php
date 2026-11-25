<?php

namespace Database\Seeders;

use App\Models\CatalogoActuado;
use App\Models\Expediente;
use App\Models\Reglamento;
use App\Models\Usuario;
use App\Services\ExpedienteService;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;

class ExpedienteDemoSorteoSeeder extends Seeder
{
    public const TAG_DEMO_VIAS = '[DEMO-VIAS]';

    /**
     * Expedientes PENDIENTE_SORTEO bajo AC-054 y AC-055 para ejercitar el
     * sorteo hacia aud_juridico y aud_financiero (el lote base solo cubre
     * AC-022). El origen de ingreso siempre es verbal. Idempotente por etiqueta.
     */
    public function run(): void
    {
        if ($this->yaExiste()) {
            $this->command->info('Expedientes de sorteos por vía ya sembrados ('.static::TAG_DEMO_VIAS.'). Saltando.');

            return;
        }

        $ventanilla = Usuario::where('username', 'ventanilla')->first();
        $reglamentoBase = Reglamento::where('codigo', Reglamento::CODIGO_AC_022)->first();
        $catalogoRegistro = CatalogoActuado::where('codigo', 'ACT_REGISTRO_DIGITALIZACION')->first();

        if (! $ventanilla || ! $reglamentoBase || ! $catalogoRegistro) {
            $this->command->error('Faltan dependencias base (ventanilla, reglamento AC-022/2018 o ACT_REGISTRO_DIGITALIZACION). Ejecutar DatabaseSeeder.');

            return;
        }

        $casos = [
            Reglamento::CODIGO_AC_054 => [
                'Revisión de legalidad en adjudicación directa de obras menores por la Alcaldía.',
                'Contrato de consultoría técnica suscrito sin proceso de licitación.',
                'Fiscalización de observaciones jurídicas en contratos de servicios municipales.',
            ],
            Reglamento::CODIGO_AC_055 => [
                'Registro de gastos sin respaldo documental en la gestión fiscal anterior.',
                'Presunta sobrevaloración de bienes consignados en acta de entrega municipal.',
                'Desviación de fondos del programa de fortalecimiento institucional.',
            ],
        ];

        $expedienteService = app(ExpedienteService::class);

        foreach ($casos as $codigoReglamento => $resumenes) {
            $reglamento = Reglamento::where('codigo', $codigoReglamento)->firstOrFail();
            $this->command->info("Creando 3 expedientes PENDIENTE_SORTEO (reglamento {$codigoReglamento})...");

            foreach ($resumenes as $resumen) {
                $expediente = $expedienteService->aperturaCausa(
                    datos: [
                        'via' => Expediente::VIA_INGRESO_VERBAL,
                        'reglamento_id' => $reglamento->id,
                        'resumen_hechos' => static::TAG_DEMO_VIAS.' '.$resumen,
                        'partes' => [
                            ['tipo' => 'DENUNCIANTE', 'nombre_completo' => 'Contraloría Ciudadana', 'documento_identidad' => '7654321'],
                            ['tipo' => 'DENUNCIADO', 'nombre_completo' => 'Entidad Municipal', 'cargo_institucion' => 'Dirección Administrativa'],
                        ],
                    ],
                    ventanilla: $ventanilla,
                    adjunto: $this->crearPdfDummy(),
                );
                $this->command->line("  - NUREJ: {$expediente->nurej_code} | {$codigoReglamento} | PENDIENTE_SORTEO");
            }
        }

        $this->command->info('Expedientes de sorteo por reglamento creados correctamente.');
    }

    private function yaExiste(): bool
    {
        return Expediente::where('resumen_hechos', 'like', '%'.static::TAG_DEMO_VIAS.'%')->exists();
    }

    private function crearPdfDummy(): UploadedFile
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'demo_pdf_via_');
        file_put_contents($tempFile, "%PDF-1.4\n% Archivo de prueba para expediente demo por vía\n%%EOF\n");

        return new UploadedFile($tempFile, 'denuncia_demo.pdf', 'application/pdf', null, true);
    }
}
