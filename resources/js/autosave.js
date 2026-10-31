// Autosave queue (plan §4.1, FR-T4). Each answer waits in localStorage (attempt:{id}:pending,
// one key per position) until the server has answered; a failed call is retried with backoff.
// The server decides whether an answer is still accepted, so the stash is only a delivery queue.
document.addEventListener('alpine:init', () => {
    let read = (storageKey) => {
        try {
            return JSON.parse(localStorage.getItem(storageKey)) || {}
        } catch (e) {
            return {}
        }
    }

    let write = (storageKey, pending) => {
        try {
            Object.keys(pending).length
                ? localStorage.setItem(storageKey, JSON.stringify(pending))
                : localStorage.removeItem(storageKey)
        } catch (e) {
            // Storage blocked: the queue still works in memory for this page.
        }
    }

    window.Alpine.store('autosave', {
        wire: null,
        storageKey: null,
        pending: {},
        sending: false,
        failed: false,
        online: navigator.onLine,
        delay: 1000,
        retry: null,

        // Called by the runner on load: replays anything stashed by an earlier page.
        start(wire, attemptId) {
            this.wire = wire
            this.storageKey = `attempt:${attemptId}:pending`
            this.pending = read(this.storageKey)

            window.addEventListener('online', () => {
                this.online = true
                this.flush()
            })
            window.addEventListener('offline', () => (this.online = false))

            this.flush()
        },

        get state() {
            if (!Object.keys(this.pending).length) {
                return 'saved'
            }

            return this.failed || !this.online ? 'offline' : 'saving'
        },

        // Optimistic: the radio is already checked; the answer is stashed before it is sent.
        save(position, key) {
            this.pending[position] = key
            write(this.storageKey, this.pending)
            this.flush()
        },

        async flush() {
            if (this.sending || !this.wire) {
                return
            }

            clearTimeout(this.retry)
            this.sending = true

            try {
                for (let position of Object.keys(this.pending)) {
                    let key = this.pending[position]
                    let handled = await this.wire.answer(Number(position), key)

                    // The attempt has ended and the page is leaving: keep the stash for the results page.
                    if (!handled) {
                        return
                    }

                    // A newer choice for the same item may have arrived meanwhile; keep it.
                    if (this.pending[position] === key) {
                        delete this.pending[position]
                        write(this.storageKey, this.pending)
                    }
                }

                this.failed = false
                this.delay = 1000
            } catch (e) {
                this.failed = true
                this.retry = setTimeout(() => this.flush(), this.delay)
                this.delay = Math.min(this.delay * 2, 30000)
            } finally {
                this.sending = false
            }

            if (!this.failed && Object.keys(this.pending).length) {
                this.flush()
            }
        },

        // Called by the results page: whatever is still stashed was never stored. The server counts
        // it (read-only), and the stash is cleared so the count shows once.
        async reportUnsaved(wire, attemptId) {
            let storageKey = `attempt:${attemptId}:pending`
            let pending = read(storageKey)
            let entries = Object.entries(pending).map(([position, key]) => ({ position: Number(position), key }))

            if (!entries.length) {
                return
            }

            await wire.replay(entries)
            write(storageKey, {})
        },
    })
})
