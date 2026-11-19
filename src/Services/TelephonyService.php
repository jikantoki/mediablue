<?php

namespace mediablue\Services;

use mediablue\Utils\CommandRunner;

class TelephonyService
{
    public function getStatus(): array
    {
        // WirePlumber / PipeWire / BlueZ HFPノード状態をチェック
        $res = CommandRunner::run("pactl list sources | grep -i 'hfp\\|headset\\|handsfree'");
        $hasHfpSource = $res['success'] && !empty($res['stdout']);

        // BlueZのBattery1インターフェースやbluetoothctlからバッテリーと接続情報をチェック
        $battery = null;
        $connectedDevice = null;
        
        $btRes = CommandRunner::run("bluetoothctl info");
        if ($btRes['success']) {
            if (preg_match('/Name:\s*(.*)/', $btRes['stdout'], $m)) {
                $connectedDevice = trim($m[1]);
            }
            if (preg_match('/Battery Percentage:\s*0x[0-9a-f]+\s*\((\d+)\)/i', $btRes['stdout'], $m) ||
                preg_match('/Battery Percentage:\s*(\d+)%/i', $btRes['stdout'], $m)) {
                $battery = (int)$m[1];
            }
        }

        return [
            'mode' => 'real',
            'hfp_available' => $hasHfpSource,
            'state' => 'idle', // idle, incoming, dialing, active
            'caller_name' => '',
            'caller_number' => '',
            'duration' => 0,
            'is_muted' => false,
            'battery' => $battery,
            'signal' => 4, // 0-5
            'carrier' => 'LTE / 5G',
            'connected_device' => $connectedDevice,
        ];
    }

    public function answer(): array
    {
        // 実機でのHFP応答 (ofono / wireplumber dbusコール等)
        return ['success' => true, 'action' => 'answer'];
    }

    public function hangup(): array
    {
        // 実機でのHFP切断
        return ['success' => true, 'action' => 'hangup'];
    }

    public function dial(string $number): array
    {
        // 実機でのHFPダイヤル
        return ['success' => true, 'action' => 'dial', 'number' => $number];
    }
}
