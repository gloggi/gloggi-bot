<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Symfony\Component\Yaml\Yaml;

class ApiController extends Controller
{
    protected function checkRequestFromBeekeeper(Request $request, $endpoint) {
        if($request->input('webhook_id') !== config("beekeeper.webhook_ids.$endpoint", Str::random(40))) {
            abort(400);
        }
    }

    protected function sendMessage(Request $request, $message) {
        $headers = ['Authorization' => 'Token ' . config('beekeeper.bot_token')];

        $conversationId = $request->input('payload.message.conversation_id');

        $url = config('beekeeper.api_base_url') . 'conversations/' . $conversationId . '/messages';

        Http::withHeaders($headers)->post($url, [
            'text' => $message
        ]);

        // TODO log to database
        echo "Sent message \"" . $message . "\"\n";
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

        $this->checkRequestFromBeekeeper($request, 'message');
        // TODO check 1 on 1 message

        $message = $this->selectRelevantUserAnswer($request);

        // TODO save message and level to database

        $this->sendBotResponse($message->get('next_level'), $request);

    }
}
