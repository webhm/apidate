<?php

/*
 * This file is part of the Ocrend Framewok 3 package.
 *
 * (c) Ocrend Software <info@ocrend.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace app\models\agendaMV\usp;

use app\models\agendaMV\usp as Model;
use Doctrine\DBAL\DriverManager;
use Exception;
use Ocrend\Kernel\Models\IModels;
use Ocrend\Kernel\Models\Models;
use Ocrend\Kernel\Models\ModelsException;
use Ocrend\Kernel\Router\IRouter;

/**
 * Modelo Odbc GEMA -> Historia clínica
 */

class Procesos extends Models implements IModels
{

    # Variables de clase
    private $historiaClinica = null;
    private $motivoConsulta;
    private $revisionOrganos;
    private $antecedentesFamiliares;
    private $signosVitales;
    private $examenFisico;
    private $diagnosticos;
    private $evoluciones;
    private $prescripciones;
    private $conexion;
    private $numeroHistoriaClinica;
    private $numeroAdmision;
    private $codigoInstitucion = 1;
    private $numeroCompania = '01';
    private $recomendacionesNoFarmacologicas;
    private $tamanioCodigoExamen = 9;
    private $tamanioDescripcionExamen = 120;
    private $pedidosLaboratorio;
    private $pedidosImagen;
    private $start = 0;
    private $length = 10;

    private $_conexion = null;

    /**
     * Get Auth
     *
     * @var
     */

    private function getAuthorization()
    {

        try {

            global $http;

            $token = $http->headers->get("Authorization");

            $auth = new Model\Auth;
            $data = $auth->GetData($token);

            $this->id_user = $data;
        } catch (ModelsException $e) {
            return array('status' => false, 'message' => $e->getMessage());
        }
    }

    /**
     * Asigna los parámetros de entrada
     */
    private function setParameters()
    {
        global $http;

        foreach ($http->request->all() as $key => $value) {
            $this->$key = strtoupper($value);
        }

        foreach ($http->query->all() as $key => $value) {
            $this->$key = $value;
        }
    }

    private function setSpanishOracle($stid)
    {

        $sql = "alter session set NLS_LANGUAGE = 'SPANISH'";
        # Execute
        $stid = oci_parse($this->conexion->getConexion(), $sql);
        oci_execute($stid);

        $sql = "alter session set NLS_TERRITORY = 'SPAIN'";
        # Execute
        $stid = oci_parse($this->conexion->getConexion(), $sql);
        oci_execute($stid);

        $sql = " alter session set NLS_DATE_FORMAT = 'DD/MM/YYYY HH24:MI'";
        # Execute
        $stid = oci_parse($this->conexion->getConexion(), $sql);
        oci_execute($stid);
    }

    private function conectar_Oracle_PRD()
    {
        global $config;
        $_config = new \Doctrine\DBAL\Configuration();
        # SETEAR LA CONNEXION A LA BASE DE DATOS DE ORACLE GEMA
        $this->_conexion = \Doctrine\DBAL\DriverManager::getConnection($config['database']['drivers']['oracle_mv'], $_config);
    }

    public function itemCitaReagendar($cita, $type, $codPrestador)
    {

        $horario = explode('.', $cita['hashCita']);

        $fechaDesde = $horario[0];
        $fechaHasta = $horario[1];

        if ($type == 1) {

            $sql = "SELECT
            it.cd_it_agenda_central as ID_IT,
            to_char(it.hr_agenda, 'YYYY-MM-DD HH24:MI') as fechaHoraCita,
            it.cd_item_agendamento,
            it.cd_paciente,
            it.nm_paciente,
            it.dt_nascimento,
            it.tp_sexo,
            it.ds_email,
            it.nr_celular,
            rc.cd_recurso_central
            FROM agenda_central ac, recurso_central rc, it_agenda_central it
            WHERE it.CD_AGENDA_CENTRAL = ac.cd_agenda_central
            and ac.cd_recurso_central = rc.cd_recurso_central
            AND rc.cd_recurso_central in ($codPrestador)
            AND to_char(it.hr_agenda, 'YYYY-MM-DD HH24:MI') >= '$fechaDesde'
            AND to_char(it.hr_agenda, 'YYYY-MM-DD HH24:MI') <= '$fechaHasta'
            ORDER BY it.hr_agenda DESC ";
        } else {

            $sql = "SELECT
            it.cd_it_agenda_central as ID_IT,
            to_char(it.hr_agenda, 'YYYY-MM-DD HH24:MI') as fechaHoraCita,
            it.cd_item_agendamento,
            it.cd_paciente,
            it.nm_paciente,
            it.dt_nascimento,
            it.tp_sexo,
            it.ds_email,
            it.nr_celular,
            ac.cd_prestador,
            pr.nm_prestador
            FROM agenda_central ac, prestador pr, it_agenda_central it
            WHERE ac.cd_prestador = pr.cd_prestador
            AND it.CD_AGENDA_CENTRAL = ac.cd_agenda_central
            AND ac.cd_prestador in ($codPrestador)
            AND to_char(it.hr_agenda, 'YYYY-MM-DD HH24:MI') >= '$fechaDesde'
            AND to_char(it.hr_agenda, 'YYYY-MM-DD HH24:MI') <= '$fechaHasta'
            ORDER BY  it.cd_it_agenda_central DESC  ";
        }

        # Conectar base de datos
        $this->conectar_Oracle_PRD();

        # Execute
        $stmt = $this->_conexion->query($sql);

        $this->_conexion->close();

        $data = $stmt->fetchAll();

        return $data[1]['ID_IT'];
    }

