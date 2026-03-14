<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

class NotesPopup extends Component
{
    public string $popupId;
    public bool $hasNotes;

    public function __construct(
        public $notes,
        public string $linkableType,
        public int|string $linkableId,
        public string $entityName = '',
    ) {
        $this->popupId  = "notes-popup-{$linkableType}-{$linkableId}";
        $this->hasNotes = $notes->isNotEmpty();
    }

    public function render(): View
    {
        return view('components.notes-popup');
    }
}
