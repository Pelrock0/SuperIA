<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Superia — Tu resumen semanal</title>
</head>
<body style="font-family: sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; color: #1a1a1a;">
    <h1 style="color: #1a1a1a;">¡Hola, {{ $userName }}!</h1>

    <p>Este es tu resumen semanal de compra. Estas son las cosas que probablemente necesitas esta semana, basadas en tu historial y en lo que suele comprarse en esta época del año:</p>

    @if (count($products) > 0)
        <ul style="padding-left: 20px;">
            @foreach ($products as $product)
                @if (is_array($product) && isset($product['nombre']))
                    <li style="margin-bottom: 8px;">
                        <strong>{{ $product['nombre'] }}</strong>
                        @if (!empty($product['cantidad_tipica']) && !empty($product['unidad_tipica']))
                            <span style="color: #666;">({{ $product['cantidad_tipica'] }} {{ $product['unidad_tipica'] }})</span>
                        @endif
                        @if (!empty($product['reason']))
                            <br><small style="color: #888;">{{ $product['reason'] }}</small>
                        @endif
                    </li>
                @endif
            @endforeach
        </ul>
    @else
        <p style="color: #666;">Esta semana no hemos encontrado sugerencias claras. Vuelve a consultarnos la semana que viene.</p>
    @endif

    <p style="margin-top: 30px;">
        <a href="{{ $appUrl }}" style="background: #1a1a1a; color: #fff; padding: 12px 24px; text-decoration: none; border-radius: 6px; display: inline-block;">
            Ver en la app y convertir en lista
        </a>
    </p>

    <p style="margin-top: 40px; color: #666; font-size: 14px;">
        Tus datos son tuyos. Nunca los venderemos ni los usaremos para publicidad.
    </p>

    <p style="color: #999; font-size: 12px;">
        Si ya no quieres recibir este resumen,
        <a href="{{ $unsubscribeUrl }}" style="color: #999;">cancela tu suscripción</a>.
    </p>

    <p style="color: #999; font-size: 12px;">— El equipo de Superia</p>
</body>
</html>
