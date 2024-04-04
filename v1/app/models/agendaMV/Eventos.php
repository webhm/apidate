<?php

/*
 * This file is part of the Ocrend Framewok 3 package.
 *
 * (c) Ocrend Software <info@ocrend.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace app\models\agendaMV;

use app\models\agendaMV as Model;
use DateTime;
use Doctrine\DBAL\DriverManager;
use Ocrend\Kernel\Helpers as Helper;
use Ocrend\Kernel\Models\IModels;
use Ocrend\Kernel\Models\Models;
use Ocrend\Kernel\Models\ModelsException;
use Ocrend\Kernel\Models\Traits\DBModel;
use Ocrend\Kernel\Router\IRouter;

/**
 * Modelo Eventos Calendario
 */

class Eventos extends Models implements IModels
{
    use DBModel;

    # Parametros de clase

    private $profile = null;
    private $user = null;

    private function getAuthorization()
    {

        try {

            global $http;

            $token = $http->headers->get("Authorization");

            $object = $this->decodeJWT($token);

            $this->user = $object['data'];
        } catch (ModelsException $e) {
            return array('status' => false, 'message' => $e->getMessage());
        }
    }


    public function decodeJWT($token)
    {

        $claims_arr = array();

        if ($token !== null) {
            $token_arr = explode('.', $token);
            $claims_enc = $token_arr[1];
            $claims_arr = json_decode($this->base64_url_decode($claims_enc), true);
        }

        return $claims_arr;
    }

    private function base64_url_decode($arg)
    {
        $res = $arg;
        $res = str_replace('-', '+', $res);
        $res = str_replace('_', '/', $res);
        switch (strlen($res) % 4) {
            case 0:
                break;
            case 2:
                $res .= "==";
                break;
            case 3:
                $res .= "=";
                break;
            default:
                break;
        }
        $res = base64_decode($res);
        return $res;
    }


    public function recordCalendar()
    {
        global $http;

        $this->getAuthorization();

        $this->profile = array(
            'timestampCalendar' => time(),
            'usersCalendar' => array(),
            'ultimosAgendamientos' => array(),
            'statusAgendamiento' => array(),
            'statusReagendamiento' => array()
        );

        // $time = new DateTime();
        // $nuevoDia = strtotime('-1 day', $time->getTimestamp());
        $_dia = date('Y-m-d');

        $stsLogPen = "_calendar/status/st_" . $_dia . "_.json";

        $parseUsr = explode('_', $http->query->get('peerId'));
        $user = $parseUsr[1];
        $hash = $parseUsr[0];

        if (@file_get_contents($stsLogPen, true) === false) {
            $this->profile['dateCalendar'] = date('d-m-Y H:i',  $this->profile['timestampCalendar']);
            $this->profile['usersCalendar'][$user] = $hash;
            file_put_contents('_calendar/status/st_' . $_dia . '_.json', json_encode($this->profile), LOCK_EX);
        } else {

            $data = '_calendar/status/st_' . $_dia . '_.json';
            $datos = file_get_contents($data);
            $this->profile = json_decode($datos, true);
            $this->profile['dateCalendar'] = date('d-m-Y H:i',  $this->profile['timestampCalendar']);
            $this->profile['usersCalendar'][$user] = $hash;
            file_put_contents('_calendar/status/st_' . $_dia . '_.json', json_encode($this->profile), LOCK_EX);
        }

        return array(
            'status' => true,
            'data' =>  $this->profile,
            'userData' => $this->user
        );
    }


    public function upStatus()
    {

        $this->getAuthorization();

        $this->profile = array(
            'timestampCalendar' => time(),
            'usersCalendar' => array(),
            'ultimosAgendamientos' => array(),
            'statusAgendamiento' => array(),
            'statusReagendamiento' => array()
        );

        $_dia = date('Y-m-d');

        file_put_contents('_calendar/status/st_' . $_dia . '_.json', json_encode($this->profile), LOCK_EX);

        $data = '_calendar/status/st_' . $_dia . '_.json';
        $datos = file_get_contents($data);
        $this->profile = json_decode($datos, true);
        $this->profile['dateCalendar'] = date('d-m-Y H:i',  $this->profile['timestampCalendar']);

        return array(
            'status' => true,
        );
    }

