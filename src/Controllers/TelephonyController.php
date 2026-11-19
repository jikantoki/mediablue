<?php

namespace mediablue\Controllers;

use mediablue\Config;
use mediablue\Services\TelephonyService;
use mediablue\Services\Mock\MockTelephonyService;
use mediablue\Utils\Response;

class TelephonyController
{
    private $telephonyService;

    public function __construct()
    {
        $this->telephonyService = Config::isMockMode()
            ? new MockTelephonyService()
            : new TelephonyService();
    }

    public function getStatus(): void
    {
        $status = $this->telephonyService->getStatus();
        Response::success(['telephony' => $status]);
    }

    public function answer(): void
    {
        $res = $this->telephonyService->answer();
        Response::success($res, 'Call answered');
    }

    public function hangup(): void
    {
        $res = $this->telephonyService->hangup();
        Response::success($res, 'Call terminated');
    }

    public function dial(): void
    {
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $number = trim($input['number'] ?? '');
        $name = trim($input['name'] ?? '');
        if (!$number) {
            Response::error('Phone number is required');
        }

        $res = $this->telephonyService->dial($number, $name);
        Response::success($res, 'Dialing initiated');
    }

    public function triggerTest(): void
    {
        if (method_exists($this->telephonyService, 'triggerIncoming')) {
            $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $name = $input['name'] ?? 'Yamada Taro (Office)';
            $number = $input['number'] ?? '090-1234-5678';
            $res = $this->telephonyService->triggerIncoming($name, $number);
            Response::success($res, 'Incoming call test triggered');
        } else {
            Response::error('Trigger test is only available in mock/simulation mode');
        }
    }
}
