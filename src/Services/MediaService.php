<?php

namespace mediablue\Services;

use mediablue\Utils\CommandRunner;

class MediaService
{
    /**
     * 接続中のBluetoothオーディオデバイスのD-Busパスを取得する
     * 例: /org/bluez/hci0/dev_64_A2_F9_3B_5C_88
     */
    public function getConnectedDevicePath(): ?string
    {
        // 1. D-Bus ObjectManager から Connected == true のデバイスを探す (最速)
        $res = CommandRunner::run("dbus-send --system --print-reply --dest=org.bluez / org.freedesktop.DBus.ObjectManager.GetManagedObjects 2>/dev/null");
        if ($res['success'] && !empty($res['stdout'])) {
            // "object path "/org/bluez/hci0/dev_..." の直近に "Connected" variant boolean true があるものを探す
            if (preg_match_all('/object path "(\/org\/bluez\/[^\/]+\/dev_[^"]+)"([\s\S]*?)(?=object path|$)/i', $res['stdout'], $matches, PREG_SET_ORDER)) {
                foreach ($matches as $m) {
                    $path = $m[1];
                    $body = $m[2];
                    if (str_contains($body, 'org.bluez.Device1') && preg_match('/string "Connected"\s+variant\s+boolean\s+true/i', $body)) {
                        return $path;
                    }
                }
            }
        }

        // 2. bluetoothctl devices から MAC を取得し、Connected == yes のものを探す
        $devRes = CommandRunner::run("bluetoothctl devices 2>/dev/null");
        if ($devRes['success'] && !empty($devRes['stdout'])) {
            $lines = explode("\n", $devRes['stdout']);
            foreach ($lines as $line) {
                if (preg_match('/Device\s+([0-9A-Fa-f:]{17})/i', trim($line), $dm)) {
                    $mac = strtoupper(trim($dm[1]));
                    $infoRes = CommandRunner::run("bluetoothctl info " . escapeshellarg($mac) . " 2>/dev/null");
                    if ($infoRes['success'] && str_contains($infoRes['stdout'], 'Connected: yes')) {
                        $formattedMac = 'dev_' . str_replace(':', '_', $mac);
                        return "/org/bluez/hci0/{$formattedMac}";
                    }
                }
            }
        }

        return null;
    }

    /**
     * 現在スマホ側でアクティブになっているMediaPlayer1のオブジェクトパスを取得する
     * BlueZの MediaControl1.Player プロパティを直接参照するため、
     * スマホ側で別プレイヤー（Spotify, Apple Music, YouTube等）に切り替えた瞬間、
     * 即座に新しいプレイヤーパス (player0, player1, player2...) に追従します。
     */
    public function findActivePlayerPath(): ?string
    {
        $devPath = $this->getConnectedDevicePath();

        // 1. デバイスの MediaControl1.Player プロパティを問い合わせる (最も正確な正規ルート)
        if ($devPath) {
            $cmd = "dbus-send --system --print-reply --dest=org.bluez " 
                 . escapeshellarg($devPath) 
                 . " org.freedesktop.DBus.Properties.Get string:org.bluez.MediaControl1 string:Player 2>/dev/null";
            $res = CommandRunner::run($cmd);
            if ($res['success'] && preg_match('/variant\s+object path "([^"]+)"/i', $res['stdout'], $m)) {
                $playerPath = trim($m[1]);
                if ($playerPath && $playerPath !== '/') {
                    return $playerPath;
                }
            }

            // 2. MediaControl1.Player が取れない場合: devPath 配下の player0 〜 player4 をスキャン
            //    Status が "playing" のものを最優先で採用
            $playingPlayer = null;
            $firstPlayer = null;

            for ($i = 0; $i <= 4; $i++) {
                $candidate = "{$devPath}/player{$i}";
                $statusCmd = "dbus-send --system --print-reply --dest=org.bluez " 
                           . escapeshellarg($candidate) 
                           . " org.freedesktop.DBus.Properties.Get string:org.bluez.MediaPlayer1 string:Status 2>/dev/null";
                $sRes = CommandRunner::run($statusCmd);
                if ($sRes['success']) {
                    if (!$firstPlayer) {
                        $firstPlayer = $candidate;
                    }
                    if (preg_match('/variant\s+string\s+"playing"/i', $sRes['stdout'])) {
                        return $candidate; // 再生中が見つかったら即時確定
                    }
                }
            }

            if ($firstPlayer) {
                return $firstPlayer;
            }
        }

        // 3. busctl があれば busctl tree org.bluez から player* を探す
        if (CommandRunner::hasCommand('busctl')) {
            $busRes = CommandRunner::run("busctl tree org.bluez 2>/dev/null");
            if ($busRes['success'] && preg_match_all('/(\/org\/bluez\/[^\s]+\/player\d+)/', $busRes['stdout'], $matches)) {
                $players = array_unique($matches[1]);
                // 再生中のものを優先
                foreach ($players as $p) {
                    $sRes = CommandRunner::run("dbus-send --system --print-reply --dest=org.bluez " . escapeshellarg($p) . " org.freedesktop.DBus.Properties.Get string:org.bluez.MediaPlayer1 string:Status 2>/dev/null");
                    if ($sRes['success'] && preg_match('/variant\s+string\s+"playing"/i', $sRes['stdout'])) {
                        return $p;
                    }
                }
                return end($players) ?: $players[0];
            }
        }

        return null;
    }

