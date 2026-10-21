<?php

declare(strict_types=1);

return [

    // Field names in the validation messages of a decision, shaped to fit "validation.required".
    'attributes' => [
        'name' => 'uw naam',
        'email' => 'uw e-mailadres',
        'consent_text' => 'uw toestemming',
        'comment' => 'een opmerking',
        'reason' => 'een reden',
        'locale' => 'een taal',
    ],

    'page' => [
        'version' => 'Versie :number',
        'fingerprint' => 'vingerafdruk',
    ],

    'form' => [
        'accept_heading' => 'Accepteren',
        'name' => 'Uw naam',
        'email' => 'Uw e-mailadres',
        'comment' => 'Opmerking (optioneel)',
        'consent' => 'Ik accepteer dit document en de voorwaarden ervan.',
        'accept_button' => 'Ik accepteer',
        'decline_heading' => 'Weigeren',
        'decline_name' => 'Uw naam (optioneel)',
        'decline_email' => 'Uw e-mailadres (optioneel)',
        'reason' => 'Reden (optioneel)',
        'decline_button' => 'Ik weiger',
        'errors' => 'Corrigeer de gemarkeerde velden.',
        'legal_note' => 'Deze bevestiging is een eenvoudige elektronische verklaring, geen gekwalificeerde elektronische handtekening.',
    ],

    'validation' => [
        'required' => 'Vul :attribute in.',
        'email' => 'Vul een geldig e-mailadres in.',
        'max' => 'Gebruik maximaal :max tekens.',
        'accepted' => 'Vink het vakje aan om te accepteren.',
        'consent_changed' => 'De voorwaarden zijn gewijzigd terwijl de pagina open stond. Lees ze opnieuw en bevestig.',
    ],

    'notice' => [
        'accepted' => 'Bedankt. Uw acceptatie is vastgelegd.',
        'declined' => 'Bedankt. Uw antwoord is vastgelegd.',
        'already_decided' => 'Voor dit document was al een beslissing vastgelegd; er is niets gewijzigd.',
        'closed' => 'Dit document kan niet meer worden geaccepteerd of geweigerd.',
    ],

    'summary' => [
        'accepted_by' => 'Geaccepteerd door :name op :date',
        'accepted' => 'Geaccepteerd op :date',
        'declined_by' => 'Geweigerd door :name op :date',
        'declined' => 'Geweigerd op :date',
        'comment' => 'Opmerking',
        'reason' => 'Reden',
        'download' => 'Document downloaden',
    ],

    'status' => [
        'superseded' => [
            'title' => 'Er is een nieuwere versie van dit document gemaakt',
            'body' => 'Deze link toont een eerdere versie, die niet meer kan worden geaccepteerd of geweigerd. U zou een link naar de nieuwere versie moeten hebben ontvangen.',
        ],
        'unverifiable' => [
            'title' => 'Dit document kan nu niet worden bevestigd',
            'body' => 'Er is niets vastgelegd. Neem contact op met de afzender.',
        ],
    ],

    'contact' => 'Vragen? Schrijf naar :email.',

];
