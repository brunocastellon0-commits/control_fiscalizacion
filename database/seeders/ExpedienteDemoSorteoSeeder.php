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
     * Expedientes PENDIENTE_SORTEO en vías JURIDICO y FINANCIERO para
     * ejercitar el sorteo hacia aud_juridico y aud_financiero (el lote base
     * solo cubrió la vía TECNICO). Idempotente por etiqueta propia.
     */
    public function run(): void
    {
        if ($this->yaExiste()) {
            $this->command->info('Expedientes de sorteos por vía ya sembrados ('.static::TAG_DEMO_VIAS.'). Saltando.');

            return;
        }

        $tecnico = Usuario::where('username', 'tecnico')->first();
        $reglamento = Reglamento::where('codigo', 'AC_022_2018')->first();
        $catalogoRegistro = CatalogoActuado::where('codigo', 'ACT_REGISTRO_DIGITALIZACION')->first();

        if (! $tecnico || ! $reglamento || ! $catalogoRegistro) {
            $this->command->error('Faltan dependencias base (tecnico, reglamento AC_022_2018 o ACT_REGISTRO_DIGITALIZACION). Ejecutar DatabaseSeeder.');

            return;
        }

        $casos = [
            'JURIDICO' => [
                'Revisión de legalidad en adjudicación directa de obras menores por la Alcaldía.',
                'Contrato de consultoría técnica suscrito sin proceso de licitación.',
                'Fiscalización de observaciones jurídicas en contratos de servicios municipales.',
            ],
            'FINANCIERO' => [
                'Registro de gastos sin respaldo documental en la gestión fiscal anterior.',
                'Presunta sobrevaloración de bienes consignados en acta de entrega municipal.',
                'Desviación de fondos del programa de fortalecimiento institucional.',
            ],
        ];

        $expedienteService = app(ExpedienteService::class);

        foreach ($casos as $via => $resumenes) {
            $this->command->info("Creando 3 expedientes PENDIENTE_SORTEO (vía {$via})...");

            foreach ($resumenes as $resumen) {
                $expediente = $expedienteService->aperturaCausa(
                    datos: [
                        'via' => $via,
                        'reglamento_id' => $reglamento->id,
                        'resumen_hechos' => static::TAG_DEMO_VIAS.' '.$resumen,
                        'partes' => [
                            ['tipo' => 'DENUNCIANTE', 'nombre_completo' => 'Contraloría Ciudadana', 'documento_identidad' => '7654321'],
                            ['tipo' => 'DENUNCIADO', 'nombre_completo' => 'Entidad Municipal', 'cargo_institucion' => 'Dirección Administrativa'],
                        ],
                    ],
                    tecnico: $tecnico,
                    adjunto: $this->crearPdfDummy(),
                );
                $this->command->line("  - NUREJ: {$expediente->nurej_code} | {$via} | PENDIENTE_SORTEO");
            }
        }

        $this->command->info('Expedientes de sorteo por vía creados correctamente.');
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
