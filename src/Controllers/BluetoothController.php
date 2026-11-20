<?php

namespace mediablue\Controllers;

use mediablue\Config;
use mediablue\Services\BluetoothInterface;
use mediablue\Services\BluezService;
use mediablue\Services\Mock\MockBluetoothService;
use mediablue\Utils\Response;

class BluetoothController
{
    private BluetoothInterface $btService;

    public function __construct()
    {
        $this->btService = Config::isMockMode()
            ? new MockBluetoothService()
            : new BluezService();
    }

    public function getStatus(): void
    {
        $status = $this->btService->getStatus();
        Response::success(['bluetooth' => $status]);
    }

    public function getDevices(): void
    {
        $devices = $this->btService->getDevices();
        Response::success(['devices' => $devices]);
    }

    public function startScan(): void
    {
        $res = $this->btService->startScan();
        Response::success(['scanning' => $res], 'Scan initiated');
    }

    public function stopScan(): void
    {
        $res = $this->btService->stopScan();
        Response::success(['scanning' => false], 'Scan stopped');
    }

    public function pair(): void
    {
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $mac = $input['mac'] ?? '';
        if (!$mac) {
            Response::error('MAC address is required');
        }

        $res = $this->btService->pair($mac);
        if ($res['success']) {
            Response::success($res, 'Paired successfully');
        } else {
            Response::error($res['output'] ?? 'Pairing failed', 500, $res);
        }
    }

    public function connect(): void
    {
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $mac = $input['mac'] ?? '';
        if (!$mac) {
            Response::error('MAC address is required');
        }

        $res = $this->btService->connect($mac);
        if ($res['success']) {
            Response::success($res, 'Connected successfully');
        } else {
            Response::error($res['output'] ?? 'Connection failed', 500, $res);
        }
    }

    public function disconnect(): void
    {
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $mac = $input['mac'] ?? '';
        if (!$mac) {
            Response::error('MAC address is required');
        }

        $res = $this->btService->disconnect($mac);
        if ($res['success']) {
            Response::success($res, 'Disconnected successfully');
        } else {
            Response::error($res['output'] ?? 'Disconnect failed', 500, $res);
        }
    }

    public function remove(): void
    {
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $mac = $input['mac'] ?? '';
        if (!$mac) {
            Response::error('MAC address is required');
        }

        $res = $this->btService->remove($mac);
        if ($res['success']) {
            Response::success($res, 'Device removed');
        } else {
            Response::error($res['output'] ?? 'Removal failed', 500, $res);
        }
    }

    public function setTrust(): void
    {
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $mac = $input['mac'] ?? '';
        $trust = isset($input['trust']) ? (bool)$input['trust'] : true;
        if (!$mac) {
            Response::error('MAC address is required');
        }

        $res = $this->btService->setTrust($mac, $trust);
        Response::success(['trusted' => $trust], $res ? 'Trust setting updated' : 'Failed to update trust');
    }

    public function setDiscoverable(): void
    {
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $discoverable = isset($input['discoverable']) ? (bool)$input['discoverable'] : true;
        $res = $this->btService->setDiscoverable($discoverable);
        Response::success(['discoverable' => $discoverable]);
    }

    public function setPower(): void
    {
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $power = isset($input['power']) ? (bool)$input['power'] : true;
        $res = $this->btService->setPower($power);
        if ($res) {
            Response::success(
                ['powered' => $power],
                $power ? 'Bluetooth turned on successfully' : 'Bluetooth turned off'
            );
        } else {
            Response::error(
                $power ? 'Failed to turn on Bluetooth. Please check host bluetoothd service or rfkill.' : 'Failed to turn off Bluetooth',
                500,
                ['powered' => false]
            );
        }
    }
}
