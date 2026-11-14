@extends('finance.print-layout')
@section('title','Comprovante interno #'.$payment->id)
@section('document')
@php use App\Support\Finance; $quote=$payment->installment->quote; @endphp
<h1>Comprovante interno #{{ $payment->id }}</h1>@if($payment->reversed_at)<div class="alert danger"><strong>ESTORNADO — SEM VALIDADE COMO PAGAMENTO</strong><p>{{ $payment->reversal_reason }}</p></div>@endif
<p>Paciente: <strong>{{ $quote->customer_name }}</strong></p><p>Orçamento #{{ $quote->id }} · {{ $payment->installment->is_deposit?'Entrada':'Parcela '.$payment->installment->sequence }}</p><div class="finance-amounts"><span>Valor registrado <strong>{{ Finance::money($payment->amount_cents) }}</strong></span><span>Data <strong>{{ $payment->paid_on->format('d/m/Y') }}</strong></span><span>Forma <strong>{{ Finance::METHODS[$payment->method] }}</strong></span></div><p>Registrado por {{ $payment->creator->name }}.</p><p>Este comprovante corresponde somente ao lançamento acima. Não comprova a quitação integral do orçamento.</p>
@endsection
