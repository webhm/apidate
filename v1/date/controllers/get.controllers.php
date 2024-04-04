<?php

/*
 * MetroVirtual PAAS
 *
 * (c) Hospital Metropolitano <dev@hmetro.med.ec>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use app\models\agendaMV as Model;

$app->get('/', function () use ($app) {
    return $app->json();
});

# Agenda MV -> Horarios
$app->get('/horarios', function () use ($app) {
    $m = new Model\Horarios;
    return $app->json($m->getHorarios());
});

$app->get('/citas/cita', function () use ($app) {
    $m = new Model\Citas;
    return $app->json($m->getCita());
});

$app->get('/citas/agendadas', function () use ($app) {
    $u = new Model\Citas;
    return $app->json($u->getCitas());
});

$app->get('/calendar/status', function () use ($app) {
    $u = new Model\Eventos;
    return $app->json($u->recordCalendar());
});

$app->get('/calendar/upstatus', function () use ($app) {
    $u = new Model\Eventos;
    return $app->json($u->upStatus());
});
