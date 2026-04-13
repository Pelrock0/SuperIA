<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Superia — Tu invitación</title>
</head>
<body style="font-family: sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;">
    <h1 style="color: #1a1a1a;">¡{{ $userName }}, estás dentro!</h1>

    <p>Tu invitación para unirte a <strong>Superia</strong> está lista.</p>

    <p>Haz clic en el siguiente enlace para crear tu cuenta:</p>

    <p style="text-align: center; margin: 30px 0;">
        <a href="{{ url('/register?token=' . $token) }}"
           style="background-color: #2563eb; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: bold;">
            Crear mi cuenta
        </a>
    </p>

    <p style="color: #666; font-size: 14px;">
        Este enlace expira el {{ $expiresAt->format('d/m/Y') }}. Si no lo usas a tiempo, contacta con nosotros.
    </p>

    <p style="color: #999; font-size: 12px;">— El equipo de Superia</p>
</body>
</html>
