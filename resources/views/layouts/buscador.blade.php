<div x-data="buscadorNurej()" class="hidden lg:flex items-center flex-1 max-w-md mx-2 relative" @click.outside="abierto = false">
    <div class="relative w-full">
        <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gris/70 text-xs"></i>
        <input type="search" x-model="q" @input.debounce.300ms="buscar()" @focus="siHayResultados()"
            placeholder="Buscar expediente por NURED..."
            class="w-full rounded-lg border border-gris-claro bg-white pl-8 pr-3 py-2 text-xs text-grafito focus:border-verde-institucional focus:ring-verde-institucional"
            aria-label="Buscar expediente por NURED">
    </div>

    <div x-show="abierto" x-cloak x-transition
        class="absolute top-full left-0 right-0 mt-2 bg-white border border-gris-claro rounded-xl shadow-xl z-50 overflow-hidden">
        <template x-if="cargando">
            <div class="p-3 text-xs text-gris"><i class="fa-solid fa-spinner fa-spin mr-2"></i>Buscando...</div>
        </template>
        <template x-if="!cargando && error">
            <div class="p-3 text-xs text-[#B53F12]"><i class="fa-solid fa-triangle-exclamation mr-1"></i><span x-text="error"></span></div>
        </template>
        <template x-if="!cargando && !error && resultados.length === 0 && q.trim().length >= 3">
            <div class="p-3 text-xs text-gris">No se encontraron expedientes con ese NURED.</div>
        </template>
        <template x-if="!cargando && resultados.length > 0">
            <ul class="max-h-72 overflow-y-auto divide-y divide-gris-claro">
                <template x-for="item in resultados" :key="item.id || item.nurej_code">
                    <li>
                        <a :href="`/expedientes/${item.id}`" class="block px-3 py-2.5 hover:bg-gris-claro/40">
                            <div class="flex items-center justify-between gap-2">
                                <span class="text-sm font-semibold text-grafito" x-text="item.nurej_code || item.codigo"></span>
                                <span class="text-[10px] px-2 py-0.5 rounded bg-gris-claro text-gris" x-text="item.estado?.nombre || item.estado || ''"></span>
                            </div>
                            <p class="text-xs text-gris mt-0.5 truncate" x-text="item.resumen_hechos || item.asignado_a || item.via || ''"></p>
                        </a>
                    </li>
                </template>
            </ul>
        </template>
        <template x-if="!cargando && !error && q.trim().length > 0 && q.trim().length < 3">
            <div class="p-3 text-xs text-gris">Ingrese al menos 3 caracteres para buscar.</div>
        </template>
    </div>

    <script>
        function buscadorNurej() {
            return {
                q: '',
                abierto: false,
                cargando: false,
                error: null,
                resultados: [],
                siHayResultados() {
                    if (this.q.trim().length > 0) this.abierto = true;
                },
                async buscar() {
                    const term = (this.q || '').trim();
                    this.abierto = term.length > 0;
                    this.error = null;
                    if (term.length < 3) {
                        this.resultados = [];
                        return;
                    }
                    this.cargando = true;
                    try {
                        const { ok, data } = await window.apiFetch(`/api/expedientes/buscar?q=${encodeURIComponent(term)}`);
                        if (ok) {
                            const lista = data.data || data || [];
                            this.resultados = Array.isArray(lista) ? lista : [];
                        } else {
                            this.resultados = [];
                            this.error = data?.message || 'No se pudo realizar la búsqueda.';
                        }
                    } catch (e) {
                        this.resultados = [];
                        this.error = e.message || 'Error al buscar.';
                    } finally {
                        this.cargando = false;
                    }
                }
            };
        }
    </script>
</div>
