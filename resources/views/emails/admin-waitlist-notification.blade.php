@extends('emails.layout', ['subject' => 'Superlistia — Nuevo registro en lista de espera'])

@section('content')
    <h1 style="margin: 0 0 24px; font-size: 26px; font-weight: 800; color: #002736; letter-spacing: -0.03em;">
        Nuevo registro en lista de espera
    </h1>

    <p style="margin: 0 0 24px; font-size: 15px; line-height: 1.6; color: #191c1e;">
        Un nuevo usuario se ha apuntado a la lista de espera de <strong>Superlistia</strong>.
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color: #f2f4f6; border-radius: 12px; margin-bottom: 24px;">
        <tr>
            <td style="padding: 20px 24px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <td style="padding: 4px 0; font-size: 14px; color: #41484c;">
                            <strong style="color: #002736;">Nombre:</strong> {{ $applicantName }}
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 4px 0; font-size: 14px; color: #41484c;">
                            <strong style="color: #002736;">Email:</strong> {{ $applicantEmail }}
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 4px 0; font-size: 14px; color: #41484c;">
                            <strong style="color: #002736;">Posición en lista:</strong> #{{ $position }}
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <p style="margin: 0; font-size: 13px; line-height: 1.5; color: #71787d;">
        Puedes gestionar la lista de espera desde el panel de administración.
    </p>
@endsection
