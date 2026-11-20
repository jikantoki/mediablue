/**
 * Media Player (A2DP Sink / AVRCP) Logic
 * Manufacturer: エノキ電気 ENOKI ELECTRONICS
 */
const Player = {
  isPlaying: false,
  lastPlayer: null,
  statusLockUntil: 0,
  lockTimeout: null,

  init() {
    const btnPlayPause = document.getElementById('btn-play-pause')
    const btnNext = document.getElementById('btn-next')
    const btnPrev = document.getElementById('btn-prev')
    const btnShuffle = document.getElementById('btn-shuffle')
    const btnRepeat = document.getElementById('btn-repeat')

    if (btnPlayPause) {
      btnPlayPause.addEventListener('click', () => this.togglePlayPause())
    }
    if (btnNext) {
      btnNext.addEventListener('click', () => this.control('next'))
    }
    if (btnPrev) {
      btnPrev.addEventListener('click', () => this.control('prev'))
    }
    if (btnShuffle) {
      btnShuffle.addEventListener('click', () => this.control('shuffle'))
    }
    if (btnRepeat) {
      btnRepeat.addEventListener('click', () => this.control('repeat'))
    }
  },

  /**
   * 再生/一時停止ボタン押下時の即時UI切り替え（楽観的UI更新）とコマンド送信
   * Bluetooth端末のステータス反映ラグを待たずに即時反映し、3秒間端末状態での上書きを抑止
   */
  togglePlayPause() {
    const prevState = this.isPlaying
    const nextState = !prevState

    // 1. Bluetooth先端末の再生状態を待たずに表示だけ即時切り替え (0ms)
    this.isPlaying = nextState
    this.updatePlayStateUI(nextState)

    // 2. 端末ステータスとの同期を3秒間ロック（古いステータスによるUI巻き戻りを防止）
    this.statusLockUntil = Date.now() + 3000

    // 3. 3秒後に端末側の真の状態に合わせて同期
    if (this.lockTimeout) {
      clearTimeout(this.lockTimeout)
    }
    this.lockTimeout = setTimeout(() => {
      this.refresh()
    }, 3000)

    // 4. 明示的なアクション ('play' または 'pause') をバックエンドへ送信
    const action = nextState ? 'play' : 'pause'
    this.sendPlayPauseCommand(action, prevState)
  },

  /**
   * 再生中/一時停止中のUI表示（アイコンおよびレコード回転）の切り替え
   */
  updatePlayStateUI(isPlaying) {
    const iconPlay = document.getElementById('icon-play')
    const iconPause = document.getElementById('icon-pause')
    const albumCover = document.getElementById('album-cover')

    if (isPlaying) {
      if (iconPlay) iconPlay.classList.add('hidden')
      if (iconPause) iconPause.classList.remove('hidden')
      if (albumCover) {
        albumCover.classList.add('playing')
        albumCover.classList.remove('paused')
      }
    } else {
      if (iconPlay) iconPlay.classList.remove('hidden')
      if (iconPause) iconPause.classList.add('hidden')
      if (albumCover) {
        albumCover.classList.remove('playing')
        albumCover.classList.add('paused')
      }
    }
  },

  /**
   * 再生/一時停止コマンド送信（失敗時はロールバック）
   */
  async sendPlayPauseCommand(action, rollbackState) {
    try {
      const res = await fetch('/api/media/control', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action }),
      })
      const data = await res.json()
      if (!data.success) {
        // コマンド失敗時はロックを解除し、元の状態に戻す
        this.statusLockUntil = 0
        this.isPlaying = rollbackState
        this.updatePlayStateUI(rollbackState)
        const errMsg = data.error || (typeof I18n !== 'undefined' ? I18n.t('toast_cmd_failed') : 'Control failed')
        console.warn('AVRCP Error:', data)
        if (typeof App !== 'undefined' && App.toast) {
          App.toast('AVRCP: ' + errMsg)
        }
      }
    } catch (e) {
      // 通信エラー時もロック解除して元の状態に戻す
      this.statusLockUntil = 0
      this.isPlaying = rollbackState
      this.updatePlayStateUI(rollbackState)
      console.error('AVRCP request error:', e)
      if (typeof App !== 'undefined' && App.toast) {
        App.toast(typeof I18n !== 'undefined' ? I18n.t('toast_cmd_failed') : 'Failed to send control command')
      }
    }
  },

  async refresh() {
    try {
      const res = await fetch('/api/media/player')
      const data = await res.json()
      if (data.success && data.player) {
        this.lastPlayer = data.player
        this.updateUI(data.player)
      }
    } catch (e) {
      console.error('Player status fetch failed', e)
    }
  },

  updateIdleTexts() {
    if (!this.lastPlayer || !this.lastPlayer.title || this.lastPlayer.title === 'No Media Playing' || this.lastPlayer.title === '再生中のメディアはありません') {
      const titleEl = document.getElementById('track-title')
      const artistEl = document.getElementById('track-artist')
      if (titleEl) {
        titleEl.textContent = (typeof I18n !== 'undefined') ? I18n.t('no_media_playing') : 'No Media Playing'
      }
      if (artistEl) {
        artistEl.textContent = (typeof I18n !== 'undefined') ? I18n.t('connect_bt_prompt') : 'Connect Bluetooth Device'
      }
    }
  },

  updateUI(p) {
    const defaultTitle = (typeof I18n !== 'undefined') ? I18n.t('no_media_playing') : 'No Media Playing'
    const defaultArtist = (typeof I18n !== 'undefined') ? I18n.t('connect_bt_prompt') : 'Connect Bluetooth Device'

    const titleEl = document.getElementById('track-title')
    const artistEl = document.getElementById('track-artist')
    const albumEl = document.getElementById('track-album')
    const codecEl = document.getElementById('player-codec')

    if (titleEl) {
      titleEl.textContent = (p.title && p.title !== 'No Media Playing') ? p.title : defaultTitle
    }
    if (artistEl) {
      artistEl.textContent = (p.artist && p.artist !== 'Connect Bluetooth Device') ? p.artist : defaultArtist
    }
    if (albumEl) {
      albumEl.textContent = p.album || ''
    }
    if (codecEl) {
      codecEl.textContent = p.codec || 'A2DP'
    }

    // アプリ名バッジの表示 (Spotify, Apple Music, YouTube 等)
    const appBadge = document.getElementById('player-app-badge')
    if (appBadge) {
      if (p.app_name) {
        appBadge.textContent = p.app_name
        appBadge.style.display = 'inline-block'
      } else {
        appBadge.style.display = 'none'
      }
    }

    // Play/Pause icon & animation (3秒ロック中は端末のstatusによる上書きを保留)
    const isLocked = Date.now() < this.statusLockUntil
    if (!isLocked) {
      this.isPlaying = p.status === 'playing'
      this.updatePlayStateUI(this.isPlaying)
    }

    const albumCover = document.getElementById('album-cover')
    if (p.cover && albumCover) {
      albumCover.style.background = p.cover
    }

    // Timeline & Progress
    // 一時停止状態（!this.isPlaying）の時は、再生位置が進まないよう表示を維持
    const cur = p.position || 0
    const dur = p.duration || 1
    const pct = Math.min(100, Math.max(0, (cur / dur) * 100))

    const fillEl = document.getElementById('progress-fill')
    const curEl = document.getElementById('time-current')
    const durEl = document.getElementById('time-duration')

    if (fillEl && (this.isPlaying || !isLocked)) fillEl.style.width = pct + '%'
    if (curEl && (this.isPlaying || !isLocked)) curEl.textContent = this.formatTime(cur)
    if (durEl) durEl.textContent = this.formatTime(dur)
  },

  async control(action) {
    if (action === 'play-pause' || action === 'toggle') {
      this.togglePlayPause()
      return
    }

    try {
      const res = await fetch('/api/media/control', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action }),
      })
      const data = await res.json()
      if (data.success) {
        // 曲送りや曲戻し時はステータスロックを解除して最新情報を取得
        this.statusLockUntil = 0
        this.refresh()
      } else {
        const errMsg = data.error || (typeof I18n !== 'undefined' ? I18n.t('toast_cmd_failed') : 'Control failed')
        console.warn('AVRCP Error:', data)
        if (typeof App !== 'undefined' && App.toast) {
          App.toast('AVRCP: ' + errMsg)
        }
      }
    } catch (e) {
      console.error('AVRCP request error:', e)
      if (typeof App !== 'undefined' && App.toast) {
        App.toast(typeof I18n !== 'undefined' ? I18n.t('toast_cmd_failed') : 'Failed to send control command')
      }
    }
  },

  formatTime(sec) {
    const m = Math.floor(sec / 60)
    const s = Math.floor(sec % 60)
    return `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`
  },
}