    public function itemCita($cita, $type, $codPrestador)
    {

        $fechaDesde = $cita['inicio'];
        $fechaHasta = $cita['fin'];

        if ($type == 1) {

            $sql = "SELECT
            it.cd_it_agenda_central as ID_IT,
            to_char(it.hr_agenda, 'YYYY-MM-DD hh24:mi') as fechaHoraCita,
            it.cd_item_agendamento,
            it.cd_paciente,
            it.nm_paciente,
            it.dt_nascimento,
            it.tp_sexo,
            it.ds_email,
            it.nr_celular,
            rc.cd_recurso_central
            FROM agenda_central ac, recurso_central rc, it_agenda_central it
            WHERE it.CD_AGENDA_CENTRAL = ac.cd_agenda_central
            and ac.cd_recurso_central = rc.cd_recurso_central
            AND rc.cd_recurso_central in ($codPrestador)
            AND to_char(it.hr_agenda, 'DD/MM/YYYY HH24:MI') >= '$fechaDesde'
            AND to_char(it.hr_agenda, 'DD/MM/YYYY HH24:MI') <= '$fechaHasta'
            ORDER BY it.hr_agenda DESC ";
        } else {

            $sql = "SELECT
            it.cd_it_agenda_central as ID_IT,
            to_char(it.hr_agenda, 'YYYY-MM-DD hh24:mi') as fechaHoraCita,
            it.cd_item_agendamento,
            it.cd_paciente,
            it.nm_paciente,
            it.dt_nascimento,
            it.tp_sexo,
            it.ds_email,
            it.nr_celular,
            ac.cd_prestador,
            pr.nm_prestador
            FROM agenda_central ac, prestador pr, it_agenda_central it
            WHERE ac.cd_prestador = pr.cd_prestador
            AND it.CD_AGENDA_CENTRAL = ac.cd_agenda_central
            AND ac.cd_prestador in ($codPrestador)
            AND to_char(it.hr_agenda, 'DD/MM/YYYY HH24:MI') >= '$fechaDesde'
            AND to_char(it.hr_agenda, 'DD/MM/YYYY HH24:MI') <= '$fechaHasta'
            ORDER BY  it.cd_it_agenda_central DESC  ";
        }

        # Conectar base de datos
        $this->conectar_Oracle_PRD();

        # Execute
        $stmt = $this->_conexion->query($sql);

        $this->_conexion->close();

        $data = $stmt->fetchAll();

        return $data[1]['ID_IT'];
    }

    public function validarAgendas($cita, $type, $codPrestador)
    {

        $fechaDesde = $cita['inicio'];
        $fechaHasta = $cita['fin'];

        if ($type == 1) {

            $sql = "SELECT
            it.cd_it_agenda_central as ID_IT,
            to_char(it.hr_agenda, 'YYYY-MM-DD hh24:mi') as fechaHoraCita,
            it.cd_item_agendamento,
            it.cd_paciente,
            it.nm_paciente,
            it.dt_nascimento,
            it.tp_sexo,
            it.ds_email,
            it.nr_celular,
            rc.cd_recurso_central
            FROM agenda_central ac, recurso_central rc, it_agenda_central it
            WHERE it.CD_AGENDA_CENTRAL = ac.cd_agenda_central
            and ac.cd_recurso_central = rc.cd_recurso_central
            AND rc.cd_recurso_central in ($codPrestador)
            AND to_char(it.hr_agenda, 'DD/MM/YYYY HH24:MI') >= '$fechaDesde'
            AND to_char(it.hr_agenda, 'DD/MM/YYYY HH24:MI') <= '$fechaHasta'
            ORDER BY it.hr_agenda DESC ";
        } else {

            $sql = "SELECT
            it.cd_it_agenda_central as ID_IT,
            to_char(it.hr_agenda, 'YYYY-MM-DD hh24:mi') as fechaHoraCita,
            it.cd_item_agendamento,
            it.cd_paciente,
            it.nm_paciente,
            it.dt_nascimento,
            it.tp_sexo,
            it.ds_email,
            it.nr_celular,
            ac.cd_prestador,
            pr.nm_prestador
            FROM agenda_central ac, prestador pr, it_agenda_central it
            WHERE ac.cd_prestador = pr.cd_prestador
            AND it.CD_AGENDA_CENTRAL = ac.cd_agenda_central
            AND ac.cd_prestador in ($codPrestador)
            AND to_char(it.hr_agenda, 'DD/MM/YYYY HH24:MI') >= '$fechaDesde'
            AND to_char(it.hr_agenda, 'DD/MM/YYYY HH24:MI') <= '$fechaHasta'
            ORDER BY  it.cd_it_agenda_central DESC  ";
        }

        # Conectar base de datos
        $this->conectar_Oracle_PRD();

        # Execute
        $stmt = $this->_conexion->query($sql);

        $this->_conexion->close();

        $data = $stmt->fetch();

        return $data;
    }

