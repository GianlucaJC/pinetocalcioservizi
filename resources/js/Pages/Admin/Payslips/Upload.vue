<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import InputError from '@/Components/InputError.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({ area: String });
const isCud = props.area === 'cuds';
const form = useForm({
    file: null,
    period_year: new Date().getFullYear() - (isCud ? 1 : 0),
    ...(isCud ? {} : { period_month: new Date().getMonth() + 1 }),
});

const months = [
    'Gennaio', 'Febbraio', 'Marzo', 'Aprile', 'Maggio', 'Giugno',
    'Luglio', 'Agosto', 'Settembre', 'Ottobre', 'Novembre', 'Dicembre',
];

const submit = () => form.post(route(`admin.${props.area}.store`));
</script>

<template>
    <Head :title="isCud ? 'Nuova analisi CU/CUD' : 'Nuova analisi buste paga'" />
    <AdminLayout>
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-950">Nuova analisi PDF</h1>
                <p class="mt-1 text-sm text-slate-600">Carica un PDF unico per estrarre i codici fiscali e preparare {{ isCud ? 'le certificazioni' : 'le buste' }} per la revisione.</p>
            </div>
            <Link :href="route(`admin.${area}.index`)" class="text-sm font-semibold text-pineto-700 hover:text-pineto-900">Torna alla consultazione</Link>
        </div>

        <form class="mt-6 max-w-3xl space-y-5 rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-200" @submit.prevent="submit">
            <div>
                <label for="file" class="text-sm font-semibold text-slate-700">{{ isCud ? 'PDF delle CU/CUD' : 'PDF delle buste paga o della tredicesima' }}</label>
                <input id="file" type="file" accept="application/pdf" required class="mt-2 block w-full text-sm text-slate-600 file:mr-4 file:rounded-lg file:border-0 file:bg-pineto-50 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-pineto-700 hover:file:bg-pineto-100" @input="form.file = $event.target.files[0]" />
                <p class="mt-1 text-xs text-slate-500">Il PDF deve contenere testo selezionabile e un solo codice fiscale del destinatario su ogni pagina. Dimensione massima: 50 MB.</p>
                <p v-if="!isCud" class="mt-1 text-xs text-slate-500">La tredicesima va caricata in un PDF separato dalle buste ordinarie.</p>
                <InputError class="mt-2" :message="form.errors.file" />
            </div>
            <div class="grid gap-5 sm:grid-cols-2">
                <div v-if="!isCud">
                    <label for="period_month" class="text-sm font-semibold text-slate-700">Mese di competenza o tredicesima</label>
                    <select id="period_month" v-model="form.period_month" class="mt-2 block w-full rounded-lg border-slate-300 shadow-sm focus:border-pineto-500 focus:ring-pineto-500">
                        <option v-for="(month, index) in months" :key="month" :value="index + 1">{{ month }}</option>
                        <option :value="13">Tredicesima</option>
                    </select>
                    <InputError class="mt-2" :message="form.errors.period_month" />
                </div>
                <div>
                    <label for="period_year" class="text-sm font-semibold text-slate-700">{{ isCud ? 'Anno dei redditi certificati' : 'Anno di competenza' }}</label>
                    <input id="period_year" v-model="form.period_year" type="number" min="2000" max="2100" class="mt-2 block w-full rounded-lg border-slate-300 shadow-sm focus:border-pineto-500 focus:ring-pineto-500" />
                    <InputError class="mt-2" :message="form.errors.period_year" />
                </div>
            </div>
            <button type="submit" :disabled="form.processing" class="rounded-lg bg-pineto-600 px-4 py-2 text-sm font-bold text-white hover:bg-pineto-700 disabled:opacity-50">Analizza PDF</button>
        </form>
    </AdminLayout>
</template>
