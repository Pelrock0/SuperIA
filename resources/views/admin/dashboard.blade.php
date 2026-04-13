@extends(backpack_view('blank'))

@push('after_styles')
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700;800&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
<style>
    .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; }
    .admin-body { font-family: 'Inter', sans-serif; background: #f7f9fb; }
    .stat-card { background: #fff; padding: 24px; border-radius: 16px; box-shadow: 0 24px 48px -12px rgba(0,39,54,0.06); transition: transform 0.2s; }
    .stat-card:hover { transform: translateY(-4px); }
    .stat-icon { width: 40px; height: 40px; border-radius: 12px; display: flex; align-items: center; justify-content: center; }
    .stat-badge { font-size: 10px; font-weight: 700; padding: 4px 8px; border-radius: 9999px; background: rgba(111,251,190,0.3); color: #10b981; }
    .stat-label { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.1em; color: #71787d; margin-bottom: 4px; }
    .stat-value { font-size: 28px; font-weight: 800; color: #002736; letter-spacing: -0.025em; }
    .ai-card { background: linear-gradient(135deg, #002736, #003e54); border-radius: 24px; padding: 32px; color: #fff; box-shadow: 0 24px 48px -12px rgba(0,39,54,0.2); position: relative; overflow: hidden; }
    .ai-card .sparkle { position: absolute; top: 0; right: 0; width: 128px; height: 128px; background: rgba(111,251,190,0.1); border-radius: 9999px; margin-right: -64px; margin-top: -64px; filter: blur(40px); }
    .waitlist-card { background: #fff; border-radius: 24px; padding: 32px; box-shadow: 0 24px 48px -12px rgba(0,39,54,0.06); }
    .system-card { background: #f2f4f6; padding: 24px; border-radius: 24px; display: flex; align-items: center; gap: 16px; }
    .system-icon { width: 48px; height: 48px; background: #fff; border-radius: 16px; display: flex; align-items: center; justify-content: center; color: #002736; box-shadow: 0 24px 48px -12px rgba(0,39,54,0.06); }
    .bar-chart { display: flex; align-items: flex-end; gap: 6px; height: 64px; opacity: 0.6; }
    .bar-chart div { background: #6ffbbe; border-radius: 2px 2px 0 0; flex: 1; }
    .invite-btn { width: 100%; padding: 16px; background: #10b981; color: #fff; font-weight: 800; border-radius: 16px; border: none; cursor: pointer; font-size: 14px; display: flex; align-items: center; justify-content: center; gap: 8px; box-shadow: 0 8px 24px rgba(16,185,129,0.2); }
    .invite-btn:hover { opacity: 0.9; }
    .quick-link { display: inline-flex; align-items: center; gap: 8px; padding: 8px 16px; background: #f2f4f6; border-radius: 12px; color: #002736; font-weight: 600; font-size: 13px; text-decoration: none; transition: background 0.2s; }
    .quick-link:hover { background: #e6e8ea; color: #002736; text-decoration: none; }
</style>
@endpush

@section('content')
<div class="admin-body" style="padding: 32px; max-width: 1280px; margin: 0 auto;">
    <h2 style="font-size: 24px; font-weight: 800; color: #002736; letter-spacing: -0.025em; margin-bottom: 32px;">Panel de Control</h2>

    {{-- Stat Cards --}}
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 24px; margin-bottom: 32px;">
        <div class="stat-card">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 16px;">
                <div class="stat-icon" style="background: #c1e8ff;">
                    <span class="material-symbols-outlined" style="color: #002736;">group</span>
                </div>
            </div>
            <div class="stat-label">Total Usuarios</div>
            <div class="stat-value">{{ number_format($metrics['users_total']) }}</div>
        </div>
        <div class="stat-card">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 16px;">
                <div class="stat-icon" style="background: #b3ebff;">
                    <span class="material-symbols-outlined" style="color: #00677d;">bolt</span>
                </div>
            </div>
            <div class="stat-label">Activos (7d)</div>
            <div class="stat-value">{{ number_format($metrics['users_active_7d']) }}</div>
        </div>
        <div class="stat-card">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 16px;">
                <div class="stat-icon" style="background: #c1e8ff;">
                    <span class="material-symbols-outlined" style="color: #002736;">format_list_bulleted</span>
                </div>
                <span class="stat-badge">{{ $metrics['lists_created_today'] }} hoy</span>
            </div>
            <div class="stat-label">Listas creadas hoy</div>
            <div class="stat-value">{{ number_format($metrics['lists_total']) }}</div>
        </div>
        <div class="stat-card">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 16px;">
                <div class="stat-icon" style="background: #6ffbbe;">
                    <span class="material-symbols-outlined" style="color: #002113;">smart_toy</span>
                </div>
                <span class="stat-badge">{{ $metrics['ai_calls_today'] }} hoy</span>
            </div>
            <div class="stat-label">Llamadas IA (mes)</div>
            <div class="stat-value">{{ number_format($metrics['ai_calls_month']) }}</div>
        </div>
    </div>

    {{-- AI Cost + Waitlist Row --}}
    <div style="display: grid; grid-template-columns: 5fr 7fr; gap: 32px; margin-bottom: 32px;">
        {{-- AI Cost Card --}}
        <div class="ai-card">
            <div class="sparkle"></div>
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 40px;">
                <div style="display: flex; align-items: center; gap: 12px;">
                    <div style="width: 48px; height: 48px; background: #6ffbbe; border-radius: 16px; display: flex; align-items: center; justify-content: center;">
                        <span class="material-symbols-outlined" style="color: #002736; font-size: 28px; font-variation-settings: 'FILL' 1;">auto_awesome</span>
                    </div>
                    <span style="font-size: 16px; font-weight: 700;">Metricas de Consumo IA</span>
                </div>
            </div>
            <div style="margin-bottom: 48px;">
                <div style="font-size: 12px; font-weight: 500; text-transform: uppercase; letter-spacing: 0.1em; color: #7aa9c3; margin-bottom: 8px;">Coste IA este mes</div>
                <div style="display: flex; align-items: baseline; gap: 8px;">
                    <span style="font-size: 48px; font-weight: 800; letter-spacing: -0.025em;">€{{ $metrics['ai_cost_month'] }}</span>
                </div>
            </div>
            <div class="bar-chart">
                <div style="height: 40%"></div>
                <div style="height: 55%"></div>
                <div style="height: 35%"></div>
                <div style="height: 70%"></div>
                <div style="height: 60%"></div>
                <div style="height: 85%"></div>
                <div style="height: 50%"></div>
                <div style="height: 95%"></div>
            </div>
        </div>

        {{-- Waitlist Card --}}
        <div class="waitlist-card">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 32px;">
                <div>
                    <h3 style="font-size: 22px; font-weight: 800; color: #002736; letter-spacing: -0.025em;">Gestion de Espera</h3>
                    <p style="color: #71787d; font-size: 14px; margin-top: 4px;">Control de acceso y liberacion de cuotas.</p>
                </div>
                <div style="background: #f2f4f6; padding: 8px 16px; border-radius: 16px; text-align: right;">
                    <span style="font-size: 24px; font-weight: 900; color: #002736;">{{ $metrics['waitlist_pending'] }}</span>
                    <span style="display: block; font-size: 10px; color: #71787d; text-transform: uppercase; font-weight: 700;">En espera</span>
                </div>
            </div>
            <div style="margin-bottom: 24px;">
                <a href="{{ backpack_url('waitlist-entry') }}" style="color: #002736; font-size: 14px; font-weight: 700; text-decoration: none;">Ver todos los usuarios →</a>
            </div>
            <a href="{{ backpack_url('waitlist-entry') }}" class="invite-btn" style="text-decoration: none;">
                <span class="material-symbols-outlined">send</span>
                Gestionar invitaciones
            </a>
        </div>
    </div>

    {{-- Quick Links --}}
    <div style="display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 32px;">
        <a href="{{ backpack_url('user') }}" class="quick-link">
            <span class="material-symbols-outlined" style="font-size: 18px;">group</span> Usuarios
        </a>
        <a href="{{ backpack_url('ai-usage') }}" class="quick-link">
            <span class="material-symbols-outlined" style="font-size: 18px;">psychology</span> Consumo IA
        </a>
        <a href="{{ backpack_url('waitlist-entry') }}" class="quick-link">
            <span class="material-symbols-outlined" style="font-size: 18px;">hourglass_empty</span> Lista de espera
        </a>
        <a href="/telescope" target="_blank" class="quick-link">
            <span class="material-symbols-outlined" style="font-size: 18px;">history</span> Logs (Telescope)
        </a>
    </div>

    {{-- System Status Cards --}}
    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 24px;">
        <div class="system-card">
            <div class="system-icon">
                <span class="material-symbols-outlined">verified_user</span>
            </div>
            <div>
                <div style="font-weight: 700; color: #002736;">Seguridad</div>
                <div style="font-size: 12px; color: #71787d;">composer security: OK · psalm taint: 0</div>
            </div>
        </div>
        <div class="system-card">
            <div class="system-icon">
                <span class="material-symbols-outlined">database</span>
            </div>
            <div>
                <div style="font-weight: 700; color: #002736;">Base de datos</div>
                <div style="font-size: 12px; color: #71787d;">MySQL · {{ number_format($metrics['lists_total']) }} listas · {{ number_format($metrics['users_total']) }} usuarios</div>
            </div>
        </div>
        <div class="system-card">
            <div class="system-icon">
                <span class="material-symbols-outlined">speed</span>
            </div>
            <div>
                <div style="font-weight: 700; color: #002736;">Claude API</div>
                <div style="font-size: 12px; color: #71787d;">{{ number_format($metrics['ai_calls_month']) }} llamadas/mes · €{{ $metrics['ai_cost_month'] }} coste</div>
            </div>
        </div>
    </div>
</div>
@endsection
