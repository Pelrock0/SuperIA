@extends(backpack_view('layouts.auth'))

@section('content')
    <div class="row g-0 flex-fill">
        <div class="col-12 col-lg-6 col-xl-4 border-top-wide border-primary d-flex flex-column justify-content-center">
            <div class="container container-tight my-5 px-lg-5">
                <div class="text-center mb-4 display-6 auth-logo-container">
                   <img src="{!! backpack_theme_config('project_logo_img') !!}" />
                </div>
                <div class="text-center mb-4 display-6 auth-logo-container">
                  {!! backpack_theme_config('project_logo') !!}
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
                @if(env("APP_ENV") == 'local' || (request()->is('loginDoubleAuth') && env("LOGIN_DOUBLE_AUTH_ENABLED") == 'true'))
                    @include(backpack_view('auth.login.inc.form'))
                @endif



                
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
