@php($appointment = $appointment ?? null)
<div class="form-grid">
@if($catalogAccess)
<div class="field"><label for="customer_id">Cliente / paciente cadastrado</label><select id="customer_id" name="customer_id"><option value="">Preencher manualmente</option>@foreach($customers as $c)<option value="{{ $c->id }}" data-name="{{ $c->name }}" data-phone="{{ $c->phone }}" data-email="{{ $c->email }}" @selected((string)old('customer_id',$appointment?->customer_id)===(string)$c->id)>{{ $c->name }}{{ $c->active ? '' : ' (arquivado)' }}</option>@endforeach</select></div>
<div class="field"><label for="service_id">Serviço / procedimento cadastrado</label><select id="service_id" name="service_id"><option value="">Preencher manualmente</option>@foreach($services as $s)<option value="{{ $s->id }}" data-name="{{ $s->name }}" data-duration="{{ $s->duration_minutes }}" @selected((string)old('service_id',$appointment?->service_id)===(string)$s->id)>{{ $s->name }} · {{ $s->duration_minutes }} min</option>@endforeach</select></div>
<div class="field full"><small>Ao selecionar um cadastro, os dados são preenchidos automaticamente. A duração sugere o horário final, que pode ser ajustado. Para corrigir dados de um atendimento vinculado, escolha preenchimento manual.</small></div>
@endif
<div class="field"><label for="client_name">Cliente</label><input id="client_name" name="client_name" value="{{ old('client_name',$appointment?->client_name) }}" required></div>
<div class="field"><label for="professional_id">Profissional</label><select id="professional_id" name="professional_id" required>@foreach($professionals as $professional)<option value="{{ $professional->id }}" @selected((string)old('professional_id',$appointment?->professional_id)===(string)$professional->id)>{{ $professional->name }}</option>@endforeach</select></div>
<div class="field"><label for="client_phone">Telefone</label><input type="tel" id="client_phone" name="client_phone" value="{{ old('client_phone',$appointment?->client_phone) }}"></div>
<div class="field"><label for="client_email">E-mail</label><input type="email" id="client_email" name="client_email" value="{{ old('client_email',$appointment?->client_email) }}"></div>
<div class="field full"><label for="service">Serviço</label><input id="service" name="service" value="{{ old('service',$appointment?->service) }}" required></div>
<fieldset class="schedule-fields field full"><legend>Data e horário</legend><div class="schedule-pair"><div class="field datetime-field"><label for="starts_at">Início</label><input id="starts_at" type="datetime-local" name="starts_at" value="{{ old('starts_at',$appointment?->starts_at?->format('Y-m-d\TH:i') ?? ($initialStart ?? null)) }}" required></div>
<div class="field datetime-field"><label for="ends_at">Término</label><input id="ends_at" type="datetime-local" name="ends_at" value="{{ old('ends_at',$appointment?->ends_at?->format('Y-m-d\TH:i') ?? ($initialEnd ?? null)) }}" required></div>
</div><p class="schedule-help">Ajuste o término se o atendimento precisar de mais tempo.</p></fieldset><div class="field"><label for="status">Status</label><select id="status" name="status"><option value="scheduled" @selected(old('status',$appointment?->status ?? 'scheduled')==='scheduled')>Agendado</option><option value="confirmed" @selected(old('status',$appointment?->status)==='confirmed')>Confirmado</option><option value="completed" @selected(old('status',$appointment?->status)==='completed')>Concluído</option><option value="cancelled" @selected(old('status',$appointment?->status)==='cancelled')>Cancelado</option><option value="no_show" @selected(old('status',$appointment?->status)==='no_show')>Faltou</option></select></div>
<div class="field full"><label for="notes">Observações</label><textarea id="notes" name="notes">{{ old('notes',$appointment?->notes) }}</textarea></div>
</div>

@if($catalogAccess)<script src="{{ asset('js/appointment-catalogs.js') }}?v=4" defer></script>@endif

<script src="{{ asset('js/schedule-fields.js') }}?v=14" defer></script>
