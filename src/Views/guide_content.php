<div class="guide-wrapper">
    <!-- Guide Header Banner -->
    <div class="card guide-hero-card">
        <div class="guide-hero-badge">
            <span class="badge badge-profile">Linux Audio Integration</span>
            <span class="badge badge-codec">PipeWire / BlueZ 5.x</span>
        </div>
        <h1 class="guide-title">Linux A2DP Sink & HFP 完全導入ガイド</h1>
        <p class="guide-subtitle">
            Ubuntu / Debian / Raspberry Pi を高音質 Bluetooth レシーバー & 車載ハンズフリー機化し、Mediablue を導入するまでの全手順
        </p>
    </div>

    <!-- Quick Navigation / TOC -->
    <div class="card guide-toc-card">
        <h3>📑 目次・ステップナビゲーション</h3>
        <div class="guide-toc-grid">
            <a href="#guide-sec-overview" class="toc-item">
                <span class="toc-num">00</span>
                <span class="toc-text">全体像と仕組み (A2DP / HFP / AVRCP)</span>
            </a>
            <a href="#guide-sec-reqs" class="toc-item">
                <span class="toc-num">01</span>
                <span class="toc-text">ハードウェア要件 & パッケージ導入</span>
            </a>
            <a href="#guide-sec-bluez" class="toc-item">
                <span class="toc-num">02</span>
                <span class="toc-text">BlueZ の設定 (Class / main.conf)</span>
            </a>
            <a href="#guide-sec-pipewire" class="toc-item">
                <span class="toc-num">03</span>
                <span class="toc-text">PipeWire & WirePlumber の設定</span>
            </a>
            <a href="#guide-sec-pulseaudio" class="toc-item">
                <span class="toc-num">04</span>
                <span class="toc-text">PulseAudio 環境の場合の設定</span>
            </a>
            <a href="#guide-sec-dbus" class="toc-item">
                <span class="toc-num">05</span>
                <span class="toc-text">ソケット権限 & ユーザーグループ</span>
            </a>
            <a href="#guide-sec-docker" class="toc-item">
                <span class="toc-num">06</span>
                <span class="toc-text">Mediablue の導入 & Docker起動</span>
            </a>
            <a href="#guide-sec-pairing" class="toc-item">
                <span class="toc-num">07</span>
                <span class="toc-text">ペアリング & 動作テスト手順</span>
            </a>
            <a href="#guide-sec-faq" class="toc-item">
                <span class="toc-num">08</span>
                <span class="toc-text">トラブルシューティング & FAQ</span>
            </a>
        </div>
    </div>

    <!-- Section 0: Overview -->
    <section id="guide-sec-overview" class="card guide-section">
        <div class="sec-header">
            <span class="sec-badge">Step 0</span>
            <h2>全体像とプロファイルの仕組み</h2>
        </div>
        <p class="sec-lead">
            Linux端末をBluetoothオーディオ受信機として機能させる場合、通常のイヤホン等（Source → Sink）とは逆向きのプロファイル設定が必要です。
        </p>

        <div class="features-grid">
            <div class="feature-box">
                <h4>🎵 A2DP Sink (Advanced Audio Distribution Profile)</h4>
                <p>スマートフォン（A2DP Source / 送信元）からワイヤレスでステレオ音声ストリームを受信し、Linuxのスピーカーや車載アンプに出力します。AAC, LDAC, aptX HD などの高音質コーデックに対応します。</p>
            </div>
            <div class="feature-box">
                <h4>🎛️ AVRCP (Audio/Video Remote Control Profile)</h4>
                <p>再生・一時停止・スキップの遠隔操作および、スマートフォンで再生中の楽曲メタデータ（曲名、アーティスト、アルバム、再生経過時間）を双方向で同期取得します。</p>
            </div>
            <div class="feature-box">
                <h4>📞 HFP-HF (Hands-Free Profile - Handsfree)</h4>
                <p>スマートフォン（HFP-AG / Audio Gateway）の電話着信を車載画面で受け、車載マイクとスピーカーで通話を行います。電波強度やバッテリー残量も取得可能です。</p>
            </div>
        </div>

        <div class="diagram-box">
            <div class="diag-node">スマートフォン (iPhone / Android)<br><small>A2DP Source / AVRCP Controller / HFP-AG</small></div>
            <div class="diag-arrow">⇄ Bluetooth ⇄</div>
            <div class="diag-node highlight">Linuxホスト (BlueZ + PipeWire)<br><small>A2DP Sink / AVRCP Target / HFP-HF</small></div>
            <div class="diag-arrow">⇄ D-Bus & Pulse Socket ⇄</div>
            <div class="diag-node">Mediablue (Web Client)<br><small>Docker UI / 車載タッチスクリーン</small></div>
        </div>
    </section>

    <!-- Section 1: Reqs & Packages -->
    <section id="guide-sec-reqs" class="card guide-section">
        <div class="sec-header">
            <span class="sec-badge">Step 1</span>
            <h2>ハードウェア要件とパッケージのインストール</h2>
        </div>
        
        <div class="guide-note">
            <strong>推奨環境:</strong> Ubuntu 22.04 LTS / 24.04 LTS、または Debian 12 (Bookworm) / Raspberry Pi OS 64-bit。<br>
            Bluetooth 4.0〜5.3 対応の内蔵チップまたはUSB Bluetoothドングルをご用意ください。
        </div>

        <h3>1. 必要なパッケージのインストール</h3>
        <p>BlueZ、PipeWire、WirePlumber、およびBluetoothオーディオプラグインをインストールします：</p>

        <div class="code-block-wrapper">
            <div class="code-block-header">
                <span>Bash (ホスト端末で実行)</span>
                <button class="btn-copy-code" data-code="sudo apt update && sudo apt install -y bluez bluez-tools pipewire-audio-client-libraries wireplumber libspa-0.2-bluetooth">📋 コピー</button>
            </div>
            <pre><code>sudo apt update && sudo apt install -y \
  bluez \
  bluez-tools \
  pipewire-audio-client-libraries \
  wireplumber \
  libspa-0.2-bluetooth</code></pre>
        </div>

        <h3>2. ユーザー権限（グループ）の追加</h3>
        <p>現在ログインしているユーザーがオーディオおよびBluetoothデバイスを直接操作できるようにグループに追加します：</p>

        <div class="code-block-wrapper">
            <div class="code-block-header">
                <span>Bash</span>
                <button class="btn-copy-code" data-code="sudo usermod -aG audio,bluetooth $USER">📋 コピー</button>
            </div>
            <pre><code>sudo usermod -aG audio,bluetooth $USER</code></pre>
        </div>
        <p class="subtext">※グループ反映のため、コマンド実行後は一度ログアウトして再ログインするか、再起動を推奨します。</p>
    </section>

    <!-- Section 2: BlueZ Config -->
    <section id="guide-sec-bluez" class="card guide-section">
        <div class="sec-header">
            <span class="sec-badge">Step 2</span>
            <h2>BlueZ の設定 (A2DP Sink / HFP 有効化 & 車載クラス設定)</h2>
        </div>
        <p>
            スマートフォン側に「車載オーディオ / ハンズフリーシステム」として正しく認識させ、自動接続を安定させるために <code>/etc/bluetooth/main.conf</code> を設定します。
        </p>

        <h3>1. /etc/bluetooth/main.conf の編集</h3>
        <div class="code-block-wrapper">
            <div class="code-block-header">
                <span>設定ファイル: /etc/bluetooth/main.conf</span>
                <button class="btn-copy-code" data-code="sudo nano /etc/bluetooth/main.conf">📋 編集コマンド</button>
            </div>
            <pre><code>[General]
