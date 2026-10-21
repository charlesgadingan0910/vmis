<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{{ $meta['title'] }} | VMIS</title>
  <link href="{{ asset('dist/img/kasurog.png') }}" rel="icon">
  <style>
    * { box-sizing: border-box; }
    body { font-family: "Segoe UI", Arial, sans-serif; color: #0f172a; margin: 0; padding: 24px; background: #fff; font-size: 12px; }
    .toolbar { display: flex; gap: 10px; margin-bottom: 18px; }
    .toolbar button, .toolbar a { font: inherit; font-weight: 700; font-size: 13px; padding: 8px 16px; border-radius: 8px; border: 1px solid #cbd5e1; background: #f8fafc; color: #0f172a; cursor: pointer; text-decoration: none; }
    .toolbar .primary { background: #1d4ed8; border-color: #1d4ed8; color: #fff; }
    .org { font-size: 11px; text-transform: uppercase; letter-spacing: .06em; color: #64748b; font-weight: 700; }
    h1 { font-size: 20px; margin: 4px 0 10px; }
    .meta { font-size: 11.5px; color: #475569; margin-bottom: 16px; line-height: 1.6; }
    table { width: 100%; border-collapse: collapse; }
    th { background: #f1f5f9; text-align: left; font-size: 10.5px; text-transform: uppercase; letter-spacing: .03em; color: #475569; padding: 7px 8px; border: 1px solid #cbd5e1; }
    td { padding: 6px 8px; border: 1px solid #e2e8f0; vertical-align: top; }
    td.num, th.num { text-align: right; white-space: nowrap; }
    tr.total td { font-weight: 800; background: #f8fafc; }
    tbody tr:nth-child(even) td { background: #fcfdff; }
    .empty { padding: 28px; text-align: center; color: #64748b; border: 1px dashed #cbd5e1; border-radius: 10px; }
    .footer { margin-top: 18px; font-size: 10.5px; color: #94a3b8; }
    @page { size: A4 landscape; margin: 12mm; }
    @media print {
      body { padding: 0; }
      .toolbar { display: none; }
      thead { display: table-header-group; }
      tr { page-break-inside: avoid; }
    }
  </style>
</head>
<body>
  <div class="toolbar">
    <button type="button" class="primary" onclick="window.print()">Print / Save as PDF</button>
    <a href="{{ route('reports.index') }}">Back to Reports</a>
  </div>

  <div class="org">Police Regional Office 5 &middot; Vehicle Management Information System</div>
  <h1>{{ $meta['title'] }}</h1>
  <div class="meta">
    <div>Generated {{ $meta['generated_at']->format('M d, Y h:i A') }} by {{ $meta['generated_by'] }}</div>
    <div>Scope: {{ implode(' | ', $meta['scope']) }}</div>
    <div>{{ number_format(count($rows)) }} {{ count($rows) === 1 ? 'row' : 'rows' }}</div>
  </div>

  @if(count($rows) === 0)
    <div class="empty">No records match these filters.</div>
  @else
    <table>
      <thead>
        <tr>
          @foreach($columns as $column)
            <th class="{{ $column['type'] === 'text' ? '' : 'num' }}">{{ $column['label'] }}</th>
          @endforeach
        </tr>
      </thead>
      <tbody>
        @foreach($rows as $row)
          <tr>
            @foreach($row as $index => $cell)
              <td class="{{ $columns[$index]['type'] === 'text' ? '' : 'num' }}">{{ $cell }}</td>
            @endforeach
          </tr>
        @endforeach
        @if($totals)
          <tr class="total">
            @foreach($totals as $index => $cell)
              <td class="{{ $columns[$index]['type'] === 'text' ? '' : 'num' }}">{{ $cell === '—' ? '' : $cell }}</td>
            @endforeach
          </tr>
        @endif
      </tbody>
    </table>
  @endif

  <div class="footer">System-generated report. Amounts are in Philippine pesos (PHP).</div>
</body>
</html>
