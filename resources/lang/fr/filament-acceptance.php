<?php

declare(strict_types=1);

return [

    // Field names in the validation messages of a decision, shaped to fit "validation.required".
    'attributes' => [
        'name' => 'votre nom',
        'email' => 'votre adresse e-mail',
        'consent_text' => 'votre consentement',
        'comment' => 'un commentaire',
        'reason' => 'un motif',
        'locale' => 'une langue',
    ],

    'page' => [
        'version' => 'Version :number',
        'fingerprint' => 'empreinte',
    ],

    'form' => [
        'accept_heading' => 'Accepter',
        'name' => 'Votre nom',
        'email' => 'Votre adresse e-mail',
        'comment' => 'Commentaire (facultatif)',
        'consent' => 'J\'accepte ce document et ses conditions.',
        'accept_button' => 'J\'accepte',
        'decline_heading' => 'Refuser',
        'decline_name' => 'Votre nom (facultatif)',
        'decline_email' => 'Votre adresse e-mail (facultatif)',
        'reason' => 'Motif (facultatif)',
        'decline_button' => 'Je refuse',
        'errors' => 'Veuillez corriger les champs signalés.',
        'legal_note' => 'Cette confirmation est une déclaration électronique simple, et non une signature électronique qualifiée.',
    ],

    'validation' => [
        'required' => 'Veuillez indiquer :attribute.',
        'email' => 'Veuillez indiquer une adresse e-mail valide.',
        'max' => 'Utilisez au maximum :max caractères.',
        'accepted' => 'Cochez la case pour accepter.',
        'consent_changed' => 'Les conditions ont changé pendant que la page était ouverte. Veuillez les relire et confirmer.',
    ],

    'notice' => [
        'accepted' => 'Merci. Votre acceptation a été enregistrée.',
        'declined' => 'Merci. Votre réponse a été enregistrée.',
        'already_decided' => 'Une décision avait déjà été enregistrée pour ce document ; rien n’a été modifié.',
        'closed' => 'Ce document ne peut plus être accepté ni refusé.',
    ],

    'summary' => [
        'accepted_by' => 'Accepté par :name le :date',
        'accepted' => 'Accepté le :date',
        'declined_by' => 'Refusé par :name le :date',
        'declined' => 'Refusé le :date',
        'comment' => 'Commentaire',
        'reason' => 'Motif',
        'download' => 'Télécharger le document',
    ],

    'status' => [
        'superseded' => [
            'title' => 'Une version plus récente de ce document a été préparée',
            'body' => 'Ce lien affiche une version antérieure, qui ne peut plus être acceptée ni refusée. Vous devriez avoir reçu un lien vers la version plus récente.',
        ],
        'unverifiable' => [
            'title' => 'Ce document ne peut pas être confirmé pour le moment',
            'body' => 'Rien n\'a été enregistré. Veuillez contacter l\'expéditeur.',
        ],
    ],

    'contact' => 'Des questions ? Écrivez à :email.',

];
