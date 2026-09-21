{{-- Slip gaji PDF; isinya di payslips/paper, lihat App\Support\Payroll\PayslipPdf. --}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ __('payroll.slip.title', [], 'id') }} — {{ $paper['name'] }}</title>
    <style>
        @page { margin: 20mm; }
        body { margin: 0; }
    </style>
</head>
<body>
    @include('payslips.paper', ['paper' => $paper])
</body>
</html>
