<?php

namespace App\Http\Controllers\Admin;

use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

class AiUsageCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;

    #[\Override]
    public function setup()
    {
        CRUD::setModel(\App\Models\AiUsageLog::class);
        CRUD::setRoute(config('backpack.base.route_prefix').'/ai-usage');
        CRUD::setEntityNameStrings('ai usage', 'ai usage');
        CRUD::denyAccess(['create', 'update', 'delete']);
    }

    protected function setupListOperation()
    {
        CRUD::column('id');
        CRUD::addColumn([
            'name' => 'user',
            'label' => 'Usuario',
            'type' => 'relationship',
            'attribute' => 'email',
        ]);
        CRUD::addColumn([
            'name' => 'operation',
            'label' => 'Operación',
            'type' => 'text',
            'value' => function ($entry) {
                return $entry->operation?->value ?? $entry->operation;
            },
        ]);
        CRUD::addColumn([
            'name' => 'status',
            'label' => 'Estado',
            'type' => 'text',
            'value' => function ($entry) {
                return $entry->status?->value ?? $entry->status;
            },
        ]);
        CRUD::column('date')->label('Fecha');
        CRUD::addColumn([
            'name' => 'estimated_cost_usd',
            'label' => 'Coste (USD)',
            'type' => 'number',
            'decimals' => 4,
        ]);

        CRUD::addFilter([
            'name' => 'operation',
            'type' => 'dropdown',
            'label' => 'Operación',
        ], [
            'suggestion' => 'Suggestion',
            'generation' => 'Generation',
            'summary' => 'Summary',
            'complement' => 'Complement',
            'replenishment' => 'Replenishment',
        ], function ($value) {
            CRUD::addClause('where', 'operation', $value);
        });

        CRUD::addFilter([
            'name' => 'date_range',
            'type' => 'date_range',
            'label' => 'Fecha',
        ], false, function ($value) {
            $dates = json_decode($value);
            CRUD::addClause('where', 'date', '>=', $dates->from);
            CRUD::addClause('where', 'date', '<=', $dates->to);
        });

        CRUD::orderBy('id', 'desc');
    }
}
