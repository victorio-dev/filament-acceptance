<?php

declare(strict_types=1);

return [

    // Field names in the validation messages of a decision.
    'attributes' => [
        'name' => 'name',
        'email' => 'email address',
        'consent_text' => 'consent',
        'comment' => 'comment',
        'reason' => 'reason',
        'locale' => 'language',
    ],

    'page' => [
        'version' => 'Version :number',
        'fingerprint' => 'fingerprint',
    ],

    'form' => [
        'accept_heading' => 'Accept',
        'name' => 'Your name',
        'email' => 'Your email address',
        'comment' => 'Comment (optional)',
        'consent' => 'I accept this document and its terms.',
        'accept_button' => 'Accept',
        'decline_heading' => 'Decline',
        'decline_name' => 'Your name (optional)',
        'decline_email' => 'Your email address (optional)',
        'reason' => 'Reason (optional)',
        'decline_button' => 'Decline',
        'errors' => 'Please correct the marked fields.',
        'legal_note' => 'This confirmation is a simple electronic declaration, not a qualified electronic signature.',
    ],

    'validation' => [
        'required' => 'Please enter your :attribute.',
        'email' => 'Please enter a valid email address.',
        'max' => 'Please use at most :max characters.',
        'accepted' => 'Please tick the box to accept.',
        'consent_changed' => 'The terms changed while the page was open. Please read them again and confirm.',
    ],

    'notice' => [
        'accepted' => 'Thank you. Your acceptance has been recorded.',
        'declined' => 'Thank you. Your answer has been recorded.',
        'already_decided' => 'A decision on this document had already been recorded; nothing was changed.',
        'closed' => 'This document can no longer be accepted or declined.',
    ],

    'summary' => [
        'accepted_by' => 'Accepted by :name on :date',
        'accepted' => 'Accepted on :date',
        'declined_by' => 'Declined by :name on :date',
        'declined' => 'Declined on :date',
        'comment' => 'Comment',
        'reason' => 'Reason',
        'download' => 'Download the document',
    ],

    'status' => [
        'superseded' => [
            'title' => 'A newer version of this document was prepared',
            'body' => 'This link shows an earlier version, which can no longer be accepted or declined. You should have received a link to the newer version.',
        ],
        'unverifiable' => [
            'title' => 'This document cannot be confirmed right now',
            'body' => 'Nothing was recorded. Please get in touch with the sender.',
        ],
    ],

    'contact' => 'Questions? Write to :email.',

];
