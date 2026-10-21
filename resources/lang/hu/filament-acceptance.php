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
        'accept_heading' => 'Elfogadás',
        'name' => 'Az Ön neve',
        'email' => 'Az Ön e-mail-címe',
        'comment' => 'Megjegyzés (nem kötelező)',
        'consent' => 'Elfogadom ezt a dokumentumot és a feltételeit.',
        'accept_button' => 'Elfogadom',
        'decline_heading' => 'Elutasítás',
        'decline_name' => 'Az Ön neve (nem kötelező)',
        'decline_email' => 'Az Ön e-mail-címe (nem kötelező)',
        'reason' => 'Indok (nem kötelező)',
        'decline_button' => 'Elutasítom',
        'errors' => 'Kérjük, javítsa a megjelölt mezőket.',
        'legal_note' => 'Ez a megerősítés egyszerű elektronikus nyilatkozat, nem minősített elektronikus aláírás.',
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

];
