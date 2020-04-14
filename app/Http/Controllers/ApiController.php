<?php

namespace App\Http\Controllers;

use App\LevelChange;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\Yaml\Yaml;
use function GuzzleHttp\Psr7\mimetype_from_filename;

class ApiController extends Controller {
    /** @var Request $request */
    protected $request;
    protected $botConfig;

    protected $webhookId;
    protected $sentByBot;
    protected $conversationId;
    protected $userId;
    protected $userName;
    protected $userMessage;
    protected $firstLevel;

    protected function getWebhookId($default = null) {
        return $this->webhookId ?? $this->webhookId = $this->request->input('webhook_id', $default);
    }

    protected function isSentByBot() {
        return $this->sentByBot ?? $this->sentByBot = $this->request->input('payload.message.sent_by_user');
    }

    protected function getConversationId() {
        return $this->conversationId ?? $this->conversationId = $this->request->input('payload.message.conversation_id');
    }

    protected function getUserId() {
        return $this->userId ?? $this->userId = $this->request->input('payload.message.user_id');
    }

    protected function getUserName() {
        return $this->userName ?? $this->userName = $this->request->input('payload.message.profile');
    }

    protected function getUserMessage() {
        return $this->userMessage ?? $this->userMessage = $this->request->input('payload.message.text');
    }

    protected function getBotConfig() {
        return $this->botConfig ?? $this->botConfig = collect(Yaml::parse(file_get_contents(__DIR__ . '/../../../resources/bot.yml')));
    }

    protected function getFirstLevel() {
        return $this->firstLevel ?? $this->firstLevel = collect($this->getBotConfig()->first())->get('level');
    }

    protected function checkRequest($endpoint) {
        // Check webhook UUID according to Beekeeper documentation
        if($this->getWebhookId() !== config("beekeeper.webhook_ids.$endpoint", Str::random(40))) {
            abort(400);
        }

        // Check the processed message is not from the Bot itself
        if($this->isSentByBot() !== false) {
            abort(204);
        }

        // Check that the conversation is a private 1 on 1 conversation
        if($this->sendApiRequest('conversations/' . $this->getConversationId() . '/members')->count() !== 2) {
            abort(400);
        }

        if($this->getBotConfig()->count() < 1) {
            Log::error('Bot config in resources/bot.yml should be a nonempty array');
            abort(500);
        }
    }

    protected function sendApiRequest($endpoint, $method = 'get', $payload = null) {
        $headers = ['Authorization' => 'Token ' . config('beekeeper.bot_token')];
        $url = config('beekeeper.api_base_url') . $endpoint;
        return collect(Http::withHeaders($headers)->$method($url, $payload)->json());
    }

    protected function uploadPhotoToS3($image) {
        $fileName = __DIR__.'/../../../resources/bot-images/'.$image;
        $uploadToken = $this->sendApiRequest('files/photo/upload/token');
        $postParams = collect($uploadToken->get('additional_form_data'))->map(function ($param) {
            return array_merge($param, ['contents' => $param['value']]);
        });
        $file = fopen($fileName, 'r');
        $response = Http::attach($uploadToken->get('file_param_name'), $file, $image, [])
            ->post($uploadToken->get('upload_url'), $postParams->all());
        if ($response->status() !== 201) {
            Log::error("Failed to upload attachment image to S3, response:\n" . print_r($response, true) . "\n" . print_r($response->body()));
            return null;
        }
        $key = $postParams->first(function($param) { return $param['name'] === 'key'; })['value'];
        return $this->sendApiRequest('files/photo/upload', 'post', [
            'media_type' => mimetype_from_filename($image),
            'name' => $image,
            'key' => $key,
            'size' => filesize($fileName),
        ]);
    }

    protected function sendMessageToApi($message, $image = null) {
        if ($image) {
            $photoObject = $this->uploadPhotoToS3($image);
            if (!$photoObject) return;

            $this->sendApiRequest('conversations/' . $this->getConversationId() . '/messages', 'post', [
                'text' => $message,
                'photos' => [ $photoObject ],
            ]);
            Log::info("Sent $image with text '$message' to conversation " . $this->getConversationId());
        } else {
            $this->sendApiRequest('conversations/' . $this->getConversationId() . '/messages', 'post', ['text' => $message]);
            Log::info("Sent text message '$message' to conversation " . $this->getConversationId());
        }
    }

    protected function getLevelConfig($level) {
        return collect($this->getBotConfig()->first(function($l) use ($level) {
            return '' . collect($l)->get('level') === '' . $level;
        }, $this->getBotConfig()->first()));
    }

    protected function normalize($answer) {
        if($answer === null) return null;
        return strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $answer));
    }

    protected function parseUserMessage($level) {
        $userMessage = $this->normalize($this->getUserMessage());

        $activeLevel = $this->getLevelConfig($level);
        $userSays = collect($activeLevel->get('user_says'));

        $matchingOption = collect($userSays->first(function($entry) use ($userMessage) {
            return $this->normalize(collect($entry)->get('text')) === $userMessage;
        }, $activeLevel->get('default')));

        $this->sendBotMessage($matchingOption);

        return $matchingOption->get('next_level');
    }

    /**
     * @param $config Collection
     */
    protected function sendBotMessage($config) {
        $botSays = $config->get('bot_says', []);
        if(is_string($botSays)) {
            $this->sendMessageToApi($botSays);
        } else {
            if(Arr::isAssoc($botSays)) $botSays = [$botSays];
            collect($botSays)->each(function($message) {
                $this->sendMessageToApi(collect($message)->get('text'), collect($message)->get('image'));
            });
        }
    }

    protected function findCurrentLevel($userId, $userName) {
        $user = LevelChange::latest()->firstOrNew(['user_id' => $userId], ['level' => $this->getFirstLevel(), 'name' => $userName]);
        $level = $user->level;
        Log::info("User $userName ($userId) is currently at level '$level'");
        return $level;
    }

    protected function persistCurrentLevel($userId, $userName, $level) {
        Log::info("User $userName ($userId) is now at level $level");
        return LevelChange::create(['user_id' => $userId, 'name' => $userName, 'level' => $level]);
    }

    public function message(Request $request) {

        $this->request = $request;
        $this->checkRequest('message');

        $currentLevel = $this->findCurrentLevel($this->getUserId(), $this->getUserName());
        Log::info(print_r($request->all(), true));

        $newLevel = $this->parseUserMessage($currentLevel);

        if($newLevel !== null) {
            $this->persistCurrentLevel($this->getUserId(), $this->getuserName(), $newLevel);
            $this->sendBotMessage($this->getLevelConfig($newLevel));
        }

    }
}
