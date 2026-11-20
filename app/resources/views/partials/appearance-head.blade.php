        {{-- Inline script to detect system dark mode preference and apply it immediately --}}
        <script>
            (function() {
                try {
                    document.documentElement.dataset.appearanceUser = '{{ auth()->id() ?? "" }}';
                    const serverAppearance = '{{ $appearance ?? "system" }}';
                    let appearance = serverAppearance;

                    if (!{{ ($appearanceIsAuthenticated ?? false) ? 'true' : 'false' }}) {
                        try {
                            const stored = window.localStorage.getItem('appearance');
                            if (['system', 'light', 'dark'].includes(stored)) appearance = stored;
                        } catch { /* Retain the validated host cookie. */ }
                    }
                    const dark = appearance === 'dark' || (appearance === 'system' && window.matchMedia?.('(prefers-color-scheme: dark)').matches);
                    document.documentElement.classList.toggle('dark', dark);
                    document.documentElement.style.colorScheme = dark ? 'dark' : 'light';
                    document.documentElement.dataset.appearance = appearance;
                } catch {
                    document.documentElement.classList.remove('dark');
                }
            })();
        </script>

        {{-- Inline style to set the HTML background color based on our theme in app.css --}}
        <style>
            html {
                background-color: #FFF8F2;
            }

            html.dark {
                background-color: #111111;
            }
        </style>

