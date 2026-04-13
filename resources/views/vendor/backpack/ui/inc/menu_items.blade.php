{{-- This file is used for menu items by any Backpack v6 theme --}}
<li class="nav-item"><a class="nav-link" href="{{ backpack_url('dashboard') }}"><i class="la la-home nav-icon"></i> {{ trans('backpack::base.dashboard') }}</a></li>
@includeWhen(class_exists(\Backpack\DevTools\DevToolsServiceProvider::class), 'backpack.devtools::buttons.sidebar_item')

<x-backpack::menu-dropdown title="Administración" icon="la la-industry">
    <x-backpack::menu-dropdown-item title="Usuarios" icon="la la-user" :link="backpack_url('user')" />
    <x-backpack::menu-dropdown-item title="Lista de espera" icon="la la-clock" :link="backpack_url('waitlist-entry')" />
    <x-backpack::menu-dropdown-item title="Consumo IA" icon="la la-robot" :link="backpack_url('ai-usage')" />
    <x-backpack::menu-dropdown-item title="Roles" icon="la la-group" :link="backpack_url('role')" />
    <x-backpack::menu-dropdown-item title="Permisos" icon="la la-key" :link="backpack_url('permission')" />
    <x-backpack::menu-dropdown-item title="Ajustes" icon="la la-cog" :link="backpack_url('setting')" />
</x-backpack::menu-dropdown>
