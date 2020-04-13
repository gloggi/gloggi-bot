<?php

namespace App\Http\Controllers;

use App\LevelChange;
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

        // TODO check bot.yml is a nonempty array
    }

    protected function sendApiRequest($endpoint, $method = 'get', $payload = null) {
        $headers = ['Authorization' => 'Token ' . config('beekeeper.bot_token')];
        $url = config('beekeeper.api_base_url') . $endpoint;
        return Http::withHeaders($headers)->$method($url, $payload)->json();
    }

    protected function sendMessage(Request $request, $message) {
        $conversationId = $request->input('payload.message.conversation_id');
        $this->sendApiRequest('conversations/' . $conversationId . '/messages', 'post', ['text' => $message]);
        Log::info("Sent message \"$message\" to conversation $conversationId\n");
    }

    protected function getBotConfig() {
        return collect(Yaml::parse(file_get_contents(__DIR__ . '/../../../resources/bot.yml')));
    }

    protected function getLevelConfig($level) {
        // TODO useful fallback if level doesn't exist in bot.yml
        return collect($this->getBotConfig()
            ->first(function($l) use($level) { return '' . collect($l)->get('level') === '' . $level; })
        );
    }

    protected function normalize($answer) {
        if ($answer === null) return null;
        return strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $answer));
    }

    protected function parseUserMessage(Request $request, $level) {
        $userMessage = $this->normalize($request->input('payload.message.text', ''));

        $activeLevel = $this->getLevelConfig($level);
        $userSays = collect($activeLevel->get('user_says'));

        // TODO implement direct responses to a certain user message
        return collect($userSays->first(function($entry) use($userMessage) {
            return $this->normalize(collect($entry)->get('text')) === $userMessage;
        }, $activeLevel->get('default')))->get('next_level');
    }

    protected function sendBotResponse($level, Request $request) {
        collect($this->getLevelConfig($level)
            ->get('bot_says', []))->each(function($message) use($request) {
            // TODO implement sending images
            $this->sendMessage($request, collect($message)->get('text'));
        });
    }

    protected function findCurrentLevel($userId, $userName) {
        // TODO fallback to first level if level not in bot.yml
        $user = LevelChange::latest()->firstOrNew(['user_id' => $userId], ['level' => '0', 'name' => $userName]);
        $level = $user->level;
        Log::info("User $userName ($userId) is currently at level '$level'");
        return $level;
    }

    protected function persistCurrentLevel($userId, $userName, $level) {
        Log::info("User $userName ($userId) is now at level $level");
        return LevelChange::create(['user_id' => $userId, 'name' => $userName, 'level' => $level]);
    }

    public function message(Request $request) {

        $this->checkRequest($request, 'message');

        $userId = $request->input('payload.message.user_id');
        $userName = $request->input('payload.message.profile');

        $currentLevel = $this->findCurrentLevel($userId, $userName);
        Log::info(print_r($request->all(), true));

        $newLevel = $this->parseUserMessage($request, $currentLevel);

        if ($newLevel !== null) {
            $this->persistCurrentLevel($userId, $userName, $newLevel);
            $this->sendBotResponse($newLevel, $request);
        }

    }
}
