#!/usr/bin/env bash
set -e

# D-Bus システムソケットの確認
if [ -S "/var/run/dbus/system_bus_socket" ]; then
    echo "[mediablue] Host D-Bus system socket detected at /var/run/dbus/system_bus_socket"
else
    echo "[mediablue] Host D-Bus socket not found. Running in simulation or fallback mode."
fi

# PulseAudio / PipeWire ソケットの確認
if [ -n "$PULSE_SERVER" ]; then
    echo "[mediablue] PULSE_SERVER is set to $PULSE_SERVER"
fi

# Apacheの実行
echo "[mediablue] Starting Apache web server..."
exec apache2-foreground
