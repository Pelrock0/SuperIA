<form action="{{ url(config('backpack.base.route_prefix') . '/waitlist-entry/export-csv') }}"
      method="POST" style="display:inline;">
    @csrf
    <button type="submit" class="btn btn-primary">
        <i class="la la-download"></i> Exportar CSV
    </button>
</form>
