<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

class PersonAvatar extends Component
{
    public string $avatarSrc;

    public function __construct(
        public object $person,
        public string $size = '8',
        public string $class = '',
    ) {
        $identities  = $person->relationLoaded('identities') ? $person->identities : collect();
        $slackIdent  = $identities->first(fn ($i) => $i->type === 'slack_user' && ! empty($i->meta_json['avatar']));
        $emailIdent  = $identities->first(fn ($i) => $i->type === 'email');
        $discordIdent = $identities->first(fn ($i) => in_array($i->type, ['discord_user', 'discord_id']) && ! empty($i->meta_json['avatar']));

        if ($slackIdent) {
            $this->avatarSrc = $slackIdent->meta_json['avatar'];
        } elseif ($emailIdent) {
            $this->avatarSrc = 'https://www.gravatar.com/avatar/' . md5(strtolower(trim($emailIdent->value))) . '?d=identicon&s=128';
        } elseif ($discordIdent) {
            $this->avatarSrc = 'https://cdn.discordapp.com/avatars/' . $discordIdent->value_normalized . '/' . $discordIdent->meta_json['avatar'] . '.webp?size=64';
        } else {
            $this->avatarSrc = 'https://www.gravatar.com/avatar/' . md5(strtolower(trim($person->full_name ?? ''))) . '?d=identicon&s=128';
        }
    }

    public function render(): View
    {
        return view('components.person-avatar');
    }
}
