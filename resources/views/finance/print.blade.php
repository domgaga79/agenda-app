@extends('finance.print-layout')
@section('title','Orçamento #'.$quote->id)
@section('document')
@php use App\Support\Finance; @endphp
<h1>Orçamento #{{ $quote->id }}</h1><p>Paciente: <strong>{{ $quote->customer_name }}</strong></p><p>Situação: {{ Finance::STATES[$quote->status] }}</p><p class="finance-description">{{ $quote->description }}</p><div class="finance-amounts"><span>Valor bruto <strong>{{ Finance::money($quote->gross_cents) }}</strong></span><span>Desconto <strong>{{ Finance::money($quote->discount_cents) }}</strong></span><span>Total <strong>{{ Finance::money($quote->total_cents) }}</strong></span></div>
@if($quote->approved_on)<p>Aprovação informada por {{ $quote->approved_by_name }} em {{ $quote->approved_on->format('d/m/Y') }}, por {{ Finance::CHANNELS[$quote->approval_channel] }}. Registro manual, sem assinatura digital.</p>@endif
@if($quote->status==='cancelled')<p><strong>CANCELADO</strong> · {{ $quote->cancel_reason }}</p>@endif
@if($quote->installments->isNotEmpty())<h2>Condições de pagamento</h2><table class="finance-table"><thead><tr><th>Parcela</th><th>Vencimento</th><th>Valor</th></tr></thead><tbody>@foreach($quote->installments as $i)<tr><td>{{ $i->is_deposit?'Entrada':$i->sequence }}</td><td>{{ $i->due_on->format('d/m/Y') }}</td><td>{{ Finance::money($i->amount_cents) }}</td></tr>@endforeach</tbody></table>@endif
@endsection
