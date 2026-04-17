@extends('emails.layout', ['subject' => 'Superlistia — Lista de espera'])

@section('content')
    <h1 style="margin: 0 0 24px; font-size: 26px; font-weight: 800; color: #002736; letter-spacing: -0.03em;">
        Hola, {{ $userName }}
    </h1>

    <p style="margin: 0 0 16px; font-size: 15px; line-height: 1.6; color: #191c1e;">
        Gracias por apuntarte a la lista de espera de <strong>Superlistia</strong>.
    </p>

    <p style="margin: 0 0 24px; font-size: 15px; line-height: 1.6; color: #191c1e;">
        Tu posicion en la cola es la <strong style="color: #002736; font-size: 20px;">#{{ $position }}</strong>.
        Te avisaremos en cuanto tengamos tu invitacion lista.
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color: #f2f4f6; border-radius: 12px;">
        <tr>
            <td style="padding: 20px 24px;">
                <p style="margin: 0; font-size: 13px; color: #41484c; line-height: 1.5;">
                    <span style="font-weight: 700; color: #002736;">Mientras tanto...</span><br>
                    Preparamos algo especial para ti. Gracias por confiar en nosotros.
                </p>
            </td>
        </tr>
    </table>
@endsection