# デバイス表示名
Name = Mediablue Car Audio

# デバイスクラス: 0x200420 (Audio, Car Audio, Handsfree)
# Major Service: Audio (0x200000) + Major: Audio/Video (0x0400) + Minor: Car Audio (0x0020)
Class = 0x200420

# タイムアウトを無期限にし常時ペアリング・検出待機可能にする
DiscoverableTimeout = 0
PairableTimeout = 0

# 複数プロファイル同時接続
MultiProfile = multiple

# 高速接続
FastConnectable = true
JustWorksRepairing = always

[Policy]
# アダプタ起動時に自動で電源ON
AutoEnable = true
ResumeDelay = 2</code></pre>
        </div>

        <div class="guide-tip">
            <strong>💡 クラス値 (0x200420) の重要性:</strong><br>
            この値を設定することで、iPhoneやAndroid端末のBluetooth設定画面に「車のアイコン（カーオーディオ）」として認識され、A2DP（音楽）とHFP（通話）の両プロファイルが即座にバインドされます。
        </div>

        <h3>2. Bluetooth サービスの再起動</h3>
        <div class="code-block-wrapper">
            <div class="code-block-header">
                <span>Bash</span>
                <button class="btn-copy-code" data-code="sudo systemctl restart bluetooth">📋 コピー</button>
            </div>
            <pre><code>sudo systemctl restart bluetooth</code></pre>
        </div>
    </section>

    <!-- Section 3: PipeWire / WirePlumber Config -->
    <section id="guide-sec-pipewire" class="card guide-section">
        <div class="sec-header">
            <span class="sec-badge">Step 3</span>
            <h2>PipeWire & WirePlumber の Bluetooth プロファイル設定 (推奨)</h2>
        </div>
        <p>
            近年のUbuntu (22.04 / 24.04) では、標準オーディオサーバーとして <strong>PipeWire + WirePlumber</strong> が採用されています。<br>
            A2DP Sink および HFP-HF (Hands-Free Unit) のエンドポイントを確実に有効化します。
        </p>

        <h3>WirePlumber 0.4系 (Ubuntu 22.04 LTS 等) の場合</h3>
        <p>設定ディレクトリを作成し、Lua設定ファイルを配置します：</p>
        <div class="code-block-wrapper">
            <div class="code-block-header">
                <span>設定ファイル: /etc/wireplumber/bluetooth.lua.d/50-bluez-config.lua</span>
                <button class="btn-copy-code" data-code="sudo mkdir -p /etc/wireplumber/bluetooth.lua.d">📋 ディレクトリ作成</button>
            </div>
            <pre><code>bluez_monitor.properties = {
  ["bluez5.enable-sbc-xq"] = true,
  ["bluez5.enable-msbc"] = true,
  ["bluez5.enable-hw-volume"] = false,
  ["bluez5.roles"] = "[ a2dp_sink a2dp_source bap_sink bap_source hsp_hs hsp_ag hfp_hf hfp_ag ]",
  ["bluez5.codecs"] = "[ ldac aptx_hd aptx aac sbc_xq sbc ]",
}</code></pre>
        </div>

        <h3>WirePlumber 0.5系 (Ubuntu 24.04 LTS 等) の場合</h3>
        <p>新しいSPA-JSON形式の設定ファイルを配置します：</p>
        <div class="code-block-wrapper">
            <div class="code-block-header">
                <span>設定ファイル: /etc/wireplumber/wireplumber.conf.d/10-bluetooth.conf</span>
                <button class="btn-copy-code" data-code="sudo mkdir -p /etc/wireplumber/wireplumber.conf.d">📋 ディレクトリ作成</button>
            </div>
            <pre><code>monitor.bluez.properties = {
  bluez5.enable-sbc-xq = true
  bluez5.enable-msbc = true
  bluez5.enable-hw-volume = false
  bluez5.roles = [ a2dp_sink a2dp_source hfp_hf hfp_ag ]
  bluez5.codecs = [ ldac aptx_hd aptx aac sbc_xq sbc ]
}</code></pre>
        </div>

        <h3>PipeWire サービスの再起動</h3>
        <div class="code-block-wrapper">
            <div class="code-block-header">
                <span>Bash (ユーザー権限で実行)</span>
                <button class="btn-copy-code" data-code="systemctl --user restart pipewire pipewire-pulse wireplumber">📋 コピー</button>
            </div>
            <pre><code>systemctl --user restart pipewire pipewire-pulse wireplumber</code></pre>
        </div>
    </section>

    <!-- Section 4: PulseAudio (Legacy) -->
    <section id="guide-sec-pulseaudio" class="card guide-section">
        <div class="sec-header">
            <span class="sec-badge">Step 4</span>
            <h2>PulseAudio 環境の場合の設定 (レガシー環境)</h2>
        </div>
        <p>Ubuntu 20.04等のPulseAudioを使用している環境では、PulseAudio Bluetoothモジュールを設定します：</p>

        <div class="code-block-wrapper">
            <div class="code-block-header">
                <span>Bash</span>
                <button class="btn-copy-code" data-code="sudo apt install -y pulseaudio-module-bluetooth">📋 コピー</button>
            </div>
            <pre><code>sudo apt install -y pulseaudio-module-bluetooth</code></pre>
        </div>
        <p><code>/etc/pulse/default.pa</code> の末尾に以下を追記します：</p>
        <div class="code-block-wrapper">
            <div class="code-block-header">
                <span>/etc/pulse/default.pa</span>
            </div>
            <pre><code>load-module module-bluetooth-policy auto_switch=2
