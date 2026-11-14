<?php
namespace App\Support;
class Expenses {
 public const CATEGORIES=['supplies'=>'Materiais e insumos','lab'=>'Laboratório','rent'=>'Aluguel','utilities'=>'Água, energia e internet','team'=>'Equipe e prestadores','maintenance'=>'Manutenção','software'=>'Sistemas e serviços','marketing'=>'Divulgação','taxes'=>'Tributos e taxas','other'=>'Outras despesas'];
 public const STATES=['all'=>'Todas','open'=>'Em aberto','paid'=>'Pagas','cancelled'=>'Canceladas'];
 public const EVENTS=['created'=>'Despesa cadastrada','updated'=>'Despesa revisada','paid'=>'Pagamento registrado','reversed'=>'Pagamento estornado','cancelled'=>'Despesa cancelada'];
}
