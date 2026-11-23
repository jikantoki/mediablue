#!/usr/bin/env bash
# Mediablue ネイティブ版 (Tauri / WebKitGTK) の .deb をビルドするスクリプト
# 使い方: bash scripts/build-deb.sh
# 出力先: dist/mediablue.deb (バージョンに関係なく毎回同じファイル名で上書き)
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

# --- Rust の準備 (なければ rustup で自動インストール) ---
# Tauri 2 のビルドには Rust 1.77 以降が必要
MIN_RUST_MINOR=77

# インストール済みだが PATH に入っていない場合 (新しい端末を開いていない等)
if ! command -v cargo >/dev/null 2>&1 && [ -f "$HOME/.cargo/env" ]; then
  . "$HOME/.cargo/env"
fi

rust_minor() {
  rustc --version 2>/dev/null | sed -n 's/^rustc 1\.\([0-9]*\).*/\1/p'
}

install_rustup() {
  echo "  rustup で Rust をインストールします (ユーザーのホームに入ります。sudo 不要)"
  curl --proto '=https' --tlsv1.2 -sSf https://sh.rustup.rs \
    | sh -s -- -y --profile minimal --default-toolchain stable
  . "$HOME/.cargo/env"
}

if ! command -v cargo >/dev/null 2>&1; then
  echo "  Rust が見つかりません"
  install_rustup
else
  MINOR=$(rust_minor)
  if [ -n "$MINOR" ] && [ "$MINOR" -lt "$MIN_RUST_MINOR" ]; then
    echo "  Rust が古すぎます ($(rustc --version))。1.${MIN_RUST_MINOR} 以降に更新します"
    if command -v rustup >/dev/null 2>&1; then
      rustup update stable
      rustup default stable
    else
      # apt 版の古い Rust しかない場合は rustup 版を入れて優先させる
      install_rustup
    fi
  fi
fi
echo "  $(rustc --version)"

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

# Tauri の出力 (Mediablue_<version>_<arch>.deb) のうち最新のものを固定名でコピー
BUILT=$(ls -1t src-tauri/target/release/bundle/deb/*.deb | head -n 1)
mkdir -p dist
cp -f "$BUILT" dist/mediablue.deb

echo
echo "完成: dist/mediablue.deb  (元ファイル: $BUILT)"
echo "インストール: sudo apt install ./dist/mediablue.deb"
