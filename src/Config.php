<?php

namespace mediablue;

use mediablue\Utils\CommandRunner;

class Config
{
    private static ?bool $mockMode = null;
    private static string $stateFile = '';

    public static function init(): void
    {
        $storageDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'mediablue';
        if (!is_dir($storageDir)) {
            @mkdir($storageDir, 0777, true);
        }
        self::$stateFile = $storageDir . DIRECTORY_SEPARATOR . 'state.json';
    }

    public static function isMockMode(): bool
    {
        if (self::$mockMode !== null) {
            return self::$mockMode;
        }

        // 1. 環境変数が明示的に指定されている場合
        $env = getenv('MOCK_MODE');
        if ($env !== false) {
            self::$mockMode = filter_var($env, FILTER_VALIDATE_BOOLEAN);
            return self::$mockMode;
        }

        // 2. 状態ファイルで手動切り替えされている場合
        $state = self::getState();
        if (isset($state['force_mock'])) {
            self::$mockMode = (bool)$state['force_mock'];
            return self::$mockMode;
        }

        // 3. 非Linux環境（Windows/macOS）の場合は強制モック
        if (PHP_OS_FAMILY !== 'Linux') {
            self::$mockMode = true;
            return self::$mockMode;
        }

        // 4. Linuxでbluetoothctlが存在しないか、BlueZ D-Busソケットが無い場合はモックにフォールバック
        if (!CommandRunner::hasCommand('bluetoothctl') || !file_exists('/var/run/dbus/system_bus_socket')) {
            self::$mockMode = true;
            return self::$mockMode;
        }

        // 実機Linux
        self::$mockMode = false;
        return self::$mockMode;
    }

    public static function setMockMode(bool $mock): void
    {
        self::$mockMode = $mock;
        $state = self::getState();
        $state['force_mock'] = $mock;
        self::saveState($state);
    }

    public static function getState(): array
    {
        self::init();
        if (file_exists(self::$stateFile)) {
            $content = file_get_contents(self::$stateFile);
            $data = json_decode($content, true);
            if (is_array($data)) {
                return $data;
            }
        }
        return [];
    }

    public static function saveState(array $data): void
    {
        self::init();
        file_put_contents(self::$stateFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    public static function getPulseServer(): string
    {
        return getenv('PULSE_SERVER') ?: 'unix:/run/user/1000/pulse/native';
    }
}