    public function actualizarListaPacientes()
    {

        # Conectar base de datos
        $this->conectar_Oracle();

        $this->setSpanishOracle();

        $fechaProceso = date('d-m-Y');

        # Devolver todos los resultados
        $sql = " SELECT * FROM cad_vw_encuesta_planetree WHERE FECHA_ADMISION = '$fechaProceso' AND COD_DPTO IS NOT NULL AND HABITACION IS NOT NULL AND NOMBRE_MEDICO IS NOT NULL AND ESPECIALIDAD IS NOT NULL  ";

        # Execute
        $stmt = $this->_conexion->query($sql);

        $this->_conexion->close();

        $data = $stmt->fetchAll();

        $_dia = date('Y-m-d');
        $_diaControl = date('Y-m-d H:i:s');

        $lista = '../../v1/kaizala/pacientesmv/' . $fechaProceso . '_.json';

        $datos = file_get_contents($lista);
        $ingresos = json_decode($datos, true);

        $indexAntPtes = array();
        foreach ($ingresos as $k => $v) {

            if (isset($v['AREA'])) {
                $v['AREA'] = $v['AREA'];
            } else if ($v['AREA'] == 'NEONATOLOGIA') {
                $v['AREA_ORIGIN'] = 'NEONATOLOGIA';
                $v['AREA'] = 'HOSPITALIZACION PB - HD';
            } else if ($v['AREA'] == 'TERAPIA INTENSIVA') {
                $v['AREA'] = 'HOSPITALIZACION PB - HD';
                $v['AREA_ORIGIN'] = 'TERAPIA INTENSIVA';
            } else {
                $v['AREA'] = '';
            }

            $indexAntPtes[] = $v['HC'];
        }

        $nuevosPtes = array();
        foreach ($data as $k => $v) {
            $nuevosPtes[] = $v;
        }

        # Agregar nuevos registros
        foreach ($nuevosPtes as $k => $v) {

            if (in_array($v['HC'], $indexAntPtes) == false) {
                $v['PROCESADO'] = 0;
                $ingresos[] = $v;
            }
        }

        $lista = '../../v1/kaizala/pacientesmv/' . $fechaProceso . '_.json';

        $json_string = json_encode($ingresos);
        file_put_contents($lista, $json_string);
    }

    public function archivarListaPacientes()
    {

        $time = new DateTime();
        $nuevoDia = strtotime('-1 day', $time->getTimestamp());
        $_dia = date('Y-m-d', $nuevoDia);

        $lista = '../../v1/kaizala/ptesmv.json';
        $datos = file_get_contents($lista);
        $ingresos = json_decode($datos, true);

        if ($ingresos['fechaProceso'] == $_dia) {

            $lista = '../../v1/kaizala/log_' . $ingresos['fechaProceso'] . '_ptesmv.json';
            $json_string = json_encode($ingresos);
            file_put_contents($lista, $json_string);

            $ingresos['fechaProceso'] = date('Y-m-d');
            $ingresos['numPtesMInterna'] = 0;
            $ingresos['numPtesPediatria'] = 0;
            $ingresos['numPtesCirugia'] = 0;
            $ingresos['numPtesGineco'] = 0;
            $ingresos['numPtesTrauma'] = 0;

            $lista = '../../v1/kaizala/ptesmv.json';
            $json_string = json_encode($ingresos);
            file_put_contents($lista, $json_string);
        }
    }

