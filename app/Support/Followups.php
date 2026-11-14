<?php
namespace App\Support;
class Followups {
 public const KINDS=['waitlist'=>'Lista de espera','recall'=>'Retorno'];
 public const STATES=['pending'=>'Pendente','contacted'=>'Contato registrado','scheduled'=>'Agendado','closed'=>'Encerrado'];
 public const PERIODS=['any'=>'Qualquer horário','morning'=>'Manhã (antes de 12h)','afternoon'=>'Tarde (12h às 17h59)','evening'=>'Noite (a partir de 18h)'];
 public const EVENTS=['created'=>'Solicitação cadastrada','updated'=>'Preferências revisadas','contacted'=>'Contato registrado','scheduled'=>'Agendamento criado','closed'=>'Solicitação encerrada','reopened'=>'Solicitação reaberta'];
 public const CHANNELS=['whatsapp'=>'WhatsApp','phone'=>'Telefone','in_person'=>'Presencial','other'=>'Outro'];
}
