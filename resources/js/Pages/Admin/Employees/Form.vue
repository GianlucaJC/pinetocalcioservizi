<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import InputError from '@/Components/InputError.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    employee: {
        type: Object,
        default: null,
    },
});

const form = useForm({
    first_name: props.employee?.first_name ?? '',
    last_name: props.employee?.last_name ?? '',
    email: props.employee?.email ?? '',
    password: '',
    type: props.employee?.type ?? 'employee',
    tax_code: props.employee?.tax_code ?? '',
    phone: props.employee?.phone ?? '',
    pec_email: props.employee?.pec_email ?? '',
    birth_date: props.employee?.birth_date ?? '',
    birth_place: props.employee?.birth_place ?? '',
    address: props.employee?.address ?? '',
    city: props.employee?.city ?? '',
    province: props.employee?.province ?? '',
    postal_code: props.employee?.postal_code ?? '',
    position: props.employee?.position ?? '',
    emergency_contact_name: props.employee?.emergency_contact_name ?? '',
    emergency_contact_phone: props.employee?.emergency_contact_phone ?? '',
    started_on: props.employee?.started_on ?? '',
    notes: props.employee?.notes ?? '',
    work_contract: null,
});

const generatedPassword = ref('');
const passwordVisible = ref(false);

const randomInteger = (max) => {
    const range = 0x100000000;
    const limit = Math.floor(range / max) * max;
    const values = new Uint32Array(1);
    let value;

    do {
        window.crypto.getRandomValues(values);
        value = values[0];
    } while (value >= limit);

    return value % max;
};

const generatePassword = () => {
    const words = [
        'albero', 'amico', 'arcobaleno', 'bosco', 'casa', 'cielo', 'colore', 'cuore',
        'fiore', 'fiume', 'foresta', 'isola', 'libro', 'luna', 'mare', 'montagna',
        'nuvola', 'orologio', 'prato', 'primavera', 'sole', 'sorriso', 'stella', 'strada',
        'temporale', 'treno', 'uccello', 'valle', 'vento', 'viaggio', 'vittoria', 'zucchero',
    ];
    const symbols = '!@#$%&*+-_?';
    const firstWord = words[randomInteger(words.length)].slice(0, 3);
    const secondWord = words[randomInteger(words.length)].slice(0, 4);
    const digits = String(randomInteger(100)).padStart(2, '0');
    const symbol = symbols[randomInteger(symbols.length)];
    const password = `${firstWord[0].toUpperCase()}${firstWord.slice(1)}-${secondWord}-${digits}${symbol}`;

    form.password = password;
    generatedPassword.value = password;
    passwordVisible.value = true;
};

const submit = () => {
    if (props.employee) {
        form.transform((data) => ({ ...data, _method: 'put' }))
            .post(route('admin.employees.update', props.employee.id));
        return;
    }

    form.post(route('admin.employees.store'));
};
</script>

