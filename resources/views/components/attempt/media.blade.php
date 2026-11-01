@props(['items' => []])

{{-- Snapshot images. Alt text is required before publishing, so it is always present. --}}
@foreach ($items as $image)
    <img src="{{ \Illuminate\Support\Facades\Storage::disk($image['disk'])->url($image['path']) }}"
         alt="{{ $image['alt_text'] }}" width="{{ $image['width'] }}" height="{{ $image['height'] }}"
         loading="lazy" {{ $attributes->class('my-3 h-auto max-w-full') }}>
@endforeach
