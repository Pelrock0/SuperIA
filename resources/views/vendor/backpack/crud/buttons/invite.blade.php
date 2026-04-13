@if ($entry->status === 'pending')
    <form action="{{ url(config('backpack.base.route_prefix') . '/waitlist-entry/' . $entry->getKey() . '/invite') }}"
          method="POST" style="display:inline;"
          onsubmit="return confirm('¿Enviar invitación a {{ $entry->email }}?')">
        @csrf
        <button type="submit" class="btn btn-sm btn-success">
            <i class="la la-envelope"></i> Invitar
        </button>
    </form>
@endif
