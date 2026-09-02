<?php

namespace Database\Seeders;

use App\Models\CatalogoActuado;
use App\Models\CatalogoEstado;
use App\Models\Expediente;
use App\Models\ParametroPlazo;
use App\Models\Plazo;
use App\Models\Reglamento;
use App\Models\Rol;
use App\Models\Usuario;
use App\Services\ActuadoService;
use App\Services\ExpedienteService;
use App\Services\PlazoCalculatorService;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;

class ExpedienteDemoSeeder extends Seeder
{
    private const TAG_DEMO = '[DEMO]';

    private const TAG_PLAZOS = '[DEMO-PLAZOS]';

    public function run(): void
    {
        if ($this->yaExiste()) {
            $this->command->info('Expedientes de prueba ya sembrados ('.static::TAG_DEMO.'). Saltando.');

            return;
        }

        $this->asegurarDependencias();

        $tecnico = Usuario::where('username', 'tecnico')->first();
        $encargada = Usuario::where('username', 'encargada')->first();
        $audJuridico = Usuario::where('username', 'aud_juridico')->first();

        if (! $tecnico || ! $encargada || ! $audJuridico) {
            $this->command->error('Faltan usuarios base (tecnico, encargada, aud_juridico). Ejecutar primero UsuarioSeeder.');

            return;
        }

        $reglamento = Reglamento::where('codigo', 'AC_022_2018')->firstOrFail();

        $expedienteService = app(ExpedienteService::class);
        $actuadoService = app(ActuadoService::class);

        $this->command->info('Creando 3 expedientes en PENDIENTE_SORTEO...');
        $this->crearPendientesSorteo($expedienteService, $tecnico, $reglamento);

        $this->command->info('Creando 1 expediente en EN_EVALUACION (asignado a auditor)...');
        $this->crearEnEvaluacion($expedienteService, $actuadoService, $tecnico, $encargada, $audJuridico, $reglamento);

        $this->command->info('Creando 1 expediente con cadena de custodia (OBSERVADO)...');
        $this->crearConCadena($expedienteService, $actuadoService, $tecnico, $encargada, $audJuridico, $reglamento);

        $this->command->info('Creando 1 expediente con variantes de semaforo (plazos)...');
        $this->crearVariantesPlazo($expedienteService, $actuadoService, $tecnico, $encargada, $audJuridico, $reglamento);

        $this->command->info('Expedientes demo creados correctamente.');
    }

    private function yaExiste(): bool
    {
        return Expediente::where('resumen_hechos', 'like', '%'.static::TAG_DEMO.'%')->exists()
            || Expediente::where('resumen_hechos', 'like', '%'.static::TAG_PLAZOS.'%')->exists();
    }

    private function asegurarDependencias(): void
    {
        if (Rol::count() === 0) {
            $this->command->info('Ejecutando RolSeeder...');
            $this->call([RolSeeder::class]);
        }
        if (CatalogoEstado::count() === 0) {
            $this->command->info('Ejecutando CatalogoEstadoSeeder...');
            $this->call([CatalogoEstadoSeeder::class]);
        }
        if (Reglamento::count() === 0) {
            $this->command->info('Ejecutando ReglamentoSeeder...');
            $this->call([ReglamentoSeeder::class]);
        }
        if (CatalogoActuado::count() === 0) {
            $this->command->info('Ejecutando CatalogoActuadoSeeder...');
            $this->call([CatalogoActuadoSeeder::class]);
        }
        if (ParametroPlazo::count() === 0) {
            $this->command->info('Ejecutando ParametroPlazoSeeder...');
            $this->call([ParametroPlazoSeeder::class]);
        }
        if (Usuario::count() === 0) {
            $this->command->info('Ejecutando UsuarioSeeder...');
            $this->call([UsuarioSeeder::class]);
        }
    }