    public function verificarNumeroPacientes($tipoVerificar = '')
    {
        global $config, $http;

        $dia = date('Y-m-d');
        $diaHora = date('Y-m-d H:i:s');

        $timeInicio = new DateTime($dia . ' 00:00:00');
        $timeProceso = new DateTime($dia . ' 06:59:59');
        $timeControl = new DateTime($diaHora);

        if ($timeControl > $timeInicio && $timeControl < $timeProceso) {
            $time = new DateTime();
            $nuevoDia = strtotime('-1 day', $time->getTimestamp());
            $_dia = date('Y-m-d', $nuevoDia);
            $statusPtes = file_get_contents('../../v1/kaizala/log_' . $_dia . '_ptesmv.json');
            $documento = json_decode($statusPtes, true);
        } else {
            $statusPtes = file_get_contents('../../v1/kaizala/ptesmv.json');
            $documento = json_decode($statusPtes, true);
        }

        # mEDIDINA INTERNA
        if ($tipoVerificar == 'MI') {
            if ($documento['numPtesMInterna'] < 100) {
                return true;
            }
        }

        # CIRUGIA
        if ($tipoVerificar == 'CIRUGIA') {
            if ($documento['numPtesCirugia'] < 100) {
                return true;
            }
        }

        # GINECO
        if ($tipoVerificar == 'GINECO') {
            if ($documento['numPtesGineco'] < 100) {
                return true;
            }
        }

        # TRAUMA
        if ($tipoVerificar == 'TRAUMA') {
            if ($documento['numPtesTrauma'] < 100) {
                return true;
            }
        }

        # PEDIATRA
        if ($tipoVerificar == 'PDTRA') {
            if ($documento['numPtesPediatria'] < 100) {
                return true;
            }
        }

        return false;
    }

    public function sendDataKaizala($dataPte = array())
    {

        global $config;

        $stringData = $dataPte;

        $data = json_encode($stringData, JSON_UNESCAPED_UNICODE);

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, 'https://prod-05.westus.logic.azure.com:443/workflows/42a9aeacc09942ab9023434e76a0bf9e/triggers/manual/paths/invoke?api-version=2016-06-01&sp=%2Ftriggers%2Fmanual%2Frun&sv=1.0&sig=RPHB29P7Irq40ffTqDx7K-ys9hYQaDlTwyBOeeH65G4');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt(
            $ch,
            CURLOPT_HTTPHEADER,
            array(
                'Accept: application/json',
                'Content-Type: application/json',
            )
        );

        $result = curl_exec($ch);
        if (curl_errno($ch)) {
            return false;
            $resultobj = curl_error($ch);
        }
        curl_close($ch);
        $resultobj = json_decode($result);

