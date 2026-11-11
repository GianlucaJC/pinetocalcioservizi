<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import Swal from 'sweetalert2';
import { computed, ref } from 'vue';
import { notificationConfirmation, notificationStatus } from './notificationStatus';

const props = defineProps({
    batch: Object,
    profiles: Array,
    deliveries: Object,
    pending_notifications_count: Number,
    sent_notifications_count: Number,
    has_replaced_documents: Boolean,
});

const assignments = Object.fromEntries(props.batch.segments.map((segment, index) => [index, segment.employee_profile_id]));
const form = useForm({ assignments });
const assignmentsVisible = ref(props.batch.status === 'review');
const publishing = ref(false);
const notificationAction = computed(() => notificationStatus(props.pending_notifications_count, props.sent_notifications_count));

const saveAssignments = () => form.patch(route('admin.payslips.update', props.batch.id));

const sendNotifications = async (batchId, afterPublication = false) => {
    let attempted = false;
    const result = await Swal.fire({
        title: afterPublication ? 'Buste paga pubblicate' : 'Invia notifiche',
        text: notificationConfirmation(props.pending_notifications_count, props.sent_notifications_count, props.has_replaced_documents, afterPublication),
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Invia email',
        cancelButtonText: 'Non inviare',
        showLoaderOnConfirm: true,
        allowOutsideClick: () => !Swal.isLoading(),
        preConfirm: async () => {
            attempted = true;
            try {
                const response = await axios.post(route('admin.payslips.send-notification', batchId));
                if (!Number.isInteger(response.data?.sent_count) || !Array.isArray(response.data?.failed)) {
                    throw new Error('Risposta inattesa dal server.');
                }
                return response.data;
            } catch (error) {
                Swal.showValidationMessage('Invio non confermato. Controlla la sessione e lo stato delle notifiche nel dettaglio del lotto prima di riprovare.');
                return false;
            }
        },
    });

    if (result.isConfirmed) {
        const { sent_count, failed } = result.value;
        if (failed.length > 0) {
            await Swal.fire({
                title: 'Alcune notifiche non sono state inviate',
                text: `Email inviate: ${sent_count}. Da riprovare: ${failed.map((recipient) => `${recipient.name} (${recipient.email})`).join(', ')}.`,
                icon: 'warning',
            });
        } else {
            await Swal.fire('Invio completato', `Email inviate: ${sent_count}.`, 'success');
        }

    }

    if (!afterPublication && attempted) {
        router.reload({ only: ['pending_notifications_count', 'sent_notifications_count', 'has_replaced_documents', 'deliveries'] });
    }
};

const publish = () => {
    if (confirm(`Pubblicare ${props.batch.assignable_count} buste riconosciute? Le ${props.batch.unmatched_count} non assegnate non verranno pubblicate.`)) {
        publishing.value = true;
        const batchId = props.batch.id;
        router.post(route('admin.payslips.publish', batchId), {}, {
            onSuccess: () => sendNotifications(batchId, true),
            onFinish: () => { publishing.value = false; },
        });
    }
};

const destroy = () => {
    if (confirm('Eliminare questa busta e tutte le buste pubblicate? Le buste precedenti sostituite saranno ripristinate, salvo rettifiche successive. L’operazione non può essere annullata.')) {
        router.delete(route('admin.payslips.destroy', props.batch.id));
    }
};
</script>