    public function validarAgendasReagendar($cita, $type, $codPrestador)
    {
        $horario = explode('.', $cita['newHashCita']);
        $fechaDesde = date('d/m/Y H:i', strtotime($horario[0]));
        $fechaHasta = date('d/m/Y H:i', strtotime($horario[1]));

        if ($type == 1) {

            $sql = "SELECT
            it.cd_it_agenda_central as ID_IT,
            to_char(it.hr_agenda, 'YYYY-MM-DD hh24:mi') as fechaHoraCita,
            it.cd_item_agendamento,
            it.cd_paciente,
            it.nm_paciente,
            it.dt_nascimento,
            it.tp_sexo,
            it.ds_email,
            it.nr_celular,
            rc.cd_recurso_central
            FROM agenda_central ac, recurso_central rc, it_agenda_central it
            WHERE it.CD_AGENDA_CENTRAL = ac.cd_agenda_central
            and ac.cd_recurso_central = rc.cd_recurso_central
            AND rc.cd_recurso_central in ($codPrestador)
            AND to_char(it.hr_agenda, 'DD/MM/YYYY HH24:MI') >= '$fechaDesde'
            AND to_char(it.hr_agenda, 'DD/MM/YYYY HH24:MI') <= '$fechaHasta'
            ORDER BY it.hr_agenda DESC ";
        } else {

            $sql = "SELECT
            it.cd_it_agenda_central as ID_IT,
            to_char(it.hr_agenda, 'YYYY-MM-DD hh24:mi') as fechaHoraCita,
            it.cd_item_agendamento,
            it.cd_paciente,
            it.nm_paciente,
            it.dt_nascimento,
            it.tp_sexo,
            it.ds_email,
            it.nr_celular,
            ac.cd_prestador,
            pr.nm_prestador
            FROM agenda_central ac, prestador pr, it_agenda_central it
            WHERE ac.cd_prestador = pr.cd_prestador
            AND it.CD_AGENDA_CENTRAL = ac.cd_agenda_central
            AND ac.cd_prestador in ($codPrestador)
            AND to_char(it.hr_agenda, 'DD/MM/YYYY HH24:MI') >= '$fechaDesde'
            AND to_char(it.hr_agenda, 'DD/MM/YYYY HH24:MI') <= '$fechaHasta'
            ORDER BY  it.cd_it_agenda_central DESC  ";
        }

        # Conectar base de datos
        $this->conectar_Oracle_PRD();

        # Execute
        $stmt = $this->_conexion->query($sql);

        $this->_conexion->close();

        $data = $stmt->fetch();

        return $data;
    }

