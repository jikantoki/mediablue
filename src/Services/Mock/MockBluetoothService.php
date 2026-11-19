<?php

namespace mediablue\Services\Mock;

use mediablue\Config;
use mediablue\Services\BluetoothInterface;

class MockBluetoothService implements BluetoothInterface
{
    private function getMockData(): array
    {
        $state = Config::getState();
        if (!isset($state['mock_bt'])) {
            $state['mock_bt'] = [
                'adapter' => [
                    'name' => 'Ubuntu mediablue (Simulated)',
                    'mac' => 'DC:A6:32:8B:12:34',
                    'powered' => true,
                    'discoverable' => true,
                    'pairable' => true,
                    'discovering' => false,
                ],
                'devices' => [
                    [
                        'mac' => 'E4:5F:01:8A:2C:99',
                        'name' => 'Ken\'s iPhone 15 Pro',
                        'alias' => 'Ken\'s iPhone 15 Pro',
                        'paired' => true,
                        'trusted' => true,
                        'connected' => true,
                        'rssi' => -48,
                        'battery' => 84,
                        'icon' => 'phone',
                        'profiles' => ['A2DP Source', 'AVRCP', 'HFP-AG'],
                    ],
                    [
                        'mac' => '94:DB:56:D3:21:00',
                        'name' => 'Google Pixel 8',
                        'alias' => 'Google Pixel 8',
                        'paired' => true,
                        'trusted' => true,
                        'connected' => false,
                        'rssi' => -65,
                        'battery' => 62,
                        'icon' => 'phone',
                        'profiles' => ['A2DP Source', 'AVRCP', 'HFP-AG'],
                    ],
                    [
                        'mac' => '00:1B:66:44:9A:F1',
                        'name' => 'Sony NW-A306 (Walkman)',
                        'alias' => 'Sony NW-A306',
                        'paired' => false,
                        'trusted' => false,
                        'connected' => false,
                        'rssi' => -78,
                        'battery' => null,
                        'icon' => 'headphones',
                        'profiles' => ['A2DP Source', 'AVRCP'],
                    ],
                ],
                'discovered_pool' => [
                    [
                        'mac' => 'A8:7C:01:4F:99:32',
                        'name' => 'Galaxy S24',
                        'alias' => 'Galaxy S24',
                        'paired' => false,
                        'trusted' => false,
                        'connected' => false,
                        'rssi' => -55,
                        'battery' => 91,
                        'icon' => 'phone',
                        'profiles' => ['A2DP Source', 'AVRCP', 'HFP-AG'],
                    ],
                    [
                        'mac' => 'BC:D0:74:11:88:FF',
                        'name' => 'iPad Air (5th gen)',
                        'alias' => 'iPad Air',
                        'paired' => false,
                        'trusted' => false,
                        'connected' => false,
                        'rssi' => -72,
                        'battery' => 45,
                        'icon' => 'tablet',
                        'profiles' => ['A2DP Source', 'AVRCP'],
                    ],
                ]
            ];
            Config::saveState($state);
        }
        return $state['mock_bt'];
    }

    private function saveMockData(array $data): void
    {
        $state = Config::getState();
        $state['mock_bt'] = $data;
        Config::saveState($state);
    }

    public function getStatus(): array
    {
        $data = $this->getMockData();
        $connected = array_filter($data['devices'], fn($d) => $d['connected']);

        return [
            'mode' => 'mock',
            'adapter' => $data['adapter'],
            'connected_count' => count($connected),
            'devices' => $data['devices'],
        ];
    }

    public function getDevices(): array
    {
        $data = $this->getMockData();
        return $data['devices'];
    }

    public function startScan(): bool
    {
        $data = $this->getMockData();
        $data['adapter']['discovering'] = true;

        // プールから未登録デバイスをデバイス一覧に追加
        if (!empty($data['discovered_pool'])) {
            $candidate = array_shift($data['discovered_pool']);
            $existing = array_column($data['devices'], 'mac');
            if (!in_array($candidate['mac'], $existing)) {
                $data['devices'][] = $candidate;
            }
        }

        $this->saveMockData($data);
        return true;
    }

    public function stopScan(): bool
    {
        $data = $this->getMockData();
        $data['adapter']['discovering'] = false;
        $this->saveMockData($data);
        return true;
    }

    public function pair(string $mac): array
    {
        $data = $this->getMockData();
        foreach ($data['devices'] as &$dev) {
            if (strcasecmp($dev['mac'], $mac) === 0) {
                $dev['paired'] = true;
                $dev['trusted'] = true;
                $this->saveMockData($data);
                return ['success' => true, 'output' => "Pairing successful for {$mac}"];
            }
        }
        return ['success' => false, 'output' => 'Device not found'];
    }

    public function connect(string $mac): array
    {
        $data = $this->getMockData();
        $found = false;
        foreach ($data['devices'] as &$dev) {
            if (strcasecmp($dev['mac'], $mac) === 0) {
                $dev['connected'] = true;
                $dev['paired'] = true;
                $found = true;
            } else {
                // A2DP Sinkでは通常1台のスマホのみアクティブ接続
                $dev['connected'] = false;
            }
        }
        if ($found) {
            $this->saveMockData($data);
            return ['success' => true, 'output' => "Connection successful: {$mac}"];
        }
        return ['success' => false, 'output' => 'Device not found'];
    }

    public function disconnect(string $mac): array
    {
        $data = $this->getMockData();
        foreach ($data['devices'] as &$dev) {
            if (strcasecmp($dev['mac'], $mac) === 0) {
                $dev['connected'] = false;
                $this->saveMockData($data);
                return ['success' => true, 'output' => "Disconnected {$mac}"];
            }
        }
        return ['success' => false, 'output' => 'Device not found'];
    }

    public function remove(string $mac): array
    {
        $data = $this->getMockData();
        $data['devices'] = array_values(array_filter($data['devices'], fn($d) => strcasecmp($d['mac'], $mac) !== 0));
        $this->saveMockData($data);
        return ['success' => true, 'output' => "Device {$mac} removed"];
    }

    public function setTrust(string $mac, bool $trust): bool
    {
        $data = $this->getMockData();
        foreach ($data['devices'] as &$dev) {
            if (strcasecmp($dev['mac'], $mac) === 0) {
                $dev['trusted'] = $trust;
                $this->saveMockData($data);
                return true;
            }
        }
        return false;
    }

    public function setDiscoverable(bool $discoverable): bool
    {
        $data = $this->getMockData();
        $data['adapter']['discoverable'] = $discoverable;
        $this->saveMockData($data);
        return true;
    }

    public function setPower(bool $power): bool
    {
        $data = $this->getMockData();
        $data['adapter']['powered'] = $power;
        $this->saveMockData($data);
        return true;
    }
}
