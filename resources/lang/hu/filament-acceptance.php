<?php

declare(strict_types=1);

return [

    // Field names in the validation messages of a decision, in the accusative ("adja meg a nevét").
    'attributes' => [
        'name' => 'a nevét',
        'email' => 'az e-mail-címét',
        'consent_text' => 'a hozzájárulását',
        'comment' => 'a megjegyzést',
        'reason' => 'az indokot',
        'locale' => 'a nyelvet',
    ],

    'page' => [
        'version' => ':number. változat',
        'fingerprint' => 'ujjlenyomat',
    ],

    'form' => [
        'heading' => 'Az Ön döntése',
        'name' => 'Az Ön neve',
        'email' => 'Az Ön e-mail-címe',
        'comment' => 'Megjegyzés (nem kötelező)',
        'comment_help' => 'Ha elutasítja, ide írhatja az okát.',
        'consent' => 'Elfogadom ezt a dokumentumot és a feltételeit.',
        'accept_button' => 'Elfogadom',
        'decline_name' => 'Az Ön neve (nem kötelező)',
        'decline_email' => 'Az Ön e-mail-címe (nem kötelező)',
        'decline_button' => 'Elutasítom',
        'errors' => 'Kérjük, javítsa a megjelölt mezőket.',
        'legal_note' => 'Ez a megerősítés egyszerű elektronikus nyilatkozat, nem minősített elektronikus aláírás.',
    ],

    'decline_confirm' => [
        'title' => 'Elutasítja ezt a dokumentumot?',
        'warning' => 'Biztosan elutasítja ezt a dokumentumot? Ez nem vonható vissza.',
        'reason' => 'Indok (nem kötelező)',
        'submit' => 'Igen, elutasítom',
        'back' => 'Vissza',
    ],

    'validation' => [
        'required' => 'Kérjük, adja meg :attribute.',
        'email' => 'Kérjük, érvényes e-mail-címet adjon meg.',
        'max' => 'Legfeljebb :max karakter adható meg.',
        'accepted' => 'Az elfogadáshoz jelölje be a négyzetet.',
        'consent_changed' => 'A feltételek szövege közben megváltozott. Kérjük, olvassa el újra, és erősítse meg.',
    ],

    'notice' => [
        'accepted' => 'Köszönjük, az elfogadást rögzítettük.',
        'declined' => 'Köszönjük, a válaszát rögzítettük.',
        'already_decided' => 'Erről a dokumentumról már korábban döntés született, nem változott semmi.',
        'closed' => 'Erről a dokumentumról már nem lehet dönteni.',
    ],

    'summary' => [
        'accepted_by' => 'Elfogadta: :name, :date',
        'accepted' => 'Elfogadva: :date',
        'declined_by' => 'Elutasította: :name, :date',
        'declined' => 'Elutasítva: :date',
        'comment' => 'Megjegyzés',
        'reason' => 'Indok',
        'download' => 'A dokumentum letöltése',
    ],

    'status' => [
        'superseded' => [
            'title' => 'Ebből a dokumentumból újabb változat készült',
            'body' => 'Ez a link egy korábbi változatot mutat, amelyről már nem lehet dönteni. Az újabb változat linkjét külön kellett megkapnia.',
        ],
        'unverifiable' => [
            'title' => 'Ezt a dokumentumot most nem lehet megerősíteni',
            'body' => 'Semmit sem rögzítettünk. Kérjük, vegye fel a kapcsolatot a küldővel.',
        ],
    ],

    'contact' => 'Kérdése van? Írjon ide: :email.',

    'approval' => [
        'yes' => 'Igen',
        'no' => 'Nem',
        'purpose' => 'Jóváhagyás',
    ],

    'filament' => [
        'relation_manager' => [
            'title' => 'Jóváhagyások',
            'empty' => 'Még semmit sem küldtek jóváhagyásra.',
        ],
        'columns' => [
            'version' => 'Változat',
            'version_number' => ':number. változat',
            'sealed_at' => 'Lezárva',
            'status' => 'Állapot',
            'name' => 'Név',
            'decided_at' => 'Döntés ideje',
            'note' => 'Megjegyzés vagy indok',
            'fingerprint' => 'Ujjlenyomat',
        ],
        'status' => [
            'pending' => 'Döntésre vár',
            'accepted' => 'Elfogadva',
            'declined' => 'Elutasítva',
            'superseded' => 'Felülírva',
        ],
        'actions' => [
            'send' => [
                'label' => 'Jóváhagyásra küldés',
                'heading' => 'Jóváhagyásra küldés',
                'url_heading' => 'Jóváhagyási link',
                'submit' => 'Küldés',
            ],
            'download' => [
                'label' => 'Dokumentum letöltése',
                'failed' => 'A dokumentumot nem sikerült ellenőrizni, ezért nem töltöttük le.',
            ],
            'verify' => [
                'label' => 'Ellenőrzés',
                'ok' => 'Sértetlen: a tartalom, a döntés és a dokumentum egyezik a hash-ével.',
                'failed' => 'Az ellenőrzés sikertelen: a tartalom, a döntés vagy a dokumentum megváltozott.',
            ],
            'activity' => [
                'label' => 'Napló',
                'heading' => 'Napló',
                'empty' => 'Még nincs bejegyzés.',
                'time' => 'Időpont',
                'event' => 'Esemény',
                'close' => 'Bezárás',
            ],
        ],
        'events' => [
            'sealed' => 'Lezárva',
            'sent' => 'Jóváhagyásra küldve',
            'accepted' => 'Elfogadva',
            'declined' => 'Elutasítva',
            'evidence_stored' => 'Dokumentum tárolva',
            'erasure' => 'Törölve',
        ],
    ],

];