    /**
     * Permite generar una nota en la cita reagendada
     */
    public function trackCallDeleteCita()
    {
        try {

            global $http;

            $this->cita = $http->request->all();

            $esmultiple = false;
            $posicion_coincidencia = strpos($this->cita['idCalendar'], ',');
            if ($posicion_coincidencia !== false) {
                $esmultiple = true;
            }

            if ($esmultiple) {

                $calendarios = $this->cita['calendarios'];

                foreach ($calendarios as $key) {
                    if ($key['TIPO'] == '1') {
                        // Es agenda de recurso
                        $sts = $this->callCancelarCita(1, $key['IDCALENDAR']);
                        if (!$sts['status']) {
                            throw new ModelsException("Error en Agendamiento");
                        }
                        $res[] = $sts;
                    }
                    if ($key['TIPO'] == '2') {
                        // Es agenda de medico/prestador
                        $sts = $this->callCancelarCita(2, $key['IDCALENDAR']);
                        if (!$sts['status']) {
                            throw new ModelsException("Error en Agendamiento");
                        }
                        $res[] = $sts;
                    }
                }

                if (count($res) !== 0) {
                    return array(
                        'status' => true,
                        'data' => [],
                        'message' => 'Proceso realizado con éxito.',
                    );
                }
            } else {

                $calendarios = $this->cita['calendarios'];

                $res = array();

                foreach ($calendarios as $key) {
                    if ($key['IDCALENDAR'] == $this->cita['idCalendar']) {
                        if ($key['TIPO'] == '1') {
                            // Es agenda de recurso
                            $sts = $this->callCancelarCita(1, $key['IDCALENDAR']);
                            if (!$sts['status']) {
                                throw new ModelsException("Error en Agendamiento");
                            }
                            $res[] = $sts;
                        }
                        if ($key['TIPO'] == '2') {
                            // Es agenda de medico/prestador
                            $sts = $this->callCancelarCita(2, $key['IDCALENDAR']);
                            if (!$sts['status']) {
                                throw new ModelsException("Error en Agendamiento");
                            }
                            $res[] = $sts;
                        }
                    }
                }

                if (count($res) !== 0) {
                    return array(
                        'status' => true,
                        'data' => [],
                        'message' => 'Proceso realizado con éxito.',
                    );
                }
            }
            //code...
        } catch (ModelsException $e) {
            return array('status' => false, 'message' => $e->getMessage());
        }
    }

    /**
     * Permite generar una nota en la cita reagendada
     */
    public function trackCallUpCita()
    {

        try {

            global $http;

            $this->cita = $http->request->all();

            $esmultiple = false;
            $posicion_coincidencia = strpos($this->cita['idCalendar'], ',');
            if ($posicion_coincidencia !== false) {
                $esmultiple = true;
            }

            if ($esmultiple) {

                $calendarios = $this->cita['calendarios'];


                # Validacion de agendamiento
                foreach ($calendarios as $key) {
                    if ($key['TIPO'] == '1') {
                        if ($this->validarAgendasReagendar($this->cita, 1, $key['IDCALENDAR']) == false) {
                            throw new ModelsException("No existe disponibilidad de Agendas. Verifique la generación y liberación de turnos o escalas en MV. Referencia: " . $key['CALENDAR']);
                        }
                    }
                    if ($key['TIPO'] == '2') {
                        if ($this->validarAgendasReagendar($this->cita, 2, $key['IDCALENDAR']) == false) {
                            throw new ModelsException("No existe disponibilidad de Agendas. Verifique la generación y liberación de turnos o escalas en MV. Referencia: " . $key['CALENDAR']);
                        }
                    }
                }

                $res = array();

                foreach ($calendarios as $key) {
                    if ($key['TIPO'] == '1') {
                        // Es agenda de recurso
                        $sts = $this->callReagendarCita(1, $key['IDCALENDAR']);
                        if (!$sts['status']) {
                            throw new ModelsException("Error en Agendamiento");
                        }
                        $res[] = $sts;
                    }
                    if ($key['TIPO'] == '2') {

                        // Es agenda de medico/prestador
                        $sts = $this->callReagendarCita(2, $key['IDCALENDAR']);
                        if (!$sts['status']) {
                            throw new ModelsException("Error en Agendamiento");
                        }
                        $res[] = $sts;
                    }
                }

                if (count($res) !== 0) {
                    return array(
                        'status' => true,
                        'data' => [],
                        'message' => 'Proceso realizado con éxito.',
                    );
                }
            } else {

                $calendarios = $this->cita['calendarios'];

                # Validacion de agendamiento
                foreach ($calendarios as $key) {
                    if ($key['IDCALENDAR'] == $this->cita['idCalendar']) {
                        if ($key['TIPO'] == '1') {
                            if ($this->validarAgendasReagendar($this->cita, 1, $key['IDCALENDAR']) == false) {
                                throw new ModelsException("No existe disponibilidad de Agendas. Verifique la generación y liberación de turnos o escalas en MV. Referencia: " . $key['CALENDAR']);
                            }
                        }
                        if ($key['TIPO'] == '2') {
                            if ($this->validarAgendasReagendar($this->cita, 2, $key['IDCALENDAR']) == false) {
                                throw new ModelsException("No existe disponibilidad de Agendas. Verifique la generación y liberación de turnos o escalas en MV. Referencia: " . $key['CALENDAR']);
                            }
                        }
                    }
                }

                $res = array();

                foreach ($calendarios as $key) {
                    if ($key['IDCALENDAR'] == $this->cita['idCalendar']) {
                        if ($key['TIPO'] == '1') {
                            // Es agenda de recurso
                            $sts = $this->callReagendarCita(1, $key['IDCALENDAR']);
                            if (!$sts['status']) {
                                throw new ModelsException("Error en Agendamiento");
                            }
                            $res[] = $sts;
                        }
                        if ($key['TIPO'] == '2') {

                            // Es agenda de medico/prestador
                            $sts = $this->callReagendarCita(2, $key['IDCALENDAR']);
                            if (!$sts['status']) {
                                throw new ModelsException("Error en Agendamiento");
                            }
                            $res[] = $sts;
                        }
                    }
                }

                if (count($res) !== 0) {
                    return array(
                        'status' => true,
                        'data' => [],
                        'message' => 'Proceso realizado con éxito.',
                    );
                }
            }
        } catch (ModelsException $e) {
            return array('status' => false, 'message' => $e->getMessage());
        }
    }

