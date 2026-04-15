@extends('emails.layout', ['subject' => 'Superia — Restablecer contrasena'])

@section('content')
    <h1 style="margin: 0 0 24px; font-size: 26px; font-weight: 800; color: #002736; letter-spacing: -0.03em;">
        Hola, {{ $userName }}
    </h1>

    <p style="margin: 0 0 24px; font-size: 15px; line-height: 1.6; color: #191c1e;">
        Has solicitado restablecer tu contrasena en <strong>Superia</strong>.
    </p>

    {{-- CTA button --}}
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td align="center" style="padding: 8px 0 32px;">
                <!--[if mso]>
                <v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" href="{{ $resetUrl }}" style="width:280px;height:48px;" arcsize="17%" fillcolor="#002736" stroke="f">
                    <v:textbox inset="0,0,0,0" style="mso-fit-shape-to-text:false;v-text-anchor:middle;">
                        <center style="color:#ffffff;font-size:15px;font-weight:700;">Restablecer contrasena</center>
                    </v:textbox>
                </v:roundrect>
                <![endif]-->
                <!--[if !mso]><!-->
                <a href="{{ $resetUrl }}"
                   style="display: inline-block; padding: 14px 40px; background: linear-gradient(to right, #002736, #003e54); color: #ffffff; font-size: 15px; font-weight: 700; text-decoration: none; border-radius: 12px; letter-spacing: -0.01em;">
                    Restablecer contrasena
                </a>
                <!--<![endif]-->
            </td>
        </tr>
    </table>

    <p style="margin: 0; font-size: 13px; line-height: 1.5; color: #71787d;">
        Este enlace expira en 1 hora. Si no solicitaste este cambio, puedes ignorar este mensaje.
    </p>
@endsection
