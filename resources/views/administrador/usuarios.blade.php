@extends('layouts.app')

@section('titulo', 'Gestión de Usuarios')

@section('contenido')

    <div x-data="usuariosAdmin()" x-init="cargar()" class="p-6 space-y-6">

        {{-- ENCABEZADO --}}
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-grafito">Gestión de Usuarios</h1>
                <p class="text-sm text-gris mt-1">Alta, edición, activación e inactivación de cuentas del sistema.</p>
            </div>

            <div class="flex items-center gap-2">
                <button @click="cargar()" class="px-4 py-2 rounded-lg border border-gris-claro text-grafito hover:bg-white transition text-sm">
                    <i class="fa-solid fa-rotate mr-1.5" :class="{ 'fa-spin': cargando }"></i>Actualizar
                </button>
                <button @click="abrirCrear()" class="px-4 py-2 rounded-lg bg-verde-profundo text-white hover:bg-verde-institucional transition text-sm font-medium">
                    <i class="fa-solid fa-user-plus mr-1.5"></i>Nuevo usuario
                </button>
            </div>
        </div>

        {{-- MENSAJE DE ERROR --}}
        <template x-if="error">
            <div class="p-4 rounded-lg bg-[#F15A24]/10 border border-[#F15A24]/30 text-[#B53F12] text-sm">
                <i class="fa-solid fa-triangle-exclamation mr-2"></i><span x-text="error"></span>
            </div>
        </template>

        {{-- FILTROS --}}
        <div class="bg-white rounded-xl shadow-sm p-5">
            <div class="flex flex-col md:flex-row gap-4">
                <div class="flex-1">
                    <label class="block text-sm font-medium text-grafito mb-1">Buscar</label>
                    <input type="text" x-model="filtros.buscar" @input="onFiltroTexto()"
                           placeholder="CI, nombres, apellidos o usuario..."
                           class="w-full border border-gris-claro rounded-lg px-4 py-2 text-sm focus:ring-2 focus:ring-verde-institucional focus:outline-none">
                </div>

                <div class="md:w-56">
                    <label class="block text-sm font-medium text-grafito mb-1">Rol</label>
                    <select x-model="filtros.rol_id" @change="cargar()"
                            class="w-full border border-gris-claro rounded-lg px-4 py-2 text-sm">
                        <option value="">Todos</option>
                        <template x-for="rol in roles" :key="rol.id">
                            <option :value="rol.id" x-text="rol.nombre"></option>
                        </template>
                    </select>
                </div>

                <div class="md:w-48">
                    <label class="block text-sm font-medium text-grafito mb-1">Estado</label>
                    <select x-model="filtros.activo" @change="cargar()"
                            class="w-full border border-gris-claro rounded-lg px-4 py-2 text-sm">
                        <option value="">Todos</option>
                        <option value="1">Activos</option>
                        <option value="0">Inactivos</option>
                    </select>
                </div>
            </div>
        </div>

        {{-- TABLA --}}
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gris-claro">
                        <tr>
                            <th class="px-4 py-3 text-left">CI</th>
                            <th class="px-4 py-3 text-left">Nombres</th>
                            <th class="px-4 py-3 text-left">Apellidos</th>
                            <th class="px-4 py-3 text-left">Usuario</th>
                            <th class="px-4 py-3 text-left">Cargo</th>
                            <th class="px-4 py-3 text-left">Rol</th>
                            <th class="px-4 py-3 text-center">Estado</th>
                            <th class="px-4 py-3 text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-if="cargando">
                            <tr>
                                <td colspan="8" class="px-4 py-8 text-center text-gris">
                                    <i class="fa-solid fa-spinner fa-spin mr-2"></i>Cargando usuarios...
                                </td>
                            </tr>
                        </template>

                        <template x-if="!cargando" x-for="usuario in usuarios" :key="usuario.id">
                            <tr class="border-t border-gris-claro hover:bg-gris-claro/40">
                                <td class="px-4 py-3" x-text="usuario.ci ?? '-'"></td>
                                <td class="px-4 py-3" x-text="usuario.nombres ?? '-'"></td>
                                <td class="px-4 py-3" x-text="usuario.apellidos ?? '-'"></td>
                                <td class="px-4 py-3" x-text="usuario.username ?? '-'"></td>
                                <td class="px-4 py-3" x-text="usuario.cargo ?? '-'"></td>
                                <td class="px-4 py-3">
                                    <span class="px-2 py-1 rounded-full text-xs font-medium bg-verde-claro/60 text-verde-profundo"
                                          x-text="usuario.rol?.nombre ?? usuario.rol?.codigo ?? '-'"></span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span x-show="usuario.activo" class="px-2 py-1 rounded-full text-xs font-medium bg-[#8CC63F]/20 text-[#3F5E1B]">Activo</span>
                                    <span x-show="!usuario.activo" class="px-2 py-1 rounded-full text-xs font-medium bg-[#F15A24]/15 text-[#B53F12]">Inactivo</span>
                                </td>
                                <td class="px-4 py-3 text-center whitespace-nowrap">
                                    <button @click="abrirEditar(usuario)" class="px-2.5 py-1 rounded-lg border border-gris-claro text-grafito text-xs hover:bg-gris-claro transition mr-1.5">
                                        <i class="fa-solid fa-pen mr-1"></i>Editar
                                    </button>
                                    <button x-show="usuario.activo" @click="inactivar(usuario)"
                                            class="px-2.5 py-1 rounded-lg bg-[#F15A24] text-white text-xs hover:bg-[#B53F12] transition">
                                        Inactivar
                                    </button>
                                    <button x-show="!usuario.activo" @click="activar(usuario)"
                                            class="px-2.5 py-1 rounded-lg bg-verde-profundo text-white text-xs hover:bg-verde-institucional transition">
                                        Activar
                                    </button>
                                </td>
                            </tr>
                        </template>

                        <template x-if="!cargando && usuarios.length === 0">
                            <tr>
                                <td colspan="8" class="px-4 py-10 text-center text-gris">
                                    <i class="fa-solid fa-user-slash text-3xl mb-2 opacity-40 block"></i>
                                    No se encontraron usuarios con los filtros aplicados.
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>

        {{-- MODAL CREAR/EDITAR --}}
        <div x-show="modalAbierto" x-cloak
             class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
             @keydown.escape.window="cerrarModal()">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-lg p-6" @click.outside="cerrarModal()">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-bold text-grafito" x-text="modoEdicion ? 'Editar usuario' : 'Nuevo usuario'"></h2>
                    <button @click="cerrarModal()" class="text-gris hover:text-grafito"><i class="fa-solid fa-xmark"></i></button>
                </div>

                <form @submit.prevent="guardar()" class="space-y-3">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-grafito mb-1">CI</label>
                            <input type="text" x-model="form.ci" required
                                   class="w-full border border-gris-claro rounded-lg px-3 py-2 text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-grafito mb-1">Usuario</label>
                            <input type="text" x-model="form.username" required
                                   class="w-full border border-gris-claro rounded-lg px-3 py-2 text-sm">
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-grafito mb-1">Nombres</label>
                            <input type="text" x-model="form.nombres" required
                                   class="w-full border border-gris-claro rounded-lg px-3 py-2 text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-grafito mb-1">Apellidos</label>
                            <input type="text" x-model="form.apellidos" required
                                   class="w-full border border-gris-claro rounded-lg px-3 py-2 text-sm">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-grafito mb-1">Cargo</label>
                        <input type="text" x-model="form.cargo"
                               class="w-full border border-gris-claro rounded-lg px-3 py-2 text-sm">
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-grafito mb-1">Rol</label>
                            <select x-model="form.rol_id" required class="w-full border border-gris-claro rounded-lg px-3 py-2 text-sm">
                                <option value="" disabled>Seleccione...</option>
                                <template x-for="rol in roles" :key="rol.id">
                                    <option :value="rol.id" x-text="rol.nombre"></option>
                                </template>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-grafito mb-1">
                                <span x-text="modoEdicion ? 'Nueva contraseña' : 'Contraseña'"></span>
                                <span x-show="modoEdicion" class="text-gris">(opcional)</span>
                            </label>
                            <input type="password" x-model="form.password" :required="!modoEdicion" minlength="8"
                                   placeholder="Mínimo 8 caracteres"
                                   class="w-full border border-gris-claro rounded-lg px-3 py-2 text-sm">
                        </div>
                    </div>

                    <template x-if="erroresForm">
                        <div class="p-3 rounded-lg bg-[#F15A24]/10 border border-[#F15A24]/30 text-[#B53F12] text-xs whitespace-pre-line" x-text="erroresForm"></div>
                    </template>

                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" @click="cerrarModal()" class="px-4 py-2 rounded-lg border border-gris-claro text-sm text-grafito hover:bg-gris-claro">
                            Cancelar
                        </button>
                        <button type="submit" :disabled="guardando"
                                class="px-4 py-2 rounded-lg bg-verde-profundo text-white text-sm hover:bg-verde-institucional disabled:opacity-50">
                            <i class="fa-solid fa-spinner fa-spin mr-1.5" x-show="guardando"></i>
                            <span x-text="modoEdicion ? 'Guardar cambios' : 'Crear usuario'"></span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function usuariosAdmin() {
            return {
                usuarios: [],
                roles: [],
                filtros: { buscar: '', rol_id: '', activo: '' },
                cargando: true,
                error: '',
                _debounce: null,

                modalAbierto: false,
                modoEdicion: false,
                guardando: false,
                erroresForm: '',
                form: { id: null, ci: '', nombres: '', apellidos: '', cargo: '', username: '', password: '', rol_id: '' },

                async cargar() {
                    this.cargando = true;
                    this.error = '';
                    try {
                        const params = new URLSearchParams();
                        if (this.filtros.buscar) params.set('buscar', this.filtros.buscar);
                        if (this.filtros.rol_id) params.set('rol_id', this.filtros.rol_id);
                        if (this.filtros.activo !== '') params.set('activo', this.filtros.activo);

                        const { ok, data } = await window.apiFetch(`/api/admin/usuarios?${params.toString()}`);
                        if (!ok) {
                            throw new Error(data?.message || 'No se pudieron cargar los usuarios.');
                        }

                        this.usuarios = data.data ?? [];
                        this.roles = data.roles ?? this.roles;
                    } catch (e) {
                        this.error = e.message;
                        this.usuarios = [];
                    } finally {
                        this.cargando = false;
                    }
                },

                onFiltroTexto() {
                    clearTimeout(this._debounce);
                    this._debounce = setTimeout(() => this.cargar(), 350);
                },

                abrirCrear() {
                    this.modoEdicion = false;
                    this.erroresForm = '';
                    this.form = { id: null, ci: '', nombres: '', apellidos: '', cargo: '', username: '', password: '', rol_id: '' };
                    this.modalAbierto = true;
                },

                abrirEditar(usuario) {
                    this.modoEdicion = true;
                    this.erroresForm = '';
                    this.form = {
                        id: usuario.id,
                        ci: usuario.ci ?? '',
                        nombres: usuario.nombres ?? '',
                        apellidos: usuario.apellidos ?? '',
                        cargo: usuario.cargo ?? '',
                        username: usuario.username ?? '',
                        password: '',
                        rol_id: usuario.rol?.id ?? '',
                    };
                    this.modalAbierto = true;
                },

                cerrarModal() {
                    this.modalAbierto = false;
                },

                async guardar() {
                    this.guardando = true;
                    this.erroresForm = '';
                    try {
                        const payload = { ...this.form };
                        if (this.modoEdicion && !payload.password) {
                            delete payload.password;
                        }

                        const url = this.modoEdicion ? `/api/admin/usuarios/${this.form.id}` : '/api/admin/usuarios';
                        const method = this.modoEdicion ? 'PUT' : 'POST';

                        const { ok, data } = await window.apiFetch(url, { method, body: payload });

                        if (!ok) {
                            if (data?.errors) {
                                this.erroresForm = Object.values(data.errors).flat().join('\n');
                            }
                            throw new Error(data?.message || 'No se pudo guardar el usuario.');
                        }

                        window.apiToast('exito', this.modoEdicion ? 'Usuario actualizado correctamente.' : 'Usuario creado correctamente.');
                        this.cerrarModal();
                        await this.cargar();
                    } catch (e) {
                        if (!this.erroresForm) {
                            window.apiToast('error', e.message);
                        }
                    } finally {
                        this.guardando = false;
                    }
                },

                async inactivar(usuario) {
                    if (!window.confirm(`¿Inactivar a ${usuario.nombres} ${usuario.apellidos}? Se cerrarán todas sus sesiones activas.`)) {
                        return;
                    }
                    await this.cambiarEstado(usuario, 'inactivar');
                },

                async activar(usuario) {
                    if (!window.confirm(`¿Reactivar a ${usuario.nombres} ${usuario.apellidos}?`)) {
                        return;
                    }
                    await this.cambiarEstado(usuario, 'activar');
                },

                async cambiarEstado(usuario, accion) {
                    try {
                        const { ok, data } = await window.apiFetch(`/api/admin/usuarios/${usuario.id}/${accion}`, { method: 'POST' });
                        if (!ok) {
                            throw new Error(data?.message || `No se pudo ${accion} al usuario.`);
                        }
                        window.apiToast('exito', data?.message || 'Operación realizada correctamente.');
                        await this.cargar();
                    } catch (e) {
                        window.apiToast('error', e.message);
                    }
                },
            };
        }
    </script>
@endsection
