@if(session('success'))<div class="alert success" role="status">{{ session('success') }}</div>@endif
@if(session('notice'))<div class="alert notice" role="status">{{ session('notice') }}</div>@endif
@if($errors->any())<div class="alert error" role="alert"><strong>Confira os dados informados:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
