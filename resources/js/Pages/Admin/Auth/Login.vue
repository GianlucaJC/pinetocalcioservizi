<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm } from '@inertiajs/vue3';

const form = useForm({ email: '', password: '' });

const submit = () => {
    form.post(route('admin.login.store'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Accesso amministrazione" />
        <div class="mb-6">
            <p class="text-xs font-bold uppercase tracking-[0.2em] text-pineto-600">Pineto Calcio</p>
            <h1 class="mt-2 text-2xl font-bold text-pineto-950">Area amministrativa</h1>
            <p class="mt-1 text-sm text-slate-500">Accedi con le credenziali amministrative configurate.</p>
        </div>
        <form class="space-y-5" @submit.prevent="submit">
            <div>
                <label for="email" class="text-sm font-semibold text-slate-700">Email</label>
                <TextInput id="email" v-model="form.email" type="email" class="mt-2 block w-full rounded-xl border-slate-200 py-3 focus:border-pineto-500 focus:ring-pineto-500" required autofocus autocomplete="username" />
                <InputError class="mt-2" :message="form.errors.email" />
            </div>
            <div>
                <label for="password" class="text-sm font-semibold text-slate-700">Password</label>
                <TextInput id="password" v-model="form.password" type="password" class="mt-2 block w-full rounded-xl border-slate-200 py-3 focus:border-pineto-500 focus:ring-pineto-500" required autocomplete="current-password" />
                <InputError class="mt-2" :message="form.errors.password" />
            </div>
            <PrimaryButton class="w-full justify-center rounded-xl bg-pineto-600 py-3 text-sm font-bold shadow-lg shadow-pineto-600/25 hover:bg-pineto-700 focus:bg-pineto-700 active:bg-pineto-950" :class="{ 'opacity-50': form.processing }" :disabled="form.processing">Accedi all'area admin</PrimaryButton>
        </form>
    </GuestLayout>
</template>
