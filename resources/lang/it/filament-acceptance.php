<?php

declare(strict_types=1);

return [

    // Field names in the validation messages of a decision, shaped to fit "validation.required".
    'attributes' => [
        'name' => 'il tuo nome',
        'email' => 'il tuo indirizzo e-mail',
        'consent_text' => 'il tuo consenso',
        'comment' => 'un commento',
        'reason' => 'un motivo',
        'locale' => 'una lingua',
    ],

    'page' => [
        'version' => 'Versione :number',
        'fingerprint' => 'impronta',
    ],

    'form' => [
        'accept_heading' => 'Accetta',
        'name' => 'Il tuo nome',
        'email' => 'Il tuo indirizzo e-mail',
        'comment' => 'Commento (facoltativo)',
        'consent' => 'Accetto questo documento e le sue condizioni.',
        'accept_button' => 'Accetto',
        'decline_heading' => 'Rifiuta',
        'decline_name' => 'Il tuo nome (facoltativo)',
        'decline_email' => 'Il tuo indirizzo e-mail (facoltativo)',
        'reason' => 'Motivo (facoltativo)',
        'decline_button' => 'Rifiuto',
        'errors' => 'Correggi i campi evidenziati.',
        'legal_note' => 'Questa conferma è una dichiarazione elettronica semplice, non una firma elettronica qualificata.',
    ],

    'validation' => [
        'required' => 'Inserisci :attribute.',
        'email' => 'Inserisci un indirizzo e-mail valido.',
        'max' => 'Usa al massimo :max caratteri.',
        'accepted' => 'Seleziona la casella per accettare.',
        'consent_changed' => 'Le condizioni sono cambiate mentre la pagina era aperta. Rileggile e conferma.',
    ],

    'notice' => [
        'accepted' => 'Grazie. La tua accettazione è stata registrata.',
        'declined' => 'Grazie. La tua risposta è stata registrata.',
        'already_decided' => 'Per questo documento era già stata registrata una decisione; non è stato modificato nulla.',
        'closed' => 'Questo documento non può più essere accettato o rifiutato.',
    ],

    'summary' => [
        'accepted_by' => 'Accettato da :name il :date',
        'accepted' => 'Accettato il :date',
        'declined_by' => 'Rifiutato da :name il :date',
        'declined' => 'Rifiutato il :date',
        'comment' => 'Commento',
        'reason' => 'Motivo',
        'download' => 'Scarica il documento',
    ],

    'status' => [
        'superseded' => [
            'title' => 'È stata preparata una versione più recente di questo documento',
            'body' => 'Questo link mostra una versione precedente, che non può più essere accettata o rifiutata. Dovresti aver ricevuto un link alla versione più recente.',
        ],
        'unverifiable' => [
            'title' => 'Questo documento non può essere confermato in questo momento',
            'body' => 'Non è stato registrato nulla. Contatta il mittente.',
        ],
    ],

    'contact' => 'Domande? Scrivi a :email.',

    'approval' => [
        'yes' => 'Sì',
        'no' => 'No',
        'purpose' => 'Approvazione',
    ],

    'filament' => [
        'relation_manager' => [
            'title' => 'Approvazioni',
            'empty' => 'Nulla è stato ancora inviato per approvazione.',
        ],
        'columns' => [
            'version' => 'Versione',
            'version_number' => 'v:number',
            'sealed_at' => 'Sigillato',
            'status' => 'Stato',
            'name' => 'Nome',
            'decided_at' => 'Deciso',
            'note' => 'Commento o motivo',
            'fingerprint' => 'Impronta',
        ],
        'status' => [
            'pending' => 'In attesa di decisione',
            'accepted' => 'Accettato',
            'declined' => 'Rifiutato',
            'superseded' => 'Sostituito',
        ],
        'actions' => [
            'send' => [
                'label' => 'Invia per approvazione',
            ],
            'download' => [
                'label' => 'Scarica documento',
                'failed' => 'Non è stato possibile verificare il documento, quindi non è stato scaricato.',
            ],
            'verify' => [
                'label' => 'Verifica',
                'ok' => 'Integro: contenuto, decisione e documento corrispondono ai loro hash.',
                'failed' => 'Verifica non riuscita: il contenuto, la decisione o il documento sono stati modificati.',
            ],
            'activity' => [
                'label' => 'Attività',
                'heading' => 'Attività',
                'empty' => 'Ancora nessuna attività.',
                'time' => 'Ora',
                'event' => 'Evento',
                'close' => 'Chiudi',
            ],
        ],
        'events' => [
            'sealed' => 'Sigillato',
            'sent' => 'Inviato per approvazione',
            'accepted' => 'Accettato',
            'declined' => 'Rifiutato',
            'evidence_stored' => 'Documento salvato',
            'erasure' => 'Cancellato',
        ],
    ],

];
