{{--
    Blocking theme script (FR-U1). Runs before any stylesheet so there is no flash.
    localStorage (shared with Filament) is the source of truth; the cookie mirrors it so the
    server can render an explicit dark theme. Exposes window.cseTheme for the toggle.
--}}
<script data-theme-script>
    (function () {
        var KEY = @js(\App\Enums\ThemeMode::STORAGE_KEY);
        var COOKIE = @js(\App\Enums\ThemeMode::COOKIE);
        var MODES = ['light', 'dark', 'system'];
        var root = document.documentElement;
        var media = window.matchMedia('(prefers-color-scheme: dark)');

        function read() {
            try {
                var stored = localStorage.getItem(KEY);
                if (MODES.indexOf(stored) !== -1) return stored;
            } catch (e) {}
            return MODES.indexOf(root.dataset.theme) !== -1 ? root.dataset.theme : 'system';
        }

        function apply(mode) {
            root.classList.toggle('dark', mode === 'dark' || (mode === 'system' && media.matches));
            root.dataset.theme = mode;
        }

        function save(mode) {
            try { localStorage.setItem(KEY, mode); } catch (e) {}
            document.cookie = COOKIE + '=' + mode + '; path=/; max-age=31536000; SameSite=Lax';
        }

        window.cseTheme = {
            mode: read(),
            set: function (mode) {
                if (MODES.indexOf(mode) === -1) return this.mode;
                this.mode = mode;
                save(mode);
                apply(mode);
                window.dispatchEvent(new CustomEvent('cse-theme', { detail: mode }));
                return mode;
            },
        };

        apply(window.cseTheme.mode);
        save(window.cseTheme.mode);

        media.addEventListener('change', function () {
            if (window.cseTheme.mode === 'system') apply('system');
        });
    })();
</script>
