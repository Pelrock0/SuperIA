@extends('emails.layout', ['subject' => 'Superlistia AI — Budget cap exceeded'])

@section('content')
    <h1 style="margin: 0 0 24px; font-size: 26px; font-weight: 800; color: #002736; letter-spacing: -0.03em;">
        Budget cap exceeded
    </h1>

    <p style="margin: 0 0 24px; font-size: 15px; line-height: 1.6; color: #191c1e;">
        The monthly Claude API spend has exceeded the configured cap.
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color: #ffdad6; border-radius: 12px; margin-bottom: 24px;">
        <tr>
            <td style="padding: 20px 24px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <td style="padding: 4px 0; font-size: 14px; color: #93000a;">
                            <strong>Current spend:</strong> ${{ number_format($currentSpendUsd, 2) }} USD
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 4px 0; font-size: 14px; color: #93000a;">
                            <strong>Configured limit:</strong> ${{ number_format($limitUsd, 2) }} USD
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <p style="margin: 0 0 16px; font-size: 15px; line-height: 1.6; color: #191c1e;">
        All Claude API calls are now blocked until the start of next month or until the limit is raised.
    </p>

    <p style="margin: 0; font-size: 13px; line-height: 1.5; color: #71787d;">
        To raise the limit, update <code style="background: #f2f4f6; padding: 2px 6px; border-radius: 4px; font-size: 12px;">AI_BUDGET_CAP_MONTHLY_USD</code> in the production environment.
    </p>
@endsection
