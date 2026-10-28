<script setup>
import Checkbox from '@/Components/Checkbox.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({ canResetPassword: Boolean, status: String });

const form = useForm({ email: '', password: '', remember: false });
const submit = () => form.post(route('login'), { onFinish: () => form.reset('password') });
</script>

<template>
    <GuestLayout>
        <Head title="Accesso utenti" />
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-pineto-950">Accesso utenti</h1>
            <p class="mt-1 text-sm text-slate-500">Accedi al tuo profilo personale.</p>
        </div>
        <div v-if="status" class="mb-4 rounded-xl bg-emerald-50 p-3 text-sm font-medium text-emerald-700">{{ status }}</div>
        <form class="space-y-5" @submit.prevent="submit">
            <div>
                <label for="email" class="text-sm font-semibold text-slate-700">Email</label>
                <TextInput id="email" v-model="form.email" type="email" class="mt-2 block w-full rounded-xl border-slate-200 py-3 focus:border-pineto-500 focus:ring-pineto-500" required autofocus autocomplete="username" />
                <InputError class="mt-2" :message="form.errors.email" />
            </div>
            <div>
                <div class="flex items-center justify-between">
                    <label for="password" class="text-sm font-semibold text-slate-700">Password</label>
                    <Link v-if="canResetPassword" :href="route('password.request')" class="text-xs font-semibold text-pineto-600 hover:text-pineto-700">Password dimenticata?</Link>
                </div>
                <TextInput id="password" v-model="form.password" type="password" class="mt-2 block w-full rounded-xl border-slate-200 py-3 focus:border-pineto-500 focus:ring-pineto-500" required autocomplete="current-password" />
                <InputError class="mt-2" :message="form.errors.password" />
            </div>
            <label class="flex items-center gap-2 text-sm text-slate-600"><Checkbox name="remember" v-model:checked="form.remember" /><span>Ricordami su questo dispositivo</span></label>
            <PrimaryButton class="w-full justify-center rounded-xl bg-pineto-600 py-3 text-sm font-bold shadow-lg shadow-pineto-600/25 hover:bg-pineto-700 focus:bg-pineto-700 active:bg-pineto-950" :class="{ 'opacity-50': form.processing }" :disabled="form.processing">Accedi</PrimaryButton>
        </form>
        <div class="mt-6 text-center">
            <a :href="route('admin.login')" class="text-sm font-semibold text-pineto-600 hover:text-pineto-700">Accesso admin</a>
        </div>
    </GuestLayout>
</template>
