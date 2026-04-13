<?php

namespace App\Http\Controllers\Admin;

use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

class AiPromptCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;

    #[\Override]
    public function setup()
    {
        CRUD::setModel(\App\Models\AiPrompt::class);
        CRUD::setRoute(config('backpack.base.route_prefix').'/ai-prompt');
        CRUD::setEntityNameStrings('prompt IA', 'prompts IA');
        CRUD::denyAccess(['create', 'delete']);
    }

    protected function setupListOperation()
    {
        CRUD::column('name')->label('Nombre');
        CRUD::column('slug')->label('Slug');
        CRUD::column('description')->label('Descripción');
        CRUD::addColumn([
            'name' => 'is_active',
            'label' => 'Activo',
            'type' => 'boolean',
        ]);
        CRUD::column('updated_at')->label('Última edición');
    }

    protected function setupShowOperation()
    {
        $this->setupListOperation();
        CRUD::addColumn([
            'name' => 'content',
            'label' => 'Contenido del prompt',
            'type' => 'textarea',
        ]);
    }

    protected function setupUpdateOperation()
    {
        CRUD::addField([
            'name' => 'name',
            'label' => 'Nombre',
            'type' => 'text',
            'tab' => 'General',
        ]);
        CRUD::addField([
            'name' => 'slug',
            'label' => 'Slug',
            'type' => 'text',
            'attributes' => ['readonly' => 'readonly'],
            'hint' => 'Identificador interno. No modificar.',
            'tab' => 'General',
        ]);
        CRUD::addField([
            'name' => 'description',
            'label' => 'Descripción',
            'type' => 'text',
            'hint' => 'Breve descripción de para qué se usa este prompt.',
            'tab' => 'General',
        ]);
        CRUD::addField([
            'name' => 'is_active',
            'label' => 'Activo',
            'type' => 'checkbox',
            'hint' => 'Si se desactiva, se usará el prompt por defecto del código.',
            'tab' => 'General',
        ]);
        CRUD::addField([
            'name' => 'content',
            'label' => 'Contenido del prompt',
            'type' => 'textarea',
            'attributes' => [
                'rows' => 20,
                'style' => 'font-family: monospace; font-size: 13px;',
            ],
            'tab' => 'Prompt',
        ]);
    }
}
