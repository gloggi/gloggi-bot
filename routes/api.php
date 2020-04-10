<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

function isRequestFromBeekeeper(Request $request, $endpoint) {
	return $request->input('webhook_id') === config("beekeeper.webhook_ids.$endpoint", Str::random(40));
}

Route::post('message', function(Request $request) {
	
	if (!isRequestFromBeekeeper($request, 'message')) {
		return response('', 400);
	}
	
	$headers = ['Authorization' => 'Token ' . config('beekeeper.bot_token')];
	
	$conversationId = $request->input('payload.message.conversation_id');
	
	$url = config('beekeeper.api_base_url') . 'conversations/' . $conversationId . '/messages';
	
	Http::withHeaders($headers)->post($url, [
		'text' => 'I have received your message.'
	]);
	
});
