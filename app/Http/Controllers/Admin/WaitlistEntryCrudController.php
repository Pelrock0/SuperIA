<?php

namespace App\Http\Controllers\Admin;

use App\Models\WaitlistEntry;
use App\Services\WaitlistService;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WaitlistEntryCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;

    public function __construct(
        private readonly WaitlistService $waitlistService
    ) {
        parent::__construct();
    }

    #[\Override]
    public function setup(): void
    {
        CRUD::setModel(WaitlistEntry::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/waitlist-entry');
        CRUD::setEntityNameStrings('entrada de lista de espera', 'lista de espera');
    }

    protected function setupListOperation(): void
    {
        CRUD::column('name')->label('Nombre');
        CRUD::column('email')->label('Email');
        CRUD::column('shopping_companion')->label('Compra con')->type('text');
        CRUD::column('status')->label('Estado')->type('enum');
        CRUD::column('created_at')->label('Fecha registro');

        CRUD::addFilter([
            'name' => 'status',
            'type' => 'dropdown',
            'label' => 'Estado',
        ], [
            'pending' => 'Pendiente',
            'invited' => 'Invitado',
            'registered' => 'Registrado',
        ], function ($value) {
            CRUD::addClause('where', 'status', $value);
        });

        CRUD::addButtonFromView('line', 'invite', 'invite', 'beginning');
        CRUD::addButtonFromView('top', 'export_csv', 'export_csv', 'beginning');
    }

    public function invite(int $id): RedirectResponse
    {
        $entry = WaitlistEntry::findOrFail($id);

        try {
            $this->waitlistService->invite($entry);
            \Alert::success('Invitación enviada a ' . $entry->email)->flash();
        } catch (\InvalidArgumentException $e) {
            \Alert::error($e->getMessage())->flash();
        }

        return redirect()->back();
    }

    public function exportCsv(): StreamedResponse
    {
        $entries = WaitlistEntry::orderBy('position')->get();

        return Response::streamDownload(function () use ($entries) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Nombre', 'Email', 'Compra con', 'Estado', 'Posición', 'Fecha registro']);

            foreach ($entries as $entry) {
                fputcsv($handle, [
                    $entry->name,
                    $entry->email,
                    $entry->shopping_companion,
                    $entry->status,
                    $entry->position,
                    $entry->created_at->format('Y-m-d H:i:s'),
                ]);
            }

            fclose($handle);
        }, 'waitlist-' . now()->format('Y-m-d') . '.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }
}
