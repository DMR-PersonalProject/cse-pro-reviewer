<header class="border-b border-line bg-surface">
    <div class="mx-auto flex max-w-5xl flex-wrap items-center justify-between gap-3 px-4 py-3">
        <a href="{{ auth()->check() ? route('dashboard') : route('start') }}" class="font-semibold">
            {{ config('app.name') }}
        </a>

        <nav aria-label="{{ __('Main') }}" class="flex flex-wrap items-center gap-4 text-sm">
            @auth
                <a href="{{ route('dashboard') }}" @if (request()->routeIs('dashboard')) aria-current="page" @endif
                   class="hover:underline aria-[current=page]:font-semibold">{{ __('Dashboard') }}</a>
                <a href="{{ route('profile') }}" @if (request()->routeIs('profile')) aria-current="page" @endif
                   class="hover:underline aria-[current=page]:font-semibold">{{ __('Profile') }}</a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="hover:underline">{{ __('Sign out') }}</button>
                </form>
            @else
                <a href="{{ route('login') }}" class="hover:underline">{{ __('Sign in') }}</a>
                <a href="{{ route('start') }}" class="hover:underline">{{ __('Get started') }}</a>
            @endauth

            <x-ui.theme-toggle />
        </nav>
    </div>
</header>