<template>
    <Head :title="employee ? 'Modifica profilo' : 'Nuovo profilo'" />
    <AdminLayout>
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-950">{{ employee ? 'Modifica profilo' : 'Nuovo dipendente o atleta' }}</h1>
                <p class="mt-1 text-sm text-slate-600">I campi contrassegnati sono necessari per creare l'accesso al portale.</p>
            </div>
            <Link :href="route('admin.employees.index')" class="text-sm font-semibold text-pineto-700 hover:text-pineto-900">Torna all'elenco</Link>
        </div>
        <form autocomplete="off" class="mt-6 max-w-3xl space-y-6 rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-200" @submit.prevent="submit">
            <section>
                <h2 class="text-base font-bold text-slate-900">Accesso e dati principali</h2>
                <div class="mt-4 grid gap-5 sm:grid-cols-2">
                <div>
                    <label for="first_name" class="text-sm font-semibold text-slate-700">Nome</label>
                    <input id="first_name" v-model="form.first_name" required autocomplete="off" class="mt-2 block w-full rounded-lg border-slate-300 shadow-sm focus:border-pineto-500 focus:ring-pineto-500" />
                    <InputError class="mt-2" :message="form.errors.first_name" />
                </div>
                <div>
                    <label for="last_name" class="text-sm font-semibold text-slate-700">Cognome</label>
                    <input id="last_name" v-model="form.last_name" required autocomplete="off" class="mt-2 block w-full rounded-lg border-slate-300 shadow-sm focus:border-pineto-500 focus:ring-pineto-500" />
                    <InputError class="mt-2" :message="form.errors.last_name" />
                </div>
                <div>
                    <label for="type" class="text-sm font-semibold text-slate-700">Tipologia</label>
                    <select id="type" v-model="form.type" required autocomplete="off" class="mt-2 block w-full rounded-lg border-slate-300 shadow-sm focus:border-pineto-500 focus:ring-pineto-500">
                        <option value="employee">Dipendente</option>
                        <option value="athlete">Atleta</option>
                    </select>
                    <InputError class="mt-2" :message="form.errors.type" />
                </div>
                <div>
                    <label for="email" class="text-sm font-semibold text-slate-700">Email</label>
                    <input id="email" v-model="form.email" required type="email" autocomplete="off" class="mt-2 block w-full rounded-lg border-slate-300 shadow-sm focus:border-pineto-500 focus:ring-pineto-500" />
                    <InputError class="mt-2" :message="form.errors.email" />
                </div>
                <div>
                    <label for="password" class="text-sm font-semibold text-slate-700">{{ employee ? 'Nuova password di primo accesso (facoltativa)' : 'Password di primo accesso' }}</label>
                    <div class="mt-2 flex gap-2">
                        <input id="password" v-model="form.password" :required="!employee" :type="passwordVisible ? 'text' : 'password'" minlength="12" autocomplete="off" class="block w-full rounded-lg border-slate-300 shadow-sm focus:border-pineto-500 focus:ring-pineto-500" @input="generatedPassword = ''" />
                        <button type="button" class="shrink-0 rounded-lg border border-slate-300 px-3 text-sm font-semibold text-slate-700 hover:bg-slate-50" @click="passwordVisible = !passwordVisible">
                            {{ passwordVisible ? 'Nascondi' : 'Mostra' }}
                        </button>
                    </div>
                    <div class="mt-1 flex flex-wrap items-center gap-3">
                        <p class="text-xs text-slate-500">La password deve essere di almeno 12 caratteri. Il generatore crea una password di 12 caratteri con due parole abbreviate, due numeri e un simbolo.</p>
                        <button type="button" class="text-xs font-semibold text-pineto-700 hover:text-pineto-900" @click="generatePassword">Genera password sicura</button>
                    </div>
                    <p v-if="generatedPassword" class="mt-2 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-900" aria-live="polite">
                        Password generata: <strong class="break-all font-mono">{{ generatedPassword }}</strong>
                    </p>
                    <InputError class="mt-2" :message="form.errors.password" />
                </div>
                <div>
                    <label for="tax_code" class="text-sm font-semibold text-slate-700">Codice fiscale</label>
                    <input id="tax_code" v-model="form.tax_code" required autocomplete="off" class="mt-2 block w-full uppercase rounded-lg border-slate-300 shadow-sm focus:border-pineto-500 focus:ring-pineto-500" />
                    <InputError class="mt-2" :message="form.errors.tax_code" />
                </div>
                <div>
                    <label for="phone" class="text-sm font-semibold text-slate-700">Telefono</label>
                    <input id="phone" v-model="form.phone" required type="tel" autocomplete="off" class="mt-2 block w-full rounded-lg border-slate-300 shadow-sm focus:border-pineto-500 focus:ring-pineto-500" />
                    <InputError class="mt-2" :message="form.errors.phone" />
                </div>
                <div>
                    <label for="pec_email" class="text-sm font-semibold text-slate-700">PEC</label>
                    <input id="pec_email" v-model="form.pec_email" type="email" autocomplete="off" class="mt-2 block w-full rounded-lg border-slate-300 shadow-sm focus:border-pineto-500 focus:ring-pineto-500" />
                    <InputError class="mt-2" :message="form.errors.pec_email" />
                </div>
                <div>
                    <label for="position" class="text-sm font-semibold text-slate-700">Ruolo/Mansione</label>
                    <input id="position" v-model="form.position" autocomplete="off" class="mt-2 block w-full rounded-lg border-slate-300 shadow-sm focus:border-pineto-500 focus:ring-pineto-500" />
                    <InputError class="mt-2" :message="form.errors.position" />
                </div>
                <div>
                    <label for="started_on" class="text-sm font-semibold text-slate-700">Data inizio collaborazione</label>
                    <input id="started_on" v-model="form.started_on" type="date" autocomplete="off" class="mt-2 block w-full rounded-lg border-slate-300 shadow-sm focus:border-pineto-500 focus:ring-pineto-500" />
                    <InputError class="mt-2" :message="form.errors.started_on" />
                </div>
                </div>
            </section>

            <section class="border-t border-slate-100 pt-6">
                <h2 class="text-base font-bold text-slate-900">Dati anagrafici e residenza</h2>
                <div class="mt-4 grid gap-5 sm:grid-cols-2">
                    <div>
                        <label for="birth_date" class="text-sm font-semibold text-slate-700">Data di nascita</label>
                        <input id="birth_date" v-model="form.birth_date" type="date" autocomplete="off" class="mt-2 block w-full rounded-lg border-slate-300 shadow-sm focus:border-pineto-500 focus:ring-pineto-500" />
                        <InputError class="mt-2" :message="form.errors.birth_date" />
                    </div>
                    <div>
                        <label for="birth_place" class="text-sm font-semibold text-slate-700">Luogo di nascita</label>
                        <input id="birth_place" v-model="form.birth_place" autocomplete="off" class="mt-2 block w-full rounded-lg border-slate-300 shadow-sm focus:border-pineto-500 focus:ring-pineto-500" />
                        <InputError class="mt-2" :message="form.errors.birth_place" />
                    </div>
                    <div class="sm:col-span-2">
                        <label for="address" class="text-sm font-semibold text-slate-700">Indirizzo di residenza</label>
                        <input id="address" v-model="form.address" autocomplete="off" class="mt-2 block w-full rounded-lg border-slate-300 shadow-sm focus:border-pineto-500 focus:ring-pineto-500" />
                        <InputError class="mt-2" :message="form.errors.address" />
                    </div>
                    <div>
                        <label for="city" class="text-sm font-semibold text-slate-700">Comune</label>
                        <input id="city" v-model="form.city" autocomplete="off" class="mt-2 block w-full rounded-lg border-slate-300 shadow-sm focus:border-pineto-500 focus:ring-pineto-500" />
                        <InputError class="mt-2" :message="form.errors.city" />
                    </div>
                    <div class="grid grid-cols-2 gap-5">
                        <div>
                            <label for="province" class="text-sm font-semibold text-slate-700">Provincia</label>
                            <input id="province" v-model="form.province" maxlength="2" autocomplete="off" class="mt-2 block w-full uppercase rounded-lg border-slate-300 shadow-sm focus:border-pineto-500 focus:ring-pineto-500" />
                            <InputError class="mt-2" :message="form.errors.province" />
                        </div>
                        <div>
                            <label for="postal_code" class="text-sm font-semibold text-slate-700">CAP</label>
                            <input id="postal_code" v-model="form.postal_code" maxlength="10" autocomplete="off" class="mt-2 block w-full rounded-lg border-slate-300 shadow-sm focus:border-pineto-500 focus:ring-pineto-500" />
                            <InputError class="mt-2" :message="form.errors.postal_code" />
                        </div>
                    </div>
                </div>
            </section>

            <section class="border-t border-slate-100 pt-6">
                <h2 class="text-base font-bold text-slate-900">Contatto di emergenza e documenti</h2>
                <div class="mt-4 grid gap-5 sm:grid-cols-2">
                    <div>
                        <label for="emergency_contact_name" class="text-sm font-semibold text-slate-700">Nome contatto di emergenza</label>
                        <input id="emergency_contact_name" v-model="form.emergency_contact_name" autocomplete="off" class="mt-2 block w-full rounded-lg border-slate-300 shadow-sm focus:border-pineto-500 focus:ring-pineto-500" />
                        <InputError class="mt-2" :message="form.errors.emergency_contact_name" />
                    </div>
                    <div>
                        <label for="emergency_contact_phone" class="text-sm font-semibold text-slate-700">Telefono contatto di emergenza</label>
                        <input id="emergency_contact_phone" v-model="form.emergency_contact_phone" type="tel" autocomplete="off" class="mt-2 block w-full rounded-lg border-slate-300 shadow-sm focus:border-pineto-500 focus:ring-pineto-500" />
                        <InputError class="mt-2" :message="form.errors.emergency_contact_phone" />
                    </div>
                    <div class="sm:col-span-2">
                        <label for="work_contract" class="text-sm font-semibold text-slate-700">{{ employee?.has_work_contract ? 'Sostituisci contratto di lavoro' : 'Contratto di lavoro' }}</label>
                        <input id="work_contract" type="file" autocomplete="off" accept="application/pdf" class="mt-2 block w-full text-sm text-slate-600 file:mr-4 file:rounded-lg file:border-0 file:bg-pineto-50 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-pineto-700 hover:file:bg-pineto-100" @input="form.work_contract = $event.target.files[0]" />
                        <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs">
                            <a v-if="employee?.has_work_contract" :href="route('admin.employees.contract', employee.id)" target="_blank" rel="noopener" class="font-semibold text-pineto-700 hover:text-pineto-900">Visualizza contratto attuale</a>
                            <p class="text-slate-500">File PDF, massimo 10 MB. {{ employee?.has_work_contract ? 'Il nuovo file sostituirà quello esistente.' : '' }}</p>
                        </div>
                        <InputError class="mt-2" :message="form.errors.work_contract" />
                    </div>
                    <div class="sm:col-span-2">
                        <label for="notes" class="text-sm font-semibold text-slate-700">Note interne</label>
                        <textarea id="notes" v-model="form.notes" rows="4" autocomplete="off" class="mt-2 block w-full rounded-lg border-slate-300 shadow-sm focus:border-pineto-500 focus:ring-pineto-500" />
                        <InputError class="mt-2" :message="form.errors.notes" />
                    </div>
                </div>
            </section>
            <div class="flex items-center justify-end gap-3 border-t border-slate-100 pt-5">
                <Link :href="route('admin.employees.index')" class="rounded-lg px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">Annulla</Link>
                <button type="submit" :disabled="form.processing" class="rounded-lg bg-pineto-600 px-4 py-2 text-sm font-bold text-white hover:bg-pineto-700 disabled:opacity-50">{{ employee ? 'Salva modifiche' : 'Crea profilo' }}</button>
            </div>
        </form>
    </AdminLayout>
</template>