load-module module-bluetooth-discover</code></pre>
        </div>
        <div class="code-block-wrapper">
            <div class="code-block-header">
                <span>PulseAudio 再起動</span>
                <button class="btn-copy-code" data-code="pulseaudio -k && pulseaudio --start">📋 コピー</button>
            </div>
            <pre><code>pulseaudio -k && pulseaudio --start</code></pre>
        </div>
    </section>

    <!-- Section 5: D-Bus & Socket -->
    <section id="guide-sec-dbus" class="card guide-section">
        <div class="sec-header">
            <span class="sec-badge">Step 5</span>
            <h2>D-Bus ソケット & オーディオソケットの確認</h2>
        </div>
        <p>Mediablue は Docker コンテナ内からホストの BlueZ (D-Bus) および PipeWire/PulseAudio ソケットにアクセスして制御を行います。</p>

        <h3>1. ホストの実行UIDを確認</h3>
        <div class="code-block-wrapper">
            <div class="code-block-header">
                <span>Bash</span>
                <button class="btn-copy-code" data-code="id -u">📋 コピー</button>
            </div>
            <pre><code>id -u
# 出力例: 1000</code></pre>
        </div>
        <p class="subtext">※ 通常は <code>1000</code> です。もし <code>1001</code> 等の場合は <code>docker-compose.yml</code> 内の <code>/run/user/1000</code> を該当のUIDに変更してください。</p>

        <h3>2. ソケットの存在確認</h3>
        <div class="code-block-wrapper">
            <div class="code-block-header">
                <span>Bash</span>
                <button class="btn-copy-code" data-code="ls -la /var/run/dbus/system_bus_socket && ls -la /run/user/$(id -u)/pulse/native">📋 コピー</button>
            </div>
            <pre><code># D-Bus システムソケットの確認