    private function crearPendientesSorteo(
        ExpedienteService $expedienteService,
        Usuario $tecnico,
        Reglamento $reglamento,
    ): void {
        $casos = [
            [
                'resumen' => 'Denuncia por incumplimiento de normativa ambiental en zona industrial.',
                'partes' => [
                    ['tipo' => 'DENUNCIANTE', 'nombre_completo' => 'Maria Lopez Mendoza'],
                    ['tipo' => 'DENUNCIADO', 'nombre_completo' => 'Empresa Industrial S.A.', 'cargo_institucion' => 'Gerente General'],
                ],
            ],
            [
                'resumen' => 'Fiscalizacion sobre presuntas irregularidades en contratacion publica.',
                'partes' => [
                    ['tipo' => 'DENUNCIANTE', 'nombre_completo' => 'Carlos Ramirez Vega'],
                    ['tipo' => 'DENUNCIADO', 'nombre_completo' => 'Direccion de Obras Publicas'],
                ],
            ],
            [
                'resumen' => 'Investigacion por posible uso indebido de recursos publicos municipales.',
                'partes' => [
                    ['tipo' => 'DENUNCIANTE', 'nombre_completo' => 'Pedro Suarez Torres', 'documento_identidad' => '9876543'],
                    ['tipo' => 'DENUNCIADO', 'nombre_completo' => 'Municipalidad de Cochabamba'],
                ],
            ],
        ];

        foreach ($casos as $caso) {
            $adjunto = $this->crearPdfDummy();
            $expediente = $expedienteService->aperturaCausa(
                datos: [
                    'via' => 'TECNICO',
                    'reglamento_id' => $reglamento->id,
                    'resumen_hechos' => static::TAG_DEMO.' '.$caso['resumen'],
                    'partes' => $caso['partes'],
                ],
                tecnico: $tecnico,
                adjunto: $adjunto,
            );
            $this->command->line('  - NUREJ: '.$expediente->nurej_code.' | PENDIENTE_SORTEO');
        }
    }

    private function crearEnEvaluacion(
        ExpedienteService $expedienteService,
        ActuadoService $actuadoService,
        Usuario $tecnico,
        Usuario $encargada,
        Usuario $audJuridico,
        Reglamento $reglamento,
    ): void {
        $adjunto = $this->crearPdfDummy();
        $expediente = $expedienteService->aperturaCausa(
            datos: [
                'via' => 'TECNICO',
                'reglamento_id' => $reglamento->id,
                'resumen_hechos' => static::TAG_DEMO.' Expediente en evaluacion: expediente con plazos abiertos para revisar documentacion.',
                'partes' => [
                    ['tipo' => 'DENUNCIANTE', 'nombre_completo' => 'Fiscalia Municipal'],
                    ['tipo' => 'DENUNCIADO', 'nombre_completo' => 'Impuesto Municipal S.A.'],
                ],
            ],
            tecnico: $tecnico,
            adjunto: $adjunto,
        );
        $this->command->line('  - NUREJ: '.$expediente->nurej_code.' | apertura OK');

        $catalogoSorteo = CatalogoActuado::where('codigo', 'ACT_SORTEO_INICIAL')->first();
        $actuadoService->registerActuado(
            expediente: $expediente,
            catalogoActuado: $catalogoSorteo,
            emisor: $encargada,
            descripcion: 'Sorteo inicial del expediente a auditoria juridica.',
            usuarioDestinoId: $audJuridico->id,
            metadatos: ['tipo' => 'SORTEO_INICIAL'],
        );
        $this->command->line('  - NUREJ: '.$expediente->fresh()->nurej_code.' | EN_EVALUACION | asignado a aud_juridico');
    }

    private function crearConCadena(
        ExpedienteService $expedienteService,
        ActuadoService $actuadoService,
        Usuario $tecnico,
        Usuario $encargada,
        Usuario $audJuridico,
        Reglamento $reglamento,
    ): void {
        $adjunto = $this->crearPdfDummy();
        $expediente = $expedienteService->aperturaCausa(
            datos: [
                'via' => 'TECNICO',
                'reglamento_id' => $reglamento->id,
                'resumen_hechos' => static::TAG_DEMO.' Expediente con cadena de custodia completa: registro, sorteo y observacion.',
                'partes' => [
                    ['tipo' => 'DENUNCIANTE', 'nombre_completo' => 'Ministerio Publico'],
                    ['tipo' => 'DENUNCIADO', 'nombre_completo' => 'Entidad Estatal Regional'],
                ],
            ],
            tecnico: $tecnico,
            adjunto: $adjunto,
        );
        $this->command->line('  - NUREJ: '.$expediente->nurej_code.' | apertura OK');

        $catalogoSorteo = CatalogoActuado::where('codigo', 'ACT_SORTEO_INICIAL')->first();
        $actuadoService->registerActuado(
            expediente: $expediente,
            catalogoActuado: $catalogoSorteo,
            emisor: $encargada,
            descripcion: 'Sorteo a auditoria juridica para investigacion.',
            usuarioDestinoId: $audJuridico->id,
            metadatos: ['tipo' => 'SORTEO_INICIAL'],
        );
        $this->command->line('  - NUREJ: '.$expediente->fresh()->nurej_code.' | sorteo OK -> EN_EVALUACION');

        $catalogoObservacion = CatalogoActuado::where('codigo', 'ACT_OBSERVACION')->first();
        $actuadoService->registerActuado(
            expediente: $expediente,
            catalogoActuado: $catalogoObservacion,
            emisor: $audJuridico,
            descripcion: 'Observacion: se requiere documentacion complementaria del expediente.',
            usuarioDestinoId: null,
            metadatos: ['tipo' => 'OBSERVACION'],
        );
        $expediente->refresh();
        $this->command->line('  - NUREJ: '.$expediente->nurej_code.' | OBSERVADO | cadena de custodia completa (3 actuados)');
    }