    /**
     * A2DP メディアプレーヤーの再生情報・メタデータを取得
     */
    public function getPlayerStatus(): array
    {
        $playerPath = $this->findActivePlayerPath();

        // 1. D-Bus System Bus (BlueZ MediaPlayer1) から現在のアクティブプレイヤー情報を取得
        if ($playerPath) {
            $cmd = "dbus-send --system --print-reply --dest=org.bluez " 
                 . escapeshellarg($playerPath) 
                 . " org.freedesktop.DBus.Properties.GetAll string:org.bluez.MediaPlayer1 2>/dev/null";
            $res = CommandRunner::run($cmd);

            if ($res['success'] && !empty($res['stdout'])) {
                $out = $res['stdout'];

                $title = '';
                $artist = '';
                $album = '';
                $appName = '';
                $status = 'stopped';
                $position = 0;
                $duration = 0;

                if (preg_match('/string\s+"Status"\s+variant\s+string\s+"([^"]*)"/i', $out, $m)) {
                    $status = strtolower(trim($m[1]));
                }
                if (preg_match('/string\s+"Name"\s+variant\s+string\s+"([^"]*)"/i', $out, $m)) {
                    $appName = trim($m[1]);
                }
                if (preg_match('/string\s+"Title"\s+variant\s+string\s+"([^"]*)"/i', $out, $m)) {
                    $title = trim($m[1]);
                }
                if (preg_match('/string\s+"Artist"\s+variant\s+string\s+"([^"]*)"/i', $out, $m)) {
                    $artist = trim($m[1]);
                }
                if (preg_match('/string\s+"Album"\s+variant\s+string\s+"([^"]*)"/i', $out, $m)) {
                    $album = trim($m[1]);
                }
                if (preg_match('/string\s+"Position"\s+variant\s+uint32\s+(\d+)/i', $out, $m)) {
                    $position = (int)round((int)$m[1] / 1000);
                }
                if (preg_match('/string\s+"Duration"\s+variant\s+uint32\s+(\d+)/i', $out, $m)) {
                    $duration = (int)round((int)$m[1] / 1000);
                }

                // 曲名が存在するか、再生中・一時停止中の場合
                if (!empty($title) || in_array($status, ['playing', 'paused'])) {
                    return [
                        'mode' => 'real',
                        'available' => true,
                        'title' => $title ?: 'Streaming Audio',
                        'artist' => $artist ?: ($appName ?: 'Bluetooth Audio'),
                        'album' => $album ?: '',
                        'app_name' => $appName ?: 'Music Player',
                        'status' => in_array($status, ['playing', 'paused']) ? $status : 'paused',
                        'position' => $position,
                        'duration' => $duration ?: 180,
                        'codec' => 'A2DP (AAC/SBC)',
                        'player_path' => $playerPath,
                    ];
                }
            }
        }

        // 2. playerctl 経由 (セッションバス / WirePlumber MPRIS2 が有効な場合)
        if (CommandRunner::hasCommand('playerctl')) {
            $format = "{{title}}::;::{{artist}}::;::{{album}}::;::{{status}}::;::{{position}}::;::{{mpris:length}}";
            $res = CommandRunner::run("playerctl metadata --format " . escapeshellarg($format) . " 2>/dev/null");
            if ($res['success'] && !empty($res['stdout'])) {
                $parts = explode("::;::", $res['stdout']);
                $title = $parts[0] ?? '';
                $artist = $parts[1] ?? '';
                $album = $parts[2] ?? '';
                $status = strtolower($parts[3] ?? 'stopped');
                $position = isset($parts[4]) ? (int)round((float)$parts[4] / 1000000) : 0;
                $duration = isset($parts[5]) ? (int)round((float)$parts[5] / 1000000) : 0;

                if (!empty($title) || in_array($status, ['playing', 'paused'])) {
                    return [
                        'mode' => 'real',
                        'available' => true,
                        'title' => $title ?: 'Streaming Audio',
                        'artist' => $artist ?: 'Bluetooth Device',
                        'album' => $album ?: 'A2DP Sink',
                        'app_name' => 'MPRIS2',
                        'status' => in_array($status, ['playing', 'paused']) ? $status : 'stopped',
                        'position' => $position,
                        'duration' => $duration ?: 180,
                        'codec' => 'A2DP (playerctl)',
                    ];
                }
            }
        }

        // 3. 接続中のBluetooth端末がある場合
        $devPath = $this->getConnectedDevicePath();
        if ($devPath) {
            // デバイス名を取得
            $nameCmd = "dbus-send --system --print-reply --dest=org.bluez " 
                     . escapeshellarg($devPath) 
                     . " org.freedesktop.DBus.Properties.Get string:org.bluez.Device1 string:Name 2>/dev/null";
            $nRes = CommandRunner::run($nameCmd);
            $deviceName = 'Connected Smartphone';
            if ($nRes['success'] && preg_match('/variant\s+string\s+"([^"]+)"/i', $nRes['stdout'], $nm)) {
                $deviceName = trim($nm[1]);
            }

            return [
                'mode' => 'real',
                'available' => true,
                'title' => 'Ready to Play',
                'artist' => $deviceName,
                'album' => 'Play audio on smartphone',
                'app_name' => '',
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
            'app_name' => '',
            'status' => 'stopped',
            'position' => 0,
            'duration' => 0,
            'codec' => '-',
        ];
    }

    /**
     * AVRCP コントロールコマンドを実行
     * 現在アクティブなプレイヤー（MediaControl1.Player）に対して的確に送信
     */
    public function control(string $action): array
    {
        $playerPath = $this->findActivePlayerPath();
        $errors = [];

        // 1. D-Bus System Bus (BlueZ MediaPlayer1) で実行
        if ($playerPath) {
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
            $errors[] = "bluetoothctl pipe: " . $res['stdout'];
        }

        // 3. playerctl (フォールバック 2)
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
                $errors[] = "playerctl: " . $res['stdout'];
            }
        }

        return [
            'success' => false,
            'error' => implode(' | ', $errors) ?: "Control failed for action '{$action}'"
        ];
    }
}
