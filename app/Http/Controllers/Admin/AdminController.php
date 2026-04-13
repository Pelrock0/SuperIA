<?php

namespace App\Http\Controllers\Admin;

use App\Services\AdminMetricsService;
use Illuminate\Routing\Controller;

class AdminController extends Controller
{
    protected $data = [];

    public function __construct()
    {
        $this->middleware(backpack_middleware());
    }

    public function dashboard(): \Illuminate\Contracts\View\View
    {
        $this->data['title'] = trans('backpack::base.dashboard');
        $this->data['metrics'] = app(AdminMetricsService::class)->getMetrics();

        return view('admin.dashboard', $this->data);
    }

    /**
     * Redirect to the dashboard.
     *
     * @return \Illuminate\Routing\Redirector|\Illuminate\Http\RedirectResponse
     */
    public function redirect()
    {
        // The '/admin' route is not to be used as a page, because it breaks the menu's active state.
        return redirect(backpack_url('dashboard'));
    }
}
