// Runner shortcuts (FR-T2): 1–5 or A–E pick the nth shown choice, ←/→ move, F flags,
// N focuses the navigator, ? opens the help. Ignored while typing or with a modifier held.
document.addEventListener('alpine:init', () => {
    window.Alpine.store('keyboard', {
        wire: null,

        bind(wire) {
            this.wire = wire
        },

        isTyping(target) {
            if (target.isContentEditable || target.tagName === 'TEXTAREA' || target.tagName === 'SELECT') {
                return true
            }

            return target.tagName === 'INPUT' && !['radio', 'checkbox', 'button', 'submit'].includes(target.type)
        },

        handle(event) {
            if (!this.wire || event.ctrlKey || event.metaKey || event.altKey || this.isTyping(event.target)) {
                return
            }

            if (document.querySelector('dialog[open]')) {
                return
            }

            let key = event.key.length === 1 ? event.key.toUpperCase() : event.key
            let index = '12345'.indexOf(key) >= 0 ? '12345'.indexOf(key) : 'ABCDE'.indexOf(key)

            if (key.length === 1 && index >= 0) {
                let radio = document.querySelectorAll('#choices input[type=radio]')[index]

                if (radio && !radio.matches(':disabled')) {
                    event.preventDefault()
                    radio.focus()

                    if (!radio.checked) {
                        radio.click()
                    }
                }

                return
            }

            let actions = {
                ArrowLeft: () => this.go('prev'),
                ArrowRight: () => this.go('next'),
                F: () => this.wire.toggleFlag(),
                N: () => { window.dispatchEvent(new CustomEvent('navigator-open')); requestAnimationFrame(() => document.getElementById('navigator')?.focus()) },
                '?': () => document.getElementById('keyboard-help')?.showModal(),
            }

            if (actions[key]) {
                event.preventDefault()
                actions[key]()
            }
        },

        // Moves to another item, then puts focus on its stem so it is read out next.
        async go(method, ...args) {
            await this.wire[method](...args)
            document.getElementById('stem')?.focus()
        },
    })
})
