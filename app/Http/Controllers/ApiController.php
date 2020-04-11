<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\Yaml\Yaml;

class ApiController extends Controller
{
    protected function checkRequest(Request $request, $endpoint) {
        // Check webhook UUID according to Beekeeper documentation
        if($request->input('webhook_id') !== config("beekeeper.webhook_ids.$endpoint", Str::random(40))) {
            abort(400);
        }

        // Check the message is not from the Bot itself
        if ($request->input('payload.message.sent_by_user', true) !== false) {
            abort(204);
        }

        // Check that the conversation is a private 1 on 1 conversation
        $conversationId = $request->input('payload.message.conversation_id');
        if (collect($this->sendApiRequest('conversations/' . $conversationId . '/members'))->count() !== 2) {
            abort(400);
        }
    }

    protected function sendApiRequest($endpoint, $method = 'get', $payload = null) {
        $headers = ['Authorization' => 'Token ' . config('beekeeper.bot_token')];
        $url = config('beekeeper.api_base_url') . $endpoint;
        return Http::withHeaders($headers)->$method($url, $payload)->json();
    }

    protected function sendMessage(Request $request, $message) {
        $conversationId = $request->input('payload.message.conversation_id');
        $this->sendApiRequest('conversations/' . $conversationId . '/messages', 'post', ['text' => $message]);
        // TODO log to database
        Log::info("Sent message \"$message\" to conversation $conversationId\n");
    }

    protected function getBotConfig() {
        $config = collect(Yaml::parse(file_get_contents(__DIR__ . '/../../../resources/bot.yml')));
        return [collect($config->get('levels')), collect($config->get('default'))];
    }

    protected function normalizeAnswer($answer) {
        if ($answer === null) return null;
        return strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $answer));
    }

    protected function selectRelevantUserAnswer(Request $request) {
        [$levels] = $this->getBotConfig();
        // TODO filter $config by current level of user
        $answer = $this->normalizeAnswer($request->input('payload.message.text', ''));
        return collect($levels
            ->flatMap(function($level) { return collect($level)->get('user_says'); })
            ->first(function ($entry) use($answer) {
                return $this->normalizeAnswer(collect($entry)->get('text')) === $answer;
            }, []));
    }

    protected function sendBotResponse($level, Request $request) {
        [$levels, $default] = $this->getBotConfig();
        /** @var \Illuminate\Support\Collection $activeLevel */
        $activeLevel = collect($levels->first(function($l) use($level) { return collect($l)->get('level') === $level; }, $default));

        collect($activeLevel->get('bot_says', []))->each(function($message) use($request) {
            // TODO implement sending images
            $this->sendMessage($request, collect($message)->get('text'));
        });
    }

    public function message(Request $request) {

        $this->checkRequest($request, 'message');
        Log::info(print_r($request->all(), true));

        $message = $this->selectRelevantUserAnswer($request);

        // TODO save message and level to database

        $this->sendBotResponse($message->get('next_level'), $request);

    }
}
