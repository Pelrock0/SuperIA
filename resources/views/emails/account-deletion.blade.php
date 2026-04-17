@extends('emails.layout', ['subject' => 'Superlistia — Cuenta eliminada'])

@section('content')
    <h1 style="margin: 0 0 24px; font-size: 26px; font-weight: 800; color: #002736; letter-spacing: -0.03em;">
        Hola, {{ $userName }}
    </h1>

    <p style="margin: 0 0 16px; font-size: 15px; line-height: 1.6; color: #191c1e;">
        Tu cuenta en <strong>Superlistia</strong> ha sido eliminada correctamente.
    </p>

    <p style="margin: 0 0 24px; font-size: 15px; line-height: 1.6; color: #191c1e;">
        Tus datos personales seran eliminados permanentemente en un plazo maximo de 30 dias, conforme al RGPD.
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color: #ffdad6; border-radius: 12px;">
        <tr>
            <td style="padding: 16px 24px;">
                <p style="margin: 0; font-size: 13px; color: #93000a; line-height: 1.5;">
                    Si no solicitaste esta eliminacion, contacta con nosotros inmediatamente.
                </p>
            </td>
        </tr>
    </table>
@endsection