        return true;
    }

    public function sendDataKaizalaBeta($dataPte = array())
    {

        global $config;

        $stringData = $dataPte;

        $data = json_encode($stringData, JSON_UNESCAPED_UNICODE);

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, 'https://prod-05.westus.logic.azure.com:443/workflows/42a9aeacc09942ab9023434e76a0bf9e/triggers/manual/paths/invoke?api-version=2016-06-01&sp=%2Ftriggers%2Fmanual%2Frun&sv=1.0&sig=RPHB29P7Irq40ffTqDx7K-ys9hYQaDlTwyBOeeH65G4');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt(
            $ch,
            CURLOPT_HTTPHEADER,
            array(
                'Accept: application/json',
                'Content-Type: application/json',
            )
        );

        $result = curl_exec($ch);
        if (curl_errno($ch)) {
            return false;
            $resultobj = curl_error($ch);
        }
        curl_close($ch);
        $resultobj = json_decode($result);

        return true;
    }

    public function crearCita_Api()
    {

        sleep(1);

        global $http;

        $this->setAuth();

        $cita = $this->cita;

        $idCita = (int) $cita['id'];

        $_datos = json_encode($cita, JSON_UNESCAPED_UNICODE);

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, 'http://172.16.253.63:8085/api/schedule-item/v1/book-hour-schedule-item/' . $idCita);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $_datos);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
        curl_setopt(
            $ch,
            CURLOPT_HTTPHEADER,
            array(
                'Accept: application/json',
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->access_token,
            )
        );

        $response = curl_exec($ch);

        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($http_code == 200) {
            # return $response;
            return true;
        } else {
            return false;
        }

        # return $response;

        curl_close($ch);
    }

    public function cancelarCita_Api()
    {

        sleep(1);

        global $http;

        $this->setAuth();

        $cita = $this->cita;

        $idCita = (int) $cita['id'];

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, 'http://172.16.253.63:8085/api/schedule-item/v1/cancel-schedule-item/' . $idCita);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt(
            $ch,
            CURLOPT_HTTPHEADER,
            array(
                'Accept: application/json',
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->access_token,
            )
        );

        $response = curl_exec($ch);

        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($http_code == 200) {
            # return $response;
            return true;
        } else {
            return false;
        }

        # return $response;

        curl_close($ch);
    }

    private function conectar_Oracle_SML()
    {
        global $config;
        $_config = new \Doctrine\DBAL\Configuration();
        # SETEAR LA CONNEXION A LA BASE DE DATOS DE ORACLE GEMA
        $this->_conexion = \Doctrine\DBAL\DriverManager::getConnection($config['database']['drivers']['oracle_mv_sml'], $_config);
    }

    private function conectar_Oracle()
    {
        global $config;

        $_config = new \Doctrine\DBAL\Configuration();

        # SETEAR LA CONNEXION A LA BASE DE DATOS DE ORACLE GEMA
        $this->_conexion = \Doctrine\DBAL\DriverManager::getConnection($config['database']['drivers']['oracle'], $_config);
    }

    private function setParameters()
    {

        global $http;

        foreach ($http->request->all() as $key => $value) {
            $this->$key = $value;
        }
    }

    public function getPaciente($nhc)
    {

        try {

            # Conectar base de datos
            $this->conectar_Oracle();

            $this->setSpanishOracle();

            # Devolver todos los resultados
            $sql = " SELECT a.discriminante, TRUNC (a.fecha_admision) fecha_admision, a.pk_numero_admision nro_admision, a.pk_fk_paciente hc, e.fk_persona COD_PERSONA,

            fun_calcula_anios_a_fecha(f.fecha_nacimiento,TRUNC(a.fecha_admision)) edad,

            f.primer_apellido || ' ' || f.segundo_apellido || ' ' || f.primer_nombre || ' ' || f.segundo_nombre nombre_paciente,

            b.pk_fk_medico cod_medico, fun_busca_nombre_medico(b.pk_fk_medico) nombre_medico, d.descripcion especialidad,

            fun_busca_ubicacion_corta(1,a.pk_fk_paciente,a.pk_numero_admision) nro_habitacion,

            fun_busca_diagnostico(1,a.pk_fk_paciente, a.pk_numero_admision) dg_principal

            FROM cad_admisiones a, cad_medicos_admision b, edm_medicos_especialidad c, aas_especialidades d, cad_pacientes e, bab_personas f

            WHERE a.alta_clinica         IS NULL            AND

                  a.pre_admision         = 'N'              AND

                  a.anulado              = 'N'              AND

                  a.discriminante        IN ('HPN','EMA')   AND

                  a.pk_fk_paciente       = b.pk_fk_paciente AND

                  a.pk_numero_admision   = b.pk_fk_admision AND

                  b.clasificacion_medico = 'TRA'            AND

                  b.pk_fk_medico         = c.pk_fk_medico   AND

                  c.principal            = 'S'              AND

                  c.pk_fk_especialidad   = d.pk_codigo      AND

                  a.pk_fk_paciente       = e.pk_nhcl        AND

                  e.fk_persona           = f.pk_codigo      AND

                  e.pk_nhcl =  '$nhc' ";

            # Execute
            $stmt = $this->_conexion->query($sql);

            $this->_conexion->close();

            $data = $stmt->fetch();

            return array(
                'status' => true,
                'data' => $data,
            );
        } catch (ModelsException $e) {
            return array('status' => false, 'data' => [], 'message' => $e->getMessage());
        }
    }

    public function getReportesPEP()
    {

        try {

            global $http;

            $url = $http->request->get('url');

            $data = base64_encode(file_get_contents($url));

            return array(
                'status' => true,
                'data' => $data,
                'message' => 'Proceso realizado con éxito.',
            );
        } catch (\Exception $e) {

            return array(
                'status' => false,
                'data' => [],
                'message' => $e->getMessage()
            );
        }
    }


    private function setSpanishOracle()
    {

        $sql = "alter session set NLS_LANGUAGE = 'SPANISH'";
        # Execute
        $stmt = $this->_conexion->query($sql);

        $sql = "alter session set NLS_TERRITORY = 'SPAIN'";
        # Execute
        $stmt = $this->_conexion->query($sql);

        $sql = " alter session set NLS_DATE_FORMAT = 'DD-MM-YYYY' ";
        # Execute
        $stmt = $this->_conexion->query($sql);
    }

    private function isRange($value)
    {

        $pos = strpos($value, 'fechas');

        if ($pos !== false) {
            return true;
        } else {
            return false;
        }
    }

    private function get_Order_Pagination(array $arr_input)
    {
        # SI ES DESCENDENTE

        $arr = array();
        $NUM = 1;

        if ($this->sortType == 'desc') {

            $NUM = count($arr_input);
            foreach ($arr_input as $key) {
                $key['NUM'] = $NUM;
                $arr[] = $key;
                $NUM--;
            }

            return $arr;
        }

        # SI ES ASCENDENTE

        foreach ($arr_input as $key) {
            $key['NUM'] = $NUM;
            $arr[] = $key;
            $NUM++;
        }

        return $arr;
    }

    private function quitar_tildes($cadena)
    {
        $no_permitidas = array("%", "é", "í", "ó", "ú", "É", "Í", "Ó", "Ú", "ñ", "À", "Ã", "Ì", "Ò", "Ù", "Ã™", "Ã ", "Ã¨", "Ã¬", "Ã²", "Ã¹", "ç", "Ç", "Ã¢", "ê", "Ã®", "Ã´", "Ã»", "Ã‚", "ÃŠ", "ÃŽ", "Ã”", "Ã›", "ü", "Ã¶", "Ã–", "Ã¯", "Ã¤", "«", "Ò", "Ã", "Ã„", "Ã‹");
        $permitidas = array("", "e", "i", "o", "u", "E", "I", "O", "U", "n", "N", "A", "E", "I", "O", "U", "a", "e", "i", "o", "u", "c", "C", "a", "e", "i", "o", "u", "A", "E", "I", "O", "U", "u", "o", "O", "i", "a", "e", "U", "I", "A", "E");
        $texto = str_replace($no_permitidas, $permitidas, $cadena);
        return $texto;
    }

    private function sanear_string($string)
    {

        $string = trim($string);

        //Esta parte se encarga de eliminar cualquier caracter extraño
        $string = str_replace(
            array(">", "< ", ";", ",", ":", "%", "|", "-", "/"),
            ' ',
            $string
        );

        return trim($string);
    }

    private function get_page(array $input, $pageNum, $perPage)
    {
        $start = ($pageNum - 1) * $perPage;
        $end = $start + $perPage;
        $count = count($input);

        // Conditionally return results
        if ($start < 0 || $count <= $start) {
            // Page is out of range
            return array();
        } else if ($count <= $end) {
            // Partially-filled page
            return array_slice($input, $start);
        } else {
            // Full page
            return array_slice($input, $start, $end - $start);
        }
    }

    private function notResults(array $data)
    {
        if (count($data) == 0) {
            throw new ModelsException('No existe más resultados.', 4080);
        }
    }

    /**
     * __construct()
     */

    public function __construct(IRouter $router = null)
    {
        parent::__construct($router);
        $this->startDBConexion();
    }
}
