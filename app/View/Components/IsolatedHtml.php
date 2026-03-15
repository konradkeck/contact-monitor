<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

class IsolatedHtml extends Component
{
    public string $id;

    public function __construct(public string $content, public string $class = '')
    {
        $this->id = 'ih-' . uniqid();
    }

    public function render(): View
    {
        return view('components.isolated-html');
    }
}
