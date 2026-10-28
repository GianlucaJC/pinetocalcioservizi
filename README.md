<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## Area amministrativa

L'area amministrativa è disponibile su `/admin/login` e non richiede un record nel database per l'amministratore.

Configurare le seguenti variabili nel file `.env`:

```env
ADMIN_EMAIL=admin@pinetocalcio.test
ADMIN_PASSWORD="scegli-una-password-lunga-e-univoca"
```

Le credenziali avviano una sessione dedicata all'admin. Da `/admin/employees` è possibile cercare, filtrare, creare, modificare ed eliminare dipendenti e atleti.

Quando si crea un profilo di dipendente o atleta, l'email e la password di primo accesso inserite nel modulo sono le credenziali del portale. Al primo accesso l'utente deve scegliere una password personale di almeno 12 caratteri prima di poter usare le altre pagine. Se l'amministratore imposta una nuova password su un profilo esistente, il cambio è richiesto al successivo accesso.

L'email delle credenziali viene inviata solo dal pulsante **Invia credenziali** nell'elenco admin, senza generare o sostituire la password salvata. La password temporanea viene conservata cifrata con `APP_KEY`, esclusa dai dati inviati al browser e cancellata quando l'utente cambia password. Proteggere `APP_KEY` e i backup. Per gli account precedenti a questa funzionalità è necessario impostare e salvare una nuova password temporanea prima dell'invio.

Applicare le migrazioni con `php artisan migrate`. Configurare il mittente autorizzato dal provider tramite `MAIL_FROM_ADDRESS` e il nome tramite `MAIL_FROM_NAME` (ad esempio `Pineto Calcio - Non rispondere`). Il nome e l'avviso nella mail indicano di non rispondere, ma non bloccano le risposte: l'eventuale gestione della casella è a carico del provider. Non modificare `MAIL_USERNAME` per cambiare il solo mittente. Dopo una modifica alla configurazione eseguire `php artisan config:clear` oppure rigenerare la cache in produzione.

Il modulo anagrafico admin consente inoltre di gestire PEC, dati di nascita e residenza, contatto di emergenza e note interne. È possibile allegare o sostituire il contratto di lavoro in PDF (massimo 10 MB); il file è conservato nello storage privato dell'applicazione e reso disponibile solo al dipendente o atleta associato.

## Importazione buste paga

Dall’area admin, la voce **Buste paga** permette di caricare un singolo PDF testuale fino a 50 MB per il mese e l’anno selezionati. Il sistema legge il codice fiscale presente in ogni pagina, raggruppa le pagine consecutive con lo stesso codice e propone una revisione prima della pubblicazione.

Le sequenze riconosciute vengono associate automaticamente al relativo profilo. Le sequenze con un codice assente o non riconosciuto restano da verificare: l’admin può assegnarle manualmente a un dipendente o atleta, oppure lasciarle fuori dalla pubblicazione. Dopo la conferma, ogni utente vede esclusivamente la propria busta paga nell’area documenti.

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

You may also try the [Laravel Bootcamp](https://bootcamp.laravel.com), where you will be guided through building a modern Laravel application from scratch.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com/)**
- **[Tighten Co.](https://tighten.co)**
- **[WebReinvent](https://webreinvent.com/)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel/)**
- **[Cyber-Duck](https://cyber-duck.co.uk)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Jump24](https://jump24.co.uk)**
- **[Redberry](https://redberry.international/laravel/)**
- **[Active Logic](https://activelogic.com)**
- **[byte5](https://byte5.de)**
- **[OP.GG](https://op.gg)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
