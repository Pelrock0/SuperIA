<?php

namespace App\Http\Controllers\Admin;

use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Backpack\PermissionManager\app\Http\Controllers\UserCrudController as PermissionManagerUserCrudController;

class UserCrudController extends PermissionManagerUserCrudController
{
    public function setupListOperation()
    {
        parent::setupListOperation();

        CRUD::addColumn([
            'name' => 'is_active',
            'label' => 'Activo',
            'type' => 'boolean',
        ]);
        CRUD::addColumn([
            'name' => 'plan',
            'label' => 'Plan',
            'type' => 'text',
            'value' => function ($entry) {
                return $entry->plan ?? 'free';
            },
        ]);
        CRUD::addColumn([
            'name' => 'ai_daily_limit_override',
            'label' => 'Limite IA',
            'type' => 'text',
            'value' => function ($entry) {
                return $entry->ai_daily_limit_override ?? 'default';
            },
        ]);

        CRUD::addFilter([
            'name' => 'is_active',
            'type' => 'dropdown',
            'label' => 'Estado',
        ], [
            1 => 'Activo',
            0 => 'Desactivado',
        ], function ($value) {
            CRUD::addClause('where', 'is_active', $value);
        });
    }

    public function setupUpdateOperation()
    {
        parent::setupUpdateOperation();

        CRUD::addField([
            'name' => 'is_active',
            'label' => 'Cuenta activa',
            'type' => 'checkbox',
            'hint' => 'Si se desactiva, el usuario no puede iniciar sesión.',
            'tab' => 'Superlistia',
        ]);
        CRUD::addField([
            'name' => 'plan',
            'label' => 'Plan',
            'type' => 'select_from_array',
            'options' => ['free' => 'Free (20 ops/día)', 'premium' => 'Premium (100 ops/día)'],
            'allows_null' => false,
            'default' => 'free',
            'hint' => 'Define el límite diario de operaciones IA compartidas.',
            'tab' => 'Superlistia',
        ]);
        CRUD::addField([
            'name' => 'ai_daily_limit_override',
            'label' => 'Límite IA diario (override)',
            'type' => 'number',
            'hint' => 'Vacio = usa el limite del plan. Si se pone un número, sobreescribe el plan para este usuario.',
            'tab' => 'Superlistia',
        ]);
    }
}
