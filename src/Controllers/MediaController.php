<?php

namespace mediablue\Controllers;

use mediablue\Config;
use mediablue\Services\MediaService;
use mediablue\Services\Mock\MockMediaService;
use mediablue\Utils\Response;

class MediaController
{
    private $mediaService;

    public function __construct()
    {
        $this->mediaService = Config::isMockMode()
            ? new MockMediaService()
            : new MediaService();
    }

    public function getPlayer(): void
    {
        $player = $this->mediaService->getPlayerStatus();
        Response::success(['player' => $player]);
    }

    public function control(): void
    {
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $action = $input['action'] ?? '';
        if (!$action) {
            Response::error('Action is required');
        }

        $res = $this->mediaService->control($action);
        if ($res['success']) {
            Response::success($res, "Action {$action} executed");
        } else {
            Response::error($res['error'] ?? 'Control failed', 400, $res);
        }
    }
}
