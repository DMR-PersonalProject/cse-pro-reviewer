{{-- Privacy note, spec §8. Also used on /privacy. --}}
<section aria-labelledby="privacy-heading" class="space-y-2 text-sm">
    <h2 id="privacy-heading" class="font-semibold">{{ __('Your privacy') }}</h2>
    <ul class="list-disc space-y-1 pl-5">
        <li><strong>{{ __('Stored:') }}</strong> {{ __('username, IGN, full name, an optional PIN (hashed, never readable), your attempts and answers, and timestamps.') }}</li>
        <li><strong>{{ __('Public:') }}</strong> {{ __('your IGN, leaderboard scores, and the time used on eligible attempts.') }} {{ __('You appear on the leaderboard only after you set a PIN.') }}</li>
        <li><strong>{{ __('Private:') }}</strong> {{ __('your full name and attempt details, visible to you and to admins only.') }}</li>
        <li>{{ __('No email, no tracking or analytics, no third-party services. To have your data removed, ask an admin (who can delete your account, including its attempts).') }}</li>
    </ul>
</section>
