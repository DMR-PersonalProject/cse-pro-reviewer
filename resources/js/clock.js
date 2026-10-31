// Runner clock (plan §5). The server sends remainingMs (or elapsedMs when untimed) with every
// response; the deadline is kept in performance.now() time, so a wrong device clock doesn't matter.
document.addEventListener('alpine:init', () => {
    window.Alpine.store('clock', {
        wire: null,
        deadline: null,
        startedAt: null,
        display: '',
        warning: false,
        announcement: '',
        messages: {},
        announced: { ten: false, one: false },
        callingTimeUp: false,
        interval: null,

        start(wire, remainingMs, elapsedMs, messages) {
            this.wire = wire
            this.messages = messages
            this.sync({ remainingMs, elapsedMs })

            // Thresholds already passed before this page load are not announced again.
            this.announced = { ten: remainingMs !== null && remainingMs <= 600000, one: remainingMs !== null && remainingMs <= 60000 }

            clearInterval(this.interval)
            this.interval = setInterval(() => this.tick(), 1000)
            window.addEventListener('clock-sync', (event) => this.sync(event.detail))
        },

        sync({ remainingMs, elapsedMs }) {
            if (remainingMs === null || remainingMs === undefined) {
                this.deadline = null
                this.startedAt = performance.now() - elapsedMs
            } else {
                this.deadline = performance.now() + remainingMs
            }

            this.tick()
        },

        format(ms) {
            let total = Math.floor(ms / 1000)
            let pad = (n) => String(n).padStart(2, '0')
            let hours = Math.floor(total / 3600)
            let rest = `${pad(Math.floor((total % 3600) / 60))}:${pad(total % 60)}`

            return hours > 0 ? `${hours}:${rest}` : rest
        },

        tick() {
            if (this.deadline === null) {
                this.display = this.format(performance.now() - this.startedAt)

                return
            }

            let remaining = Math.max(0, this.deadline - performance.now())
            this.display = this.format(remaining)
            this.warning = remaining <= 600000

            if (remaining <= 600000 && !this.announced.ten) {
                this.announced.ten = true
                this.announcement = this.messages.ten
            }

            if (remaining <= 60000 && !this.announced.one) {
                this.announced.one = true
                this.announcement = this.messages.one
            }

            if (remaining === 0) {
                this.timeUp()
            }
        },

        // The server finalizes only if its own deadline has passed; otherwise it sends the real time back.
        async timeUp() {
            if (this.callingTimeUp) {
                return
            }

            this.callingTimeUp = true

            try {
                let remainingMs = await this.wire.timeUp()

                if (remainingMs > 0) {
                    this.sync({ remainingMs })
                }
            } finally {
                this.callingTimeUp = false
            }
        },
    })
})
