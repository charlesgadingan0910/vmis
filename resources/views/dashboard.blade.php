@extends('layout.master')

@section('title')
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>VMIS | Dashboard</title>
<link href="{{ asset('dist/img/kasurog.png') }}" rel="icon">
@endsection

@section('css')
<style>
  .welcome-banner{
    background:linear-gradient(135deg, #1e293b, #0f172a);
    border-radius:14px; padding:24px 28px; color:#fff; margin-bottom:24px;
    display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:14px;
  }
  .welcome-banner h4{margin:0; font-weight:700; letter-spacing:-0.3px;}
  .welcome-banner p{margin:4px 0 0; color:#94a3b8; font-size:13.5px;}
  .welcome-banner .badge-role{
    background:rgba(59,130,246,0.15); color:#60a5fa; border:1px solid rgba(59,130,246,0.3);
    font-size:11px; font-weight:700; letter-spacing:0.05em; padding:5px 10px; border-radius:20px;
  }
</style>
@endsection

@section('nav-title', 'VMIS | Dashboard')

@section('nav-actions')

@endsection

@section('content')
<section class="content pt-3">
    <div class="container-fluid">

        <div class="welcome-banner">
            <div>
                <h4>Welcome back, {{ trim((auth()->user()->rank ? auth()->user()->rank.' ' : '').auth()->user()->firstname.' '.auth()->user()->lastname) }}</h4>
                <p>Here's what's happening with the PRO5 fleet today.</p>
            </div>
            <span class="badge-role">{{ strtoupper(auth()->user()->account_type) }}</span>
        </div>

        {{-- Fleet summary cards go here next — wire these to real counts once the vehicles table exists. --}}

    </div>
</section>
@endsection

@section('script')
<script>
  document.addEventListener('DOMContentLoaded', function () {
    @if (session('success'))
      toastr.success(@json(session('success')));
    @endif
    @if (session('error'))
      toastr.error(@json(session('error')));
    @endif
  });
</script>
@endsection
