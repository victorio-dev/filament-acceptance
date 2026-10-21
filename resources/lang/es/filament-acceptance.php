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
        'heading' => 'Su decisión',
        'name' => 'Su nombre',
        'email' => 'Su correo electrónico',
        'comment' => 'Comentario (opcional)',
        'comment_help' => 'Si lo rechaza, puede escribir aquí el motivo.',
        'consent' => 'Acepto este documento y sus condiciones.',
        'accept_button' => 'Acepto',
        'decline_name' => 'Su nombre (opcional)',
        'decline_email' => 'Su correo electrónico (opcional)',
        'decline_button' => 'Rechazo',
        'errors' => 'Corrija los campos marcados.',
        'legal_note' => 'Esta confirmación es una declaración electrónica simple, no una firma electrónica cualificada.',
    ],

    'decline_confirm' => [
        'title' => '¿Rechazar este documento?',
        'warning' => '¿Seguro que quiere rechazar este documento? No se puede deshacer.',
        'reason' => 'Motivo (opcional)',
        'submit' => 'Sí, rechazar',
        'back' => 'Volver',
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

    'approval' => [
        'yes' => 'Sí',
        'no' => 'No',
        'purpose' => 'Aprobación',
    ],

    'filament' => [
        'relation_manager' => [
            'title' => 'Aprobaciones',
            'empty' => 'Aún no se ha enviado nada para su aprobación.',
        ],
        'columns' => [
            'version' => 'Versión',
            'version_number' => 'v:number',
            'sealed_at' => 'Sellado',
            'status' => 'Estado',
            'name' => 'Nombre',
            'decided_at' => 'Decidido',
            'note' => 'Comentario o motivo',
            'fingerprint' => 'Huella',
        ],
        'status' => [
            'pending' => 'Pendiente de decisión',
            'accepted' => 'Aceptado',
            'declined' => 'Rechazado',
            'superseded' => 'Sustituido',
        ],
        'actions' => [
            'send' => [
                'label' => 'Enviar para aprobación',
                'heading' => 'Enviar para aprobación',
                'url_heading' => 'Enlace de aprobación',
                'submit' => 'Enviar',
            ],
            'download' => [
                'label' => 'Descargar documento',
                'failed' => 'No se pudo verificar el documento, por lo que no se descargó.',
            ],
            'verify' => [
                'label' => 'Verificar',
                'ok' => 'Intacto: el contenido, la decisión y el documento coinciden con sus hashes.',
                'failed' => 'La verificación falló: se modificó el contenido, la decisión o el documento.',
            ],
            'activity' => [
                'label' => 'Actividad',
                'heading' => 'Actividad',
                'empty' => 'Todavía no hay actividad.',
                'time' => 'Hora',
                'event' => 'Evento',
                'close' => 'Cerrar',
            ],
        ],
        'events' => [
            'sealed' => 'Sellado',
            'sent' => 'Enviado para aprobación',
            'accepted' => 'Aceptado',
            'declined' => 'Rechazado',
            'evidence_stored' => 'Documento guardado',
            'erasure' => 'Borrado',
        ],
    ],

];
