@extends('layouts.app')

@section('titulo', 'Bandeja de supervisión')

@section('contenido')
<div class="p-6" x-data="bandejaSupervisionEncargada()" x-init="cargar()">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-5">
        <div>
            <h1 class="text-xl font-bold text-grafito">Bandeja de supervisión</h1>
            <p class="text-sm text-gris">Causas que requieren su revisión o decisión como Encargada.</p>
        </div>
        <div class="flex items-center gap-3">
            <span x-show="cargando" class="text-sm text-gris inline-flex items-center gap-2"><i class="fa-solid fa-spinner fa-spin"></i> Actualizando...</span>
            <button type="button" @click="cargar()" class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-gris-claro bg-white text-sm text-grafito hover:bg-gris-claro/40">
                <i class="fa-solid fa-arrows-rotate" :class="{ 'fa-spin': cargando }"></i> Actualizar
            </button>
        </div>
    </div>

    <template x-if="error">
        <div class="bg-[#F15A24]/10 border border-[#F15A24]/30 text-[#B53F12] rounded-xl p-5 text-sm mb-5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
            <div><i class="fa-solid fa-triangle-exclamation mr-2"></i><span x-text="error"></span></div>
            <button type="button" @click="cargar()" class="underline font-medium">Reintentar</button>
        </div>
    </template>

    <div class="bg-white rounded-xl border border-gris-claro shadow-sm p-4 mb-5" x-show="!error">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
            <div>
                <label class="block text-xs font-medium text-grafito mb-1">Categoría</label>
                <select x-model="filtros.categoria" @change="cargar()"
                    class="w-full border border-gris-claro rounded-lg px-3 py-2 text-sm text-grafito focus:border-verde-institucional focus:ring-verde-institucional">
                    <option value="">Todas</option>
                    <option value="planificacion">Visto bueno de planificación</option>
                    <option value="ampliacion">Ampliaciones por resolver</option>
                    <option value="impugnacion">Impugnaciones recibidas</option>
                    <option value="informe_final">Informes finales para visto bueno</option>
                    <option value="incompetencia">Incompetencias por resolver</option>
                    <option value="derivacion">Causas por derivar</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-grafito mb-1">Buscar por NUREJ</label>
                <input type="text" x-model="filtros.q" placeholder="Ej.: 123/2026"
                    class="w-full border border-gris-claro rounded-lg px-3 py-2 text-sm text-grafito focus:border-verde-institucional focus:ring-verde-institucional">
            </div>
            <div class="flex items-end">
                <button type="button" @click="limpiarFiltros()" class="text-sm text-verde-profundo hover:underline">Limpiar filtros</button>
            </div>
        </div>
    </div>

    <template x-if="!cargando && !error && filtrados().length === 0">
        <div class="bg-white rounded-xl shadow-sm p-10 text-center text-gris">
            <i class="fa-solid fa-clipboard-check text-4xl mb-3 opacity-40"></i>
            <p class="font-medium text-grafito">No hay pendientes de supervisión</p>
            <p class="text-sm mt-1">Cuando existan actuaciones que requieran su decisión aparecerán aquí.</p>
        </div>
    </template>

    <div class="space-y-3" x-show="!error && filtrados().length > 0">
        <template x-for="item in filtrados()" :key="item.id || item.expediente_id || item.nurej_code">
            <article class="bg-white rounded-xl shadow-sm border-l-4 p-5" :class="bordeUrgencia(item)">
                <div class="flex flex-col lg:flex-row lg:items-start gap-3">
                    <div class="flex-1 min-w-0">
                        <div class="flex flex-wrap items-center gap-2 mb-1.5">
                            <a :href="`/expedientes/${item.expediente_id || item.id}`" class="font-semibold text-grafito hover:underline" x-text="item.nurej_code || item.nurej || 'Expediente'"></a>
                            <span x-show="item.accion_pendiente || item.categoria" class="px-2 py-0.5 rounded-full text-[10px] uppercase tracking-wide font-medium bg-amber-100 text-amber-800" x-text="etiquetaAccion(item)"></span>
                            <span x-show="item.semaforo?.codigo_color || item.urgencia" class="px-2 py-0.5 rounded-full text-[10px] uppercase tracking-wide font-bold text-white" :class="claseSemaforo(item)" x-text="textoSemaforo(item)"></span>
                        </div>
                        <p class="text-sm text-grafito" x-text="item.descripcion || item.resumen_hechos || item.partes || 'Sin descripción disponible.'"></p>
                        <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-gris">
                            <span x-show="item.asignado_a"><i class="fa-solid fa-user mr-1"></i><span x-text="item.asignado_a"></span></span>
                            <span x-show="item.plazo_restante !== undefined && item.plazo_restante !== null"><i class="fa-solid fa-hourglass-half mr-1"></i><span x-text="item.plazo_restante + ' día(s) restante(s)'"></span></span>
                            <span x-show="item.fecha_limite"><i class="fa-solid fa-calendar mr-1"></i>Límite: <span x-text="formatearFecha(item.fecha_limite)"></span></span>
                        </div>
                    </div>
                    <div class="shrink-0">
                        <a :href="`/expedientes/${item.expediente_id || item.id}`" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-verde-profundo text-white text-sm font-medium hover:bg-verde-institucional transition">
                            <i class="fa-solid fa-arrow-up-right-from-square"></i> Revisar
                        </a>
                    </div>
                </div>
            </article>
        </template>
    </div>