<template>
    <Head title="Revisione buste paga" />
    <AdminLayout>
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-950">{{ batch.status === 'completed' ? 'Dettaglio buste' : 'Revisione buste' }}</h1>
                <p class="mt-1 text-sm text-slate-600">{{ batch.original_filename }} · {{ batch.period_month }}/{{ batch.period_year }}</p>
            </div>
            <div class="flex items-center gap-4">
                <button v-if="batch.can_delete" type="button" class="text-sm font-semibold text-red-700 hover:text-red-900" @click="destroy">Elimina busta</button>
                <Link :href="route('admin.payslips.index')" class="text-sm font-semibold text-pineto-700 hover:text-pineto-900">Torna alle buste</Link>
            </div>
        </div>

        <div class="mt-6 grid gap-4 sm:grid-cols-2">
            <div class="rounded-xl bg-emerald-50 p-5 ring-1 ring-emerald-200"><p class="text-sm font-semibold text-emerald-800">Buste assegnabili</p><p class="mt-1 text-3xl font-bold text-emerald-900">{{ batch.assignable_count }}</p></div>
            <div class="rounded-xl bg-amber-50 p-5 ring-1 ring-amber-200"><p class="text-sm font-semibold text-amber-800">Da verificare</p><p class="mt-1 text-3xl font-bold text-amber-900">{{ batch.unmatched_count }}</p></div>
        </div>

        <section v-if="batch.status === 'completed'" class="mt-6 overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-200">
            <div class="flex flex-col gap-3 border-b border-slate-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="font-bold text-slate-900">Invii ai dipendenti e atleti</h2>
                    <p class="mt-1 text-sm text-slate-600">Stato delle notifiche e della prima visualizzazione delle buste pubblicate con questo invio.</p>
                    <p class="mt-1 text-sm text-slate-600">Notifiche inviate: {{ sent_notifications_count }} · Destinatari da notificare: {{ pending_notifications_count }}</p>
                    <p v-if="has_replaced_documents" class="mt-1 text-sm text-slate-600">Le buste sostituite da rettifiche sono escluse dalle notifiche di questo lotto.</p>
                </div>
                <button v-if="notificationAction.canSend" type="button" class="rounded-lg bg-pineto-600 px-4 py-2 text-sm font-bold text-white hover:bg-pineto-700" @click="sendNotifications(batch.id)">{{ notificationAction.label }}</button>
                <span v-else :class="sent_notifications_count > 0 ? 'rounded-full bg-emerald-50 px-3 py-1 text-sm font-bold text-emerald-700' : 'text-sm font-semibold text-slate-600'">{{ notificationAction.label }}</span>
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
                        <span v-if="!delivery.is_visible_to_employee" class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-700">Sostituita dalla rettifica n. {{ delivery.replaced_by_revision_number }}</span>
                        <span v-else class="rounded-full px-3 py-1 text-xs font-bold" :class="delivery.notification_sent_at ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700'">{{ delivery.notification_sent_at ? `Notifica inviata il ${delivery.notification_sent_at}` : 'Notifica da inviare' }}</span>
                        <span class="rounded-full px-3 py-1 text-xs font-bold" :class="delivery.viewed_at ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700'">{{ delivery.viewed_at ? `Visionata il ${delivery.viewed_at}` : 'Non ancora visionata' }}</span>
                        <a :href="route('admin.payslips.documents.show', delivery.id)" target="_blank" rel="noopener" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Apri busta</a>
                    </div>
                </article>
            </div>
        </section>
        <nav v-if="batch.status === 'completed' && deliveries.links.length > 3" class="mt-6 flex flex-wrap gap-1">
            <Link v-for="link in deliveries.links" :key="link.label" :href="link.url || '#'" :class="['rounded px-3 py-2 text-sm', link.active ? 'bg-pineto-600 font-bold text-white' : 'bg-white text-slate-600 ring-1 ring-slate-200', !link.url && 'pointer-events-none opacity-50']" v-html="link.label" />
        </nav>
        <p v-if="batch.status === 'completed' && !batch.can_delete" class="mt-6 rounded-xl bg-amber-50 px-5 py-4 text-sm text-amber-800 ring-1 ring-amber-200">Questa busta non può essere eliminata perché almeno un dipendente o atleta l’ha già visualizzata.</p>

        <section class="mt-6 overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-200">
            <button v-if="batch.status === 'completed'" type="button" class="flex w-full items-center justify-between px-5 py-4 text-left hover:bg-slate-50" @click="assignmentsVisible = !assignmentsVisible">
                <span>
                    <span class="block font-bold text-slate-900">Assegnazioni delle buste</span>
                    <span class="mt-1 block text-sm text-slate-600">Codici fiscali e pagine associate durante l’elaborazione.</span>
                </span>
                <span class="text-sm font-semibold text-pineto-700">{{ assignmentsVisible ? 'Nascondi' : 'Mostra' }}</span>
            </button>
            <div v-if="assignmentsVisible" class="divide-y divide-slate-100" :class="{ 'border-t border-slate-100': batch.status === 'completed' }">
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
            <button type="button" :disabled="batch.assignable_count === 0 || publishing" class="rounded-lg bg-pineto-600 px-4 py-2 text-sm font-bold text-white hover:bg-pineto-700 disabled:cursor-not-allowed disabled:opacity-50" @click="publish">Pubblica buste riconosciute</button>
        </div>
    </AdminLayout>
</template>
