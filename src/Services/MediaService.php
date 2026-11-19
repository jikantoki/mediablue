<?php

namespace mediablue\Services;

use mediablue\Utils\CommandRunner;

class MediaService
{
    /**
     * D-Busから現在利用可能なBlueZ MediaPlayer1のオブジェクトパスを検索する
     */
    public function findMediaPlayerPath(): ?string
    {
        // 1. D-Bus ObjectManager から探索 (最も確実)
        $res = CommandRunner::run("dbus-send --system --print-reply --dest=org.bluez / org.freedesktop.DBus.ObjectManager.GetManagedObjects 2>/dev/null");
        if ($res['success'] && !empty($res['stdout'])) {
            // "org.bluez.MediaPlayer1" を持つオブジェクトパスを探す
            if (preg_match('/object path "(\/org\/bluez\/[^\/]+\/dev_[^\/]+\/player\d+)"/i', $res['stdout'], $m)) {
                return $m[1];
            }
        }

        // 2. 接続中のBluetoothデバイスMACアドレスから推測
        $btRes = CommandRunner::run("bluetoothctl info 2>/dev/null");
        if ($btRes['success'] && preg_match('/Device\s+([0-9A-Fa-f:]{17})/i', $btRes['stdout'], $m)) {
            $formattedMac = 'dev_' . str_replace(':', '_', strtoupper(trim($m[1])));
            // デフォルトコントローラー hci0 を想定
            $candidate = "/org/bluez/hci0/{$formattedMac}/player0";
            
            // このパスが存在するか確認
            $check = CommandRunner::run("dbus-send --system --print-reply --dest=org.bluez " . escapeshellarg($candidate) . " org.freedesktop.DBus.Properties.Get string:org.bluez.MediaPlayer1 string:Status 2>/dev/null");
            if ($check['success']) {
                return $candidate;
            }
        }

        // 3. busctl があれば busctl tree org.bluez で探す
        if (CommandRunner::hasCommand('busctl')) {
            $busRes = CommandRunner::run("busctl tree org.bluez 2>/dev/null");
            if ($busRes['success'] && preg_match('/(\/org\/bluez\/[^\s]+\/player\d+)/', $busRes['stdout'], $m)) {
                return trim($m[1]);
            }
        }

        return null;
    }

    /**
     * A2DP メディアプレーヤーの再生情報・メタデータを取得
     */
    public function getPlayerStatus(): array
    {
        $playerPath = $this->findMediaPlayerPath();

        // 1. D-Bus System Bus 経由で取得 (BlueZ MediaPlayer1)
        if ($playerPath) {
            $cmd = "dbus-send --system --print-reply --dest=org.bluez " . escapeshellarg($playerPath) . " org.freedesktop.DBus.Properties.GetAll string:org.bluez.MediaPlayer1 2>/dev/null";
            $res = CommandRunner::run($cmd);

            if ($res['success'] && !empty($res['stdout'])) {
                $out = $res['stdout'];
                
                $title = 'Streaming Audio';
                $artist = 'Bluetooth Device';
                $album = 'A2DP Sink';
                $status = 'stopped';
                $position = 0;
                $duration = 0;

                if (preg_match('/string\s+"Status"\s+variant\s+string\s+"([^"]+)"/i', $out, $m)) {
                    $status = strtolower($m[1]);
                }
                if (preg_match('/string\s+"Title"\s+variant\s+string\s+"([^"]+)"/i', $out, $m)) {
                    $title = $m[1];
                }
                if (preg_match('/string\s+"Artist"\s+variant\s+string\s+"([^"]+)"/i', $out, $m)) {
                    $artist = $m[1];
                }
                if (preg_match('/string\s+"Album"\s+variant\s+string\s+"([^"]+)"/i', $out, $m)) {
                    $album = $m[1];
                }
                if (preg_match('/string\s+"Position"\s+variant\s+uint32\s+(\d+)/i', $out, $m)) {
                    $position = (int)round((int)$m[1] / 1000);
                }
                if (preg_match('/string\s+"Duration"\s+variant\s+uint32\s+(\d+)/i', $out, $m)) {
                    $duration = (int)round((int)$m[1] / 1000);
                }

                return [
                    'mode' => 'real',
                    'available' => true,
                    'title' => $title,
                    'artist' => $artist,
                    'album' => $album,
                    'status' => in_array($status, ['playing', 'paused']) ? $status : 'stopped',
                    'position' => $position,
                    'duration' => $duration ?: 180,
                    'codec' => 'A2DP (SBC/AAC)',
                    'player_path' => $playerPath,
                ];
            }
        }

        // 2. playerctl 経由 (ホストのセッションバスが接続されている場合)
        if (CommandRunner::hasCommand('playerctl')) {
            $format = "{{title}}::;::{{artist}}::;::{{album}}::;::{{status}}::;::{{position}}::;::{{mpris:length}}";
            $res = CommandRunner::run("playerctl metadata --format " . escapeshellarg($format) . " 2>/dev/null");
            
            if ($res['success'] && !empty($res['stdout'])) {
                $parts = explode("::;::", $res['stdout']);
                $title = $parts[0] ?? 'Streaming Audio';
                $artist = $parts[1] ?? 'Bluetooth Device';
                $album = $parts[2] ?? 'A2DP Sink';
                $status = strtolower($parts[3] ?? 'stopped');
                $position = isset($parts[4]) ? (int)round((float)$parts[4] / 1000000) : 0;
                $duration = isset($parts[5]) ? (int)round((float)$parts[5] / 1000000) : 0;

                return [
                    'mode' => 'real',
                    'available' => true,
                    'title' => $title ?: 'Streaming Audio',
                    'artist' => $artist ?: 'Bluetooth Device',
                    'album' => $album ?: 'A2DP Sink',
                    'status' => in_array($status, ['playing', 'paused']) ? $status : 'stopped',
                    'position' => $position,
                    'duration' => $duration ?: 180,
                    'codec' => 'A2DP (playerctl)',
                ];
            }
        }

        // 3. bluetoothctl info から接続機器を特定
        $res = CommandRunner::run("bluetoothctl info 2>/dev/null");
        if ($res['success'] && str_contains($res['stdout'], 'Connected: yes')) {
            $deviceName = 'Connected Smartphone';
            if (preg_match('/Name:\s*(.+)$/m', $res['stdout'], $m)) {
                $deviceName = trim($m[1]);
            }
            return [
                'mode' => 'real',
                'available' => true,
                'title' => 'Bluetooth Audio Connected',
                'artist' => $deviceName,
                'album' => 'Open music app on phone to play',
                'status' => 'paused',
                'position' => 0,
                'duration' => 0,
                'codec' => 'A2DP Sink',
            ];
        }

        return [
            'mode' => 'real',
            'available' => false,
            'title' => 'No Media Playing',
            'artist' => 'Connect a phone via Bluetooth',
            'album' => '',
            'status' => 'stopped',
            'position' => 0,
            'duration' => 0,
            'codec' => '-',
        ];
    }

