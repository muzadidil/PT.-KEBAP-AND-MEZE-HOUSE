{{-- Pratinjau slip di formulir; kertas yang sama dengan PDF-nya. --}}
<div>
    <p class="pos__label" style="margin-bottom: .5rem">{{ __('payroll.preview') }}</p>

    <div style="overflow-x: auto">
        <div style="min-width: 520px; max-width: 680px; margin: 0 auto; padding: 32px 36px; background: #fff; border: 1px solid #d4dae0; box-shadow: 0 2px 6px rgba(28,39,51,.10), 0 16px 40px rgba(28,39,51,.12)">
            @include('payslips.paper', ['paper' => $paper])
        </div>
    </div>
</div>
