<?php




return [
    'private_key_path' => env('PRIVATE_KEY_PATH'),
    'public_key_path' => env('PUBLIC_KEY_PATH'),
    'private_key_passphrase' => env('PRIVATE_KEY_PASSPHRASE'),

    'verification_route_name' => 'field-inspector.documents.verify',

    'disk' => env('DOCUMENT_SIGNING_DISK', 'local'),
    'directory' => env(
        'DOCUMENT_SIGNING_DIRECTORY',
        'generated-documents/service-requests'
    ),
];
