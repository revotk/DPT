<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
</script>

<template>
    <Head title="Bienvenido">
        <link rel="preconnect" href="https://rsms.me/" />
        <link rel="stylesheet" href="https://rsms.me/inter/inter.css" />
    </Head>
    <div class="flex min-h-screen flex-col items-center bg-[#f5f5f5] p-6 text-[#000000] lg:justify-center lg:p-8">
        <header class="not-has-[nav]:hidden mb-6 w-full max-w-[335px] text-sm lg:max-w-4xl">
            <nav class="flex items-center justify-end gap-4">
                <Link
                    v-if="$page.props.auth.user"
                    :href="route('dashboard')"
                    class="inline-block rounded-sm border border-[#b2b2b2] px-5 py-1.5 text-sm leading-normal text-[#000000] hover:border-[#d5007f] hover:text-[#d5007f]"
                >
                    Panel principal
                </Link>
                <template v-else>
                    <Link
                        :href="route('login')"
                        class="inline-block rounded-sm border border-transparent px-5 py-1.5 text-sm leading-normal text-[#000000] hover:text-[#d5007f]"
                    >
                        Iniciar sesión
                    </Link>
                    <Link
                        :href="route('register')"
                        class="inline-block rounded-sm border border-[#b2b2b2] px-5 py-1.5 text-sm leading-normal text-[#000000] hover:border-[#d5007f] hover:text-[#d5007f]"
                    >
                        Registrarse
                    </Link>
                </template>
            </nav>
        </header>
        <div class="duration-750 starting:opacity-0 flex w-full items-center justify-center opacity-100 transition-opacity lg:grow">
            <main class="flex w-full max-w-[335px] flex-col-reverse overflow-hidden rounded-lg lg:max-w-4xl lg:flex-row">
                <div
                    class="flex-1 rounded-bl-lg rounded-br-lg bg-white p-6 pb-12 text-[13px] leading-[20px] shadow-[inset_0px_0px_0px_1px_rgba(0,0,0,0.16)] lg:rounded-br-none lg:rounded-tl-lg lg:p-20"
                >
                    <h1 class="mb-1 font-medium">Comencemos</h1>
                    <p class="mb-2 text-[#706f6c]">
                        Bienvenido al de monitoreo DESO.DPT. <br />Sugerimos comenzar con lo siguiente.
                    </p>
                    <ul class="mb-4 flex flex-col lg:mb-6">
                        <li
                            class="relative flex items-center gap-4 py-2 before:absolute before:bottom-0 before:left-[0.4rem] before:top-1/2 before:border-l before:border-[#b2b2b2]"
                        >
                            <span class="relative bg-white py-1">
                                <span
                                    class="flex h-3.5 w-3.5 items-center justify-center rounded-full border border-[#b2b2b2] bg-[#f5f5f5] shadow-[0px_0px_1px_0px_rgba(0,0,0,0.03),0px_1px_2px_0px_rgba(0,0,0,0.06)]"
                                >
                                    <span class="h-1.5 w-1.5 rounded-full bg-[#b2b2b2]" />
                                </span>
                            </span>
                            <span>
                                Consulta el sistema de Monitoreo de Alertas
                                <a
                                    href="https://deso.ine.mx/alertas"
                                    target="_blank"
                                    class="ml-1 inline-flex items-center space-x-1 font-medium text-[#d5007f] underline underline-offset-4"
                                >
                                    <span>Sistema DeSo Alerts</span>
                                    <svg
                                        width="{10}"
                                        height="{11}"
                                        viewBox="0 0 10 11"
                                        fill="none"
                                        xmlns="http://www.w3.org/2000/svg"
                                        class="h-2.5 w-2.5"
                                    >
                                        <path d="M7.70833 6.95834V2.79167H3.54167M2.5 8L7.5 3.00001" stroke="currentColor" stroke-linecap="square" />
                                    </svg>
                                </a>
                            </span>
                        </li>
                        <li
                            class="relative flex items-center gap-4 py-2 before:absolute before:bottom-1/2 before:left-[0.4rem] before:top-0 before:border-l before:border-[#b2b2b2]"
                        >
                            <span class="relative bg-white py-1">
                                <span
                                    class="flex h-3.5 w-3.5 items-center justify-center rounded-full border border-[#b2b2b2] bg-[#f5f5f5] shadow-[0px_0px_1px_0px_rgba(0,0,0,0.03),0px_1px_2px_0px_rgba(0,0,0,0.06)]"
                                >
                                    <span class="h-1.5 w-1.5 rounded-full bg-[#b2b2b2]" />
                                </span>
                            </span>
                            <span>
                                Sistema de Administracion DPT - DESO (Correo Electronico)
                                <a
                                    href="mailto:deso.dpt@ine.mx"
                                    target="_blank"
                                    class="ml-1 inline-flex items-center space-x-1 font-medium text-[#d5007f] underline underline-offset-4"
                                >
                                    <span>deso.dpt@ine.mx</span>
                                    <svg
                                        width="{10}"
                                        height="{11}"
                                        viewBox="0 0 10 11"
                                        fill="none"
                                        xmlns="http://www.w3.org/2000/svg"
                                        class="h-2.5 w-2.5"
                                    >
                                        <path d="M7.70833 6.95834V2.79167H3.54167M2.5 8L7.5 3.00001" stroke="currentColor" stroke-linecap="square" />
                                    </svg>
                                </a>
                            </span>
                        </li>
                    </ul>
                    <ul class="flex gap-3 text-sm leading-normal">
                        <li>
                            <a
                                href="#"
                                target="_blank"
                                class="inline-block rounded-sm border border-[#d5007f] bg-[#d5007f] px-5 py-1.5 text-sm leading-normal text-white hover:bg-[#950054] hover:border-[#950054] transition-colors"
                            >
                                Comenzar ahora
                            </a>
                        </li>
                    </ul>
                </div>
                <div
                    class="relative -mb-px aspect-[335/376] w-full shrink-0 overflow-hidden rounded-t-lg bg-[#f5f5f5] lg:-ml-px lg:mb-0 lg:aspect-auto lg:w-[438px] lg:rounded-r-lg lg:rounded-t-none"
                >
                    <!-- Logo oficial del INE -->
                    <div class="flex items-center justify-center w-full h-full p-8">
                        <img
                            src="https://upload.wikimedia.org/wikipedia/commons/4/4e/Logo_Instituto_Nacional_Electoral.svg"
                            alt="Logo oficial del Instituto Nacional Electoral"
                            class="max-w-full max-h-full"
                        />
                    </div>

                    <div
                        class="absolute inset-0 rounded-t-lg shadow-[inset_0px_0px_0px_1px_rgba(213,0,127,0.2)] lg:overflow-hidden lg:rounded-r-lg lg:rounded-t-none"
                    ></div>
                </div>
            </main>
        </div>
        <div class="h-14.5 hidden lg:block"></div>
    </div>
</template>
