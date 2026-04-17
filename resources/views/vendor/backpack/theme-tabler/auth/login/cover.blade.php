@extends(backpack_view('layouts.auth'))

@section('content')
    <div class="row g-0 flex-fill">
        <div class="col-12 col-lg-6 col-xl-4 border-top-wide border-primary d-flex flex-column justify-content-center">
            <div class="container container-tight my-5 px-lg-5">
                <div class="text-center mb-4">
                    <div style="display:inline-flex;align-items:center;gap:10px">
                        <svg width="36" height="36" viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M20 10C20 10 21 6 25 4C25 4 24 8 20 10Z" fill="#10B981"/>
                            <path d="M20 10C17 10 16 8 16 8C16 8 18.5 6 20 6" stroke="#10B981" stroke-linecap="round" stroke-width="1.5"/>
                            <path d="M28 16C28 12.6863 25.3137 10 22 10H18C14.6863 10 12 12.6863 12 16C12 19.3137 14.6863 22 18 22H22C25.3137 22 28 24.6863 28 28C28 31.3137 25.3137 34 22 34H18C14.6863 34 12 31.3137 12 28" stroke="#003E54" stroke-linecap="round" stroke-width="4"/>
                        </svg>
                        <span style="font-size:24px;font-weight:800;color:#003E54;letter-spacing:-0.02em">Superlistia</span>
                    </div>
                    <br>
                    <span style="font-size:10px;color:#10B981;text-transform:uppercase;letter-spacing:0.1em;font-weight:700">ADMIN CONSOLE</span>
                </div>
                
                @if(Session::has('message'))
                
                    <p class="alert alert-danger">{{ Session::get('message') }}</p>
                    @php
                    if(Session::has('message')){
                        Session::forget('message');
                    }   
                @endphp
                @endif

                <!-- os la app env está en local o la url contiene como path /loginDoubleAuth -->
                @if(request()->is('loginDoubleAuth'))
                    <span>{{ __('opa.views.login_double_auth') }}</span>
                @endif
                @include(backpack_view('auth.login.inc.form'))



                
            </div>
        </div>
        <div class="col-12 col-lg-6 col-xl-8 d-none d-lg-block">
            <div class="bg-cover h-100 min-vh-100" style="background-image: url({{ asset('img/sede.jpg') }})"></div>
        </div>
    </div>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            if (window.self !== window.top) {
               window.top.location.reload();
            }
        });
    </script>
@endsection