ls -la /var/run/dbus/system_bus_socket

# PipeWire / PulseAudio ソケットの確認
ls -la /run/user/$(id -u)/pulse/native</code></pre>
        </div>
    </section>

    <!-- Section 6: Docker Launch -->
    <section id="guide-sec-docker" class="card guide-section">
        <div class="sec-header">
            <span class="sec-badge">Step 6</span>
            <h2>Mediablue の導入と Docker 起動</h2>
        </div>
        <p>Docker および Docker Compose を使用して、Mediablue アプリケーションをビルド・起動します。</p>

        <h3>1. リポジトリの配置と起動</h3>
        <div class="code-block-wrapper">
            <div class="code-block-header">
                <span>Bash</span>
                <button class="btn-copy-code" data-code="git clone https://github.com/tokis/mediablue.git && cd mediablue && docker compose up -d --build">📋 コピー</button>
            </div>
            <pre><code># リポジトリのクローン
git clone https://github.com/tokis/mediablue.git
cd mediablue

# コンテナのビルド & バックグラウンド起動
docker compose up -d --build</code></pre>
        </div>

        <h3>2. コンテナログの確認</h3>
        <div class="code-block-wrapper">
            <div class="code-block-header">
                <span>Bash</span>
                <button class="btn-copy-code" data-code="docker compose logs -f">📋 コピー</button>
            </div>
            <pre><code>docker compose logs -f</code></pre>
        </div>
        <p>ログに <code>Ready to accept connections on port 12080</code> と表示されれば正常に起動しています。</p>

        <h3>3. ブラウザでアクセス</h3>
        <p>車載タッチディスプレイまたは同一LAN内のブラウザから以下にアクセスします：</p>
        <div class="guide-url-box">
            👉 <a href="http://localhost:12080/" target="_blank" class="guide-link">http://&lt;ホストのIPアドレス&gt;:12080/</a>
        </div>
    </section>

    <!-- Section 7: Pairing & Testing -->
    <section id="guide-sec-pairing" class="card guide-section">
        <div class="sec-header">
            <span class="sec-badge">Step 7</span>
            <h2>ペアリング & 動作テスト手順</h2>
        </div>

        <h3>1. ホスト側を検出可能 (Discoverable) にする</h3>
        <div class="code-block-wrapper">
            <div class="code-block-header">
                <span>Bash</span>
                <button class="btn-copy-code" data-code="bluetoothctl discoverable on && bluetoothctl pairable on">📋 コピー</button>
            </div>
            <pre><code>bluetoothctl discoverable on
