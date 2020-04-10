<?php

return [

    'api_base_url' => env('BEEKEEPER_API_BASE_URL'),
    'bot_token' => env('BEEKEEPER_BOT_TOKEN'),
    'webhook_ids' => [
		'message' => env('BEEKEEPER_WEBHOOK_ID_MESSAGE'),
    ]

];
