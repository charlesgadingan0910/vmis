<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>Sign in — VMIS | PRO5</title>
<link href="{{ asset('dist/img/kasurog.png') }}" rel="icon">

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Barlow+Semi+Condensed:wght@600;700;800&family=Inter:wght@400;500;600;700&family=Roboto+Mono:wght@500&display=swap" rel="stylesheet">

<style>
  :root{
    --navy-950:#060D1A; --navy-900:#0A1830; --navy-850:#0D1F3D; --navy-800:#102544;
    --gold-600:#9C7A3B; --gold-500:#C6A15B; --gold-400:#D8B876; --gold-300:#EAD7A8;
    --paper-50:#F7F8FA; --ink-950:#0B111C; --ink-600:#525C6D; --line:#E3E6EC;
    --ok-600:#2F7D4F; --ok-bg:#EAF6EF; --err-600:#C0362C; --err-bg:#FCEDEC;
  }
  *{box-sizing:border-box; -webkit-tap-highlight-color:transparent;}
  html,body{height:100%;}
  body{
    margin:0; font-family:'Inter',sans-serif; color:var(--ink-950);
    background:
      radial-gradient(ellipse 900px 520px at 84% 8%, rgba(198,161,91,0.14), transparent 60%),
      radial-gradient(ellipse 650px 480px at 6% 92%, rgba(32,68,122,0.3), transparent 60%),
      linear-gradient(175deg, var(--navy-950) 0%, var(--navy-900) 55%, var(--navy-850) 100%);
    min-height:100%; display:flex; align-items:center; justify-content:center; padding:28px;
  }
  h1,h2,.display{font-family:'Barlow Semi Condensed',sans-serif; font-weight:700; letter-spacing:-0.005em; line-height:1.08;}
  .blueprint{
    position:fixed; inset:0; opacity:0.28; pointer-events:none; z-index:0;
    background-image:linear-gradient(rgba(255,255,255,0.04) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,0.04) 1px, transparent 1px);
    background-size:46px 46px;
    mask-image:radial-gradient(ellipse 900px 620px at 70% 30%, black 5%, transparent 70%);
  }

  .auth-card{
    position:relative; z-index:1; width:100%; max-width:860px;
    background:#fff; border-radius:20px; overflow:hidden;
    display:grid; grid-template-columns:1fr 1fr;
    box-shadow:0 30px 80px rgba(0,0,0,0.4);
  }

  .auth-side{
    position:relative; background:linear-gradient(165deg, var(--navy-850), var(--navy-950));
    padding:44px 40px; color:#fff; display:flex; flex-direction:column; justify-content:space-between; overflow:hidden;
  }
  .auth-side::before{
    content:''; position:absolute; inset:0;
    background-image:linear-gradient(rgba(255,255,255,0.035) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,0.035) 1px, transparent 1px);
    background-size:20px 20px;
  }
  .auth-side::after{
    content:''; position:absolute; top:-30%; right:-30%; width:65%; height:65%;
    background:radial-gradient(circle, rgba(198,161,91,0.18), transparent 65%);
  }
  .auth-side > *{position:relative; z-index:1;}
  .brand-mark{width:40px; height:40px;}
  .brand-name{font-family:'Barlow Semi Condensed',sans-serif; font-weight:700; font-size:19px; margin-top:14px;}
  .brand-sub{font-size:10.5px; color:var(--gold-400); letter-spacing:0.06em; font-weight:600; margin-top:2px;}
  .side-title{font-size:26px; margin-top:34px; max-width:13ch;}
  .side-copy{font-size:13.5px; color:#9FADC4; margin-top:10px; line-height:1.65; max-width:26ch;}
  .side-points{list-style:none; margin:26px 0 0; padding:0; display:flex; flex-direction:column; gap:12px;}
  .side-points li{display:flex; align-items:center; gap:10px; font-size:12.5px; color:#C7D2E4;}
  .side-points svg{width:15px; height:15px; flex:none; color:var(--gold-400);}
  .side-foot{font-size:11px; color:#5E7096; border-top:1px solid rgba(255,255,255,0.1); padding-top:16px; line-height:1.6;}

  .auth-form{padding:44px 42px;}
  .form-head .eyebrow{font-size:11.5px; font-weight:600; color:var(--gold-600); letter-spacing:0.04em; margin-bottom:8px;}
  .form-head h1{font-size:26px; color:var(--navy-900);}
  .form-head p{font-size:13.5px; color:var(--ink-600); margin:6px 0 0;}

  .banner{
    display:flex; align-items:flex-start; gap:10px; border-radius:10px; padding:12px 14px;
    font-size:13px; margin:22px 0 4px; line-height:1.5;
  }
  .banner svg{width:17px; height:17px; flex:none; margin-top:1px;}
  .banner-error{background:var(--err-bg); color:var(--err-600);}
  .banner-success{background:var(--ok-bg); color:var(--ok-600);}

  form{margin-top:22px;}
  .field{margin-bottom:17px;}
  .field label{display:block; font-size:12.5px; font-weight:600; color:var(--ink-950); margin-bottom:7px;}
  .field-input{position:relative;}
  .field input{
    width:100%; padding:12px 13px; border:1.5px solid var(--line); border-radius:9px; font-size:14.5px;
    font-family:'Inter',sans-serif; color:var(--ink-950); transition:border-color .15s, box-shadow .15s;
  }
  .field input.has-toggle{padding-right:42px;}
  .field input:focus{outline:none; border-color:var(--navy-800); box-shadow:0 0 0 3.5px rgba(16,37,68,0.12);}
  .field input.is-invalid{border-color:var(--err-600); box-shadow:0 0 0 3.5px rgba(192,54,44,0.1);}
  .field-error{font-size:12px; color:var(--err-600); margin-top:6px;}
  .toggle-pw{
    position:absolute; right:4px; top:50%; transform:translateY(-50%); width:34px; height:34px;
    border:none; background:none; cursor:pointer; color:#8896AA; display:flex; align-items:center; justify-content:center; border-radius:7px;
  }
  .toggle-pw:hover{color:var(--navy-900); background:var(--paper-50);}
  .toggle-pw svg{width:18px; height:18px;}

  .field-row{display:flex; justify-content:space-between; align-items:center; margin-bottom:22px; font-size:13px;}
  .remember{display:flex; align-items:center; gap:8px; color:var(--ink-600); cursor:pointer;}
  .remember input{width:15px; height:15px; accent-color:var(--navy-800);}
  .forgot{color:#8896AA; font-size:12.5px; text-decoration:none; cursor:default;}

  .btn-submit{
    width:100%; padding:13px; font-size:15px; font-weight:600; border:none; border-radius:9px; cursor:pointer;
    background:linear-gradient(180deg, var(--gold-400), var(--gold-500)); color:#251B08;
    box-shadow:0 1px 0 rgba(255,255,255,0.35) inset, 0 10px 24px rgba(198,161,91,0.28);
    display:flex; align-items:center; justify-content:center; gap:8px;
    transition:transform .15s ease, box-shadow .15s ease;
  }
  .btn-submit:hover{transform:translateY(-1px);}
  .btn-submit:disabled{opacity:0.75; cursor:default; transform:none;}
  .spinner{width:15px; height:15px; border-radius:50%; border:2px solid rgba(37,27,8,0.3); border-top-color:#251B08; animation:spin .7s linear infinite; display:none;}
  .btn-submit.loading .spinner{display:inline-block;}
  @keyframes spin{to{transform:rotate(360deg);}}

  .form-foot{font-size:11.5px; color:#8896AA; text-align:center; margin-top:22px; line-height:1.6;}

  @media (max-width:720px){
    .auth-card{grid-template-columns:1fr; max-width:420px;}
    .auth-side{display:none;}
    .auth-form{padding:36px 26px;}
  }
</style>
</head>
<body>
<div class="blueprint"></div>

<div class="auth-card">
  <div class="auth-side">
    <div>
      <svg class="brand-mark" viewBox="0 0 48 48" fill="none">
        <path d="M24 3L42 10V22C42 33 34.6 41.6 24 45C13.4 41.6 6 33 6 22V10L24 3Z" fill="#0F2547" stroke="#C6A15B" stroke-width="1.5"/>
        <circle cx="24" cy="23" r="8" fill="none" stroke="#C6A15B" stroke-width="1.2"/>
        <path d="M24 17V23L28 26" stroke="#D8B876" stroke-width="1.4" stroke-linecap="round"/>
      </svg>
      <div class="brand-name">VMIS &middot; PRO5</div>
      <div class="brand-sub">POLICE REGIONAL OFFICE 5</div>
      <div class="side-title">Vehicle Management Information System</div>
      <p class="side-copy">Sign in with your PRO5-issued credentials to view and update vehicle records.</p>
      <ul class="side-points">
        <li><svg viewBox="0 0 24 24" fill="none"><path d="M4 12L10 18L20 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>Fleet inventory &amp; assignment</li>
        <li><svg viewBox="0 0 24 24" fill="none"><path d="M4 12L10 18L20 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>Maintenance &amp; PMS scheduling</li>
        <li><svg viewBox="0 0 24 24" fill="none"><path d="M4 12L10 18L20 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>QR-linked vehicle profiles</li>
      </ul>
    </div>
    <div class="side-foot">Unauthorized access to this system is prohibited and may be subject to disciplinary and/or legal action.</div>
  </div>

  <div class="auth-form">
    <div class="form-head">
      <div class="eyebrow">SIGN IN</div>
      <h1>Welcome back</h1>
      <p>Enter your credentials to access the system.</p>
    </div>

    @if (session('success'))
      <div class="banner banner-success">
        <svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.6"/><path d="M8 12.5L10.5 15L16 9" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
        <span>{{ session('success') }}</span>
      </div>
    @endif

    @if ($errors->any())
      <div class="banner banner-error">
        <svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.6"/><path d="M12 8V13" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/><circle cx="12" cy="16" r="1" fill="currentColor"/></svg>
        <span>{{ $errors->first() }}</span>
      </div>
    @endif

    <form method="POST" action="{{ route('login.authenticate') }}" id="loginForm" novalidate>
      @csrf
      <div class="field">
        <label for="login">PNP Email</label>
        <input type="text" id="login" name="login" value="{{ old('login') }}"
               class="{{ $errors->has('login') ? 'is-invalid' : '' }}"
               placeholder="e.g. juan.delacruz@pnp.gov.ph"
               autocomplete="username" autofocus required>
        @error('login')<div class="field-error">{{ $message }}</div>@enderror
      </div>

      <div class="field">
        <label for="password">Password</label>
        <div class="field-input">
          <input type="password" id="password" name="password"
                 class="has-toggle {{ $errors->has('password') ? 'is-invalid' : '' }}"
                 placeholder="••••••••" autocomplete="current-password" required>
          <button type="button" class="toggle-pw" data-toggle-target="password" aria-label="Show password">
            <svg viewBox="0 0 24 24" fill="none"><path d="M2 12C2 12 5.5 5.5 12 5.5C18.5 5.5 22 12 22 12C22 12 18.5 18.5 12 18.5C5.5 18.5 2 12 2 12Z" stroke="currentColor" stroke-width="1.6"/><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.6"/></svg>
          </button>
        </div>
        @error('password')<div class="field-error">{{ $message }}</div>@enderror
      </div>

      <div class="field-row">
        <label class="remember"><input type="checkbox" name="remember"> Keep me signed in</label>
        <span class="forgot" title="Contact your system administrator to reset your password">Forgot password?</span>
      </div>

      <button type="submit" class="btn-submit" id="loginSubmit">
        <span class="spinner"></span>
        <span class="btn-label">Sign in</span>
      </button>
    </form>

    <p class="form-foot">Access is limited to accounts provisioned by the PRO5 admin.<br>Contact your unit's system administrator if you need an account.</p>
  </div>
</div>

<script>
  document.querySelectorAll('[data-toggle-target]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var input = document.getElementById(btn.dataset.toggleTarget);
      input.type = input.type === 'password' ? 'text' : 'password';
      btn.setAttribute('aria-label', input.type === 'password' ? 'Show password' : 'Hide password');
    });
  });

  // Prevent double-submit while the page navigates away; the real validation happens server-side.
  document.getElementById('loginForm').addEventListener('submit', function () {
    var btn = document.getElementById('loginSubmit');
    btn.classList.add('loading');
    btn.disabled = true;
    btn.querySelector('.btn-label').textContent = 'Signing in…';
  });
</script>
</body>
</html>
