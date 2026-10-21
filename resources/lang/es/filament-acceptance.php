<?php

declare(strict_types=1);

return [

    // Field names in the validation messages of a decision, shaped to fit "validation.required".
    'attributes' => [
        'name' => 'su nombre',
        'email' => 'su correo electrónico',
        'consent_text' => 'su consentimiento',
        'comment' => 'un comentario',
        'reason' => 'un motivo',
        'locale' => 'un idioma',
    ],

    'page' => [
        'version' => 'Versión :number',
        'fingerprint' => 'huella',
    ],

    'form' => [
        'accept_heading' => 'Aceptar',
        'name' => 'Su nombre',
        'email' => 'Su correo electrónico',
        'comment' => 'Comentario (opcional)',
        'consent' => 'Acepto este documento y sus condiciones.',
        'accept_button' => 'Acepto',
        'decline_heading' => 'Rechazar',
        'decline_name' => 'Su nombre (opcional)',
        'decline_email' => 'Su correo electrónico (opcional)',
        'reason' => 'Motivo (opcional)',
        'decline_button' => 'Rechazo',
        'errors' => 'Corrija los campos marcados.',
        'legal_note' => 'Esta confirmación es una declaración electrónica simple, no una firma electrónica cualificada.',
    ],

    'validation' => [
        'required' => 'Indique :attribute.',
        'email' => 'Indique un correo electrónico válido.',
        'max' => 'Use como máximo :max caracteres.',
        'accepted' => 'Marque la casilla para aceptar.',
        'consent_changed' => 'Las condiciones cambiaron mientras la página estaba abierta. Léalas de nuevo y confirme.',
    ],

    'notice' => [
        'accepted' => 'Gracias. Su aceptación ha quedado registrada.',
        'declined' => 'Gracias. Su respuesta ha quedado registrada.',
        'already_decided' => 'Ya se había registrado una decisión sobre este documento; no se ha cambiado nada.',
        'closed' => 'Este documento ya no se puede aceptar ni rechazar.',
    ],

    'summary' => [
        'accepted_by' => 'Aceptado por :name el :date',
        'accepted' => 'Aceptado el :date',
        'declined_by' => 'Rechazado por :name el :date',
        'declined' => 'Rechazado el :date',
        'comment' => 'Comentario',
        'reason' => 'Motivo',
        'download' => 'Descargar el documento',
    ],

    'status' => [
        'superseded' => [
            'title' => 'Se ha preparado una versión más reciente de este documento',
            'body' => 'Este enlace muestra una versión anterior, que ya no se puede aceptar ni rechazar. Debería haber recibido un enlace a la versión más reciente.',
        ],
        'unverifiable' => [
            'title' => 'Este documento no se puede confirmar en este momento',
            'body' => 'No se ha registrado nada. Póngase en contacto con el remitente.',
        ],
    ],

    'contact' => '¿Preguntas? Escriba a :email.',

];
