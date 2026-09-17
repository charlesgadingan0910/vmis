<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>Set a new password — VMIS | PRO5</title>
<link href="{{ asset('dist/img/kasurog.png') }}" rel="icon">

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Barlow+Semi+Condensed:wght@600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>
  :root{
    --navy-950:#060D1A; --navy-900:#0A1830; --navy-850:#0D1F3D; --navy-800:#102544;
    --gold-600:#9C7A3B; --gold-500:#C6A15B; --gold-400:#D8B876;
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
  h1{font-family:'Barlow Semi Condensed',sans-serif; font-weight:700; letter-spacing:-0.005em;}

  .card{
    position:relative; z-index:1; width:100%; max-width:460px; background:#fff; border-radius:20px;
    padding:40px 38px; box-shadow:0 30px 80px rgba(0,0,0,0.4);
  }
  .brand{display:flex; align-items:center; gap:11px; margin-bottom:26px;}
  .brand-mark{width:34px; height:34px;}
  .brand span{font-family:'Barlow Semi Condensed',sans-serif; font-weight:700; font-size:16px; color:var(--navy-900);}

  h1{font-size:23px; color:var(--navy-900);}
  .lead{font-size:13.5px; color:var(--ink-600); margin:8px 0 0; line-height:1.55;}

  .banner{
    display:flex; align-items:flex-start; gap:10px; border-radius:10px; padding:12px 14px;
    font-size:13px; margin:20px 0 0; line-height:1.5;
  }
  .banner svg{width:17px; height:17px; flex:none; margin-top:1px;}
  .banner-status{background:#EFF4FB; color:var(--navy-800);}
  .banner-error{background:var(--err-bg); color:var(--err-600);}

  form{margin-top:22px;}
  .field{margin-bottom:16px;}
  .field label{display:block; font-size:12.5px; font-weight:600; color:var(--ink-950); margin-bottom:7px;}
  .field-input{position:relative;}
  .field input{
    width:100%; padding:12px 42px 12px 13px; border:1.5px solid var(--line); border-radius:9px; font-size:14.5px;
    font-family:'Inter',sans-serif; color:var(--ink-950); transition:border-color .15s, box-shadow .15s;
  }
  .field input:focus{outline:none; border-color:var(--navy-800); box-shadow:0 0 0 3.5px rgba(16,37,68,0.12);}
  .field input.is-invalid{border-color:var(--err-600); box-shadow:0 0 0 3.5px rgba(192,54,44,0.1);}
  .field-error{font-size:12px; color:var(--err-600); margin-top:6px;}
  .toggle-pw{
    position:absolute; right:4px; top:50%; transform:translateY(-50%); width:34px; height:34px;
    border:none; background:none; cursor:pointer; color:#8896AA; display:flex; align-items:center; justify-content:center; border-radius:7px;
  }
  .toggle-pw:hover{color:var(--navy-900); background:var(--paper-50);}
  .toggle-pw svg{width:18px; height:18px;}

  .checklist{list-style:none; margin:14px 0 20px; padding:12px 14px; background:var(--paper-50); border-radius:10px; display:flex; flex-direction:column; gap:7px;}
  .checklist li{display:flex; align-items:center; gap:9px; font-size:12.5px; color:var(--ink-600); transition:color .15s ease;}
  .checklist li .chk{width:15px; height:15px; border-radius:50%; border:1.5px solid var(--line); flex:none; display:flex; align-items:center; justify-content:center; transition:all .15s ease;}
  .checklist li .chk svg{width:9px; height:9px; opacity:0; transform:scale(0.6); transition:all .15s ease;}
  .checklist li.met{color:var(--ok-600);}
  .checklist li.met .chk{background:var(--ok-600); border-color:var(--ok-600);}
  .checklist li.met .chk svg{opacity:1; transform:scale(1);}

  .btn-submit{
    width:100%; padding:13px; font-size:15px; font-weight:600; border:none; border-radius:9px; cursor:pointer;
    background:linear-gradient(180deg, var(--gold-400), var(--gold-500)); color:#251B08;
    box-shadow:0 1px 0 rgba(255,255,255,0.35) inset, 0 10px 24px rgba(198,161,91,0.28);
    display:flex; align-items:center; justify-content:center; gap:8px;
    transition:transform .15s ease;
  }
  .btn-submit:hover{transform:translateY(-1px);}
  .btn-submit:disabled{opacity:0.55; cursor:not-allowed; transform:none;}
  .spinner{width:15px; height:15px; border-radius:50%; border:2px solid rgba(37,27,8,0.3); border-top-color:#251B08; animation:spin .7s linear infinite; display:none;}
  .btn-submit.loading .spinner{display:inline-block;}
  @keyframes spin{to{transform:rotate(360deg);}}

  .logout-row{text-align:center; margin-top:18px;}
  .logout-row button{
    background:none; border:none; color:#8896AA; font-size:12.5px; cursor:pointer; text-decoration:underline;
    font-family:'Inter',sans-serif;
  }
  .logout-row button:hover{color:var(--navy-900);}

  @media (max-width:480px){ .card{padding:32px 24px;} }
</style>
</head>
<body>
<div class="card">
  <div class="brand">
    <svg class="brand-mark" viewBox="0 0 48 48" fill="none">
      <path d="M24 3L42 10V22C42 33 34.6 41.6 24 45C13.4 41.6 6 33 6 22V10L24 3Z" fill="#0F2547" stroke="#C6A15B" stroke-width="1.5"/>
      <circle cx="24" cy="23" r="8" fill="none" stroke="#C6A15B" stroke-width="1.2"/>
      <path d="M24 17V23L28 26" stroke="#D8B876" stroke-width="1.4" stroke-linecap="round"/>
    </svg>
    <span>VMIS &middot; PRO5</span>
  </div>

  <h1>Set a new password</h1>
  <p class="lead">Your account is still using a temporary password. Choose a new one to continue to the dashboard.</p>

  @if (session('status'))
    <div class="banner banner-status">
      <svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.6"/><path d="M12 8V13" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/><circle cx="12" cy="16" r="1" fill="currentColor"/></svg>
      <span>{{ session('status') }}</span>
    </div>
  @endif

  @if ($errors->any())
    <div class="banner banner-error">
      <svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.6"/><path d="M12 8V13" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/><circle cx="12" cy="16" r="1" fill="currentColor"/></svg>
      <span>{{ $errors->first() }}</span>
    </div>
  @endif

  <form method="POST" action="{{ route('password.update') }}" id="pwForm" novalidate>
    @csrf

    <div class="field">
      <label for="current_password">Current (temporary) password</label>
      <div class="field-input">
        <input type="password" id="current_password" name="current_password"
               class="{{ $errors->has('current_password') ? 'is-invalid' : '' }}"
               autocomplete="current-password" required>
        <button type="button" class="toggle-pw" data-toggle-target="current_password" aria-label="Show password">
          <svg viewBox="0 0 24 24" fill="none"><path d="M2 12C2 12 5.5 5.5 12 5.5C18.5 5.5 22 12 22 12C22 12 18.5 18.5 12 18.5C5.5 18.5 2 12 2 12Z" stroke="currentColor" stroke-width="1.6"/><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.6"/></svg>
        </button>
      </div>
      @error('current_password')<div class="field-error">{{ $message }}</div>@enderror
    </div>

    <div class="field">
      <label for="password">New password</label>
      <div class="field-input">
        <input type="password" id="password" name="password"
               class="{{ $errors->has('password') ? 'is-invalid' : '' }}"
               autocomplete="new-password" required>
        <button type="button" class="toggle-pw" data-toggle-target="password" aria-label="Show password">
          <svg viewBox="0 0 24 24" fill="none"><path d="M2 12C2 12 5.5 5.5 12 5.5C18.5 5.5 22 12 22 12C22 12 18.5 18.5 12 18.5C5.5 18.5 2 12 2 12Z" stroke="currentColor" stroke-width="1.6"/><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.6"/></svg>
        </button>
      </div>
      @error('password')<div class="field-error">{{ $message }}</div>@enderror
    </div>

    <ul class="checklist" id="checklist">
      <li data-rule="length"><span class="chk"><svg viewBox="0 0 24 24" fill="none"><path d="M4 12L9 17L20 6" stroke="#fff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></svg></span>At least 8 characters</li>
      <li data-rule="case"><span class="chk"><svg viewBox="0 0 24 24" fill="none"><path d="M4 12L9 17L20 6" stroke="#fff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></svg></span>Upper &amp; lower case letters</li>
      <li data-rule="number"><span class="chk"><svg viewBox="0 0 24 24" fill="none"><path d="M4 12L9 17L20 6" stroke="#fff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></svg></span>At least one number</li>
      <li data-rule="symbol"><span class="chk"><svg viewBox="0 0 24 24" fill="none"><path d="M4 12L9 17L20 6" stroke="#fff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></svg></span>At least one symbol (!@#$…)</li>
    </ul>

    <div class="field">
      <label for="password_confirmation">Confirm new password</label>
      <div class="field-input">
        <input type="password" id="password_confirmation" name="password_confirmation"
               autocomplete="new-password" required>
        <button type="button" class="toggle-pw" data-toggle-target="password_confirmation" aria-label="Show password">
          <svg viewBox="0 0 24 24" fill="none"><path d="M2 12C2 12 5.5 5.5 12 5.5C18.5 5.5 22 12 22 12C22 12 18.5 18.5 12 18.5C5.5 18.5 2 12 2 12Z" stroke="currentColor" stroke-width="1.6"/><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.6"/></svg>
        </button>
      </div>
      <div class="field-error" id="matchError" style="display:none;">Passwords do not match.</div>
    </div>

    <button type="submit" class="btn-submit" id="pwSubmit">
      <span class="spinner"></span>
      <span class="btn-label">Update password &amp; continue</span>
    </button>
  </form>

  <div class="logout-row">
    <form id="logout-form" action="{{ route('logout') }}" method="POST" style="display:none;">@csrf</form>
    <button type="button" onclick="document.getElementById('logout-form').submit();">Log out instead</button>
  </div>
</div>

<script>
  document.querySelectorAll('[data-toggle-target]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var input = document.getElementById(btn.dataset.toggleTarget);
      input.type = input.type === 'password' ? 'text' : 'password';
    });
  });

  var pwInput = document.getElementById('password');
  var confirmInput = document.getElementById('password_confirmation');
  var matchError = document.getElementById('matchError');
  var rules = {
    length: function (v) { return v.length >= 8; },
    case:   function (v) { return /[a-z]/.test(v) && /[A-Z]/.test(v); },
    number: function (v) { return /[0-9]/.test(v); },
    symbol: function (v) { return /[^A-Za-z0-9]/.test(v); }
  };

  function updateChecklist() {
    var val = pwInput.value;
    Object.keys(rules).forEach(function (key) {
      var li = document.querySelector('#checklist li[data-rule="' + key + '"]');
      li.classList.toggle('met', rules[key](val));
    });
  }
  function checkMatch() {
    if (confirmInput.value.length === 0) { matchError.style.display = 'none'; return; }
    matchError.style.display = (confirmInput.value !== pwInput.value) ? 'block' : 'none';
  }

  pwInput.addEventListener('input', function () { updateChecklist(); checkMatch(); });
  confirmInput.addEventListener('input', checkMatch);

  document.getElementById('pwForm').addEventListener('submit', function () {
    var btn = document.getElementById('pwSubmit');
    btn.classList.add('loading');
    btn.disabled = true;
    btn.querySelector('.btn-label').textContent = 'Updating…';
  });
</script>
</body>
</html>
