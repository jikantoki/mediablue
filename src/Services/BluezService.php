<?php

namespace mediablue\Services;

use mediablue\Utils\CommandRunner;

class BluezService implements BluetoothInterface
{
    private string $adapter = 'hci0';

    public function getStatus(): array
    {
        $res = CommandRunner::run("bluetoothctl show");
        $powered = false;
        $discoverable = false;
        $pairable = false;
        $discovering = false;
        $name = 'Ubuntu mediablue';
        $mac = '00:00:00:00:00:00';

        if ($res['success']) {
            $lines = explode("\n", $res['stdout']);
            foreach ($lines as $line) {
                $line = trim($line);
                if (preg_match('/^Controller\s+([0-9A-Fa-f:]+)\s*(.*)$/', $line, $m)) {
                    $mac = $m[1];
                } elseif (preg_match('/^Name:\s*(.*)$/', $line, $m)) {
                    $name = $m[1];
                } elseif (preg_match('/^Powered:\s*(yes|no)/i', $line, $m)) {
                    $powered = (strtolower($m[1]) === 'yes');
                } elseif (preg_match('/^Discoverable:\s*(yes|no)/i', $line, $m)) {
                    $discoverable = (strtolower($m[1]) === 'yes');
                } elseif (preg_match('/^Pairable:\s*(yes|no)/i', $line, $m)) {
                    $pairable = (strtolower($m[1]) === 'yes');
                } elseif (preg_match('/^Discovering:\s*(yes|no)/i', $line, $m)) {
                    $discovering = (strtolower($m[1]) === 'yes');
                }
            }
        }

        $devices = $this->getDevices();
        $connectedDevices = array_filter($devices, fn($d) => $d['connected']);

        return [
            'mode' => 'real',
            'adapter' => [
                'name' => $name,
                'mac' => $mac,
                'powered' => $powered,
                'discoverable' => $discoverable,
                'pairable' => $pairable,
                'discovering' => $discovering,
            ],
            'connected_count' => count($connectedDevices),
            'devices' => array_values($devices),
        ];
    }

    public function getDevices(): array
    {
        $devices = [];
        
        // 1. ペアリング済み/キャッシュ済みデバイス
        $res = CommandRunner::run("bluetoothctl devices");
        if ($res['success']) {
            $lines = explode("\n", $res['stdout']);
            foreach ($lines as $line) {
                // Device XX:XX:XX:XX:XX:XX DeviceName
                if (preg_match('/^Device\s+([0-9A-Fa-f:]{17})\s+(.*)$/', trim($line), $m)) {
                    $mac = strtoupper($m[1]);
                    $name = trim($m[2]);
                    $devices[$mac] = [
                        'mac' => $mac,
                        'name' => $name,
                        'alias' => $name,
                        'paired' => false,
                        'trusted' => false,
                        'connected' => false,
                        'rssi' => null,
                        'battery' => null,
                        'icon' => 'phone',
                        'profiles' => [],
                    ];
                }
            }
        }

        // 各デバイスの詳細情報を取得
        foreach ($devices as $mac => &$info) {
            $info = $this->getDeviceInfo($mac, $info);
        }
        unset($info);

        return array_values($devices);
    }

