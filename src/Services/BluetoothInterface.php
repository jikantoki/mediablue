<?php

namespace mediablue\Services;

interface BluetoothInterface
{
    /**
     * アダプタ情報および全体のBluetoothステータスを取得
     */
    public function getStatus(): array;

    /**
     * ペアリング済みデバイスおよびスキャンで検出されたデバイス一覧を取得
     */
    public function getDevices(): array;

    /**
     * デバイススキャンを開始
     */
    public function startScan(): bool;

    /**
     * デバイススキャンを停止
     */
    public function stopScan(): bool;

    /**
     * 指定デバイスとペアリング
     */
    public function pair(string $mac): array;

    /**
     * 指定デバイスと接続（A2DP / HFP）
     */
    public function connect(string $mac): array;

    /**
     * 指定デバイスを切断
     */
    public function disconnect(string $mac): array;

    /**
     * 指定デバイスのペアリングを解除（削除）
     */
    public function remove(string $mac): array;

    /**
     * デバイスの信頼（Trust）設定
     */
    public function setTrust(string $mac, bool $trust): bool;

    /**
     * アダプタの検出可能（Discoverable）モードを設定
     */
    public function setDiscoverable(bool $discoverable): bool;

    /**
     * アダプタの電源（Powered）を設定
     */
    public function setPower(bool $power): bool;
}
