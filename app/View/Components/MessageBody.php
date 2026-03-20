<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;
use League\CommonMark\GithubFlavoredMarkdownConverter;

class MessageBody extends Component
{
    public string $id;
    public ?string $renderedHtml;
    public ?string $plainText;

    public function __construct(
        ?string $bodyHtml,
        ?string $bodyText,
        public bool $usesMarkdown = false,
        public string $class = '',
    ) {
        $this->id = 'mb-' . uniqid();

        if ($bodyHtml !== null && $bodyHtml !== '') {
            $this->renderedHtml = $bodyHtml;
            $this->plainText    = null;
        } elseif ($bodyText !== null && $bodyText !== '') {
            $decoded = html_entity_decode($bodyText, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if ($usesMarkdown) {
                static $converter = null;
                if ($converter === null) {
                    $converter = new GithubFlavoredMarkdownConverter([
                        'html_input'         => 'escape',
                        'allow_unsafe_links' => false,
                    ]);
                }
                $this->renderedHtml = $converter->convert($decoded)->getContent();
                $this->plainText    = null;
            } else {
                $this->renderedHtml = null;
                $this->plainText    = $decoded;
            }
        } else {
            $this->renderedHtml = null;
            $this->plainText    = null;
        }
    }

    public function render(): View
    {
        return view('components.message-body');
    }
}
