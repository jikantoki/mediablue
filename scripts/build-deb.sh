#!/usr/bin/env bash
# Mediablue ネイティブ版 (Tauri / WebKitGTK) の .deb をビルドするスクリプト
# 使い方: ./scripts/build-deb.sh
# 出力先: src-tauri/target/release/bundle/deb/Mediablue_<version>_<arch>.deb
set -euo pipefail
cd "$(dirname "$0")/.."

echo "[1/3] ビルドに必要なパッケージを確認します"
NEED=(libwebkit2gtk-4.1-dev build-essential curl file libssl-dev librsvg2-dev libayatana-appindicator3-dev)
MISSING=()
for p in "${NEED[@]}"; do
  dpkg -s "$p" >/dev/null 2>&1 || MISSING+=("$p")
done
if [ ${#MISSING[@]} -gt 0 ]; then
  echo "  不足: ${MISSING[*]}"
  sudo apt-get update
  sudo apt-get install -y "${MISSING[@]}"
fi

if ! command -v cargo >/dev/null 2>&1; then
  echo "  Rust が見つかりません。次のコマンドでインストールしてから再実行してください:"
  echo "    curl --proto '=https' --tlsv1.2 -sSf https://sh.rustup.rs | sh"
  exit 1
fi

echo "[2/3] Tauri CLI を準備します"
if cargo tauri --version >/dev/null 2>&1; then
  TAURI=(cargo tauri)
elif command -v npx >/dev/null 2>&1; then
  TAURI=(npx --yes @tauri-apps/cli@^2)
else
  echo "  Tauri CLI をインストールします (初回のみ数分かかります)"
  cargo install tauri-cli --version "^2" --locked
  TAURI=(cargo tauri)
fi

echo "[3/3] .deb をビルドします"
"${TAURI[@]}" build --bundles deb

echo
echo "完成: $(ls -1 src-tauri/target/release/bundle/deb/*.deb)"
echo "インストール: sudo apt install ./$(ls -1 src-tauri/target/release/bundle/deb/*.deb)"
