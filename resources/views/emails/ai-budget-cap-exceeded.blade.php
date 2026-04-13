<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Superia AI — Budget cap exceeded</title>
</head>
<body>
    <h1>Superia AI — Budget cap exceeded</h1>
    <p>The monthly Claude API spend has exceeded the configured cap.</p>
    <ul>
        <li><strong>Current spend:</strong> ${{ number_format($currentSpendUsd, 2) }} USD</li>
        <li><strong>Configured limit:</strong> ${{ number_format($limitUsd, 2) }} USD</li>
    </ul>
    <p>All Claude API calls are now blocked until the start of next month or until the limit is raised.</p>
    <p>To raise the limit, update <code>AI_BUDGET_CAP_MONTHLY_USD</code> in the production environment.</p>
</body>
</html>
