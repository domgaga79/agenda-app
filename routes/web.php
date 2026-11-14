<?php

use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\TeamController;
use Illuminate\Support\Facades\Route;

Route::middleware(\App\Http\Middleware\AgendaLocale::class)->group(function(){
Route::redirect('/', '/dashboard');
Route::get('/confirmar/{token}',[\App\Http\Controllers\PublicConfirmationController::class,'show'])->where('token','[a-f0-9]{64}')->middleware('throttle:public-view')->name('confirmation.show');
Route::post('/confirmar/{token}',[\App\Http\Controllers\PublicConfirmationController::class,'respond'])->where('token','[a-f0-9]{64}')->middleware('throttle:public-booking')->name('confirmation.respond');
Route::get('/agendar/{slug}', [\App\Http\Controllers\PublicBookingController::class,'index'])->middleware('throttle:public-view')->name('booking.index');
Route::post('/agendar/{slug}', [\App\Http\Controllers\PublicBookingController::class,'store'])->middleware('throttle:public-booking')->name('booking.store');
Route::get('/agendar/{slug}/concluido', [\App\Http\Controllers\PublicBookingController::class,'done'])->name('booking.done');
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:20,1')->name('login.attempt');
});
Route::middleware(['auth','auth.session','active'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/alterar-senha', [AuthController::class, 'showChangePassword'])->name('password.change');
    Route::put('/alterar-senha', [AuthController::class, 'updatePassword'])->middleware('throttle:10,1')->name('password.update');

    Route::middleware('password.changed')->group(function () {
        Route::get('/agenda/lista-diaria',[\App\Http\Controllers\DailyScheduleController::class,'index'])->name('appointments.daily');
        Route::get('/financeiro/contas-a-receber',[\App\Http\Controllers\ReceivablesController::class,'index'])->name('finance.receivables');
        Route::get('/prontuarios/{id}/imprimir',[\App\Http\Controllers\ClinicalPrintController::class,'preview'])->whereNumber('id')->middleware('throttle:30,1')->name('clinical.print');
        Route::get('/ajuda',[\App\Http\Controllers\HelpController::class,'index'])->name('help.index');
        Route::get('/ajuda/manual',[\App\Http\Controllers\HelpController::class,'download'])->name('help.download');
        Route::get('/prontuarios/{id}/anexos',[\App\Http\Controllers\ClinicalAttachmentController::class,'listing'])->whereNumber('id')->name('attachments.index');
        Route::post('/prontuarios/{id}/anexos',[\App\Http\Controllers\ClinicalAttachmentController::class,'store'])->whereNumber('id')->middleware('throttle:20,1')->name('attachments.store');
        Route::get('/prontuarios/{id}/anexos/{attachment}/baixar',[\App\Http\Controllers\ClinicalAttachmentController::class,'download'])->whereNumber(['id','attachment'])->name('attachments.download');
        Route::post('/prontuarios/{id}/anexos/{attachment}/desconsiderar',[\App\Http\Controllers\ClinicalAttachmentController::class,'disregard'])->whereNumber(['id','attachment'])->name('attachments.disregard');
        Route::get('/relatorios/agenda',[\App\Http\Controllers\ScheduleReportController::class,'index'])->name('reports.schedule');
        Route::get('/relatorios/agenda.csv',[\App\Http\Controllers\ScheduleReportController::class,'export'])->middleware('throttle:20,1')->name('reports.schedule.export');
        Route::get('/retornos',[\App\Http\Controllers\FollowupController::class,'index'])->name('followups.index');
        Route::get('/retornos/nova',[\App\Http\Controllers\FollowupController::class,'create'])->name('followups.create');
        Route::post('/retornos',[\App\Http\Controllers\FollowupController::class,'store'])->name('followups.store');
        Route::get('/retornos/{id}',[\App\Http\Controllers\FollowupController::class,'show'])->whereNumber('id')->name('followups.show');
        Route::get('/retornos/{id}/editar',[\App\Http\Controllers\FollowupController::class,'edit'])->whereNumber('id')->name('followups.edit');
        Route::put('/retornos/{id}',[\App\Http\Controllers\FollowupController::class,'update'])->whereNumber('id')->name('followups.update');
        Route::post('/retornos/{id}/contato',[\App\Http\Controllers\FollowupController::class,'contact'])->whereNumber('id')->name('followups.contact');
        Route::post('/retornos/{id}/situacao',[\App\Http\Controllers\FollowupController::class,'state'])->whereNumber('id')->name('followups.state');
        Route::get('/despesas',[\App\Http\Controllers\ExpenseController::class,'index'])->name('expenses.index');
        Route::get('/despesas/nova',[\App\Http\Controllers\ExpenseController::class,'create'])->name('expenses.create');
        Route::post('/despesas',[\App\Http\Controllers\ExpenseController::class,'store'])->name('expenses.store');
        Route::get('/despesas/{id}',[\App\Http\Controllers\ExpenseController::class,'show'])->whereNumber('id')->name('expenses.show');
        Route::get('/despesas/{id}/editar',[\App\Http\Controllers\ExpenseController::class,'edit'])->whereNumber('id')->name('expenses.edit');
        Route::put('/despesas/{id}',[\App\Http\Controllers\ExpenseController::class,'update'])->whereNumber('id')->name('expenses.update');
        Route::post('/despesas/{id}/pagar',[\App\Http\Controllers\ExpenseController::class,'pay'])->whereNumber('id')->name('expenses.pay');
        Route::post('/despesas/{id}/cancelar',[\App\Http\Controllers\ExpenseController::class,'cancel'])->whereNumber('id')->name('expenses.cancel');
        Route::post('/despesas/pagamentos/{id}/estornar',[\App\Http\Controllers\ExpenseController::class,'reverse'])->whereNumber('id')->name('expenses.reverse');
        Route::get('/financeiro/relatorio',[\App\Http\Controllers\FinancialReportController::class,'index'])->name('finance.report');
        Route::get('/lembretes',[\App\Http\Controllers\ReminderController::class,'index'])->name('reminders.index');
        Route::get('/agenda/{id}/lembrete',[\App\Http\Controllers\ReminderController::class,'show'])->whereNumber('id')->name('reminders.show');
        Route::post('/agenda/{id}/lembrete',[\App\Http\Controllers\ReminderController::class,'issue'])->whereNumber('id')->name('reminders.issue');
        Route::post('/agenda/{id}/lembrete/enviado',[\App\Http\Controllers\ReminderController::class,'sent'])->whereNumber('id')->name('reminders.sent');
        Route::post('/agenda/{id}/lembrete/desativar',[\App\Http\Controllers\ReminderController::class,'revoke'])->whereNumber('id')->name('reminders.revoke');
        Route::get('/financeiro',[\App\Http\Controllers\FinanceController::class,'index'])->name('finance.index');
        Route::get('/financeiro/novo',[\App\Http\Controllers\FinanceController::class,'create'])->name('finance.create');
        Route::post('/financeiro',[\App\Http\Controllers\FinanceController::class,'store'])->name('finance.store');
        Route::get('/financeiro/orcamentos/{id}',[\App\Http\Controllers\FinanceController::class,'show'])->whereNumber('id')->name('finance.show');
        Route::put('/financeiro/orcamentos/{id}',[\App\Http\Controllers\FinanceController::class,'update'])->whereNumber('id')->name('finance.update');
        Route::post('/financeiro/orcamentos/{id}/aprovar',[\App\Http\Controllers\FinanceController::class,'approve'])->whereNumber('id')->name('finance.approve');
        Route::post('/financeiro/orcamentos/{id}/cancelar',[\App\Http\Controllers\FinanceController::class,'cancel'])->whereNumber('id')->name('finance.cancel');
        Route::get('/financeiro/orcamentos/{id}/imprimir',[\App\Http\Controllers\FinanceController::class,'printQuote'])->whereNumber('id')->name('finance.print');
        Route::post('/financeiro/parcelas/{id}/pagar',[\App\Http\Controllers\FinanceController::class,'pay'])->whereNumber('id')->name('finance.pay');
        Route::post('/financeiro/pagamentos/{id}/estornar',[\App\Http\Controllers\FinanceController::class,'reverse'])->whereNumber('id')->name('finance.reverse');
        Route::get('/financeiro/pagamentos/{id}/comprovante',[\App\Http\Controllers\FinanceController::class,'receipt'])->whereNumber('id')->name('finance.receipt');
        Route::post('/prontuarios/{id}/plano/orcamento',[\App\Http\Controllers\TreatmentFinanceController::class,'export'])->whereNumber('id')->name('treatment.export');
        Route::get('/prontuarios/{id}/odontograma',[\App\Http\Controllers\OdontogramController::class,'editChart'])->whereNumber('id')->name('odontogram.edit');
        Route::post('/prontuarios/{id}/odontograma',[\App\Http\Controllers\OdontogramController::class,'saveChart'])->whereNumber('id')->name('odontogram.save');
        Route::get('/prontuarios/{id}/odontograma/versoes/{version}',[\App\Http\Controllers\OdontogramController::class,'version'])->whereNumber(['id','version'])->name('odontogram.version');
        Route::get('/prontuarios/{id}/plano',[\App\Http\Controllers\TreatmentPlanController::class,'editPlan'])->whereNumber('id')->name('treatment.edit');
        Route::post('/prontuarios/{id}/plano',[\App\Http\Controllers\TreatmentPlanController::class,'savePlan'])->whereNumber('id')->name('treatment.save');
        Route::get('/prontuarios',[\App\Http\Controllers\ClinicalController::class,'index'])->name('clinical.index');
        Route::get('/prontuarios/{id}',[\App\Http\Controllers\ClinicalController::class,'show'])->whereNumber('id')->name('clinical.show');
        Route::post('/prontuarios/{id}',[\App\Http\Controllers\ClinicalController::class,'store'])->whereNumber('id')->name('clinical.store');
        Route::get('/auditoria-clinica',[\App\Http\Controllers\ClinicalController::class,'auditLog'])->name('clinical.audit');
        Route::get('/disponibilidade', [\App\Http\Controllers\AvailabilityController::class,'index'])->name('availability.index');
        foreach (['settings'=>'configuracao','services'=>'procedimentos'] as $method=>$path) Route::put('/disponibilidade/'.$path,[\App\Http\Controllers\AvailabilityController::class,$method])->name('availability.'.$method);
        Route::post('/disponibilidade/expediente',[\App\Http\Controllers\AvailabilityController::class,'period'])->name('availability.period');
        Route::delete('/disponibilidade/expediente/{id}',[\App\Http\Controllers\AvailabilityController::class,'removePeriod'])->name('availability.removePeriod');
        Route::post('/disponibilidade/bloqueios',[\App\Http\Controllers\AvailabilityController::class,'block'])->name('availability.block');
        Route::delete('/disponibilidade/bloqueios/{id}',[\App\Http\Controllers\AvailabilityController::class,'removeBlock'])->name('availability.removeBlock');
        foreach (['customers'=>'clientes','services'=>'servicos'] as $name=>$path) {
            Route::get('/'.$path, [\App\Http\Controllers\CatalogController::class,'index'])->name($name.'.index');
            Route::get('/'.$path.'/novo', [\App\Http\Controllers\CatalogController::class,'create'])->name($name.'.create');
            Route::post('/'.$path, [\App\Http\Controllers\CatalogController::class,'store'])->name($name.'.store');
            Route::get('/'.$path.'/{id}/editar', [\App\Http\Controllers\CatalogController::class,'edit'])->name($name.'.edit');
            Route::put('/'.$path.'/{id}', [\App\Http\Controllers\CatalogController::class,'update'])->name($name.'.update');
        }
        Route::get('/clientes/{id}/historico', [\App\Http\Controllers\CatalogController::class,'show'])->name('customers.show');

        Route::get('/dashboard', DashboardController::class)->name('dashboard');
        Route::get('/empresa', [CompanyController::class, 'edit'])->name('company.edit');
        Route::put('/empresa', [CompanyController::class, 'update'])->name('company.update');
        Route::get('/equipe', [TeamController::class, 'index'])->name('team.index');
        Route::get('/equipe/novo', [TeamController::class, 'create'])->name('team.create');
        Route::post('/equipe', [TeamController::class, 'store'])->name('team.store');
        Route::get('/equipe/{id}/editar', [TeamController::class, 'edit'])->name('team.edit');
        Route::put('/equipe/{id}', [TeamController::class, 'update'])->name('team.update');
        Route::get('/agenda', [AppointmentController::class, 'index'])->name('appointments.index');
        Route::get('/agenda/novo', [AppointmentController::class, 'create'])->name('appointments.create');
        Route::post('/agenda', [AppointmentController::class, 'store'])->name('appointments.store');
        Route::get('/agenda/{id}/editar', [AppointmentController::class, 'edit'])->name('appointments.edit');
        Route::put('/agenda/{id}', [AppointmentController::class, 'update'])->name('appointments.update');
        Route::delete('/agenda/{id}', [AppointmentController::class, 'destroy'])->name('appointments.destroy');
    });
});

});
