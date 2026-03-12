{{--
    Universal integration badge. Single source of truth → IntegrationRegistry → iconHtml().
    Usage: <x-channel-badge type="discord" />
           <x-channel-badge type="ticket" :label="false" />
--}}
@props(['type', 'label' => true])
{!! \App\Integrations\IntegrationRegistry::get($type ?? '')->iconHtml('w-5 h-5', $label) !!}
