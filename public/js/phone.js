/**
 * Phone & Telephony (HFP - Hands-Free Profile) Logic
 */
const Phone = {
  currentState: 'idle',
  dialBuffer: '',

  init() {
    this.initKeypad()
    this.initCallActions()
    this.initModalActions()
  },

  initKeypad() {
    const keys = document.querySelectorAll('.key-btn')
    const dialDisplay = document.getElementById('dial-display')
    const btnBackspace = document.getElementById('btn-backspace')

    keys.forEach((k) => {
      k.addEventListener('click', () => {
        const char = k.getAttribute('data-key')
        this.dialBuffer += char
        dialDisplay.value = this.dialBuffer
      })
    })

    if (btnBackspace) {
      btnBackspace.addEventListener('click', () => {
        this.dialBuffer = this.dialBuffer.slice(0, -1)
        dialDisplay.value = this.dialBuffer
      })
    }
  },

  initCallActions() {
    const btnCallStart = document.getElementById('btn-call-start')
    const btnCallHangup = document.getElementById('btn-call-hangup')
    const btnCallMute = document.getElementById('btn-call-mute')
    const btnTriggerCall = document.getElementById('btn-trigger-call')

    if (btnCallStart) {
      btnCallStart.addEventListener('click', () => {
        if (!this.dialBuffer) {
          App.toast('Enter a number to call')
          return
        }
        this.dial(this.dialBuffer)
      })
    }

    if (btnCallHangup) {
      btnCallHangup.addEventListener('click', () => this.hangup())
    }

    if (btnCallMute) {
      btnCallMute.addEventListener('click', async () => {
        App.toast('Microphone muted/unmuted')
      })
    }

    if (btnTriggerCall) {
      btnTriggerCall.addEventListener('click', async () => {
        try {
          await fetch('/api/telephony/test-incoming', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
              name: 'Tanaka Kenji (Home)',
              number: '080-9988-7766',
            }),
          })
          App.toast('Simulated incoming call started')
          this.refresh()
        } catch (e) {
          App.toast('Test call trigger failed')
        }
      })
    }
  },

  initModalActions() {
    const btnAnswer = document.getElementById('btn-modal-answer')
    const btnReject = document.getElementById('btn-modal-reject')

    if (btnAnswer) {
      btnAnswer.addEventListener('click', () => {
        this.answer()
        document.getElementById('incoming-modal').classList.add('hidden')
      })
    }

    if (btnReject) {
      btnReject.addEventListener('click', () => {
        this.hangup()
        document.getElementById('incoming-modal').classList.add('hidden')
      })
    }
  },

  async refresh() {
    try {
      const res = await fetch('/api/telephony/status')
      const data = await res.json()
      if (data.success && data.telephony) {
        this.updateUI(data.telephony)
      }
    } catch (e) {
      console.error('Telephony fetch error', e)
    }
  },

  updateUI(t) {
    this.currentState = t.state

    // Top Status Bar Updates
    const carrierEl = document.getElementById('top-carrier')
    const battText = document.getElementById('top-battery-text')
    const battLevel = document.getElementById('top-battery-level')
    const badgeNav = document.getElementById('phone-nav-badge')

    if (carrierEl) carrierEl.textContent = t.carrier || '5G'
    if (battText && t.battery !== null) {
      battText.textContent = t.battery + '%'
      battLevel.style.width = t.battery + '%'
    }

    const stateBadge = document.getElementById('call-state-badge')
    const callerName = document.getElementById('active-caller-name')
    const callerNum = document.getElementById('active-caller-number')
    const callTimer = document.getElementById('call-timer')
    const incallActions = document.getElementById('incall-actions')
    const incomingModal = document.getElementById('incoming-modal')

    if (t.state === 'incoming') {
      stateBadge.textContent = 'INCOMING CALL'
      stateBadge.className = 'badge badge-ringing'
      badgeNav.classList.remove('hidden')

      // Show Modal if not already shown
      document.getElementById('modal-caller-name').textContent =
        t.caller_name || 'Unknown Caller'
      document.getElementById('modal-caller-number').textContent =
        t.caller_number || ''
      incomingModal.classList.remove('hidden')

      callerName.textContent = t.caller_name || 'Incoming Call'
      callerNum.textContent = t.caller_number || ''
      callTimer.textContent = 'Ringing...'
      incallActions.classList.add('hidden')
    } else if (t.state === 'active') {
      stateBadge.textContent = 'CONNECTED'
      stateBadge.className = 'badge badge-active'
      badgeNav.classList.add('hidden')
      incomingModal.classList.add('hidden')

      callerName.textContent = t.caller_name || 'Active Call'
      callerNum.textContent = t.caller_number || ''
      callTimer.textContent = Player.formatTime(t.duration || 0)
      incallActions.classList.remove('hidden')
    } else {
      stateBadge.textContent = 'IDLE'
      stateBadge.className = 'badge badge-idle'
      badgeNav.classList.add('hidden')
      incomingModal.classList.add('hidden')

      callerName.textContent = 'No Active Call'
      callerNum.textContent = t.connected_device
        ? `Ready (${t.connected_device})`
        : 'Pair phone in Bluetooth tab'
      callTimer.textContent = '00:00'
      incallActions.classList.add('hidden')
    }
  },

  async answer() {
    try {
      await fetch('/api/telephony/answer', { method: 'POST' })
      App.toast('Call connected')
      this.refresh()
    } catch (e) {
      App.toast('Failed to answer call')
    }
  },

  async hangup() {
    try {
      await fetch('/api/telephony/hangup', { method: 'POST' })
      App.toast('Call ended')
      this.refresh()
    } catch (e) {
      App.toast('Failed to terminate call')
    }
  },

  async dial(number) {
    try {
      const res = await fetch('/api/telephony/dial', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ number }),
      })
      const data = await res.json()
      if (data.success) {
        App.toast('Dialing ' + number)
        this.dialBuffer = ''
        document.getElementById('dial-display').value = ''
        this.refresh()
      }
    } catch (e) {
      App.toast('Failed to dial number')
    }
  },
}
