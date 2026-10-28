<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
    profile: {
        type: Object,
        default: null,
    },
});

const formatDate = (date) => {
    if (!date) {
        return 'Non indicata';
    }

    const parsedDate = new Date(date);

    return Number.isNaN(parsedDate.getTime())
        ? 'Non indicata'
        : new Intl.DateTimeFormat('it-IT', { day: '2-digit', month: 'long', year: 'numeric' }).format(parsedDate);
};
</script>

<template>
    <Head title="Il mio profilo" />

    <AuthenticatedLayout>
        <div class="mx-auto max-w-md px-4 py-6 pb-12 sm:max-w-3xl">
            <section class="overflow-hidden rounded-3xl bg-pineto-950 text-white shadow-xl">
                <div class="bg-gradient-to-br from-pineto-500/90 via-pineto-600/80 to-pineto-950 px-6 py-7">
                    <p class="text-xs font-bold uppercase tracking-[0.2em] text-white/75">Pineto Calcio Servizi</p>
                    <div class="mt-5 flex items-center gap-4">
                        <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-white text-xl font-black text-pineto-700 shadow-lg">
                            {{ $page.props.auth.user.full_name.slice(0, 1) }}
                        </div>
                        <div>
                            <h1 class="text-2xl font-bold leading-tight">{{ $page.props.auth.user.full_name }}</h1>
                            <p class="mt-1 text-sm text-white/80">{{ profile?.position || 'Profilo in aggiornamento' }}</p>
                        </div>
                    </div>
                </div>
                <div class="flex items-center justify-between px-6 py-4">
                    <span class="rounded-full bg-white/15 px-3 py-1 text-xs font-bold uppercase tracking-wide">
                        {{ profile?.type === 'athlete' ? 'Atleta' : 'Dipendente' }}
                    </span>
                    <span class="text-sm text-white/75">Profilo personale</span>
                </div>
            </section>

            <section class="mt-6">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-lg font-bold text-slate-900">I miei dati</h2>
                    <span class="text-xs font-medium text-slate-500">Anagrafica</span>
                </div>
                <div class="divide-y divide-slate-100 overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
                    <div class="flex gap-4 px-5 py-4">
                        <span class="mt-0.5 text-pineto-600">✉</span>
                        <div><p class="text-xs font-medium text-slate-500">Email</p><p class="mt-1 font-medium">{{ $page.props.auth.user.email }}</p></div>
                    </div>
                    <div class="flex gap-4 px-5 py-4">
                        <span class="mt-0.5 text-pineto-600">✉</span>
                        <div><p class="text-xs font-medium text-slate-500">PEC</p><p class="mt-1 font-medium">{{ profile?.pec_email || 'Non indicata' }}</p></div>
                    </div>
                    <div class="flex gap-4 px-5 py-4">
                        <span class="mt-0.5 text-pineto-600">☎</span>
                        <div><p class="text-xs font-medium text-slate-500">Telefono</p><p class="mt-1 font-medium">{{ profile?.phone || 'Non indicato' }}</p></div>
                    </div>
                    <div class="flex gap-4 px-5 py-4">
                        <span class="mt-0.5 text-pineto-600">▣</span>
                        <div><p class="text-xs font-medium text-slate-500">Codice fiscale</p><p class="mt-1 font-mono text-sm font-medium">{{ profile?.tax_code || 'Non indicato' }}</p></div>
                    </div>
                    <div class="flex gap-4 px-5 py-4">
                        <span class="mt-0.5 text-pineto-600">⌖</span>
                        <div><p class="text-xs font-medium text-slate-500">Residenza</p><p class="mt-1 font-medium">{{ profile?.address || 'Non indicata' }}, {{ profile?.postal_code }} {{ profile?.city }} ({{ profile?.province }})</p></div>
                    </div>
                </div>
            </section>

            <section class="mt-6">
                <h2 class="mb-3 text-lg font-bold text-slate-900">Rapporto di lavoro</h2>
                <div class="grid grid-cols-2 gap-3">
                    <div class="rounded-2xl bg-pineto-50 p-4 ring-1 ring-pineto-100"><p class="text-xs font-medium text-pineto-700">Ruolo</p><p class="mt-2 font-bold text-pineto-950">{{ profile?.position || 'Da definire' }}</p></div>
                    <div class="rounded-2xl bg-pineto-50 p-4 ring-1 ring-pineto-100"><p class="text-xs font-medium text-pineto-700">Inizio collaborazione</p><p class="mt-2 font-bold text-pineto-950">{{ formatDate(profile?.started_on) }}</p></div>
                </div>
            </section>

            <section v-if="profile?.work_contract_path" class="mt-6">
                <h2 class="mb-3 text-lg font-bold text-slate-900">Documenti</h2>
                <a :href="route('contract.show')" target="_blank" class="flex items-center justify-between rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200 transition hover:ring-pineto-500">
                    <div><p class="font-bold text-slate-900">Contratto di lavoro</p><p class="mt-1 text-sm text-slate-500">Apri il documento PDF</p></div>
                    <span class="text-xl text-pineto-600">↗</span>
                </a>
            </section>

            <section class="mt-6 rounded-2xl border border-amber-200 bg-amber-50 p-5">
                <p class="text-sm font-bold text-amber-900">Contatto di emergenza</p>
                <p class="mt-1 text-sm text-amber-800">{{ profile?.emergency_contact_name || 'Non indicato' }} · {{ profile?.emergency_contact_phone || 'Non indicato' }}</p>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