    /**
     * AVRCP コントロールコマンドを実行
     */
    public function control(string $action): array
    {
        $playerPath = $this->findMediaPlayerPath();
        $errors = [];

        // 1. D-Bus System Bus 経由で実行 (BlueZ MediaPlayer1)
        if ($playerPath) {
            // トグルの場合は現在の再生状態から判定
            $method = match ($action) {
                'play' => 'Play',
                'pause' => 'Pause',
                'play-pause', 'toggle' => null, // 後で判定
                'next' => 'Next',
                'previous', 'prev' => 'Previous',
                'stop' => 'Stop',
                default => null,
            };

            if ($action === 'play-pause' || $action === 'toggle') {
                $statusInfo = $this->getPlayerStatus();
                $method = ($statusInfo['status'] === 'playing') ? 'Pause' : 'Play';
            }

            if ($method) {
                $cmd = "dbus-send --system --type=method_call --print-reply --dest=org.bluez " 
                     . escapeshellarg($playerPath) 
                     . " org.bluez.MediaPlayer1." . $method . " 2>&1";
                $res = CommandRunner::run($cmd);

                if ($res['success']) {
                    return [
                        'success' => true,
                        'action' => $action,
                        'method' => $method,
                        'player_path' => $playerPath,
                        'via' => 'dbus-system-bus',
                        'output' => $res['stdout']
                    ];
                }
                $errors[] = "D-Bus ({$playerPath}) failed: " . ($res['stderr'] ?: $res['stdout']);
            }
        } else {
            $errors[] = "No active BlueZ MediaPlayer1 object found on D-Bus. Is your phone connected and playing music?";
        }

        // 2. bluetoothctl 対話モードパイプ (フォールバック 1)
        $btCommand = match ($action) {
            'play' => 'play',
            'pause' => 'pause',
            'play-pause', 'toggle' => 'play',
            'next' => 'next',
            'previous', 'prev' => 'previous',
            'stop' => 'stop',
            default => null,
        };

        if ($btCommand) {
            // bluetoothctl の menu player に入って操作
            $pipeScript = "menu player\n{$btCommand}\nback\nquit\n";
            $cmd = "printf " . escapeshellarg($pipeScript) . " | bluetoothctl 2>&1";
            $res = CommandRunner::run($cmd);
            if ($res['success'] && !str_contains($res['stdout'], 'Failed') && !str_contains($res['stdout'], 'not available')) {
                return [
                    'success' => true,
                    'action' => $action,
                    'via' => 'bluetoothctl-pipe',
                    'output' => $res['stdout']
                ];
            }
            $errors[] = "bluetoothctl pipe failed: " . $res['stdout'];
        }

        // 3. playerctl CLI (フォールバック 2: Session Bus がある場合)
        if (CommandRunner::hasCommand('playerctl')) {
            $pAction = match ($action) {
                'play' => 'play',
                'pause' => 'pause',
                'play-pause', 'toggle' => 'play-pause',
                'next' => 'next',
                'previous', 'prev' => 'previous',
                'stop' => 'stop',
                default => null,
            };

            if ($pAction) {
                $res = CommandRunner::run("playerctl {$pAction} 2>&1");
                if ($res['success']) {
                    return [
                        'success' => true,
                        'action' => $action,
                        'via' => 'playerctl',
                        'output' => $res['stdout']
                    ];
                }
                $errors[] = "playerctl failed: " . $res['stdout'];
            }
        }

        return [
            'success' => false,
            'error' => implode(' | ', $errors) ?: "Control failed for action '{$action}'"
        ];
    }
}
