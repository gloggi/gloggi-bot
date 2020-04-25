<?php

namespace App\Http\Controllers;

use App\Http\Requests\SchleckRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Yaml\Yaml;

class SchleckController extends Controller {
    /** @var Request $request */
    protected $request;
    protected $sentences;
    protected $labels;

    protected function checkRequest() {
        try {
            $this->request->validated();
        } catch (ValidationException $e) {
            abort(400);
        }
    }

    protected function readConfig() {
        $config = collect(Yaml::parse(file_get_contents(__DIR__ . '/../../../resources/schleck.yml')));
        $this->sentences = $config->get('sentences');
        $this->labels = $config->get('labels');
    }

    protected function sendApiRequest($endpoint, $method = 'get', $payload = null) {
        $headers = ['Authorization' => 'Token ' . config('beekeeper.bot_token')];
        $url = config('beekeeper.api_base_url') . $endpoint;
        return collect(Http::withHeaders($headers)->$method($url, $payload)->json());
    }

    public function notify(SchleckRequest $request) {
        Log::info('Schleck notification request: ' . print_r($request->all(), true));
        $this->request = $request;
        $this->checkRequest();

        $this->readConfig();

        $sentence = trans($this->sentences[array_rand($this->sentences)], [
            'source' => $request->get('s'),
            'target' => $request->get('t'),
            'url' => config('schleck.url')
        ]);
        $label = $this->labels[array_rand($this->labels)];
        $streamId = config('schleck.beekeeper_stream_id');

        Log::info('Sending to Beekeeper Stream ' . $streamId . ' the message "' . $sentence . '" with label "' . $label . '"');
        $this->sendApiRequest('streams/' . $streamId . '/posts', 'post', [
            'text' => $sentence,
            'labels' => [ $label ],
        ]);
    }
}
