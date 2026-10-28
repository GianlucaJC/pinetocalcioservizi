<x-mail::message>
# Ciao {{ $firstName }},

Ecco le credenziali temporanee per accedere al portale Pineto Calcio.

Email di accesso: {{ $email }}

Password temporanea: <code>{{ $temporaryPassword }}</code>

Al primo accesso ti verrà chiesto di scegliere una nuova password.

<x-mail::button :url="$loginUrl">
Accedi al portale
</x-mail::button>

Questo messaggio è automatico. Non rispondere a questa email.
</x-mail::message>
