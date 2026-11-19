#!/usr/bin/env bash
set -e

echo "[mediablue] Initializing mediablue container..."

# ユーザー権限の設定 (デフォルトはrootまたはwww-dataに適切なグループを追加)
if [ "${RUN_AS_ROOT:-true}" = "true" ]; then
    echo "[mediablue] Running Apache as root for direct hardware/D-Bus access"
    sed -i "s/export APACHE_RUN_USER=.*/export APACHE_RUN_USER=root/" /etc/apache2/envvars
    sed -i "s/export APACHE_RUN_GROUP=.*/export APACHE_RUN_GROUP=root/" /etc/apache2/envvars
else
    groupadd -g 114 bluetooth 2>/dev/null || true
    usermod -aG audio,bluetooth,pulse-access www-data 2>/dev/null || true
fi

# D-Bus システムソケットの確認
if [ -S "/var/run/dbus/system_bus_socket" ]; then
    echo "[mediablue] Host D-Bus system socket detected at /var/run/dbus/system_bus_socket"
    # BlueZ との疎通確認テスト
    if dbus-send --system --print-reply --dest=org.bluez / org.freedesktop.DBus.Peer.Ping >/dev/null 2>&1; then
        echo "[mediablue] ✅ Successfully connected to BlueZ via D-Bus system bus!"
    else
        echo "[mediablue] ⚠️ D-Bus socket exists but BlueZ service did not respond (Check if bluetoothd is running on host)"
    fi
else
    echo "[mediablue] ⚠️ Host D-Bus socket not found. Running in simulation or fallback mode."
fi

# D-Bus ユーザーセッションバスの確認 (playerctl / MPRIS用)
if [ -S "${DBUS_SESSION_BUS_ADDRESS#unix:path=}" ]; then
    echo "[mediablue] Host D-Bus session bus detected at ${DBUS_SESSION_BUS_ADDRESS#unix:path=}"
fi

# PulseAudio / PipeWire ソケットの確認
if [ -n "$PULSE_SERVER" ]; then
    echo "[mediablue] PULSE_SERVER is configured: $PULSE_SERVER"
fi

# Apacheの実行
echo "[mediablue] Starting Apache web server on port 12080..."
exec apache2-foreground
