<script setup>
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div class="min-h-screen bg-slate-100">
        <header class="border-b border-pineto-900 bg-pineto-950 text-white">
            <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-4 sm:px-6 lg:px-8">
                <div class="flex items-center gap-5">
                    <Link :href="route('admin.employees.index')" class="font-bold tracking-wide">PINETO CALCIO <span class="text-pineto-300">ADMIN</span></Link>
                    <Link :href="route('admin.payslips.index')" class="text-sm font-semibold text-pineto-100 hover:text-white">Buste paga</Link>
                </div>
                <Link :href="route('admin.logout')" method="post" as="button" class="rounded-lg border border-white/30 px-3 py-2 text-sm font-semibold hover:bg-white/10">Esci</Link>
            </div>
        </header>
        <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
            <div v-if="$page.props.flash.success" class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
                {{ $page.props.flash.success }}
            </div>
            <slot />
        </main>
    </div>
</template>
