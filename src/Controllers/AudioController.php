<?php

namespace mediablue\Controllers;

use mediablue\Config;
use mediablue\Services\AudioService;
use mediablue\Services\Mock\MockAudioService;
use mediablue\Utils\Response;

class AudioController
{
    private $audioService;

    public function __construct()
    {
        $this->audioService = Config::isMockMode()
            ? new MockAudioService()
            : new AudioService();
    }

    public function getStatus(): void
    {
        $status = $this->audioService->getStatus();
        Response::success(['audio' => $status]);
    }

    public function setVolume(): void
    {
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        if (!isset($input['volume'])) {
            Response::error('Volume is required');
        }

        $volume = (int)$input['volume'];
        $sink = $input['sink'] ?? null;
        $res = $this->audioService->setVolume($volume, $sink);
        Response::success(['volume' => $volume, 'success' => $res]);
    }

    public function setMute(): void
    {
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        if (!isset($input['mute'])) {
            Response::error('Mute boolean is required');
        }

        $mute = (bool)$input['mute'];
        $sink = $input['sink'] ?? null;
        $res = $this->audioService->setMute($mute, $sink);
        Response::success(['muted' => $mute, 'success' => $res]);
    }

    public function setDefaultSink(): void
    {
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $sink = $input['sink'] ?? '';
        if (!$sink) {
            Response::error('Sink name is required');
        }

        $res = $this->audioService->setDefaultSink($sink);
        Response::success(['default_sink' => $sink, 'success' => $res]);
    }
}
