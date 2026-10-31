<?php

declare(strict_types=1);

namespace App\Domain\Content;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\Table\TableExtension;
use League\CommonMark\MarkdownConverter;

/**
 * Renders stems, passages and explanations. Raw HTML is stripped and unsafe links
 * (javascript:, vbscript:, file:, data: other than images) are dropped. Tables are supported.
 */
class MarkdownRenderer
{
    private MarkdownConverter $converter;

    public function __construct()
    {
        $environment = new Environment([
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
            'max_nesting_level' => 20,
        ]);
        $environment->addExtension(new CommonMarkCoreExtension);
        $environment->addExtension(new TableExtension);

        $this->converter = new MarkdownConverter($environment);
    }

    public function render(?string $markdown): string
    {
        if ($markdown === null || trim($markdown) === '') {
            return '';
        }

        $html = trim($this->converter->convert($markdown)->getContent());

        // Wide tables scroll inside a labelled, focusable box (FR-T1). Raw HTML is stripped,
        // so every <table> here came from Markdown.
        return str_replace(
            ['<table>', '</table>'],
            ['<div role="region" aria-label="'.e(__('Data table')).'" tabindex="0" class="overflow-x-auto"><table>', '</table></div>'],
            $html,
        );
    }
}