    /**
     * Permite registrar una nueva cita
     */
    public function trackCallCita()
    {

        try {

            global $http;

            $this->cita = $http->request->all();
            $esmultiple = false;
            $contadorMedico = 0;
            $contadorRecurso = 0;
            $CD_PRESTADOR = '';

            $posicion_coincidencia = strpos($this->cita['idCalendar'], ',');
            if ($posicion_coincidencia !== false) {
                $esmultiple = true;
            }

            if ($esmultiple) {

                $calendarios = $this->cita['calendarios'];


                # Validacion de agendamiento
                foreach ($calendarios as $key) {
                    if ($key['TIPO'] == '2') {
                        $CD_PRESTADOR = $key['IDCALENDAR'];
                    }
                }


                $res = array();

                foreach ($calendarios as $key) {
                    sleep(0.5);
                    if ($key['TIPO'] == '1') {
                        // Es agenda de recurso
                        $sts = $this->callCita(1, $key['IDCALENDAR'],  $CD_PRESTADOR);
                        if (!$sts['status']) {
                            throw new ModelsException("Error en Agendamiento");
                        }
                        $res[] = $sts;
                    }
                    if ($key['TIPO'] == '2') {
                        // Es agenda de medico/prestador
                        $sts = $this->callCita(2, $key['IDCALENDAR']);
                        if (!$sts['status']) {
                            throw new ModelsException("Error en Agendamiento");
                        }
                        $res[] = $sts;
                    }
                }

                if (count($res) !== 0) {
                    return array(
                        'status' => true,
                        'data' => $res,
                        'message' => 'Proceso realizado con éxito.',
                    );
                }
            } else {

                $calendarios = $this->cita['calendarios'];

                # Validacion de agendamiento
                foreach ($calendarios as $key) {
                    if ($key['IDCALENDAR'] == $this->cita['idCalendar']) {
                        if ($key['TIPO'] == '2') {
                            $CD_PRESTADOR = $key['IDCALENDAR'];
                        }
                    }
                }


                $res = array();

                foreach ($calendarios as $key) {
                    sleep(0.5);
                    if ($key['IDCALENDAR'] == $this->cita['idCalendar']) {
                        if ($key['TIPO'] == '1') {
                            // Es agenda de recurso
                            $sts = $this->callCita(1, $key['IDCALENDAR'],  $CD_PRESTADOR);
                            if (!$sts['status']) {
                                throw new ModelsException("Error en Agendamiento");
                            }
                            $res[] = $sts;
                        }
                        if ($key['TIPO'] == '2') {
                            // Es agenda de medico/prestador
                            $sts = $this->callCita(2, $key['IDCALENDAR']);
                            if (!$sts['status']) {
                                throw new ModelsException("Error en Agendamiento");
                            }
                            $res[] = $sts;
                        }
                    }
                }

                if (count($res) !== 0) {
                    return array(
                        'status' => true,
                        'data' => $res,
                        'message' => 'Proceso realizado con éxito.',

                    );
                }
            }
        } catch (ModelsException $e) {
            return array('status' => false, 'message' => $e->getMessage());
        }
    }

