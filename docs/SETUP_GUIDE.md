# Linux A2DP Sink & HFP プロファイル有効化・Mediablue 完全導入ガイド

Ubuntu / Debian / Raspberry Pi 等の Linux 端末を高品位 Bluetooth オーディオ受信機（A2DP Sink）および車載ハンズフリー通話機（HFP-HF）としてセットアップし、Mediablue 車載 Web アプリケーションを導入・運用するための包括的な解説手順書です。

---

## 📑 目次

1. [全体像とプロファイルの仕組み](#1-全体像とプロファイルの仕組み)
2. [動作要件・前提ハードウェア](#2-動作要件前提ハードウェア)
3. [Step 1: 必要なパッケージのインストール](#3-step-1-必要なパッケージのインストール)
4. [Step 2: BlueZ の設定（A2DP Sink / HFP 有効化 & 車載クラス設定）](#4-step-2-bluez-の設定a2dp-sink--hfp-有効化--車載クラス設定)
5. [Step 3: PipeWire & WirePlumber の Bluetooth 設定（推奨環境）](#5-step-3-pipewire--wireplumber-の-bluetooth-設定推奨環境)
6. [Step 4: PulseAudio 環境の場合の設定（レガシー環境）](#6-step-4-pulseaudio-環境の場合の設定レガシー環境)
7. [Step 5: D-Bus および Audio ソケットの権限確認](#7-step-5-d-bus-および-audio-ソケットの権限確認)
8. [Step 6: Mediablue の導入と Docker コンテナ起動](#8-step-6-mediablue-の導入と-docker-コンテナ起動)
9. [Step 7: スマートフォンとのペアリング & 動作確認](#9-step-7-スマートフォンとのペアリング--動作確認)
10. [Bluetooth オフ検知 & ワンクリック復帰機能](#10-bluetooth-オフ検知--ワンクリック復帰機能)
11. [よくある質問 & トラブルシューティング (FAQ)](#11-よくある質問--トラブルシューティング-faq)

---

## 1. 全体像とプロファイルの仕組み

Linux 端末でスマートフォンからの音楽や通話を受信・操作する場合、通常のイヤホン接続（Source → Sink）とは**プロファイルの向きが逆**になります。

```
+-------------------------------------------------------+
|              スマートフォン (iPhone / Android)          |
|    - A2DP Source (音声送信)                            |
|    - AVRCP Controller (メディア制御送信)               |
|    - HFP-AG (Audio Gateway: 電話音声送信)             |
+---------------------------+---------------------------+
                            | Bluetooth (L2CAP / RFCOMM)
+---------------------------v---------------------------+
|                   Linux ホスト端末                     |
|                                                       |
|   [BlueZ 5.x]              [PipeWire / WirePlumber]   |
|    - bluetoothd             - A2DP Sink (オーディオ受信)|
|    - Class = 0x200420       - HFP-HF (ハンズフリー通話) |
|    - D-Bus System Bus       - 高品位コーデック (AAC/LDAC)|
+---------------------------+---------------------------+
                            | Mount (/var/run/dbus, Pulse Socket)
+---------------------------v---------------------------+
|              Docker コンテナ (Mediablue)              |
|                                                       |
|    - PHP 8.2 Backend (BluezService, AudioService)     |
|    - 車載タッチ UI (PWA, A2DP Player, Bluetooth Manager)|
|    - ポート 12080 (http://<ホストIP>:12080/)          |
+-------------------------------------------------------+
```

- **A2DP Sink (Audio Sink)**: スマホからの音楽ストリーミングを受信し、Linux のスピーカーや車載アンプから再生。
- **AVRCP (Target / Controller)**: 楽曲の曲名・アーティスト名・アルバム名・再生時間を取得し、再生/一時停止/スキップを遠隔操作。
- **HFP-HF (Hands-Free)**: スマホの着信を受け、車載マイクとスピーカーでハンズフリー通話を実施。

---

## 2. 動作要件・前提ハードウェア

- **ホスト OS**:
  - Ubuntu 22.04 LTS / 24.04 LTS（推奨）
  - Debian 12 (Bookworm)
  - Raspberry Pi OS (64-bit Bookworm / Bullseye)
- **Bluetooth ハードウェア**:
  - Bluetooth 4.0 / 4.2 / 5.0 / 5.3 対応 USB ドングルまたは内蔵チップ
  - `lsusb` または `rfkill list` で認識されていること
- **オーディオサーバー**:
  - PipeWire + WirePlumber（Ubuntu 22.04 以降標準）
  - または PulseAudio (pulseaudio-module-bluetooth)
- **コンテナ環境**:
  - Docker Engine 20.10+ & Docker Compose v2+

---

## 3. Step 1: 必要なパッケージのインストール

ホスト端末（Linux）の端末で以下を実行します：

```bash
sudo apt update && sudo apt install -y \
  bluez \
  bluez-tools \
  pipewire-audio-client-libraries \
  wireplumber \
  libspa-0.2-bluetooth
```

ユーザーを `audio` および `bluetooth` グループに追加します：

```bash
sudo usermod -aG audio,bluetooth $USER
```

> **注意**: グループ設定の反映のため、実行後は一度ログアウトして再ログインするか、再起動してください。

---

## 4. Step 2: BlueZ の設定（A2DP Sink / HFP 有効化 & 車載クラス設定）

スマートフォンの Bluetooth 検索画面に「車載オーディオ / カーナビ」として認識させ、A2DP と HFP の両方を自動バインドさせるために `/etc/bluetooth/main.conf` を編集します。

```bash
sudo nano /etc/bluetooth/main.conf
```

以下の内容を設定します（既存の項目を編集または追記）：

```ini
[General]
# デバイスの表示名（スマホ側で表示される名前）
Name = Mediablue Car Audio

# 車載ハンズフリー・オーディオ機器としてのクラス指定 (0x200420)
# Major Service: Audio (0x200000)
# Major Device: Audio/Video (0x0400)
# Minor Device: Car Audio (0x0020)
Class = 0x200420

# タイムアウトを 0 (無制限) にして常時接続・検出可能にする
DiscoverableTimeout = 0
PairableTimeout = 0

# 複数プロファイル（A2DP + AVRCP + HFP）の同時接続を許可
MultiProfile = multiple

# 高速接続
FastConnectable = true
JustWorksRepairing = always

[Policy]
# Bluetoothアダプタ起動時に自動電源ON
AutoEnable = true
ResumeDelay = 2
```

設定を反映するため、Bluetooth サービスを再起動します：

```bash
sudo systemctl restart bluetooth
```

---

## 5. Step 3: PipeWire & WirePlumber の Bluetooth 設定（推奨環境）

PipeWire のセッションマネージャーである **WirePlumber** で、A2DP Sink および HFP-HF エンドポイントを有効化します。

### WirePlumber 0.4系（Ubuntu 22.04 LTS 等）の場合

設定ディレクトリを作成し、Lua 設定ファイルを配置します：

```bash
sudo mkdir -p /etc/wireplumber/bluetooth.lua.d
sudo nano /etc/wireplumber/bluetooth.lua.d/50-bluez-config.lua
```

内容：

```lua
bluez_monitor.properties = {
  ["bluez5.enable-sbc-xq"] = true,
  ["bluez5.enable-msbc"] = true,
  ["bluez5.enable-hw-volume"] = false,
  ["bluez5.roles"] = "[ a2dp_sink a2dp_source bap_sink bap_source hsp_hs hsp_ag hfp_hf hfp_ag ]",
  ["bluez5.codecs"] = "[ ldac aptx_hd aptx aac sbc_xq sbc ]",
}
```

### WirePlumber 0.5系（Ubuntu 24.04 LTS 等）の場合

新しい SPA-JSON 設定ファイルを配置します：

```bash
sudo mkdir -p /etc/wireplumber/wireplumber.conf.d
sudo nano /etc/wireplumber/wireplumber.conf.d/10-bluetooth.conf
```

内容：

```spa-json
monitor.bluez.properties = {
  bluez5.enable-sbc-xq = true
  bluez5.enable-msbc = true
  bluez5.enable-hw-volume = false
  bluez5.roles = [ a2dp_sink a2dp_source hfp_hf hfp_ag ]
  bluez5.codecs = [ ldac aptx_hd aptx aac sbc_xq sbc ]
}
```

### ユーザーオーディオサービスの再起動

```bash
systemctl --user restart pipewire pipewire-pulse wireplumber
```

---

## 6. Step 4: PulseAudio 環境の場合の設定（レガシー環境）

PipeWire ではなく PulseAudio を使用している古いシステムの場合は、以下を実施します：

```bash
sudo apt install -y pulseaudio-module-bluetooth
```

`/etc/pulse/default.pa` の末尾に以下を追記：

```ini
load-module module-bluetooth-policy auto_switch=2
load-module module-bluetooth-discover
```

PulseAudio を再起動：

```bash
pulseaudio -k && pulseaudio --start
```

---

## 7. Step 5: D-Bus および Audio ソケットの権限確認

Mediablue コンテナからホストの Bluetooth (D-Bus) および PipeWire/PulseAudio にアクセスするためのソケットを確認します。

```bash
# 1. ユーザー UID の確認 (通常は 1000)
id -u

# 2. D-Bus システムソケットの確認
ls -la /var/run/dbus/system_bus_socket

# 3. PulseAudio / PipeWire ソケットの確認
ls -la /run/user/$(id -u)/pulse/native
```

※ もし UID が `1000` 以外（例: `1001`）の場合は、後述の `docker-compose.yml` でマウントパスの UID を変更してください。

---

## 8. Step 6: Mediablue の導入と Docker コンテナ起動

リポジトリをクローンしてコンテナを起動します：

```bash
git clone https://github.com/tokis/mediablue.git
cd mediablue

# コンテナのビルドおよび起動
docker compose up -d --build
```

起動ログを確認：

```bash
docker compose logs -f
```

### ブラウザでアクセス

車載ディスプレイやタブレット、スマートフォンからアクセスします：

👉 **`http://<ホストのIPアドレス>:12080/`**

スタンドアロンの導入ガイドページにも直接アクセスできます：
👉 **`http://<ホストのIPアドレス>:12080/guide.php`**

---

## 9. Step 7: スマートフォンとのペアリング & 動作確認

### 1. ホスト側を検出可能にする

```bash
bluetoothctl discoverable on
bluetoothctl pairable on
```

### 2. スマートフォンからのペアリング

1. スマートフォンの「設定」→「Bluetooth」を開きます。
2. その他のデバイス一覧に **「Mediablue Car Audio」**（車のアイコン）が表示されるのでタップします。
3. 画面に表示されるペアリング要求を承認します。

### 3. 次回以降の自動接続（Trust）設定

```bash
bluetoothctl trust <スマートフォンのMACアドレス>
```

### 4. 音楽再生テスト

スマートフォンの音楽アプリ（Spotify、Apple Music、YouTube等）で曲を再生します。
Mediablue の画面上に曲名・アーティスト名・アルバムアートがリアルタイムに反映され、スピーカーから音楽が流れます。

---

## 10. Bluetooth オフ検知 & ワンクリック復帰機能

Mediablue では、ホスト端末の Bluetooth がオフ（`powered: false`）になっている場合や、アダプタが未検出の場合をリアルタイムに自動監視します。

### Bluetooth オフ時の警告画面
- Bluetooth がオフになると、画面上に **「Bluetoothがオフになっています」** という専用の警告モーダルが自動表示されます。
- **「📶 Bluetoothをオンにする」** ボタンをタップすると、アプリからバックエンド経由で `rfkill unblock` および `bluetoothctl power on` が発行され、自動的にオン状態へ復帰します。
- ホスト端末で直接オンにしたい場合のためのコマンド表示・ワンクリックコピー機能も搭載しています。
- ホスト側で `bluetoothctl power on` が実行された場合も、ポーリング検知により数秒以内に自動で通常画面へ復帰します。

---

## 11. よくある質問 & トラブルシューティング (FAQ)

### Q1. 「Bluetoothがオフになっています」からオンにできない
**原因**: ハードウェアレベルで rfkill ブロックされているか、`bluetoothd` サービスが停止しています。
**対処法**:
```bash
sudo rfkill unblock bluetooth
sudo systemctl restart bluetooth
bluetoothctl power on
```

### Q2. 接続できているがスピーカーから音が出ない
**原因**: PipeWire の出力先（Sink）がミュートされているか、HDMIなど別の出力先に割り当てられています。
**対処法**:
```bash
# ノード状態を確認
wpctl status

# デフォルト出力先の音量設定とミュート解除
wpctl set-mute @DEFAULT_AUDIO_SINK@ 0
wpctl set-volume @DEFAULT_AUDIO_SINK@ 0.8
```

### Q3. 曲情報（曲名やアーティスト）が表示されない・ボタンが効かない
**原因**: スマホ側で音楽再生アプリが一度も起動していない場合、BlueZ の `MediaPlayer1` オブジェクトが生成されません。
**対処法**: スマホ側で Spotify や Apple Music などのアプリを開き、1曲再生を開始してください。一度再生が始まれば、BlueZ がプレイヤーを検知して双方向操作が可能になります。

### Q4. 車載ディスプレイに全画面アプリとして起動したい
**対処法**:
Mediablue は PWA に完全対応しています。Chrome または Edge で開き、画面上部の **「📲 アプリをインストール」** ボタンまたはブラウザメニューから「ホーム画面に追加」を行ってください。アドレスバーのないネイティブアプリとして全画面起動します。
