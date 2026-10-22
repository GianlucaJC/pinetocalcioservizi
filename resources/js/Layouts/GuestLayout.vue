<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div class="flex min-h-screen flex-col items-center justify-center bg-gradient-to-b from-pineto-950 via-pineto-700 to-pineto-500 px-5 py-10">
        <div class="text-center">
            <Link href="/">
                <ApplicationLogo class="mx-auto h-24 w-auto rounded-2xl bg-white p-2 shadow-xl" />
            </Link>
            <p class="mt-4 text-lg font-bold tracking-tight text-white">Pineto Calcio Servizi</p>
            <p class="mt-1 text-sm text-white/75">Area riservata</p>
        </div>

        <div class="mt-7 w-full max-w-md overflow-hidden rounded-3xl bg-white px-6 py-7 shadow-2xl">
            <slot />
        </div>
    </div>
</template>
