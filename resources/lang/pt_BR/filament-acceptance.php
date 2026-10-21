<?php

declare(strict_types=1);

return [

    // Field names in the validation messages of a decision, shaped to fit "validation.required".
    'attributes' => [
        'name' => 'seu nome',
        'email' => 'seu e-mail',
        'consent_text' => 'seu consentimento',
        'comment' => 'um comentário',
        'reason' => 'um motivo',
        'locale' => 'um idioma',
    ],

    'page' => [
        'version' => 'Versão :number',
        'fingerprint' => 'impressão digital',
    ],

    'form' => [
        'accept_heading' => 'Aceitar',
        'name' => 'Seu nome',
        'email' => 'Seu e-mail',
        'comment' => 'Comentário (opcional)',
        'consent' => 'Aceito este documento e suas condições.',
        'accept_button' => 'Aceito',
        'decline_heading' => 'Recusar',
        'decline_name' => 'Seu nome (opcional)',
        'decline_email' => 'Seu e-mail (opcional)',
        'reason' => 'Motivo (opcional)',
        'decline_button' => 'Recuso',
        'errors' => 'Corrija os campos destacados.',
        'legal_note' => 'Esta confirmação é uma declaração eletrônica simples, não uma assinatura eletrônica qualificada.',
    ],

    'validation' => [
        'required' => 'Informe :attribute.',
        'email' => 'Informe um e-mail válido.',
        'max' => 'Use no máximo :max caracteres.',
        'accepted' => 'Marque a caixa para aceitar.',
        'consent_changed' => 'As condições mudaram enquanto a página estava aberta. Leia-as novamente e confirme.',
    ],

    'notice' => [
        'accepted' => 'Obrigado. Sua aceitação foi registrada.',
        'declined' => 'Obrigado. Sua resposta foi registrada.',
        'already_decided' => 'Uma decisão sobre este documento já havia sido registrada; nada foi alterado.',
        'closed' => 'Este documento não pode mais ser aceito nem recusado.',
    ],

    'summary' => [
        'accepted_by' => 'Aceito por :name em :date',
        'accepted' => 'Aceito em :date',
        'declined_by' => 'Recusado por :name em :date',
        'declined' => 'Recusado em :date',
        'comment' => 'Comentário',
        'reason' => 'Motivo',
        'download' => 'Baixar o documento',
    ],

    'status' => [
        'superseded' => [
            'title' => 'Uma versão mais recente deste documento foi preparada',
            'body' => 'Este link mostra uma versão anterior, que não pode mais ser aceita nem recusada. Você deve ter recebido um link para a versão mais recente.',
        ],
        'unverifiable' => [
            'title' => 'Este documento não pode ser confirmado agora',
            'body' => 'Nada foi registrado. Entre em contato com o remetente.',
        ],
    ],

    'contact' => 'Dúvidas? Escreva para :email.',

    'approval' => [
        'yes' => 'Sim',
        'no' => 'Não',
        'purpose' => 'Aprovação',
    ],

    'filament' => [
        'relation_manager' => [
            'title' => 'Aprovações',
            'empty' => 'Nada foi enviado para aprovação ainda.',
        ],
        'columns' => [
            'version' => 'Versão',
            'version_number' => 'v:number',
            'sealed_at' => 'Selado',
            'status' => 'Status',
            'name' => 'Nome',
            'decided_at' => 'Decidido',
            'note' => 'Comentário ou motivo',
            'fingerprint' => 'Impressão digital',
        ],
        'status' => [
            'pending' => 'Aguardando decisão',
            'accepted' => 'Aceito',
            'declined' => 'Recusado',
            'superseded' => 'Substituído',
        ],
        'actions' => [
            'send' => [
                'label' => 'Enviar para aprovação',
            ],
            'download' => [
                'label' => 'Baixar documento',
                'failed' => 'Não foi possível verificar o documento, por isso ele não foi baixado.',
            ],
            'verify' => [
                'label' => 'Verificar',
                'ok' => 'Íntegro: o conteúdo, a decisão e o documento correspondem aos seus hashes.',
                'failed' => 'A verificação falhou: o conteúdo, a decisão ou o documento foram alterados.',
            ],
            'activity' => [
                'label' => 'Atividade',
                'heading' => 'Atividade',
                'empty' => 'Ainda não há atividade.',
                'time' => 'Horário',
                'event' => 'Evento',
                'close' => 'Fechar',
            ],
        ],
        'events' => [
            'sealed' => 'Selado',
            'sent' => 'Enviado para aprovação',
            'accepted' => 'Aceito',
            'declined' => 'Recusado',
            'evidence_stored' => 'Documento armazenado',
            'erasure' => 'Apagado',
        ],
    ],

];
