<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>@yield('doc-title', 'Print') | VMIS</title>
  <link href="{{ asset('dist/img/kasurog.png') }}" rel="icon">
  <style>
    * { box-sizing: border-box; }
    body { font-family: "Segoe UI", Arial, sans-serif; color: #0f172a; margin: 0; padding: 24px; background: #e2e8f0; font-size: 12px; line-height: 1.4; }
    .toolbar { max-width: 210mm; margin: 0 auto 16px; display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
    .toolbar button, .toolbar a { font: inherit; font-weight: 700; font-size: 13px; padding: 8px 16px; border-radius: 8px; border: 1px solid #cbd5e1; background: #fff; color: #0f172a; cursor: pointer; text-decoration: none; }
    .toolbar .primary { background: #1d4ed8; border-color: #1d4ed8; color: #fff; }
    .toolbar .note { font-size: 11.5px; color: #64748b; }

    .page { width: 210mm; min-height: 297mm; margin: 0 auto 16px; padding: 14mm 14mm 12mm; background: #fff; box-shadow: 0 2px 12px rgba(15, 23, 42, .12); position: relative; }
    .letterhead { display: flex; align-items: center; justify-content: center; gap: 14px; text-align: center; border-bottom: 2px solid #0f172a; padding-bottom: 8px; margin-bottom: 10px; }
    .letterhead img { height: 54px; width: 54px; object-fit: contain; }
    .letterhead .l1 { font-size: 10.5px; letter-spacing: .04em; text-transform: uppercase; }
    .letterhead .l2 { font-size: 13px; font-weight: 800; text-transform: uppercase; }
    .form-title { text-align: center; font-size: 15px; font-weight: 800; text-transform: uppercase; letter-spacing: .03em; margin: 6px 0 10px; }
    .form-sub { text-align: center; font-size: 11px; color: #475569; margin-top: -6px; margin-bottom: 10px; }

    table.grid { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
    table.grid th, table.grid td { border: 1px solid #334155; padding: 4px 6px; vertical-align: top; text-align: left; }
    table.grid th { background: #f1f5f9; font-size: 10px; text-transform: uppercase; letter-spacing: .03em; font-weight: 700; }
    table.grid td.num, table.grid th.num { text-align: right; white-space: nowrap; }
    table.grid td.label { background: #f8fafc; font-weight: 700; width: 24%; font-size: 10.5px; }
    .part-title { font-size: 11px; font-weight: 800; text-transform: uppercase; background: #0f172a; color: #fff; padding: 3px 8px; margin: 10px 0 0; }
    .box { border: 1px solid #334155; padding: 6px 8px; min-height: 44px; margin-bottom: 10px; white-space: pre-wrap; }
    .box-label { font-size: 10px; font-weight: 700; text-transform: uppercase; color: #475569; }
    .checks { display: flex; flex-wrap: wrap; gap: 4px 18px; margin: 4px 0 8px; }
    .sign-row { display: flex; gap: 14px; margin-top: 18px; }
    .sign { flex: 1; text-align: center; }
    .sign .line { border-bottom: 1px solid #0f172a; min-height: 26px; padding-bottom: 2px; font-weight: 700; text-transform: uppercase; font-size: 11px; }
    .sign .cap { font-size: 10px; color: #475569; margin-top: 2px; }
    .two-col { column-count: 2; column-gap: 14px; }
    .sys { break-inside: avoid; margin-bottom: 8px; }
    .sys h4 { margin: 0; font-size: 10.5px; text-transform: uppercase; background: #e2e8f0; border: 1px solid #334155; border-bottom: none; padding: 2px 6px; }
    .sys table { width: 100%; border-collapse: collapse; font-size: 10.5px; }
    .sys td { border: 1px solid #334155; padding: 1px 5px; }
    .sys td.st { width: 34px; text-align: center; font-weight: 700; }
    .legend { font-size: 10px; margin-bottom: 8px; }
    .footer-note { position: absolute; left: 14mm; right: 14mm; bottom: 8mm; font-size: 9.5px; color: #94a3b8; display: flex; justify-content: space-between; }
    .qr { text-align: center; }
    .qr img { width: 38mm; height: 38mm; }

    @page { size: A4 portrait; margin: 0; }
    @media print {
      body { background: #fff; padding: 0; }
      .toolbar { display: none; }
      .page { margin: 0; box-shadow: none; page-break-after: always; }
      .page:last-child { page-break-after: auto; }
    }
  </style>
</head>
<body>
  <div class="toolbar">
    <button type="button" class="primary" onclick="window.print()">Print / Save as PDF</button>
    <a href="javascript:window.close()">Close</a>
    <span class="note">VMIS layout based on the fields stored in the system; check it against the official paper form before issuing.</span>
  </div>

  @yield('pages')
</body>
</html>
