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
use Symfony\Component\HttpFoundation\Response;

# Agenda MV -> Horarios
$app->put('/citas/cita', function () use ($app) {
    $u = new Model\Citas;
    return $app->json($u->updateCita());
});