bluetoothctl pairable on</code></pre>
        </div>

        <h3>2. スマートフォンからのペアリング</h3>
        <ol class="guide-step-list">
            <li>スマートフォン（iPhone / Android）の「設定」→「Bluetooth」を開きます。</li>
            <li>「その他のデバイス」一覧に <strong>「Mediablue Car Audio」</strong>（車のアイコン）が表示されるのでタップします。</li>
            <li>ペアリング要求コード（PIN）が画面に出たら「ペアリング」を承認します。</li>
        </ol>

        <h3>3. ホスト側での自動信頼 (Trust)</h3>
        <p>一度ペアリングしたスマホが次回以降自動接続できるように信頼設定を行います：</p>
        <div class="code-block-wrapper">
            <div class="code-block-header">
                <span>Bash</span>
                <button class="btn-copy-code" data-code="bluetoothctl trust &lt;スマホのMACアドレス&gt;">📋 コピー</button>
            </div>
            <pre><code>bluetoothctl trust &lt;スマホのMACアドレス&gt;</code></pre>
        </div>

        <h3>4. 音楽再生テスト</h3>
        <p>
            スマートフォンの音楽アプリ（Spotify、Apple Music、YouTube等）で曲を再生します。<br>
            Mediablue の画面上に曲名・アーティスト名・アルバムアートが進捗バーとともにリアルタイム表示され、スピーカーから音楽が流れます！
        </p>
    </section>

    <!-- Section 8: FAQ & Troubleshooting -->
    <section id="guide-sec-faq" class="card guide-section">
        <div class="sec-header">
            <span class="sec-badge">Step 8</span>
            <h2>よくある質問 & トラブルシューティング</h2>
        </div>

        <div class="faq-item">
            <h4 class="faq-q">Q1. 「Bluetoothがオフになっています」という画面が表示される</h4>
            <div class="faq-a">
                <p>Linuxホスト側でBluetoothアダプタが無効化（rfkillブロック）されているか、電源がOFFになっています。</p>
                <div class="code-block-wrapper">
                    <div class="code-block-header">
                        <span>Bash</span>
                        <button class="btn-copy-code" data-code="sudo rfkill unblock bluetooth && bluetoothctl power on">📋 コピー</button>
                    </div>
                    <pre><code># ブロック解除と電源オン
sudo rfkill unblock bluetooth
bluetoothctl power on

# 状態確認
rfkill list bluetooth</code></pre>
                </div>
                <p>また、画面上の <strong>「📶 Bluetoothをオンにする」</strong> ボタンを押すことで、アプリ側から自動復帰を試みることもできます。</p>
            </div>
        </div>

        <div class="faq-item">
            <h4 class="faq-q">Q2. Bluetooth接続は成功するがスピーカーから音が出ない</h4>
            <div class="faq-a">
                <p>PipeWireの出力ノードがミュートされているか、デフォルト出力先が意図しないデバイス（HDMI等）になっている可能性があります。</p>
                <div class="code-block-wrapper">
                    <div class="code-block-header">
                        <span>Bash</span>
                        <button class="btn-copy-code" data-code="wpctl status">📋 コピー</button>
                    </div>
                    <pre><code># PipeWireノード状態確認
wpctl status

# 音量設定 (IDを指定してミュート解除)
wpctl set-mute @DEFAULT_AUDIO_SINK@ 0
wpctl set-volume @DEFAULT_AUDIO_SINK@ 0.8</code></pre>
                </div>
            </div>
        </div>

        <div class="faq-item">
            <h4 class="faq-q">Q3. 曲の再生・停止ボタン（AVRCP）が効かない</h4>
            <div class="faq-a">
                <p>
                    スマートフォン側で音楽アプリが一度も起動していない場合、BlueZの <code>MediaPlayer1</code> インターフェースが生成されません。<br>
                    <strong>スマートフォン側で音楽アプリを開き、1曲再生を開始した状態</strong> にしてください。その後は画面上のAVRCPボタンから自由に操作可能になります。
                </p>
            </div>
        </div>

        <div class="faq-item">
            <h4 class="faq-q">Q4. 車載ディスプレイやタブレットに全画面で常駐させたい</h4>
            <div class="faq-a">
                <p>
                    Mediablue は PWA (Progressive Web App) に完全対応しています。<br>
                    ChromeまたはEdgeのメニューから <strong>「アプリをインストール」</strong> または <strong>「ホーム画面に追加」</strong> を選択してください。アドレスバーのないネイティブ車載アプリとしてフルスクリーン起動します。
                </p>
            </div>
        </div>
    </section>
</div>
