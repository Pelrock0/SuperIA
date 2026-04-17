@extends('emails.layout', ['subject' => 'Superlistia — Suscripcion cancelada'])

@section('content')
    <div style="text-align: center;">
        <h1 style="margin: 0 0 24px; font-size: 26px; font-weight: 800; color: #002736; letter-spacing: -0.03em;">
            Te has dado de baja
        </h1>

        <p style="margin: 0 0 16px; font-size: 15px; line-height: 1.6; color: #191c1e;">
            {{ $userName }}, ya no recibiras el resumen semanal por email.
        </p>

        <p style="margin: 0; font-size: 13px; line-height: 1.5; color: #71787d;">
            Puedes volver a activarlo en cualquier momento desde los ajustes de tu cuenta en la app.
        </p>
    </div>
@endsection
