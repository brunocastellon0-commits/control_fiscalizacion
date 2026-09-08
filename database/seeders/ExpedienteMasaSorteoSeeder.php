<?php

namespace Database\Seeders;

use App\Models\CatalogoActuado;
use App\Models\Expediente;
use App\Models\Reglamento;
use App\Models\Usuario;
use App\Services\ExpedienteService;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;

class ExpedienteMasaSorteoSeeder extends Seeder
{
    public const TAG_DEMO_MASA = '[DEMO-MASA]';

    public const CASOS_POR_VIA = 4;

    /**
     * Lote masivo de expedientes PENDIENTE_SORTEO (varias causas por vía)
     * para ejercitar el sorteo en lote ("Sortear todo") desde la workstation.
     * Idempotente por etiqueta propia.
     */
    public function run(): void
    {
        if ($this->yaExiste()) {
            $this->command->info('Expedientes masivos de sorteo ya sembrados ('.static::TAG_DEMO_MASA.'). Saltando.');

            return;
        }

        $tecnico = Usuario::where('username', 'tecnico')->first();
        $reglamento = Reglamento::where('codigo', 'AC_022_2018')->first();
        $catalogoRegistro = CatalogoActuado::where('codigo', 'ACT_REGISTRO_DIGITALIZACION')->first();

        if (! $tecnico || ! $reglamento || ! $catalogoRegistro) {
            $this->command->error('Faltan dependencias base (tecnico, reglamento AC_022_2018 o ACT_REGISTRO_DIGITALIZACION). Ejecutar DatabaseSeeder.');

            return;
        }

        $resumenes = [
            'TECNICO' => [
                'Presunta contaminación ambiental por residuos industriales sin licencia.',
                'Denuncia por incumplimiento de normas técnicas en obras de infraestructura.',
                'Fiscalización de permisos de funcionamiento otorgados sin inspección previa.',
                'Irregularidades en la gestión de residuos sólidos municipales.',
            ],
            'JURIDICO' => [
                'Revisión de nulidad en proceso de licitación con observaciones de legalidad.',
                'Denuncia por contratación directa de servicios jurídicos sin convocatoria.',
                'Fiscalización del cumplimiento de plazos procesales en resoluciones administrativas.',
                'Presunto conflicto de intereses en adjudicación de concesiones.',
            ],
            'FINANCIERO' => [
                'Presunta planilla de personal sin respaldo en el sistema contable.',
                'Revisión de transferencias presupuestarias sin aprobación del órgano competente.',
                'Desviación de fondos en proyectos de inversión municipal.',
                'Fiscalización de rendición de cuentas de convenios interinstitucionales.',
            ],
        ];

        $expedienteService = app(ExpedienteService::class);

        foreach ($resumenes as $via => $casos) {
            $this->command->info('Creando '.static::CASOS_POR_VIA." expedientes PENDIENTE_SORTEO (vía {$via})...");

            foreach (array_slice($casos, 0, static::CASOS_POR_VIA) as $resumen) {
                $expediente = $expedienteService->aperturaCausa(
                    datos: [
                        'via' => $via,
                        'reglamento_id' => $reglamento->id,
                        'resumen_hechos' => static::TAG_DEMO_MASA.' '.$resumen,
                        'partes' => [
                            ['tipo' => 'DENUNCIANTE', 'nombre_completo' => 'Unidad de Control Ciudadano', 'documento_identidad' => '5544332'],
                            ['tipo' => 'DENUNCIADO', 'nombre_completo' => 'Institución Pública', 'cargo_institucion' => 'Dirección General'],
                        ],
                    ],
                    tecnico: $tecnico,
                    adjunto: $this->crearPdfDummy(),
                );
                $this->command->line("  - NUREJ: {$expediente->nurej_code} | {$via} | PENDIENTE_SORTEO");
            }
        }

        $this->command->info('Lote masivo de expedientes de sorteo creado correctamente.');
    }

    private function yaExiste(): bool
    {
        return Expediente::where('resumen_hechos', 'like', '%'.static::TAG_DEMO_MASA.'%')->exists();
    }

    private function crearPdfDummy(): UploadedFile
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'demo_pdf_masa_');
        file_put_contents($tempFile, "%PDF-1.4\n% Archivo de prueba para lote masivo de expedientes\n%%EOF\n");

        return new UploadedFile($tempFile, 'denuncia_demo.pdf', 'application/pdf', null, true);
    }
}
