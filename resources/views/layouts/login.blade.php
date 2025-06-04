<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1.0, user-scalable=no">
	<meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ env('APP_NAME') }}</title>

    <link rel="icon" href="{{ asset('favicon.ico') }}">

    <link rel="stylesheet" href="{{ asset('css_v2.0.1_f1/style.css') }}">
    <link rel="stylesheet" href="{{ asset('css_v2.0.1_f1/login_style.css') }}">
    <link rel="stylesheet" href="{{ asset('css_v2.0.1_f1/settings_style.css') }}">
    <link rel="stylesheet" href="{{ url('xfel/xfel.css') }}">

    <script src="{{ asset('js_v2.0.1_f1/functions.js') }}"></script>
    <script src="{{ asset('js_v2.0.1_f1/settings_functions.js') }}"></script>

    {!! $settingsPanel !!}

    <script>
		InitializePreDomSettings(false);
        UpdateSettingsLanguage('{{ Session::get("language")['id'] }}');
	</script>
    
</head>
<body>
<div class="wrapper">
    <div class="sidebar">
        <div class="logo"><div style="font-size: 10px; margin-top: -15px;">powered by HAWKI</div></div>

        <div class="loginPanel">
			{!! $authForms !!}
        </div>


        <div class="footerPanel">
  
            <button class="btn-sm" onclick="toggleSettingsPanel(true)">
                <x-icon name="settings-icon"/>
            </button>
            <div class="impressumPanel">
                <a href="/dataprotection" target="_blank">{{ $translation["DataProtection"] }}</a>
                <a href="{{ env("IMPRINT_LOCATION") }}" target="_blank">{{ $translation["Impressum"] }}</a>
            </div>
        </div>

    </div>

    <main>
        <div class="center-text" style="padding-top: 50px;">
            <img src="{{url('xfel/xfel_logo.png')}}" alt="European XFEL">
            <h1>RAY - Reasoning Assistant for You</h1>
        </div>
    </main>
</div>

@include('partials.overlay')

</body>
</html>

<script>
    window.addEventListener('DOMContentLoaded', (event) => {
        if(window.innerWidth < 480){
            const bgVideo = document.querySelector(".image_preview_container");
            bgVideo.remove();
        }

        // console.log(@json($activeOverlay));
        setTimeout(() => {
            if(@json($activeOverlay)){
                // console.log('close overlay');
                setOverlay(false, true)
            }
        }, 100);
    });

    function onLoginKeydown(event){
        if(event.key == "Enter"){
            const username = document.getElementById('account');
            // console.log(username.value);
            if(!username.value){
                return;
            }
            const password = document.getElementById('password');
            if(document.activeElement != password){
                password.focus();
                return;
            }
            if(username.value && password.value){
                LoginLDAP();
            }
        }
    }
    async function LoginLDAP() {
        const progress = document.getElementById("login-progress");
        const button = document.getElementById("loginButton");
        const message = document.getElementById("login-message");
        
        progress.style.display = "block";
        button.style.display = "none";
        message.textContent = '';        
        try {
            var formData = new FormData();
            formData.append("account", document.getElementById("account").value);
            formData.append("password", document.getElementById("password").value);
            const csrfToken = document.getElementById('loginForm-LDAP').querySelector('input[name="_token"]').value;

            const response = await fetch('/req/login-ldap', {
                method: "POST",
                headers: {
                    "X-CSRF-TOKEN": csrfToken
                },
                body: formData
            });

            if (!response.ok) {
                throw new Error("Login request failed");
            }

            const data = await response.json();

            if (data.success) {
                await setOverlay(true, true)
                window.location.href = data.redirectUri;

            } else {
                // console.log('login failed');
                message.textContent = data.message;
            }
        } catch (error) {
            console.error(error);
        } finally {
            progress.style.display = "none";
            button.style.display = "block";
        }
        
    }
</script>