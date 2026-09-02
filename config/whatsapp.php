<?php

return [
    'driver' => env('WHATSAPP_DRIVER', 'log'),
    'graph_url' => env('WHATSAPP_GRAPH_URL', 'https://graph.facebook.com'),
    'graph_version' => env('WHATSAPP_GRAPH_VERSION', 'v23.0'),
    'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
    'access_token' => env('WHATSAPP_ACCESS_TOKEN'),
    'verify_token' => env('WHATSAPP_VERIFY_TOKEN'),
    'app_secret' => env('WHATSAPP_APP_SECRET'),
    'default_country_code' => env('WHATSAPP_DEFAULT_COUNTRY_CODE', '52'),
    'language' => env('WHATSAPP_TEMPLATE_LANGUAGE', 'es_MX'),
    'ticket_url_minutes' => (int) env('WHATSAPP_TICKET_URL_MINUTES', 60),
    'templates' => [
        'confirmation' => env('WHATSAPP_TEMPLATE_CONFIRMATION', 'barbercontrol_confirmacion_cita'),
        'reminder_24h' => env('WHATSAPP_TEMPLATE_REMINDER_24H', 'barbercontrol_recordatorio_24h'),
        'reminder_2h' => env('WHATSAPP_TEMPLATE_REMINDER_2H', 'barbercontrol_recordatorio_2h'),
        'cancellation' => env('WHATSAPP_TEMPLATE_CANCELLATION', 'barbercontrol_cancelacion_cita'),
        'rescheduled' => env('WHATSAPP_TEMPLATE_RESCHEDULED', 'barbercontrol_reprogramacion_cita'),
        'ticket' => env('WHATSAPP_TEMPLATE_TICKET', 'barbercontrol_ticket'),
    ],
];
