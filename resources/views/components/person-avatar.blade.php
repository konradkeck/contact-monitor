@props(['person', 'size' => '8', 'class' => ''])

@php
    $identities  = $person->relationLoaded('identities') ? $person->identities : collect();
    $slackIdent  = $identities->first(fn($i) => $i->type === 'slack_user' && !empty($i->meta_json['avatar']));
    $emailIdent  = $identities->first(fn($i) => $i->type === 'email');
    $discordIdent = $identities->first(fn($i) => in_array($i->type, ['discord_user', 'discord_id']) && !empty($i->meta_json['avatar']));

    if ($slackIdent) {
        $avatarSrc = $slackIdent->meta_json['avatar'];
    } elseif ($emailIdent) {
        $avatarSrc = 'https://www.gravatar.com/avatar/' . md5(strtolower(trim($emailIdent->value))) . '?d=identicon&s=128';
    } elseif ($discordIdent) {
        $avatarSrc = 'https://cdn.discordapp.com/avatars/' . $discordIdent->value_normalized . '/' . $discordIdent->meta_json['avatar'] . '.webp?size=64';
    } else {
        $avatarSrc = 'https://www.gravatar.com/avatar/' . md5(strtolower(trim($person->full_name ?? ''))) . '?d=identicon&s=128';
    }
@endphp

<img src="{{ $avatarSrc }}"
     alt="{{ $person->full_name }}"
     title="{{ $person->full_name }}"
     class="w-{{ $size }} h-{{ $size }} rounded-full object-cover {{ $class }}">
