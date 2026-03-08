{{--
    Integration icon — identical badge style as x-channel-badge, just at a custom icon size.
    Usage: @include('synchronizer._type_icon', ['type' => 'whmcs', 'class' => 'w-8 h-8'])
--}}
{!! \App\Integrations\IntegrationRegistry::get($type ?? '')->iconHtml($class ?? 'w-6 h-6') !!}
