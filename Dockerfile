FROM php:8.2-apache

# 非対話インストール設定
ENV DEBIAN_FRONTEND=noninteractive

# 必要なパッケージをインストール (BlueZ, PulseAudio/PipeWire utils, playerctl, dbus, alsa)
RUN apt-get update && apt-get install -y --no-install-recommends \
    bluez \
    bluez-tools \
    pulseaudio-utils \
    playerctl \
    dbus \
    libdbus-1-3 \
    alsa-utils \
    curl \
    jq \
    && rm -rf /var/lib/apt/lists/*

# Apache mod_rewrite を有効化 & ポートを12080に変更
RUN a2enmod rewrite && sed -i 's/Listen 80/Listen 12080/' /etc/apache2/ports.conf

# Apache VirtualHost 設定を上書き
COPY apache-vhost.conf /etc/apache2/sites-available/000-default.conf

# 作業ディレクトリ設定
WORKDIR /var/www/html

# ソースコードをコピー
COPY . /var/www/html

# entrypoint スクリプトに実行権限を付与
RUN chmod +x /var/www/html/entrypoint.sh

# 一時ストレージディレクトリ作成
RUN mkdir -p /tmp/mediablue && chmod 777 /tmp/mediablue

# ポート12080を公開
EXPOSE 12080

# コンテナ起動コマンド
ENTRYPOINT ["/var/www/html/entrypoint.sh"]
