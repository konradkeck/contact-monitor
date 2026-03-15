import './bootstrap';

import Alpine from 'alpinejs';
window.Alpine = Alpine;
Alpine.start();

import { easepick, RangePlugin, PresetPlugin } from '@easepick/bundle';
window._EP = { easepick, RangePlugin, PresetPlugin };