    public function trackCallValidateCita()
    {

        try {

            global $http;

            $this->cita = $http->request->all();
            $esmultiple = false;
            $contadorMedico = 0;
            $contadorRecurso = 0;

            $posicion_coincidencia = strpos($this->cita['idCalendar'], ',');
            if ($posicion_coincidencia !== false) {
                $esmultiple = true;
            }

            if ($esmultiple) {

                $calendarios = $this->cita['calendarios'];

                # Validación agendamiento un recurso un prestador
                foreach ($calendarios as $key) {
                    if ($key['TIPO'] == '1') {
                        $contadorRecurso++;
                    }
                    if ($key['TIPO'] == '2') {
                        $contadorMedico++;
                    }
                }

                # Validación de Médico
                if ($contadorMedico == 0) {
                    throw new ModelsException("No se puede completar este agendamiento. Es necesario seleccionar la agenda de un Médico para continuar. ");
                }

                if ($contadorMedico > 1) {
                    throw new ModelsException("No se puede completar este agendamiento. Solo se debe escoger la agenda de un Médico para continuar.");
                }

                # Validación de Recurso
                if ($contadorRecurso == 0) {
                    throw new ModelsException("No se puede completar este agendamiento. Es necesario seleccionar la agenda de un Recurso o Sala para continuar. ");
                }

                if ($contadorRecurso > 1) {
                    throw new ModelsException("No se puede completar este agendamiento. Solo se debe escoger la agenda de un Recurso o Sala para continuar.");
                }



                # Validacion de agendamiento
                foreach ($calendarios as $key) {
                    if ($key['TIPO'] == '1') {
                        if ($this->validarAgendas($this->cita, 1, $key['IDCALENDAR']) == false) {
                            throw new ModelsException("No existe disponibilidad de Agendas. Verifique la generación y liberación de turnos o escalas en MV. Referencia: " . $key['CALENDAR']);
                        }
                    }
                    if ($key['TIPO'] == '2') {
                        if ($this->validarAgendas($this->cita, 2, $key['IDCALENDAR']) == false) {
                            throw new ModelsException("No existe disponibilidad de Agendas. Verifique la generación y liberación de turnos o escalas en MV. Referencia: " . $key['CALENDAR']);
                        }
                    }
                }


                return array(
                    'status' => true,
                    'data' => [],
                    'message' => 'Proceso realizado con éxito.',
                );
            } else {

                $calendarios = $this->cita['calendarios'];

                # Validación agendamiento un recurso un prestador
                foreach ($calendarios as $key) {
                    if ($key['IDCALENDAR'] == $this->cita['idCalendar']) {
                        if ($key['TIPO'] == '1') {
                            $contadorRecurso++;
                        }
                        if ($key['TIPO'] == '2') {
                            $contadorMedico++;
                        }
                    }
                }

                # Validación de Médico
                if ($contadorMedico == 0) {
                    throw new ModelsException("No se puede completar este agendamiento. Es necesario seleccionar la agenda de un Médico para continuar. ");
                }

                if ($contadorMedico > 1) {
                    throw new ModelsException("No se puede completar este agendamiento. Solo se debe escoger la agenda de un Médico para continuar.");
                }

                # Validación de Recurso
                if ($contadorRecurso == 0) {
                    throw new ModelsException("No se puede completar este agendamiento. Es necesario seleccionar la agenda de un Recurso o Sala para continuar. ");
                }

                if ($contadorRecurso > 1) {
                    throw new ModelsException("No se puede completar este agendamiento. Solo se debe escoger la agenda de un Recurso o Sala para continuar.");
                }

                # Validacion de agendamiento
                foreach ($calendarios as $key) {
                    if ($key['IDCALENDAR'] == $this->cita['idCalendar']) {
                        if ($key['TIPO'] == '1') {
                            if ($this->validarAgendas($this->cita, 1, $key['IDCALENDAR']) == false) {
                                throw new ModelsException("No existe disponibilidad de Agendas. Verifique la generación y liberación de turnos o escalas en MV. Referencia: " . $key['CALENDAR']);
                            }
                        }
                        if ($key['TIPO'] == '2') {
                            if ($this->validarAgendas($this->cita, 2, $key['IDCALENDAR']) == false) {
                                throw new ModelsException("No existe disponibilidad de Agendas. Verifique la generación y liberación de turnos o escalas en MV. Referencia: " . $key['CALENDAR']);
                            }
                        }
                    }
                }

                return array(
                    'status' => true,
                    'data' => [],
                    'message' => 'Proceso realizado con éxito.',
                );
            }
        } catch (ModelsException $e) {
            return array('status' => false, 'message' => $e->getMessage());
        }
    }


