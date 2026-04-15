@extends('emails.layout', ['subject' => 'Superia — Tu resumen semanal'])

@section('content')
    <h1 style="margin: 0 0 24px; font-size: 26px; font-weight: 800; color: #002736; letter-spacing: -0.03em;">
        Hola, {{ $userName }}
    </h1>

    <p style="margin: 0 0 24px; font-size: 15px; line-height: 1.6; color: #191c1e;">
        Este es tu resumen semanal. Cosas que probablemente necesitas esta semana, basadas en tu historial:
    </p>

    @if (count($products) > 0)
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 24px;">
            @foreach ($products as $product)
                @if (is_array($product) && isset($product['nombre']))
                    <tr>
                        <td style="padding: 12px 16px; border-bottom: 1px solid #f2f4f6; vertical-align: top;">
                            <p style="margin: 0; font-size: 15px; font-weight: 600; color: #002736;">
                                {{ $product['nombre'] }}
                                @if (!empty($product['cantidad_tipica']) && !empty($product['unidad_tipica']))
                                    <span style="font-weight: 400; color: #71787d; font-size: 13px;">
                                        &middot; {{ $product['cantidad_tipica'] }} {{ $product['unidad_tipica'] }}
                                    </span>
                                @endif
                            </p>
                            @if (!empty($product['reason']))
                                <p style="margin: 4px 0 0; font-size: 12px; color: #a3a9ae; line-height: 1.4;">{{ $product['reason'] }}</p>
                            @endif
                        </td>
                    </tr>
                @endif
            @endforeach
        </table>
    @else
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color: #f2f4f6; border-radius: 12px; margin-bottom: 24px;">
            <tr>
                <td style="padding: 20px 24px; text-align: center;">
                    <p style="margin: 0; font-size: 14px; color: #71787d;">
                        Esta semana no hemos encontrado sugerencias claras. Vuelve a consultarnos la semana que viene.
                    </p>
                </td>
            </tr>
        </table>
    @endif

    {{-- CTA button --}}
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td align="center" style="padding: 8px 0 16px;">
                <!--[if mso]>
                <v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" href="{{ $appUrl }}" style="width:320px;height:48px;" arcsize="17%" fillcolor="#002736" stroke="f">
                    <v:textbox inset="0,0,0,0" style="mso-fit-shape-to-text:false;v-text-anchor:middle;">
                        <center style="color:#ffffff;font-size:15px;font-weight:700;">Ver en la app y convertir en lista</center>
                    </v:textbox>
                </v:roundrect>
                <![endif]-->
                <!--[if !mso]><!-->
                <a href="{{ $appUrl }}"
                   style="display: inline-block; padding: 14px 40px; background: linear-gradient(to right, #002736, #003e54); color: #ffffff; font-size: 15px; font-weight: 700; text-decoration: none; border-radius: 12px; letter-spacing: -0.01em;">
                    Ver en la app y convertir en lista
                </a>
                <!--<![endif]-->
            </td>
        </tr>
    </table>
@endsection

@section('footer-extra')
    <p style="margin: 8px 0 0; font-size: 11px; color: #a3a9ae;">
        Si ya no quieres recibir este resumen,
        <a href="{{ $unsubscribeUrl }}" style="color: #71787d; text-decoration: underline;">cancela tu suscripcion</a>.
    </p>
@endsection
