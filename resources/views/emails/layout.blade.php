<!DOCTYPE html>
<html lang="es" xmlns:v="urn:schemas-microsoft-com:vml">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light">
    <meta name="supported-color-schemes" content="light">
    <title>{{ $subject ?? 'Superia' }}</title>
    <!--[if mso]>
    <noscript>
        <xml>
            <o:OfficeDocumentSettings>
                <o:PixelsPerInch>96</o:PixelsPerInch>
            </o:OfficeDocumentSettings>
        </xml>
    </noscript>
    <![endif]-->
</head>
<body style="margin: 0; padding: 0; width: 100%; background-color: #f2f4f6; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; -webkit-font-smoothing: antialiased; -moz-osx-font-smoothing: grayscale;">

    <!-- Outer wrapper -->
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color: #f2f4f6;">
        <tr>
            <td align="center" style="padding: 40px 16px;">

                <!-- Logo + brand -->
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width: 560px;">
                    <tr>
                        <td align="center" style="padding-bottom: 32px;">
                            <table role="presentation" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td style="vertical-align: middle;">
                                        <!--[if mso]>
                                        <v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" style="width:36px;height:36px;" arcsize="33%" fillcolor="#002736" stroke="f">
                                            <v:textbox inset="0,0,0,0" style="mso-fit-shape-to-text:false;v-text-anchor:middle;">
                                                <center style="color:#ffffff;font-size:20px;font-weight:800;">S</center>
                                            </v:textbox>
                                        </v:roundrect>
                                        <![endif]-->
                                        <!--[if !mso]><!-->
                                        <div style="width: 36px; height: 36px; background-color: #002736; border-radius: 10px; display: inline-block; text-align: center; line-height: 36px;">
                                            <span style="color: #ffffff; font-size: 20px; font-weight: 800; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">S</span>
                                        </div>
                                        <!--<![endif]-->
                                    </td>
                                    <td style="vertical-align: middle; padding-left: 12px;">
                                        <span style="font-size: 22px; font-weight: 700; color: #002736; letter-spacing: -0.03em;">Superia</span>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>

                <!-- Main card -->
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width: 560px; background-color: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 1px 3px rgba(0, 39, 54, 0.06);">
                    {{-- Accent top bar --}}
                    <tr>
                        <td style="height: 4px; background: linear-gradient(to right, #002736, #003e54); font-size: 0; line-height: 0;">&nbsp;</td>
                    </tr>

                    {{-- Body --}}
                    <tr>
                        <td style="padding: 40px 36px;">
                            @yield('content')
                        </td>
                    </tr>
                </table>

                <!-- Footer -->
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width: 560px;">
                    <tr>
                        <td align="center" style="padding: 32px 16px 8px;">
                            <p style="margin: 0 0 6px; font-size: 12px; color: #71787d; line-height: 1.5;">
                                Tus datos son tuyos. Nunca los venderemos ni los usaremos para publicidad.
                            </p>
                            @hasSection('footer-extra')
                                @yield('footer-extra')
                            @endif
                            <p style="margin: 12px 0 0; font-size: 11px; color: #a3a9ae;">
                                &copy; {{ date('Y') }} Superia &middot; La compra, mas inteligente
                            </p>
                        </td>
                    </tr>
                </table>

            </td>
        </tr>
    </table>

</body>
</html>