    /**
     * Permite registrar una nueva Cita
     */
    public function callCita($type = '', $codPrestador = '',  $cdPrestadorAgenda = '')
    {

        //Inicialización de variables
        $stmt = null;
        $codigoRetorno = null;
        $mensajeRetorno = null;
        $r = false;
        $rCommit = false;
        $codigoError = -1;
        $mensajeError = null;

        try {

            // Parametros de consulta
            $pn_paciente = $this->cita['nhc'];
            $pc_nm_paciente = $this->cita['paciente'];
            $pn_prestador = ($type == '1' ? $cdPrestadorAgenda : $codPrestador);
            $pn_prestador_solicita = $this->cita['cd_prestador'];
            $pc_inicio = $this->cita['inicio'];
            $pc_fin = $this->cita['fin'];
            $pn_it_agenda_central = $this->itemCita($this->cita, $type, $codPrestador);
            $pc_fecha_nacimiento = $this->cita['fecha_nacimiento'];
            $pn_item_agendamento = $this->cita['id_estudio'];
            $pn_convenio = 4;
            $pn_con_pla = 1;
            $pc_telefono = $this->cita['telefono'];
            $pc_email = $this->cita['email'];
            $pc_sexo = $this->cita['sexo'];

            //Conectar a la BDD
            $this->conexion->conectar();

            //Setear idioma y formatos en español para Oracle
            $this->setSpanishOracle($stmt);

            $stmt = oci_parse($this->conexion->getConexion(), "BEGIN
                hmetro.pr_itg_agenda_cita(:pn_paciente, :pc_nm_paciente, :pn_prestador, :pn_prestador_solicita, :pn_it_agenda_central,
                :pc_fecha_nacimiento, :pn_item_agendamento, :pc_inicio, :pc_fin, :pn_convenio, :pn_con_pla, :pc_telefono, :pc_sexo, :pc_email, :pc_error); END;");

            // Bind the input parameter
            oci_bind_by_name($stmt, ':pn_paciente', $pn_paciente, 32);
            oci_bind_by_name($stmt, ':pc_nm_paciente', $pc_nm_paciente, 32);
            oci_bind_by_name($stmt, ':pn_prestador', $pn_prestador, 32);
            oci_bind_by_name($stmt, ':pn_prestador_solicita', $pn_prestador_solicita, 32);
            oci_bind_by_name($stmt, ':pn_it_agenda_central', $pn_it_agenda_central, 32);
            oci_bind_by_name($stmt, ':pc_fecha_nacimiento', $pc_fecha_nacimiento, 32);
            oci_bind_by_name($stmt, ':pn_item_agendamento', $pn_item_agendamento, 32);
            oci_bind_by_name($stmt, ':pc_inicio', $pc_inicio, 32);
            oci_bind_by_name($stmt, ':pc_fin', $pc_fin, 32);
            oci_bind_by_name($stmt, ':pn_convenio', $pn_convenio, 32);
            oci_bind_by_name($stmt, ':pn_con_pla', $pn_con_pla, 32);
            oci_bind_by_name($stmt, ':pc_telefono', $pc_telefono, 32);
            oci_bind_by_name($stmt, ':pc_sexo', $pc_sexo, 32);
            oci_bind_by_name($stmt, ':pc_email', $pc_email, 42);
            oci_bind_by_name($stmt, ':pc_error', $mensajeError, 500);

            //Ejecuta el SP
            oci_execute($stmt);

            $rCommit = oci_commit($this->conexion->getConexion());

            //Error al ORACLE
            if (!$rCommit) {
                $e = oci_error($stmt);
                $mensajeError = "Error, consulte con el Administrador del Sistema. " . $e['message'];
            }

            if ($mensajeError !== null) {
                throw new ModelsException($mensajeError, 0);
            }

            $mensajeRetorno = 'Proceso ejecutado con éxito.';

            return array(
                'status' => true,
                'data' => $this->cita,
                'message' => $mensajeRetorno,
            );
        } catch (ModelsException $e) {

            return array(
                'status' => false,
                'data' => [],
                'message' => $e->getMessage(),
                'errorCode' => $e->getCode(),
            );
        } catch (Exception $ex) {
            //
            $mensajeError = $ex->getMessage();

            return array(
                'status' => false,
                'data' => [],
                'message' => $mensajeError,
                'errorCode' => $codigoError,
            );
        } finally {
            //Libera recursos de conexión
            if ($stmt != null) {
                oci_free_statement($stmt);
            }

            //Cierra la conexión
            $this->conexion->cerrar();
        }
    }

    public function callReagendarCita($type = '', $codPrestador = '')
    {
        global $http;

        //Inicialización de variables
        $stmt = null;
        $codigoRetorno = null;
        $mensajeRetorno = null;
        $r = false;
        $rCommit = false;
        $codigoError = -1;
        $mensajeError = null;

        try {

            $horario = explode('.', $this->cita['hashCita']);
            $fechaDesde = date('d/m/Y H:i', strtotime($horario[0]));
            $fechaHasta = date('d/m/Y H:i', strtotime($horario[1]));

            //  $pc_motivo = 'CITA REAGENDADA: ' . $this->cita['paciente'];
            $pc_motivo = '';
            $pc_inicio = $fechaDesde;
            $pc_fin = $fechaHasta;
            $pn_it_agenda_central = $this->itemCitaReagendar($this->cita, $type, $codPrestador);

            //Conectar a la BDD
            $this->conexion->conectar();

            //Setear idioma y formatos en español para Oracle
            $this->setSpanishOracle($stmt);

            $stmt = oci_parse($this->conexion->getConexion(), "BEGIN
                hmetro.pr_itg_notificacion_cita(:pc_motivo, :pn_it_agenda_central, :pc_inicio, :pc_fin, :pc_error); END;");

            // Bind the input parameter
            oci_bind_by_name($stmt, ':pc_motivo', $pc_motivo, 32);
            oci_bind_by_name($stmt, ':pn_it_agenda_central', $pn_it_agenda_central, 32);
            oci_bind_by_name($stmt, ':pc_inicio', $pc_inicio, 32);
            oci_bind_by_name($stmt, ':pc_fin', $pc_fin, 32);
            oci_bind_by_name($stmt, ':pc_error', $mensajeError, 500);

            //Ejecuta el SP
            oci_execute($stmt);

            $rCommit = oci_commit($this->conexion->getConexion());

            //Error al ORACLE
            if (!$rCommit) {
                $e = oci_error($stmt);
                $mensajeError = "Error, consulte con el Administrador del Sistema. " . $e['message'];
            }

            if ($mensajeError !== null) {
                throw new ModelsException($mensajeError, 0);
            }

            $mensajeRetorno = 'Proceso ejecutado con éxito.';

            return array(
                'status' => true,
                'data' => $http->request->all(),
                'message' => $mensajeRetorno,
            );
        } catch (ModelsException $e) {

            return array(
                'status' => false,
                'data' => [],
                'message' => $e->getMessage(),
                'errorCode' => $e->getCode(),
            );
        } catch (Exception $ex) {
            //
            $mensajeError = $ex->getMessage();

            return array(
                'status' => false,
                'data' => [],
                'message' => $mensajeError,
                'errorCode' => $codigoError,
            );
        } finally {
            //Libera recursos de conexión
            if ($stmt != null) {
                oci_free_statement($stmt);
            }

            //Cierra la conexión
            $this->conexion->cerrar();
        }
    }

    public function callCancelarCita($type = '', $codPrestador = '')
    {
        global $http;

        //Inicialización de variables
        $stmt = null;
        $codigoRetorno = null;
        $mensajeRetorno = null;
        $r = false;
        $rCommit = false;
        $codigoError = -1;
        $mensajeError = null;

        try {

            // $pc_motivo = 'CITA CANCELADA: ' . $this->cita['paciente'];
            $pc_motivo = '';
            $pc_inicio = $this->cita['inicio'];
            $pc_fin = $this->cita['fin'];
            $pn_it_agenda_central = $this->itemCita($this->cita, $type, $codPrestador);

            //Conectar a la BDD
            $this->conexion->conectar();

            //Setear idioma y formatos en español para Oracle
            $this->setSpanishOracle($stmt);

            $stmt = oci_parse($this->conexion->getConexion(), "BEGIN
                hmetro.pr_itg_notificacion_cita(:pc_motivo, :pn_it_agenda_central, :pc_inicio, :pc_fin, :pc_error); END;");

            // Bind the input parameter
            oci_bind_by_name($stmt, ':pc_motivo', $pc_motivo, 32);
            oci_bind_by_name($stmt, ':pn_it_agenda_central', $pn_it_agenda_central, 32);
            oci_bind_by_name($stmt, ':pc_inicio', $pc_inicio, 32);
            oci_bind_by_name($stmt, ':pc_fin', $pc_fin, 32);
            oci_bind_by_name($stmt, ':pc_error', $mensajeError, 500);

            //Ejecuta el SP
            oci_execute($stmt);

            $rCommit = oci_commit($this->conexion->getConexion());

            //Error al ORACLE
            if (!$rCommit) {
                $e = oci_error($stmt);
                $mensajeError = "Error, consulte con el Administrador del Sistema. " . $e['message'];
            }

            if ($mensajeError !== null) {
                throw new ModelsException($mensajeError, 0);
            }

            $mensajeRetorno = 'Proceso ejecutado con éxito.';

            return array(
                'status' => true,
                'data' => $http->request->all(),
                'message' => $mensajeRetorno,
            );
        } catch (ModelsException $e) {

            return array(
                'status' => false,
                'data' => [],
                'message' => $e->getMessage(),
                'errorCode' => $e->getCode(),
            );
        } catch (Exception $ex) {
            //
            $mensajeError = $ex->getMessage();

            return array(
                'status' => false,
                'data' => [],
                'message' => $mensajeError,
                'errorCode' => $codigoError,
            );
        } finally {
            //Libera recursos de conexión
            if ($stmt != null) {
                oci_free_statement($stmt);
            }

            //Cierra la conexión
            $this->conexion->cerrar();
        }
    }

    /**
     * __construct()
     */
    public function __construct(IRouter $router = null)
    {
        parent::__construct($router);

        //Instancia la clase conexión a la base de datos
        $this->conexion = new Conexion();
    }
}