    public function getDeviceInfo(string $mac, array $default = []): array
    {
        $mac = strtoupper($mac);
        $res = CommandRunner::run("bluetoothctl info " . escapeshellarg($mac));

        $data = array_merge([
            'mac' => $mac,
            'name' => 'Unknown Device',
            'alias' => 'Unknown Device',
            'paired' => false,
            'trusted' => false,
            'connected' => false,
            'rssi' => null,
            'battery' => null,
            'icon' => 'phone',
            'profiles' => [],
        ], $default);

        if ($res['success']) {
            $lines = explode("\n", $res['stdout']);
            foreach ($lines as $line) {
                $line = trim($line);
                if (preg_match('/^Name:\s*(.*)$/', $line, $m)) {
                    $data['name'] = $m[1];
                } elseif (preg_match('/^Alias:\s*(.*)$/', $line, $m)) {
                    $data['alias'] = $m[1];
                } elseif (preg_match('/^Paired:\s*(yes|no)/i', $line, $m)) {
                    $data['paired'] = (strtolower($m[1]) === 'yes');
                } elseif (preg_match('/^Trusted:\s*(yes|no)/i', $line, $m)) {
                    $data['trusted'] = (strtolower($m[1]) === 'yes');
                } elseif (preg_match('/^Connected:\s*(yes|no)/i', $line, $m)) {
                    $data['connected'] = (strtolower($m[1]) === 'yes');
                } elseif (preg_match('/^Battery Percentage:\s*0x[0-9a-f]+\s*\((\d+)\)/i', $line, $m) ||
                           preg_match('/^Battery Percentage:\s*(\d+)%/i', $line, $m)) {
                    $data['battery'] = (int)$m[1];
                } elseif (preg_match('/^RSSI:\s*(-?\d+)/i', $line, $m)) {
                    $data['rssi'] = (int)$m[1];
                } elseif (preg_match('/^Icon:\s*(.*)$/i', $line, $m)) {
                    $icon = strtolower($m[1]);
                    if (str_contains($icon, 'phone')) $data['icon'] = 'phone';
                    elseif (str_contains($icon, 'audio') || str_contains($icon, 'headset')) $data['icon'] = 'headphones';
                    elseif (str_contains($icon, 'computer')) $data['icon'] = 'laptop';
                } elseif (preg_match('/UUID:\s*(.+?)\s*\(([0-9a-f-]+)\)/i', $line, $m)) {
                    $desc = $m[1];
                    $uuid = strtolower($m[2]);
                    if (str_contains($uuid, '110a') || str_contains($desc, 'Audio Source')) {
                        $data['profiles'][] = 'A2DP Source';
                    } elseif (str_contains($uuid, '110b') || str_contains($desc, 'Audio Sink')) {
                        $data['profiles'][] = 'A2DP Sink';
                    } elseif (str_contains($uuid, '110e') || str_contains($desc, 'A/V Remote Control')) {
                        $data['profiles'][] = 'AVRCP';
                    } elseif (str_contains($uuid, '111f') || str_contains($desc, 'Handsfree Audio Gateway')) {
                        $data['profiles'][] = 'HFP-AG';
                    } elseif (str_contains($uuid, '111e') || str_contains($desc, 'Handsfree')) {
                        $data['profiles'][] = 'HFP-HF';
                    }
                }
            }
            $data['profiles'] = array_values(array_unique($data['profiles']));
        }

        return $data;
    }

    public function startScan(): bool
    {
        // 5秒間バックグラウンドスキャンを実施
        CommandRunner::run("bluetoothctl --timeout 5 scan on > /dev/null 2>&1 &");
        return true;
    }

    public function stopScan(): bool
    {
        $res = CommandRunner::run("bluetoothctl scan off");
        return $res['success'];
    }

    public function pair(string $mac): array
    {
        $mac = escapeshellarg($mac);
        // 自動信頼とペアリング
        $cmd = "bluetoothctl pair {$mac} && bluetoothctl trust {$mac}";
        $res = CommandRunner::run($cmd, 15);
        return [
            'success' => $res['success'],
            'output' => $res['stdout'] . "\n" . $res['stderr'],
        ];
    }

    public function connect(string $mac): array
    {
        $mac = escapeshellarg($mac);
        $res = CommandRunner::run("bluetoothctl connect {$mac}", 15);
        return [
            'success' => $res['success'],
            'output' => $res['stdout'] . "\n" . $res['stderr'],
        ];
    }

    public function disconnect(string $mac): array
    {
        $mac = escapeshellarg($mac);
        $res = CommandRunner::run("bluetoothctl disconnect {$mac}", 10);
        return [
            'success' => $res['success'],
            'output' => $res['stdout'] . "\n" . $res['stderr'],
        ];
    }

    public function remove(string $mac): array
    {
        $mac = escapeshellarg($mac);
        $res = CommandRunner::run("bluetoothctl remove {$mac}", 10);
        return [
            'success' => $res['success'],
            'output' => $res['stdout'] . "\n" . $res['stderr'],
        ];
    }

    public function setTrust(string $mac, bool $trust): bool
    {
        $action = $trust ? 'trust' : 'untrust';
        $res = CommandRunner::run("bluetoothctl {$action} " . escapeshellarg($mac));
        return $res['success'];
    }

    public function setDiscoverable(bool $discoverable): bool
    {
        $val = $discoverable ? 'on' : 'off';
        $res = CommandRunner::run("bluetoothctl discoverable {$val}");
        return $res['success'];
    }

    public function setPower(bool $power): bool
    {
        $val = $power ? 'on' : 'off';
        $res = CommandRunner::run("bluetoothctl power {$val}");
        return $res['success'];
    }
}
