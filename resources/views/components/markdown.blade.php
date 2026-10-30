@props(['text' => null])

{{-- Safe Markdown for stems, passages and explanations. Wide tables scroll inside the block. --}}
<div {{ $attributes->class('prose max-w-none text-ink dark:prose-invert prose-table:block prose-table:overflow-x-auto') }}>
    {!! app(\App\Domain\Content\MarkdownRenderer::class)->render($text) !!}
</div>
