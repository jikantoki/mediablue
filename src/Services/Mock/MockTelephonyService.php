<?php

namespace mediablue\Services\Mock;

use mediablue\Config;

class MockTelephonyService
{
    private function getTelephonyState(): array
    {
        $state = Config::getState();
        if (!isset($state['mock_telephony'])) {
            $state['mock_telephony'] = [
                'state' => 'idle', // idle, incoming, dialing, active
                'caller_name' => '',
                'caller_number' => '',
                'call_start_time' => null,
                'is_muted' => false,
                'battery' => 84,
                'signal' => 4,
                'carrier' => 'docomo 5G',
                'connected_device' => 'Ken\'s iPhone 15 Pro',
            ];
            Config::saveState($state);
        }
        return $state['mock_telephony'];
    }

    private function saveTelephonyState(array $data): void
    {
        $state = Config::getState();
        $state['mock_telephony'] = $data;
        Config::saveState($state);
    }

    public function getStatus(): array
    {
        $data = $this->getTelephonyState();
        $duration = 0;
        if ($data['state'] === 'active' && $data['call_start_time']) {
            $duration = time() - $data['call_start_time'];
        }

        return [
            'mode' => 'mock',
            'hfp_available' => true,
            'state' => $data['state'],
            'caller_name' => $data['caller_name'],
            'caller_number' => $data['caller_number'],
            'duration' => $duration,
            'is_muted' => $data['is_muted'],
            'battery' => $data['battery'],
            'signal' => $data['signal'],
            'carrier' => $data['carrier'],
            'connected_device' => $data['connected_device'],
        ];
    }

    public function triggerIncoming(string $name = 'Yamada Taro (Office)', string $number = '090-1234-5678'): array
    {
        $data = $this->getTelephonyState();
        $data['state'] = 'incoming';
        $data['caller_name'] = $name;
        $data['caller_number'] = $number;
        $data['call_start_time'] = null;
        $this->saveTelephonyState($data);

        return ['success' => true, 'message' => 'Incoming call triggered'];
    }

    public function answer(): array
    {
        $data = $this->getTelephonyState();
        if ($data['state'] === 'incoming') {
            $data['state'] = 'active';
            $data['call_start_time'] = time();
            $this->saveTelephonyState($data);
            return ['success' => true, 'action' => 'answered'];
        }
        return ['success' => false, 'error' => 'No incoming call to answer'];
    }

    public function hangup(): array
    {
        $data = $this->getTelephonyState();
        $data['state'] = 'idle';
        $data['caller_name'] = '';
        $data['caller_number'] = '';
        $data['call_start_time'] = null;
        $data['is_muted'] = false;
        $this->saveTelephonyState($data);

        return ['success' => true, 'action' => 'hungup'];
    }

    public function dial(string $number, string $name = 'Outgoing Call'): array
    {
        $data = $this->getTelephonyState();
        $data['state'] = 'active';
        $data['caller_name'] = $name ?: 'Calling...';
        $data['caller_number'] = $number;
        $data['call_start_time'] = time();
        $this->saveTelephonyState($data);

        return ['success' => true, 'action' => 'dialed', 'number' => $number];
    }

    public function toggleMute(): array
    {
        $data = $this->getTelephonyState();
        $data['is_muted'] = !$data['is_muted'];
        $this->saveTelephonyState($data);
        return ['success' => true, 'is_muted' => $data['is_muted']];
    }
}