</div>

<script>
    function bandejaSupervisionEncargada() {
        return {
            cargando: false,
            error: null,
            items: [],
            filtros: { categoria: '', q: '' },
            async cargar() {
                this.cargando = true;
                this.error = null;
                try {
                    const params = new URLSearchParams();
                    if (this.filtros.categoria) params.set('categoria', this.filtros.categoria);
                    const qs = params.toString() ? `?${params.toString()}` : '';
                    const { ok, data, status } = await window.apiFetch(`/api/encargada/bandeja-supervision${qs}`);
                    if (ok) {
                        const payload = data.data ?? data.items ?? data ?? [];
                        this.items = Array.isArray(payload) ? payload : (Array.isArray(payload.data) ? payload.data : []);
                    } else if (status === 404) {
                        this.items = [];
                        this.error = 'La bandeja de supervisión aún no está disponible en el sistema. Intente más tarde.';
                    } else {
                        this.error = data?.message || 'No se pudo cargar la bandeja de supervisión.';
                        this.items = [];
                    }
                } catch (e) {
                    this.error = e.message || 'Error al cargar la bandeja de supervisión.';
                    this.items = [];
                } finally {
                    this.cargando = false;
                }
            },
            filtrados() {
                const q = (this.filtros.q || '').trim().toLowerCase();
                if (!q) return this.items;
                return this.items.filter(i => String(i.nurej_code || i.nurej || '').toLowerCase().includes(q));
            },
            limpiarFiltros() {
                this.filtros.categoria = '';
                this.filtros.q = '';
                this.cargar();
            },
            etiquetaAccion(item) {
                const map = {
                    planificacion: 'Visto bueno de planificación',
                    ampliacion: 'Ampliación por resolver',
                    impugnacion: 'Impugnación',
                    informe_final: 'Informe final',
                    incompetencia: 'Incompetencia',
                    derivacion: 'Derivación',
                };
                return item.accion_pendiente || map[item.categoria] || item.categoria || 'Pendiente';
            },
            claseSemaforo(item) {
                const color = item.semaforo?.codigo_color || item.urgencia || '';
                const map = { VERDE: 'bg-[#285C3A]', AMARILLO: 'bg-amber-500', ROJO: 'bg-red-500', FUERA_DE_PLAZO: 'bg-grafito' };
                return map[color] || 'bg-gris';
            },
            textoSemaforo(item) {
                const sem = item.semaforo;
                if (sem) {
                    if (sem.codigo_color === 'FUERA_DE_PLAZO') return 'Fuera de plazo';
                    if (sem.dias_restantes != null) return `${sem.codigo_color} · ${sem.dias_restantes} días`;
                    return sem.codigo_color;
                }
                if (item.plazo_restante != null) return `${item.plazo_restante} días`;
                return '';
            },
            bordeUrgencia(item) {
                const color = item.semaforo?.codigo_color || item.urgencia || '';
                const map = { VERDE: 'border-[#285C3A]', AMARILLO: 'border-amber-400', ROJO: 'border-red-500', FUERA_DE_PLAZO: 'border-grafito' };
                return map[color] || 'border-gris-claro';
            },
            formatearFecha(fecha) {
                if (!fecha) return '';
                const d = new Date(fecha);
                return isNaN(d) ? '' : d.toLocaleDateString('es-ES', { day: '2-digit', month: 'short', year: 'numeric' });
            }
        };
    }
</script>
@endsection
