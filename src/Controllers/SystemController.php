<?php

namespace mediablue\Controllers;

use mediablue\Config;
use mediablue\Utils\CommandRunner;
use mediablue\Utils\Response;

class SystemController
{
    public function getInfo(): void
    {
        $hasBluetoothctl = CommandRunner::hasCommand('bluetoothctl');
        $hasPactl = CommandRunner::hasCommand('pactl');
        $hasPlayerctl = CommandRunner::hasCommand('playerctl');
        $hasWpctl = CommandRunner::hasCommand('wpctl');
        $hasDbusSocket = file_exists('/var/run/dbus/system_bus_socket');

        Response::success([
            'system' => [
                'os' => PHP_OS,
                'php_version' => PHP_VERSION,
                'mock_mode' => Config::isMockMode(),
                'has_bluetoothctl' => $hasBluetoothctl,
                'has_pactl' => $hasPactl,
                'has_playerctl' => $hasPlayerctl,
                'has_wpctl' => $hasWpctl,
                'has_dbus_socket' => $hasDbusSocket,
                'pulse_server' => Config::getPulseServer(),
                'server_time' => date('Y-m-d H:i:s'),
                'uptime' => @file_get_contents('/proc/uptime') ? strtok(file_get_contents('/proc/uptime'), ' ') : 'N/A',
            ]
        ]);
    }

    public function setMode(): void
    {
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        if (!isset($input['mock'])) {
            Response::error('mock boolean is required');
        }

        $mock = (bool)$input['mock'];
        Config::setMockMode($mock);
        Response::success(['mock_mode' => Config::isMockMode()], 'Mode updated');
    }

    public function resetSimulation(): void
    {
        $state = Config::getState();
        unset($state['mock_bt'], $state['mock_media'], $state['mock_audio'], $state['mock_telephony']);
        Config::saveState($state);
        Response::success([], 'Simulation state reset');
    }
}
