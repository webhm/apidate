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
use app\models\agendaMV\usp as UspModel;

# Agenda MV -> Horarios
$app->post('/horario', function () use ($app) {
    $u = new Model\Horarios;
    return $app->json($u->registrarHorario());
});

$app->post('/horario/liberar', function () use ($app) {
    $u = new Model\Horarios;
    return $app->json($u->liberarHorario());
});

$app->post('/citas/call', function () use ($app) {
    $u = new UspModel\Procesos;
    return $app->json($u->trackCallCita());
});

$app->post('/citas/call-validate', function () use ($app) {
    $u = new UspModel\Procesos;
    return $app->json($u->trackCallValidateCita());
});

$app->post('/citas/upcall', function () use ($app) {
    $u = new UspModel\Procesos;
    return $app->json($u->trackCallUpCita());
});

$app->post('/citas/delcall', function () use ($app) {
    $u = new UspModel\Procesos;
    return $app->json($u->trackCallDeleteCita());
});

$app->post('/citas/nueva', function () use ($app) {
    $u = new Model\Citas;
    return $app->json($u->crearCita());
});

$app->post('/citas/update', function () use ($app) {
    $u = new Model\Citas;
    return $app->json($u->updateCita());
});

$app->post('/citas/perfil', function () use ($app) {
    $u = new Model\Citas;
    return $app->json($u->getPerfilCitas());
});

$app->post('/citas/reagendar', function () use ($app) {
    $u = new Model\Citas;
    return $app->json($u->setReagendarCita());
});

$app->post('/citas/reagendar/cancel', function () use ($app) {
    $u = new Model\Citas;
    return $app->json($u->setCancelReagendarCita());
});

$app->post('/citas/delete', function () use ($app) {
    $u = new Model\Citas;
    return $app->json($u->cancelarCita());
});

$app->post('/pacientes', function () use ($app) {
    $u = new Model\Pacientes;
    return $app->json($u->getPacientes());
});

$app->post('/medicos', function () use ($app) {
    $u = new Model\Medicos;
    return $app->json($u->getMedicos());
});

$app->post('/items', function () use ($app) {
    $u = new Model\Medicos;
    return $app->json($u->getItems());
});
