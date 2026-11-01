@props(['text' => null])

{{-- Safe Markdown for stems, passages and explanations. Wide tables scroll inside their own region. --}}
<div {{ $attributes->class('prose max-w-none text-ink dark:prose-invert') }}>
    {!! app(\App\Domain\Content\MarkdownRenderer::class)->render($text) !!}
</div>
