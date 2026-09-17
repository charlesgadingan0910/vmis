@extends('layout.master')

@section('title')
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>VMIS | {{ $title }}</title>
<link href="{{ asset('dist/img/kasurog.png') }}" rel="icon">
@endsection

@section('nav-title', 'VMIS | ' . $title)

@section('content')
<section class="content pt-3">
    <div class="container-fluid">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-5">
                <div class="mx-auto mb-3 d-flex align-items-center justify-content-center"
                     style="width:64px;height:64px;border-radius:16px;background:linear-gradient(160deg,#1e293b,#0f172a);">
                    <i class="fas fa-tools" style="color:#C6A15B;font-size:24px;"></i>
                </div>
                <h4 class="font-weight-bold mb-2">{{ $title }}</h4>
                <p class="text-muted mb-0" style="max-width:420px;margin-inline:auto;">
                    This module is on the roadmap and isn't built yet. The menu link is live so the navigation
                    is already in place once it's ready.
                </p>
            </div>
        </div>
    </div>
</section>
@endsection
