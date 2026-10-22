<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';

const props = defineProps({
    batch: Object,
    profiles: Array,
    deliveries: Object,
});

const assignments = Object.fromEntries(props.batch.segments.map((segment, index) => [index, segment.employee_profile_id]));
const form = useForm({ assignments });

const saveAssignments = () => form.patch(route('admin.payslips.update', props.batch.id));

const publish = () => {
    if (confirm(`Pubblicare ${props.batch.assignable_count} buste riconosciute? Le ${props.batch.unmatched_count} non assegnate non verranno pubblicate.`)) {
        router.post(route('admin.payslips.publish', props.batch.id));
    }
};
</script>

<template>
    <Head title="Revisione buste paga" />
    <AdminLayout>
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-950">Revisione del lotto</h1>
                <p class="mt-1 text-sm text-slate-600">{{ batch.original_filename }} · {{ batch.period_month }}/{{ batch.period_year }}</p>
            </div>
            <Link :href="route('admin.payslips.index')" class="text-sm font-semibold text-pineto-700 hover:text-pineto-900">Torna ai lotti</Link>
        </div>

        <div class="mt-6 grid gap-4 sm:grid-cols-2">
            <div class="rounded-xl bg-emerald-50 p-5 ring-1 ring-emerald-200"><p class="text-sm font-semibold text-emerald-800">Buste assegnabili</p><p class="mt-1 text-3xl font-bold text-emerald-900">{{ batch.assignable_count }}</p></div>
            <div class="rounded-xl bg-amber-50 p-5 ring-1 ring-amber-200"><p class="text-sm font-semibold text-amber-800">Da verificare</p><p class="mt-1 text-3xl font-bold text-amber-900">{{ batch.unmatched_count }}</p></div>
        </div>

        <section class="mt-6 overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-200">
            <div class="divide-y divide-slate-100">
                <article v-for="(segment, index) in batch.segments" :key="`${segment.first_page}-${segment.last_page}`" class="flex flex-col gap-3 p-5 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="font-mono text-sm font-bold text-slate-900">{{ segment.tax_code || 'Codice fiscale non identificato' }}</p>
                        <p class="mt-1 text-sm text-slate-600">Pagine {{ segment.first_page }}{{ segment.last_page !== segment.first_page ? `-${segment.last_page}` : '' }}</p>
                    </div>
                    <p v-if="segment.employee_profile_id" class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-bold text-emerald-700">Assegnata</p>
                    <div v-else class="w-full sm:max-w-sm">
                        <p class="mb-2 text-sm font-medium text-amber-700">{{ segment.issue }}</p>
                        <select v-model="form.assignments[index]" class="block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-pineto-500 focus:ring-pineto-500">
                            <option :value="null">Non assegnare</option>
                            <option v-for="profile in profiles" :key="profile.id" :value="profile.id">{{ profile.name }} · {{ profile.tax_code }}</option>
                        </select>
                    </div>
                </article>
            </div>
        </section>

        <div v-if="batch.status === 'review'" class="mt-6 flex flex-wrap justify-end gap-3">
            <button type="button" :disabled="form.processing" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-bold text-slate-700 hover:bg-slate-50 disabled:opacity-50" @click="saveAssignments">Salva assegnazioni</button>
            <button type="button" :disabled="batch.assignable_count === 0" class="rounded-lg bg-pineto-600 px-4 py-2 text-sm font-bold text-white hover:bg-pineto-700 disabled:cursor-not-allowed disabled:opacity-50" @click="publish">Pubblica buste riconosciute</button>
        </div>
        <section v-if="batch.status === 'completed'" class="mt-6 overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-200">
            <div class="border-b border-slate-100 px-5 py-4">
                <h2 class="font-bold text-slate-900">Invii ai dipendenti e atleti</h2>
                <p class="mt-1 text-sm text-slate-600">Stato di prima visualizzazione delle buste pubblicate con questo invio.</p>
            </div>
            <div v-if="deliveries.data.length === 0" class="p-5 text-sm text-slate-500">Non è stata pubblicata alcuna busta in questo lotto.</div>
            <div v-else class="divide-y divide-slate-100">
                <article v-for="delivery in deliveries.data" :key="delivery.id" class="flex flex-col gap-3 p-5 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="font-semibold text-slate-900">{{ delivery.name }}</p>
                        <p class="mt-1 text-sm text-slate-600">{{ delivery.email }} · <span class="font-mono text-xs">{{ delivery.tax_code }}</span></p>
                        <p class="mt-1 text-xs text-slate-500">Pagine {{ delivery.first_page }}{{ delivery.last_page !== delivery.first_page ? `-${delivery.last_page}` : '' }}</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-3">
                        <span class="rounded-full px-3 py-1 text-xs font-bold" :class="delivery.viewed_at ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700'">{{ delivery.viewed_at ? `Visionata il ${delivery.viewed_at}` : 'Non ancora visionata' }}</span>
                        <a :href="route('admin.payslips.documents.show', delivery.id)" target="_blank" rel="noopener" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Apri busta</a>
                    </div>
                </article>
            </div>
        </section>
        <nav v-if="batch.status === 'completed' && deliveries.links.length > 3" class="mt-6 flex flex-wrap gap-1">
            <Link v-for="link in deliveries.links" :key="link.label" :href="link.url || '#'" :class="['rounded px-3 py-2 text-sm', link.active ? 'bg-pineto-600 font-bold text-white' : 'bg-white text-slate-600 ring-1 ring-slate-200', !link.url && 'pointer-events-none opacity-50']" v-html="link.label" />
        </nav>
    </AdminLayout>
</template>
