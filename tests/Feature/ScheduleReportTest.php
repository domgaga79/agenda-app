<?php
namespace Tests\Feature;

use App\Models\{Company,User,Appointment};
use App\Services\ScheduleReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\{Carbon,Str};
use Tests\TestCase;

class ScheduleReportTest extends TestCase
{
    use RefreshDatabase;
    private Company $company;
    private User $owner;
    private User $dentist;

    protected function setUp():void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-10-01 01:00:00','UTC'));
        $this->company=Company::create(['name'=>'Clínica teste','slug'=>'reports','booking_timezone'=>'America/Sao_Paulo']);
        $this->owner=$this->user('owner');$this->dentist=$this->user('professional');$this->actingAs($this->owner);
    }
    private function user(string $role,?Company $company=null):User
    {
        return User::create(['company_id'=>($company??$this->company)->id,'name'=>'Usuário '.$role,'email'=>Str::uuid().'@example.test','password'=>'OnlyTest-2026','role'=>$role,'active'=>true]);
    }
    private function appointment(string $start,string $status='scheduled',?User $dentist=null):Appointment
    {
        $dentist??=$this->dentist;
        return Appointment::create(['company_id'=>$dentist->company_id,'professional_id'=>$dentist->id,'created_by'=>$this->owner->id,'client_name'=>'Nome privado do paciente','client_phone'=>'75999991234','notes'=>'Observação privada','service'=>'Procedimento privado','starts_at'=>$start,'ends_at'=>Carbon::parse($start)->addMinutes(30),'status'=>$status]);
    }
    private function filters(array $changes=[]):array {return array_replace(['from'=>'2026-09-01','to'=>'2026-09-30'],$changes);}
    public function test_month_defaults_follow_clinic_timezone_and_exclude_next_midnight():void
    {
        $this->appointment('2026-09-01 00:00');$this->appointment('2026-09-30 23:59','confirmed');$this->appointment('2026-10-01 00:00');$this->appointment('2026-08-31 23:59');
        $this->get(route('reports.schedule'))->assertOk()->assertHeader('Cache-Control','no-store, private')->assertViewHas('from','2026-09-01')->assertViewHas('to','2026-09-30')->assertViewHas('report',fn($r)=>$r['total']===2&&count($r['days'])===2)->assertDontSee('Nome privado')->assertDontSee('Observação privada')->assertDontSee('Procedimento privado');
    }
    public function test_status_totals_absence_denominator_and_professional_filter():void
    {
        foreach(['scheduled','confirmed','completed','completed','completed','no_show','cancelled'] as $n=>$status)$this->appointment('2026-09-10 '.sprintf('%02d:00',8+$n),$status);
        $other=$this->user('professional');$this->appointment('2026-09-10 15:00','no_show',$other);
        $this->get(route('reports.schedule',$this->filters(['professional_id'=>$this->dentist->id])))->assertOk()->assertViewHas('report',fn($r)=>$r['total']===7&&$r['finished']===4&&$r['absenceRate']===25.0&&$r['totals']['cancelled']===1&&count($r['dentists'])===1)->assertSee('25,0%');
        $this->get(route('reports.schedule',$this->filters()))->assertOk()->assertViewHas('report',fn($r)=>$r['total']===8&&count($r['dentists'])===2);
    }
    public function test_empty_state_no_undefined_rate_and_no_table_header():void
    {
        $this->get(route('reports.schedule',$this->filters()))->assertOk()->assertViewHas('report',fn($r)=>$r['total']===0&&$r['absenceRate']===null)->assertSee('Nenhum atendimento encontrado')->assertDontSee('<th>Dentista</th>',false);
        $this->appointment('2026-09-01 10:00');$this->get(route('reports.schedule',$this->filters()))->assertViewHas('report',fn($r)=>$r['absenceRate']===null);
    }
    public function test_tenant_and_professional_scope_on_page_and_export():void
    {
        $this->appointment('2026-09-01 10:00');$peer=$this->user('professional');$peer->update(['name'=>'Dentista restrito']);$this->appointment('2026-09-01 11:00','completed',$peer);
        $company=Company::create(['name'=>'Clínica externa','slug'=>'reports-other']);$external=$this->user('professional',$company);$external->update(['name'=>'Dentista externo']);$this->appointment('2026-09-01 12:00','completed',$external);
        $this->get(route('reports.schedule',$this->filters()))->assertOk()->assertViewHas('report',fn($r)=>$r['total']===2)->assertDontSee('Dentista externo');
        foreach(['reports.schedule','reports.schedule.export'] as $route)$this->get(route($route,$this->filters(['professional_id'=>$external->id])))->assertNotFound();
        $this->flushSession();$this->actingAs($this->dentist);
        $this->get(route('reports.schedule',$this->filters()))->assertOk()->assertViewHas('report',fn($r)=>$r['total']===1)->assertDontSee('Dentista restrito')->assertDontSee('Dentista externo');
        $csv=$this->get(route('reports.schedule.export',$this->filters()))->assertOk()->streamedContent();$this->assertStringNotContainsString('Dentista restrito',$csv);$this->assertStringNotContainsString('Dentista externo',$csv);
        foreach(['reports.schedule','reports.schedule.export'] as $route)$this->get(route($route,$this->filters(['professional_id'=>$peer->id])))->assertNotFound();
        $this->flushSession();$this->actingAs($this->user('reception'))->get(route('reports.schedule',$this->filters()))->assertOk()->assertViewHas('report',fn($r)=>$r['total']===2);
    }
    public function test_csv_matches_counts_is_private_and_escapes_spreadsheet_formulas():void
    {
        $this->dentist->update(['name'=>'=1+1']);$this->appointment('2026-09-01 10:00','completed');$this->appointment('2026-09-01 11:00','no_show');$this->appointment('2026-09-02 10:00','cancelled');
        $response=$this->get(route('reports.schedule.export',$this->filters()))->assertOk()->assertHeader('Cache-Control','no-store, private')->assertHeader('X-Content-Type-Options','nosniff')->assertDownload('agenda-resumo-2026-09-01-a-2026-09-30.csv');
        $csv=$response->streamedContent();$this->assertStringStartsWith("\xEF\xBB\xBF",$csv);$this->assertStringNotContainsString('Nome privado',$csv);$this->assertStringNotContainsString('75999991234',$csv);$this->assertStringNotContainsString('Procedimento privado',$csv);
        $lines=explode("\n",trim(substr($csv,3)));$this->assertCount(3,$lines);$row=str_getcsv($lines[1],';','"','');$this->assertSame(['01/09/2026',"'=1+1",'0','0','1','0','1','2'],$row);
        foreach(['+SUM(A1)','-1+1','@test',"\t=1+1","\r\n=1+1"] as $name)$this->assertSame("'".$name,app(ScheduleReport::class)->csvText($name));
    }
    public function test_date_validation_limits_and_archived_professionals():void
    {
        foreach([['from'=>'2026-09-31'],['to'=>'2026-08-31'],['from'=>'2025-01-01','to'=>'2026-01-03'],['from'=>'1800-01-01']] as $bad)foreach(['reports.schedule','reports.schedule.export'] as $route)$this->get(route($route,$this->filters($bad)))->assertSessionHasErrors();
        $this->appointment('2026-09-01 10:00');$this->dentist->update(['active'=>false]);$this->get(route('reports.schedule',$this->filters(['professional_id'=>$this->dentist->id])))->assertOk()->assertSee('(inativo)')->assertViewHas('report',fn($r)=>$r['total']===1);
    }
}
