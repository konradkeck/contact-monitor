@props(['stage'])
@php
    $map = [
        'lead'      => 'blue',
        'prospect'  => 'purple',
        'trial'     => 'yellow',
        'active'    => 'green',
        'churned'   => 'red',
    ];
    $color = $map[strtolower($stage ?? '')] ?? 'gray';
@endphp
<x-badge :color="$color">{{ $stage }}</x-badge>
