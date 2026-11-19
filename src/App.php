<?php

namespace mediablue;

use mediablue\Controllers\BluetoothController;
use mediablue\Controllers\MediaController;
use mediablue\Controllers\TelephonyController;
use mediablue\Controllers\AudioController;
use mediablue\Controllers\SystemController;
use mediablue\Utils\Response;

class App
{
    public static function registerAutoloader(): void
    {
        spl_autoload_register(function ($class) {
            $prefix = 'mediablue\\';
            $baseDir = __DIR__ . '/';

            $len = strlen($prefix);
            if (strncmp($prefix, $class, $len) !== 0) {
                return;
            }

            $relativeClass = substr($class, $len);
            $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

            if (file_exists($file)) {
                require $file;
            }
        });
    }

    public static function run(): void
    {
        self::registerAutoloader();
        Config::init();

        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        // /api プレフィックスのルーティング
        if (str_starts_with($uri, '/api/')) {
            self::routeApi($uri, $method);
            return;
        }

        // 静的ファイルまたはトップ画面のルーティング
        if ($uri === '/' || $uri === '/index.php') {
            require __DIR__ . '/../public/index.php';
            return;
        }

        // 静的アセット配信チェック (CLIサーバー等での実行用)
        $publicFile = __DIR__ . '/../public' . $uri;
        if (file_exists($publicFile) && !is_dir($publicFile)) {
            return; // 組み込みサーバーが直接配信
        }

        Response::error('Not Found', 404);
    }

    private static function routeApi(string $uri, string $method): void
    {
        // Bluetooth
        if ($uri === '/api/bluetooth/status' && $method === 'GET') {
            (new BluetoothController())->getStatus();
        } elseif ($uri === '/api/bluetooth/devices' && $method === 'GET') {
            (new BluetoothController())->getDevices();
        } elseif ($uri === '/api/bluetooth/scan' && $method === 'POST') {
            (new BluetoothController())->startScan();
        } elseif ($uri === '/api/bluetooth/scan/stop' && $method === 'POST') {
            (new BluetoothController())->stopScan();
        } elseif ($uri === '/api/bluetooth/pair' && $method === 'POST') {
            (new BluetoothController())->pair();
        } elseif ($uri === '/api/bluetooth/connect' && $method === 'POST') {
            (new BluetoothController())->connect();
        } elseif ($uri === '/api/bluetooth/disconnect' && $method === 'POST') {
            (new BluetoothController())->disconnect();
        } elseif ($uri === '/api/bluetooth/remove' && $method === 'POST') {
            (new BluetoothController())->remove();
        } elseif ($uri === '/api/bluetooth/trust' && $method === 'POST') {
            (new BluetoothController())->setTrust();
        } elseif ($uri === '/api/bluetooth/discoverable' && $method === 'POST') {
            (new BluetoothController())->setDiscoverable();
        } elseif ($uri === '/api/bluetooth/power' && $method === 'POST') {
            (new BluetoothController())->setPower();
        }

        // Media (A2DP / AVRCP)
        elseif ($uri === '/api/media/player' && $method === 'GET') {
            (new MediaController())->getPlayer();
        } elseif ($uri === '/api/media/control' && $method === 'POST') {
            (new MediaController())->control();
        }

        // Telephony (HFP)
        elseif ($uri === '/api/telephony/status' && $method === 'GET') {
            (new TelephonyController())->getStatus();
        } elseif ($uri === '/api/telephony/answer' && $method === 'POST') {
            (new TelephonyController())->answer();
        } elseif ($uri === '/api/telephony/hangup' && $method === 'POST') {
            (new TelephonyController())->hangup();
        } elseif ($uri === '/api/telephony/dial' && $method === 'POST') {
            (new TelephonyController())->dial();
        } elseif ($uri === '/api/telephony/test-incoming' && $method === 'POST') {
            (new TelephonyController())->triggerTest();
        }

        // Audio (Audioctl / pactl / wpctl)
        elseif ($uri === '/api/audio/status' && $method === 'GET') {
            (new AudioController())->getStatus();
        } elseif ($uri === '/api/audio/volume' && $method === 'POST') {
            (new AudioController())->setVolume();
        } elseif ($uri === '/api/audio/mute' && $method === 'POST') {
            (new AudioController())->setMute();
        } elseif ($uri === '/api/audio/sink' && $method === 'POST') {
            (new AudioController())->setDefaultSink();
        }

        // System
        elseif ($uri === '/api/system/info' && $method === 'GET') {
            (new SystemController())->getInfo();
        } elseif ($uri === '/api/system/mode' && $method === 'POST') {
            (new SystemController())->setMode();
        } elseif ($uri === '/api/system/reset-mock' && $method === 'POST') {
            (new SystemController())->resetSimulation();
        }

        // 404
        else {
            Response::error("Endpoint {$method} {$uri} not found", 404);
        }
    }
}
