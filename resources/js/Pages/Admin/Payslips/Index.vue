<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { reactive, watch } from 'vue';

const props = defineProps({
    batches: Object,
    filters: Object,
});

const months = [
    'Gennaio', 'Febbraio', 'Marzo', 'Aprile', 'Maggio', 'Giugno',
    'Luglio', 'Agosto', 'Settembre', 'Ottobre', 'Novembre', 'Dicembre',
];

const statusLabel = (status) => status === 'completed' ? 'Pubblicato' : 'Da revisionare';

const filters = reactive({
    period_year: props.filters.period_year,
    period_month: props.filters.period_month,
});

let filterTimer;

watch(filters, () => {
    clearTimeout(filterTimer);
    filterTimer = setTimeout(() => {
        router.get(route('admin.payslips.index'), filters, {
            preserveState: true,
            replace: true,
        });
    }, 300);
});
</script>

<template>
    <Head title="Buste paga" />
    <AdminLayout>
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-950">Consultazione invii</h1>
                <p class="mt-1 text-sm text-slate-600">Consulta i lotti elaborati, le buste pubblicate e il relativo stato di visualizzazione.</p>
            </div>
            <Link :href="route('admin.payslips.create')" class="inline-flex items-center justify-center rounded-lg bg-pineto-600 px-4 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-pineto-700">Nuova analisi PDF</Link>
        </div>

        <section class="mt-6 grid gap-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200 sm:grid-cols-2">
            <div>
                <label for="filter_period_month" class="text-sm font-semibold text-slate-700">Filtra per mese</label>
                <select id="filter_period_month" v-model="filters.period_month" class="mt-2 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-pineto-500 focus:ring-pineto-500">
                    <option value="">Tutti i mesi</option>
                    <option v-for="(month, index) in months" :key="month" :value="String(index + 1)">{{ month }}</option>
                </select>
            </div>
            <div>
                <label for="filter_period_year" class="text-sm font-semibold text-slate-700">Filtra per anno</label>
                <input id="filter_period_year" v-model="filters.period_year" type="number" min="2000" max="2100" placeholder="Tutti gli anni" class="mt-2 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-pineto-500 focus:ring-pineto-500" />
            </div>
        </section>

        <section class="mt-8 overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-200">
            <div class="border-b border-slate-100 px-5 py-4"><h2 class="font-bold text-slate-900">Lotti importati</h2></div>
            <div v-if="batches.data.length === 0" class="p-6 text-sm text-slate-500">Non è stato ancora importato alcun lotto.</div>
            <div v-else class="divide-y divide-slate-100">
                <Link v-for="batch in batches.data" :key="batch.id" :href="route('admin.payslips.show', batch.id)" class="flex items-center justify-between gap-4 p-5 hover:bg-slate-50">
                    <div>
                        <p class="font-semibold text-slate-900">{{ batch.original_filename }}</p>
                        <p class="mt-1 text-sm text-slate-600">{{ months[batch.period_month - 1] }} {{ batch.period_year }} · {{ batch.assignable_count }} assegnabili · {{ batch.unmatched_count }} da verificare</p>
                    </div>
                    <span class="rounded-full px-2.5 py-1 text-xs font-bold" :class="batch.status === 'completed' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700'">{{ statusLabel(batch.status) }}</span>
                </Link>
            </div>
        </section>
        <nav v-if="batches.links.length > 3" class="mt-6 flex flex-wrap gap-1">
            <Link v-for="link in batches.links" :key="link.label" :href="link.url || '#'" :class="['rounded px-3 py-2 text-sm', link.active ? 'bg-pineto-600 font-bold text-white' : 'bg-white text-slate-600 ring-1 ring-slate-200', !link.url && 'pointer-events-none opacity-50']" v-html="link.label" />
        </nav>
    </AdminLayout>
</template>