    private function crearVariantesPlazo(
        ExpedienteService $expedienteService,
        ActuadoService $actuadoService,
        Usuario $tecnico,
        Usuario $encargada,
        Usuario $audJuridico,
        Reglamento $reglamento,
    ): void {
        $adjunto = $this->crearPdfDummy();
        $expediente = $expedienteService->aperturaCausa(
            datos: [
                'via' => 'TECNICO',
                'reglamento_id' => $reglamento->id,
                'resumen_hechos' => static::TAG_PLAZOS.' Expediente de demostracion para validar la tarjeta de plazos y el semaforo.',
                'partes' => [
                    ['tipo' => 'DENUNCIANTE', 'nombre_completo' => 'Sociedad Civil Organizada'],
                    ['tipo' => 'DENUNCIADO', 'nombre_completo' => 'Servicio Departamental de Salud'],
                ],
            ],
            tecnico: $tecnico,
            adjunto: $adjunto,
        );
        $this->command->line('  - NUREJ: '.$expediente->nurej_code.' | apertura OK');

        $catalogoSorteo = CatalogoActuado::where('codigo', 'ACT_SORTEO_INICIAL')->first();
        $actuadoSorteo = $actuadoService->registerActuado(
            expediente: $expediente,
            catalogoActuado: $catalogoSorteo,
            emisor: $encargada,
            descripcion: 'Sorteo a auditoria juridica para el expediente de variantes de plazo.',
            usuarioDestinoId: $audJuridico->id,
            metadatos: ['tipo' => 'SORTEO_INICIAL'],
        );
        $this->command->line('  - NUREJ: '.$expediente->fresh()->nurej_code.' | EN_EVALUACION | asignado a aud_juridico');

        // El sorteo abre el plazo EVALUACION natural de 2 días hábiles (AMARILLO).
        // Se agregan variantes manuales para cubrir el resto del semáforo.
        $parametro = ParametroPlazo::where('reglamento_id', $reglamento->id)
            ->where('tipo_plazo', 'EVALUACION')
            ->whereNull('subtipo')
            ->first();

        if ($parametro === null) {
            $this->command->error('No se encontro el parametro de plazo EVALUACION. Se omiten las variantes.');

            return;
        }

        $calculadora = app(PlazoCalculatorService::class);

        $variantes = [
            ['estado' => 'VIGENTE', 'fecha_limite' => $calculadora->calculateDueDate(now(), 10)],
            ['estado' => 'VIGENTE', 'fecha_limite' => $calculadora->calculateDueDate(now(), 0)],
            ['estado' => 'FUERA_DE_PLAZO', 'fecha_limite' => now()->subDays(5)],
            ['estado' => 'CERRADO', 'fecha_limite' => now()->subDays(20)],
        ];

        foreach ($variantes as $variante) {
            Plazo::create([
                'expediente_id' => $expediente->id,
                'tipo_plazo' => 'EVALUACION',
                'parametro_plazo_id' => $parametro->id,
                'dias_habiles_otorgados' => $parametro->dias_habiles,
                'fecha_inicio' => now()->subDays(10),
                'fecha_limite' => $variante['fecha_limite'],
                'estado' => $variante['estado'],
                'actuado_disparador_id' => $actuadoSorteo->id,
            ]);
        }

        $this->command->line('  - NUREJ: '.$expediente->fresh()->nurej_code.' | plazos VERDE/AMARILLO/ROJO/FUERA/CERRADO');
    }

    private function crearPdfDummy(): UploadedFile
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'demo_pdf_');
        file_put_contents($tempFile, "%PDF-1.4\n% Archivo de prueba para expediente demo\n%%EOF\n");

        return new UploadedFile($tempFile, 'denuncia_demo.pdf', 'application/pdf', null, true);
    }
}
