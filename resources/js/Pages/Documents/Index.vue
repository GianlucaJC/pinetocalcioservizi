<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { reactive } from 'vue';

const props = defineProps({
    documents: Object,
    filters: Object,
    years: Array,
});

const filterForm = reactive({ ...props.filters });

const applyFilters = () => {
    router.get(route('documents.index'), filterForm, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    });
};

const documentLabels = {
    payslip: 'Busta paga',
    cud: 'CU/CUD',
    other: 'Altro documento',
};

const formatPeriod = (document) => {
    if (!document.period_year) {
        return 'Data non indicata';
    }

    if (!document.period_month) {
        return String(document.period_year);
    }

    return new Intl.DateTimeFormat('it-IT', { month: 'long', year: 'numeric' })
        .format(new Date(document.period_year, document.period_month - 1, 1));
};
</script>

<template>
    <Head title="I miei documenti" />

    <AuthenticatedLayout>
        <div class="mx-auto max-w-3xl px-4 py-6 pb-12">
            <div class="rounded-3xl bg-pineto-950 px-6 py-7 text-white shadow-xl">
                <p class="text-xs font-bold uppercase tracking-[0.2em] text-white/70">Archivio personale</p>
                <h1 class="mt-2 text-2xl font-bold">I miei documenti</h1>
                <p class="mt-2 text-sm text-white/75">Buste paga, CU/CUD e documenti messi a disposizione dalla società.</p>
            </div>

            <form class="mt-6 space-y-3 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200" @submit.prevent="applyFilters">
                <label class="block">
                    <span class="text-xs font-semibold text-slate-600">Cerca nei documenti</span>
                    <input v-model="filterForm.search" type="search" placeholder="Es. busta paga" class="mt-1.5 block w-full rounded-xl border-slate-200 text-sm focus:border-pineto-500 focus:ring-pineto-500">
                </label>
                <div class="grid grid-cols-2 gap-3">
                    <label>
                        <span class="text-xs font-semibold text-slate-600">Tipologia</span>
                        <select v-model="filterForm.type" class="mt-1.5 block w-full rounded-xl border-slate-200 text-sm focus:border-pineto-500 focus:ring-pineto-500">
                            <option value="">Tutte</option>
                            <option value="payslip">Buste paga</option>
                            <option value="cud">CU/CUD</option>
                            <option value="other">Altri documenti</option>
                        </select>
                    </label>
                    <label>
                        <span class="text-xs font-semibold text-slate-600">Anno</span>
                        <select v-model="filterForm.year" class="mt-1.5 block w-full rounded-xl border-slate-200 text-sm focus:border-pineto-500 focus:ring-pineto-500">
                            <option value="">Tutti</option>
                            <option v-for="year in years" :key="year" :value="String(year)">{{ year }}</option>
                        </select>
                    </label>
                </div>
                <button type="submit" class="w-full rounded-xl bg-pineto-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-pineto-700">Applica filtri</button>
            </form>

            <section class="mt-6">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-lg font-bold text-slate-900">Storico</h2>
                    <span class="text-sm text-slate-500">{{ documents.total }} documenti</span>
                </div>
                <div v-if="documents.data.length" class="space-y-3">
                    <a v-for="document in documents.data" :key="document.id" :href="route('documents.show', document.id)" target="_blank" class="flex items-center justify-between rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200 transition hover:ring-pineto-500">
                        <div>
                            <span class="rounded-full bg-pineto-50 px-2.5 py-1 text-xs font-bold text-pineto-700">{{ documentLabels[document.type] }}</span>
                            <p class="mt-3 font-bold text-slate-900">{{ document.title }}</p>
                            <p class="mt-1 text-sm text-slate-500">{{ formatPeriod(document) }}</p>
                        </div>
                        <span class="text-xl text-pineto-600">↗</span>
                    </a>
                </div>
                <div v-else class="rounded-2xl border border-dashed border-slate-300 bg-white p-8 text-center text-sm text-slate-500">
                    Nessun documento corrisponde ai filtri selezionati.
                </div>
            </section>

            <div v-if="documents.last_page > 1" class="mt-6 flex justify-center gap-2">
                <Link v-for="link in documents.links" :key="link.label" :href="link.url || '#'" :class="['rounded-lg px-3 py-2 text-sm', link.active ? 'bg-pineto-600 text-white' : 'bg-white text-slate-600 ring-1 ring-slate-200', !link.url && 'pointer-events-none opacity-40']" v-html="link.label" preserve-scroll />
            </div>
        </div>
    </AuthenticatedLayout>
</template>
