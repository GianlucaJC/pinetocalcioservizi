<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { reactive, ref, watch } from 'vue';

const props = defineProps({
    employees: Object,
    filters: Object,
});

const filters = reactive({
    search: props.filters.search,
    type: props.filters.type,
});

const sendingCredentialsFor = ref(null);

let filterTimer;

watch(filters, () => {
    clearTimeout(filterTimer);
    filterTimer = setTimeout(() => {
        router.get(route('admin.employees.index'), filters, {
            preserveState: true,
            replace: true,
        });
    }, 300);
});

const remove = (employee) => {
    if (confirm(`Eliminare definitivamente il profilo di ${employee.display_name}?`)) {
        router.delete(route('admin.employees.destroy', employee.id));
    }
};

const sendCredentials = (employee) => {
    if (!confirm(`Inviare a ${employee.display_name} la password di primo accesso salvata nel profilo? La password non verrà modificata.`)) {
        return;
    }

    sendingCredentialsFor.value = employee.id;
    router.post(route('admin.employees.send-credentials', employee.id), {}, {
        onFinish: () => {
            sendingCredentialsFor.value = null;
        },
    });
};

const typeLabel = (type) => type === 'athlete' ? 'Atleta' : 'Dipendente';
</script>

<template>
    <Head title="Gestione dipendenti e atleti" />
    <AdminLayout>
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-950">Dipendenti e atleti</h1>
                <p class="mt-1 text-sm text-slate-600">Gestisci anagrafiche e accessi al portale.</p>
            </div>
            <Link :href="route('admin.employees.create')" class="inline-flex items-center justify-center rounded-lg bg-pineto-600 px-4 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-pineto-700">Nuovo profilo</Link>
        </div>

        <section class="mt-6 grid gap-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200 sm:grid-cols-[1fr_200px]">
            <input v-model="filters.search" type="search" placeholder="Cerca nome, email o codice fiscale" class="rounded-lg border-slate-300 text-sm shadow-sm focus:border-pineto-500 focus:ring-pineto-500" />
            <select v-model="filters.type" class="rounded-lg border-slate-300 text-sm shadow-sm focus:border-pineto-500 focus:ring-pineto-500">
                <option value="">Tutte le tipologie</option>
                <option value="employee">Dipendenti</option>
                <option value="athlete">Atleti</option>
            </select>
        </section>

        <section class="mt-6 overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-200">
            <p v-if="$page.props.errors.credentials" role="alert" class="border-b border-red-200 bg-red-50 p-4 text-sm text-red-700">{{ $page.props.errors.credentials }}</p>
            <div v-if="employees.data.length === 0" class="p-8 text-center text-sm text-slate-500">Nessun profilo corrisponde ai filtri selezionati.</div>
            <div v-else class="divide-y divide-slate-100">
                <article v-for="employee in employees.data" :key="employee.id" class="flex flex-col gap-4 p-5 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <div class="flex items-center gap-2">
                            <h2 class="font-bold text-slate-900">{{ employee.display_name }}</h2>
                            <span class="rounded-full bg-pineto-50 px-2.5 py-1 text-xs font-bold text-pineto-700">{{ typeLabel(employee.type) }}</span>
                        </div>
                        <p class="mt-1 text-sm text-slate-600">{{ employee.email }}<span v-if="employee.position"> · {{ employee.position }}</span></p>
                        <p class="mt-1 font-mono text-xs text-slate-500">{{ employee.tax_code }}</p>
                    </div>
                    <div class="flex gap-3">
                        <button type="button" class="rounded-lg border border-pineto-200 px-3 py-2 text-sm font-semibold text-pineto-700 hover:bg-pineto-50 disabled:cursor-wait disabled:opacity-50" :disabled="sendingCredentialsFor === employee.id" @click="sendCredentials(employee)">
                            {{ sendingCredentialsFor === employee.id ? 'Invio...' : 'Invia credenziali' }}
                        </button>
                        <Link :href="route('admin.employees.edit', employee.id)" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Modifica</Link>
                        <button type="button" class="rounded-lg border border-red-200 px-3 py-2 text-sm font-semibold text-red-700 hover:bg-red-50" @click="remove(employee)">Elimina</button>
                    </div>
                </article>
            </div>
        </section>
        <nav v-if="employees.links.length > 3" class="mt-6 flex flex-wrap gap-1">
            <Link v-for="link in employees.links" :key="link.label" :href="link.url || '#'" :class="['rounded px-3 py-2 text-sm', link.active ? 'bg-pineto-600 font-bold text-white' : 'bg-white text-slate-600 ring-1 ring-slate-200', !link.url && 'pointer-events-none opacity-50']" v-html="link.label" />
        </nav>
    </AdminLayout>
</template>
