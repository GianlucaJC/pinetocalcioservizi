<?php

return [

    'pdftotext' => [
        'binary' => env('PDFTOTEXT_BINARY', 'pdftotext'),
        'timeout' => (int) env('PDFTOTEXT_TIMEOUT', 30),
    ],

];
