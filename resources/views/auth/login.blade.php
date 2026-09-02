<!DOCTYPE html>
<html lang="es" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso al Sistema | Control y Fiscalización</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
        /* Colores personalizados */
        .bg-verde-institucional { background-color: #63A66B; }
        .bg-verde-profundo { background-color: #285C3A; }
        .bg-azul-petroleo { background-color: #173F4F; }
        .bg-marfil { background-color: #F4F1E6; }
        .bg-grafito { background-color: #202625; }
        .bg-gris-claro { background-color: #E6EAE7; }
        
        .text-verde-institucional { color: #63A66B; }
        .text-verde-profundo { color: #285C3A; }
        .text-azul-petroleo { color: #173F4F; }
        .text-marfil { color: #F4F1E6; }
        .text-grafito { color: #202625; }
        .text-gris { color: #66706B; }
        
        .border-verde-institucional { border-color: #63A66B; }
        .border-verde-profundo { border-color: #285C3A; }
        .border-azul-petroleo { border-color: #173F4F; }
        .border-gris-claro { border-color: #E6EAE7; }
        
        .hover\:bg-verde-profundo:hover { background-color: #285C3A; }
        .hover\:bg-verde-institucional:hover { background-color: #63A66B; }
        
        /* Colores secundarios del logo */
        .bg-azul-logo { background-color: #27A9E0; }
        .bg-violeta-logo { background-color: #8B7DBB; }
        .bg-verde-lima { background-color: #8CC63F; }
        .bg-amarillo-logo { background-color: #FDBD10; }
        .bg-naranja-logo { background-color: #F7941D; }
        .bg-rojo-logo { background-color: #F15A24; }
        
        .text-azul-logo { color: #27A9E0; }
        .text-violeta-logo { color: #8B7DBB; }
        .text-verde-lima { color: #8CC63F; }
        .text-amarillo-logo { color: #FDBD10; }
        .text-naranja-logo { color: #F7941D; }
        .text-rojo-logo { color: #F15A24; }

        /* Efecto shimmer para la barra superior */
        @keyframes shimmer {
            0% { transform: translateX(-50%); }
            100% { transform: translateX(50%); }
        }
        .animate-shimmer {
            animation: shimmer 3s ease-in-out infinite;
        }

        /* Fondo con degradado institucional */
        .bg-institucional-gradient {
            background: linear-gradient(135deg, #285C3A 0%, #173F4F 50%, #202625 100%);
        }
    </style>
</head>
<body class="h-full flex items-center justify-center p-4 antialiased bg-institucional-gradient relative">
    
    <!-- Barra superior verde institucional -->
    <div class="fixed top-0 left-0 right-0 h-10 z-50 overflow-hidden shadow-lg">
        <div class="absolute inset-0 bg-[#285C3A]"></div>
        <div class="absolute inset-0 bg-gradient-to-r from-transparent via-[#63A66B] to-transparent opacity-60 animate-shimmer" 
            style="width: 200%; transform: translateX(0%);">
        </div>
    </div>
    
    <!-- Fondo con imagen -->
    <div class="fixed inset-0 z-0">
        <div class="absolute inset-0 bg-cover bg-center bg-no-repeat" 
             style="background-image: url('/images/fondologin.jpg');">
        </div>
        <div class="absolute inset-0 bg-black/70"></div>
    </div>

    <!-- Formulario con fondo BLANCO -->
    <div 
        x-data="loginForm()" 
        class="relative z-10 w-full max-w-md bg-white rounded-2xl p-8 shadow-[0_20px_70px_-15px_rgba(0,0,0,0.5)] overflow-hidden mt-14"
    >
        <!-- Acento decorativo superior con colores del logo -->
        <div class="absolute top-0 left-0 right-0 h-1 flex">
            <div class="flex-1 bg-[#27A9E0]"></div>
            <div class="flex-1 bg-[#8B7DBB]"></div>
            <div class="flex-1 bg-[#8CC63F]"></div>
            <div class="flex-1 bg-[#FDBD10]"></div>
            <div class="flex-1 bg-[#F7941D]"></div>
            <div class="flex-1 bg-[#F15A24]"></div>
        </div>

        <!-- Encabezado con Logo -->
        <div class="text-center mb-8">
            <div class="flex justify-center mb-4">
                <img 
                    src="/images/logoconsejo_sinfondo.png" 
                    alt="Consejo de la Magistratura" 
                    class="w-full max-w-[200px] h-auto object-contain"
                >
            </div>
            
            <!-- Título -->
            <h1 class="text-2xl font-bold tracking-tight text-[#202625] uppercase leading-tight">
                Consejo de la<br>
                <span class="text-[#285C3A]">Magistratura</span>
            </h1>
            <div class="flex items-center justify-center gap-2 mt-2">
                <div class="h-px w-8 bg-gradient-to-r from-transparent to-[#63A66B]"></div>
                <p class="text-xs font-medium text-[#63A66B] tracking-wider uppercase">
                    Sistema de Control y Fiscalización
                </p>
                <div class="h-px w-8 bg-gradient-to-l from-transparent to-[#63A66B]"></div>
            </div>

        </div>

        <!-- Alerta de Error -->
        <div 
            x-show="errorMessage" 
            x-transition 
            class="mb-6 p-3.5 rounded-lg bg-[#F15A24]/10 border border-[#F15A24]/20 text-[#F15A24] text-xs flex items-center gap-2.5"
            style="display: none;"
        >
            <i class="fa-solid fa-circle-exclamation text-sm shrink-0"></i>
            <span x-text="errorMessage"></span>
        </div>

        <!-- Alerta de Éxito -->
        <div 
            x-show="successMessage" 
            x-transition 
            class="mb-6 p-3.5 rounded-lg bg-[#8CC63F]/10 border border-[#8CC63F]/20 text-[#8CC63F] text-xs flex items-center gap-2.5"
            style="display: none;"
        >
            <i class="fa-solid fa-circle-check text-sm shrink-0"></i>
            <span x-text="successMessage"></span>
        </div>

        <!-- Formulario -->
        <form @submit.prevent="submitLogin" class="space-y-5">
            <!-- Usuario -->
            <div>
                <label class="block text-xs font-semibold text-[#66706B] uppercase tracking-wider mb-2">
                    <i class="fa-regular fa-user mr-1.5 text-[#63A66B]"></i>
                    Usuario
                </label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-[#66706B]">
                        <i class="fa-regular fa-user text-xs"></i>
                    </span>
                    <input 
                        type="text" 
                        x-model="form.username" 
                        required 
                        placeholder="Ej. admin o operador_tec"
                        class="w-full bg-[#F4F1E6] border border-[#E6EAE7] rounded-lg pl-10 pr-3.5 py-2.5 text-xs text-[#202625] placeholder-[#66706B] focus:outline-none focus:border-[#63A66B] focus:ring-2 focus:ring-[#63A66B]/30 transition duration-150"
                    >
                </div>
            </div>

            <!-- Contraseña -->
            <div>
                <label class="block text-xs font-semibold text-[#66706B] uppercase tracking-wider mb-2">
                    <i class="fa-regular fa-lock mr-1.5 text-[#63A66B]"></i>
                    Contraseña
                </label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-[#66706B]">
                        <i class="fa-regular fa-lock text-xs"></i>
                    </span>
                    <input 
                        :type="showPassword ? 'text' : 'password'" 
                        x-model="form.password" 
                        required 
                        placeholder="••••••••••••"
                        class="w-full bg-[#F4F1E6] border border-[#E6EAE7] rounded-lg pl-10 pr-10 py-2.5 text-xs text-[#202625] placeholder-[#66706B] focus:outline-none focus:border-[#63A66B] focus:ring-2 focus:ring-[#63A66B]/30 transition duration-150"
                    >
                    <button 
                        type="button" 
                        @click="showPassword = !showPassword" 
                        class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-[#66706B] hover:text-[#202625] transition"
                    >
                        <i class="fa-regular" :class="showPassword ? 'fa-eye-slash' : 'fa-eye'"></i>
                    </button>
                </div>
            </div>

            <!-- Botón de Envío -->
            <button 
                type="submit" 
                :disabled="loading"
                class="w-full mt-2 bg-[#285C3A] hover:bg-[#63A66B] disabled:opacity-50 text-white font-bold py-3 rounded-lg text-xs uppercase tracking-wider transition-all duration-300 shadow-lg shadow-[#285C3A]/30 flex items-center justify-center gap-2 group"
            >
                <i x-show="loading" class="fa-solid fa-circle-notch fa-spin text-sm" style="display: none;"></i>
                <span x-text="loading ? 'Validando...' : 'Iniciar Sesión'"></span>
                <i x-show="!loading" class="fa-solid fa-arrow-right text-xs group-hover:translate-x-1 transition-transform duration-300"></i>
            </button>

            <!-- Enlace de ayuda -->
            <div class="text-center mt-4">
                <a href="#" class="text-[10px] text-[#66706B] hover:text-[#63A66B] transition-colors duration-300 tracking-wider">
                    <i class="fa-regular fa-circle-question mr-1"></i>
                    ¿Olvidaste tu contraseña?
                </a>
            </div>
        </form>

        <!-- Pie de página -->
        <div class="mt-8 pt-4 border-t border-[#E6EAE7] text-center">
            <p class="text-[11px] text-[#66706B] font-light tracking-wider">
                Consejo de la Magistratura &copy; 2026
            </p>
        </div>
    </div>

    <!-- Script -->
    <script>
        function loginForm() {
            return {
                form: {
                    username: '',
                    password: ''
                },
                showPassword: false,
                loading: false,
                errorMessage: '',
                successMessage: '',

                async submitLogin() {
                    this.loading = true;
                    this.errorMessage = '';
                    this.successMessage = '';

                    try {
                // Obtener cookie CSRF-XSRF (sanctum/csrf-cookie) para la sesión stateful
                const csrfHandle = await fetch('/sanctum/csrf-cookie', {
                    method: 'GET',
                    credentials: 'same-origin'
                });

                if (!csrfHandle.ok) {
                    throw new Error('No se pudo inicializar la sesión segura.');
                }

                const xsrfToken = document.cookie
                    .split('; ')
                    .find(row => row.startsWith('XSRF-TOKEN='))
                    ?.split('=')[1];

                const response = await fetch('/api/login', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-XSRF-TOKEN': decodeURIComponent(xsrfToken || '')
                    },
                    body: JSON.stringify(this.form)
                });

                const data = await response.json();

                if (!response.ok) {
                    throw new Error(data.message || 'Error al autenticar credenciales.');
                }

                // Autenticación stateful: la sesión la maneja la cookie HttpOnly.
                // No se almacena el token en localStorage (mitiga riesgo XSS).
                this.successMessage = `¡Bienvenido(a), ${data.usuario.nombre}! (Rol: ${data.usuario.rol})`;

                const destino = data.usuario.rol === 'ENCARGADA' ? '/bandeja/sorteo' : '/expedientes';

                setTimeout(() => {
                    window.location.href = destino;
                }, 800);

                    } catch (error) {
                        this.errorMessage = error.message;
                    } finally {
                        this.loading = false;
                    }
                }
            }
        }
    </script>
</body>
</html>