<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm } from '@inertiajs/vue3';

const form = useForm({
    password: '',
    password_confirmation: '',
});

const submit = () => form.put(route('password.first-login.update'), {
    onFinish: () => form.reset(),
});
</script>

<template>
    <GuestLayout>
        <Head title="Imposta la tua password" />
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-pineto-950">Imposta la tua password</h1>
            <p class="mt-1 text-sm text-slate-500">Per continuare, sostituisci la password temporanea con una personale di almeno 12 caratteri.</p>
        </div>
        <form class="space-y-5" @submit.prevent="submit">
            <div>
                <label for="password" class="text-sm font-semibold text-slate-700">Nuova password</label>
                <TextInput id="password" v-model="form.password" type="password" minlength="12" required autocomplete="new-password" class="mt-2 block w-full rounded-xl border-slate-200 py-3 focus:border-pineto-500 focus:ring-pineto-500" />
                <InputError class="mt-2" :message="form.errors.password" />
            </div>
            <div>
                <label for="password_confirmation" class="text-sm font-semibold text-slate-700">Conferma nuova password</label>
                <TextInput id="password_confirmation" v-model="form.password_confirmation" type="password" minlength="12" required autocomplete="new-password" class="mt-2 block w-full rounded-xl border-slate-200 py-3 focus:border-pineto-500 focus:ring-pineto-500" />
                <InputError class="mt-2" :message="form.errors.password_confirmation" />
            </div>
            <PrimaryButton class="w-full justify-center rounded-xl bg-pineto-600 py-3 text-sm font-bold hover:bg-pineto-700" :disabled="form.processing">Salva e continua</PrimaryButton>
        </form>
    </GuestLayout>
</template>
