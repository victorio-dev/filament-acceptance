<?php

declare(strict_types=1);

return [

    // Field names in the validation messages of a decision, shaped to fit "validation.required".
    'attributes' => [
        'name' => 'Ihren Namen',
        'email' => 'Ihre E-Mail-Adresse',
        'consent_text' => 'Ihre Zustimmung',
        'comment' => 'einen Kommentar',
        'reason' => 'einen Grund',
        'locale' => 'eine Sprache',
    ],

    'page' => [
        'version' => 'Version :number',
        'fingerprint' => 'Fingerabdruck',
    ],

    'form' => [
        'accept_heading' => 'Annehmen',
        'name' => 'Ihr Name',
        'email' => 'Ihre E-Mail-Adresse',
        'comment' => 'Kommentar (optional)',
        'consent' => 'Ich nehme dieses Dokument und seine Bedingungen an.',
        'accept_button' => 'Annehmen',
        'decline_heading' => 'Ablehnen',
        'decline_name' => 'Ihr Name (optional)',
        'decline_email' => 'Ihre E-Mail-Adresse (optional)',
        'reason' => 'Grund (optional)',
        'decline_button' => 'Ablehnen',
        'errors' => 'Bitte korrigieren Sie die markierten Felder.',
        'legal_note' => 'Diese Bestätigung ist eine einfache elektronische Erklärung, keine qualifizierte elektronische Signatur.',
    ],

    'validation' => [
        'required' => 'Bitte geben Sie :attribute ein.',
        'email' => 'Bitte geben Sie eine gültige E-Mail-Adresse ein.',
        'max' => 'Bitte verwenden Sie höchstens :max Zeichen.',
        'accepted' => 'Bitte setzen Sie das Häkchen, um anzunehmen.',
        'consent_changed' => 'Die Bedingungen haben sich geändert, während die Seite geöffnet war. Bitte lesen Sie sie erneut und bestätigen Sie.',
    ],

    'notice' => [
        'accepted' => 'Vielen Dank. Ihre Annahme wurde gespeichert.',
        'declined' => 'Vielen Dank. Ihre Antwort wurde gespeichert.',
        'already_decided' => 'Zu diesem Dokument wurde bereits eine Entscheidung gespeichert; es wurde nichts geändert.',
        'closed' => 'Dieses Dokument kann nicht mehr angenommen oder abgelehnt werden.',
    ],

    'summary' => [
        'accepted_by' => 'Angenommen von :name am :date',
        'accepted' => 'Angenommen am :date',
        'declined_by' => 'Abgelehnt von :name am :date',
        'declined' => 'Abgelehnt am :date',
        'comment' => 'Kommentar',
        'reason' => 'Grund',
        'download' => 'Dokument herunterladen',
    ],

    'status' => [
        'superseded' => [
            'title' => 'Von diesem Dokument wurde eine neuere Version erstellt',
            'body' => 'Dieser Link zeigt eine frühere Version, die nicht mehr angenommen oder abgelehnt werden kann. Sie sollten einen Link zur neueren Version erhalten haben.',
        ],
        'unverifiable' => [
            'title' => 'Dieses Dokument kann gerade nicht bestätigt werden',
            'body' => 'Es wurde nichts gespeichert. Bitte wenden Sie sich an den Absender.',
        ],
    ],

    'contact' => 'Fragen? Schreiben Sie an :email.',

    'approval' => [
        'yes' => 'Ja',
        'no' => 'Nein',
        'purpose' => 'Freigabe',
    ],

    'filament' => [
        'relation_manager' => [
            'title' => 'Freigaben',
            'empty' => 'Noch nichts zur Freigabe gesendet.',
        ],
        'columns' => [
            'version' => 'Version',
            'version_number' => 'v:number',
            'sealed_at' => 'Versiegelt',
            'status' => 'Status',
            'name' => 'Name',
            'decided_at' => 'Entschieden',
            'note' => 'Kommentar oder Grund',
            'fingerprint' => 'Fingerabdruck',
        ],
        'status' => [
            'pending' => 'Wartet auf Entscheidung',
            'accepted' => 'Angenommen',
            'declined' => 'Abgelehnt',
            'superseded' => 'Ersetzt',
        ],
        'actions' => [
            'send' => [
                'label' => 'Zur Freigabe senden',
            ],
            'download' => [
                'label' => 'Dokument herunterladen',
                'failed' => 'Das Dokument konnte nicht geprüft werden und wurde daher nicht heruntergeladen.',
            ],
            'verify' => [
                'label' => 'Prüfen',
                'ok' => 'Unverändert: Inhalt, Entscheidung und Dokument stimmen mit ihren Hashes überein.',
                'failed' => 'Prüfung fehlgeschlagen: Inhalt, Entscheidung oder Dokument wurden verändert.',
            ],
            'activity' => [
                'label' => 'Verlauf',
                'heading' => 'Verlauf',
                'empty' => 'Noch keine Einträge.',
                'time' => 'Zeit',
                'event' => 'Ereignis',
                'close' => 'Schließen',
            ],
        ],
        'events' => [
            'sealed' => 'Versiegelt',
            'sent' => 'Zur Freigabe gesendet',
            'accepted' => 'Angenommen',
            'declined' => 'Abgelehnt',
            'evidence_stored' => 'Dokument gespeichert',
            'erasure' => 'Gelöscht',
        ],
    ],

];
