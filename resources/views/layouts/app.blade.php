<!DOCTYPE html>
<html lang="es" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('titulo', 'Sistema de Control y Fiscalización') | Consejo de la Magistratura</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        /* Colores más claros */
        .bg-verde-profundo { background-color: #3A7D4A; }
        .bg-verde-institucional { background-color: #7BC47F; }
        .bg-verde-claro { background-color: #A8D8AD; }
        .bg-azul-petroleo { background-color: #2A5F6F; }
        .bg-marfil { background-color: #F4F1E6; }
        .bg-grafito { background-color: #3A4240; }
        .bg-gris-claro { background-color: #F0F3F1; }

        .text-verde-profundo { color: #3A7D4A; }
        .text-verde-institucional { color: #7BC47F; }
        .text-azul-petroleo { color: #2A5F6F; }
        .text-grafito { color: #3A4240; }
        .text-gris { color: #7A8A84; }

        .hover\:bg-verde-profundo:hover { background-color: #3A7D4A; }
        .hover\:bg-verde-institucional:hover { background-color: #7BC47F; }
        .hover\:bg-verde-claro:hover { background-color: #A8D8AD; }

        .bg-institucional-gradient {
            background: linear-gradient(135deg, #3A7D4A 0%, #2A5F6F 50%, #3A4240 100%);
        }

        .sidebar-gradient {
            background: linear-gradient(180deg, #2A5F6F 0%, #3A7D4A 100%);
        }

        @keyframes shimmer {
            0% { transform: translateX(-50%); }
            100% { transform: translateX(50%); }
        }
        .animate-shimmer { animation: shimmer 3s ease-in-out infinite; }

        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="h-full bg-gris-claro antialiased">
    @include('partials.api-helper')

    <div
        x-data="workstation()"
        @toast.window="mostrarToast($event.detail)"
        class="min-h-full flex flex-col"
    >
        <div x-init="$nextTick(() => cargarUsuario())">
            <!-- Barra superior -->
            <header class="fixed top-0 left-0 right-0 h-14 z-40 bg-verde-profundo shadow-lg flex items-center px-4 gap-3">
                <!-- Logo más grande -->
                <img src="/images/logoconsejo_sinfondo.png" alt="Consejo de la Magistratura" class="h-11 w-auto object-contain hidden sm:block">
                <div class="hidden md:block text-white/90 text-xs tracking-wider uppercase">
                    Sistema de Control y Fiscalización
                </div>
                <div class="flex-1"></div>
                <div class="hidden sm:flex items-center gap-2 text-white/95 text-xs">
                    <i class="fa-solid fa-circle-user text-verde-institucional text-base"></i>
                    <span class="max-w-[180px] truncate font-medium" x-text="usuario?.nombre || '...'"></span>
                    <span class="px-2 py-0.5 rounded-full bg-verde-institucional/40 text-white text-[10px] uppercase tracking-wide" x-text="usuario?.rol || ''"></span>
                </div>
                <button @click="cerrarSesion" class="text-white/90 hover:text-white transition flex items-center gap-1.5 text-xs" aria-label="Cerrar sesión">
                    <i class="fa-solid fa-right-from-bracket"></i>
                    <span class="hidden sm:inline">Salir</span>
                </button>
            </header>

            <!-- Sidebar -->
            <aside
                class="fixed left-0 top-14 bottom-0 w-60 z-30 sidebar-gradient text-white/95 shadow-xl overflow-y-auto"
            >
                <nav class="p-3 space-y-1 text-sm">
                    <a href="/expedientes" class="flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-white/20 transition">
                        <i class="fa-solid fa-inbox w-5 text-center text-verde-institucional"></i>
                        <span>Bandeja de entrada</span>
                    </a>
                    <template x-if="usuario?.rol === 'ENCARGADA'">
                        <a href="/bandeja/sorteo" class="flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-white/20 transition">
                            <i class="fa-solid fa-shuffle w-5 text-center text-verde-institucional"></i>
                            <span>Sorteo de expedientes</span>
                        </a>
                    </template>
                    <template x-if="usuario?.rol === 'TECNICO'">
                        <a href="/expedientes/nuevo" class="flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-white/20 transition">
                            <i class="fa-solid fa-folder-plus w-5 text-center text-verde-institucional"></i>
                            <span>Apertura de causa</span>
                        </a>
                    </template>
                </nav>
            </aside>

            <!-- Contenido -->
            <main class="pl-60 pt-14 transition-all">
                @yield('contenido')
            </main>

            <!-- Sistema de toasts -->
            <div class="fixed bottom-4 right-4 z-50 space-y-2 w-full max-w-sm">
                <template x-for="t in toasts" :key="t.id">
                    <div
                        x-show="t.visible"
                        x-transition
                        class="rounded-lg border shadow-lg text-sm p-3.5 flex items-start gap-3"
                        :class="{
                            'bg-[#F15A24]/10 border-[#F15A24]/30 text-[#B53F12]': t.tipo === 'error',
                            'bg-[#8CC63F]/10 border-[#8CC63F]/40 text-[#3F5E1B]': t.tipo === 'exito',
                            'bg-[#27A9E0]/10 border-[#27A9E0]/30 text-[#145D82]': t.tipo === 'info'
                        }"
                    >
                        <i :class="{
                            'fa-solid fa-circle-exclamation': t.tipo === 'error',
                            'fa-solid fa-circle-check': t.tipo === 'exito',
                            'fa-solid fa-circle-info': t.tipo === 'info'
                        }" class="mt-0.5 shrink-0"></i>
                        <div>
                            <p class="font-semibold leading-snug" x-text="t.mensaje"></p>
                            <pre v-if="t.detalle" x-show="t.detalle" class="mt-1 text-xs whitespace-pre-line font-sans" x-text="t.detalle"></pre>
                        </div>
                        <button @click="cerrarToast(t.id)" class="ml-auto text-xs opacity-60 hover:opacity-100">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    </div>
                </template>
            </div>
        </div>
    </div>

    <script>
        function workstation() {
            return {
                usuario: null,
                toasts: [],
                _toastId: 0,

                async cargarUsuario() {
                    try {
                        const { ok, data } = await window.apiFetch('/api/me');
                        if (ok) {
                            this.usuario = data;
                            this.usuario.nombre = [data.nombres, data.apellidos].filter(Boolean).join(' ');
                            this.usuario.rol = data.rol?.codigo || '';
                        }
                    } catch (error) {
                        // 401 redirige a /login desde apiFetch
                    }
                },

                async cerrarSesion() {
                    try {
                        await window.apiFetch('/api/logout', { method: 'POST' });
                    } catch (error) {
                        // Ya redirigirá apiFetch si 401; de lo contrario seguimos
                    }
                    window.location.href = '/login';
                },

                mostrarToast(detalle) {
                    const id = ++this._toastId;
                    this.toasts.push({ id, visible: true, ...detalle });
                    setTimeout(() => this.cerrarToast(id), detalle.tipo === 'error' ? 6000 : 3500);
                },

                cerrarToast(id) {
                    const toast = this.toasts.find(t => t.id === id);
                    if (toast) {
                        toast.visible = false;
                        setTimeout(() => {
                            this.toasts = this.toasts.filter(t => t.id !== id);
                        }, 200);
                    }
                },
            };
        }
    </script>
</body>
</html>