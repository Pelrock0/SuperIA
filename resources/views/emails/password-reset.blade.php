<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Superia — Restablecer contraseña</title>
</head>
<body style="font-family: sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;">
    <h1 style="color: #1a1a1a;">Hola {{ $userName }}</h1>

    <p>Has solicitado restablecer tu contraseña en <strong>Superia</strong>.</p>

    <p style="text-align: center; margin: 30px 0;">
        <a href="{{ $resetUrl }}"
           style="background-color: #2563eb; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: bold;">
            Restablecer contraseña
        </a>
    </p>

    <p style="color: #666; font-size: 14px;">
        Este enlace expira en 1 hora. Si no solicitaste este cambio, puedes ignorar este mensaje.
    </p>

    <p style="color: #999; font-size: 12px;">— El equipo de Superia</p>
</body>
</html>
