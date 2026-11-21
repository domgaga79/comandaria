<?php

/*
|--------------------------------------------------------------------------
| Status de pedido
|--------------------------------------------------------------------------
| Compatível com o ENUM atual do banco:
| pedidos.status ENUM('pendente','pago','pronto','cancelado')
*/
const STATUS_PENDENTE  = 'pendente';
const STATUS_PAGO      = 'pago';
const STATUS_PRONTO    = 'pronto';
const STATUS_CANCELADO = 'cancelado';

/*
|--------------------------------------------------------------------------
| Formas de pagamento
|--------------------------------------------------------------------------
| Compatível com pedidos.forma_pagamento.
*/
const PAGAMENTO_DINHEIRO = 'dinheiro';
const PAGAMENTO_PIX      = 'pix';
const PAGAMENTO_CARTAO   = 'cartao';
const PAGAMENTO_PRAZO    = 'prazo';
const PAGAMENTO_ANTECIPADO = 'antecipado';

/*
|--------------------------------------------------------------------------
| Controle de contas a prazo
|--------------------------------------------------------------------------
| Usado apenas quando pedidos.forma_pagamento = 'prazo'.
*/
const PRAZO_STATUS_ABERTO  = 'aberto';
const PRAZO_STATUS_QUITADO = 'quitado';

/*
|--------------------------------------------------------------------------
| Sessão de caixa
|--------------------------------------------------------------------------
*/
const CAIXA_STATUS_ABERTO  = 'aberto';
const CAIXA_STATUS_FECHADO = 'fechado';

const CAIXA_MOVIMENTO_PAGAMENTO_PEDIDO   = 'pagamento_pedido';
const CAIXA_MOVIMENTO_PAGAMENTO_ANTECIPADO = 'pagamento_antecipado';
const CAIXA_MOVIMENTO_VENDA_PRAZO        = 'venda_prazo';


/*
|--------------------------------------------------------------------------
| Tipos de usuário
|--------------------------------------------------------------------------
| Compatível com:
| usuarios.tipo ENUM('admin','atendente','cozinha','caixa')
*/
const USER_ADMIN     = 'admin';
const USER_ATENDENTE = 'atendente';
const USER_COZINHA   = 'cozinha';
const USER_CAIXA     = 'caixa';

/*
|--------------------------------------------------------------------------
| Status de usuário
|--------------------------------------------------------------------------
*/
const USER_STATUS_ATIVO   = 'ativo';
const USER_STATUS_INATIVO = 'inativo';

/*
|--------------------------------------------------------------------------
| Configuração operacional
|--------------------------------------------------------------------------
*/
const APP_TIMEZONE = 'America/Bahia';
const APP_NAME     = 'Comandaria Express';