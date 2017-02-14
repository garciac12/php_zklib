<?php

/**
 * Created by PhpStorm.
 * User: cgarcia
 * Date: 18/01/17
 * Time: 13:02
 */
class ZkSocket
{
    /**
     * @var $socket
     */
    private $socket;

    /**
     * @var string
     */
    private $ip;

    /**
     * @var integer
     */
    private $port;

    /**
     * @var array
     */
    private $timeout = array('sec'=>60,'usec'=>500000);

    /** @var  string */
    private $data;

    /** @var  integer */
    private $session_id;

    /** @var integer */
    private $reply_id;

    /**
     * @var null
     */
    private $result = null;

    public function __construct($ip = '', $port = 4370)
    {
        $this->port = $port;
        $this->ip = $ip;
    }

    /**
     * @return string
     */
    public function getIp()
    {
        return $this->ip;
    }

    /**
     * @param string $ip
     * @return ZkSocket
     */
    public function setIp($ip)
    {
        $this->ip = $ip;
        return $this;
    }

    /**
     * @return int
     */
    public function getPort()
    {
        return $this->port;
    }

    /**
     * @param int $port
     * @return ZkSocket
     */
    public function setPort($port)
    {
        $this->port = $port;
        return $this;
    }

    /**
     * @return array
     */
    public function getTimeout()
    {
        return $this->timeout;
    }

    /**
     * @param array $timeout
     * @return ZkSocket
     */
    public function setTimeout($timeout)
    {
        $this->timeout = $timeout;
        return $this;
    }

    /**
     * @return null
     */
    public function getResult()
    {
        return $this->result;
    }

    /**
     * @param null $result
     * @return ZkSocket
     */
    public function setResult($result)
    {
        $this->result = $result;
        return $this;
    }



    /**
     * Junta lo necesario para poder ejecutar el comando contra el dispositivo y lo convierte en una cadena de bytes.
     * @param $command
     * @param $chksum
     * @param $session_id
     * @param $reply_id
     * @param $command_string
     * @return string
     */
    private function createHeader($command, $command_string, $chksum=0) {
        $buf = pack('SSSS', $command, $chksum, $this->session_id, $this->reply_id).$command_string;

        $buf = unpack('C'.(8+strlen($command_string)).'c', $buf);

        $u = unpack('S', $this->createChkSum($buf));

        if ( is_array( $u ) ) {
            while( list( $key ) = each( $u ) ) {
                $u = $u[$key];
                break;
            }
        }
        $chksum = $u;

        $this->reply_id += 1;

        if ($this->reply_id >= USHRT_MAX) {
            $this->reply_id -= USHRT_MAX;
        }

        $buf = pack('SSSS', $command, $chksum, $this->session_id, $this->reply_id);

        return $buf.$command_string;

    }

    /**
     * Calcula el checksum para el envio de datos
     * Copiada de  zkemsdk.c
     *
     * @param $buffer
     * @return string
     */
    protected function createChkSum($buffer) {

        $l = count($buffer);
        $chksum = 0;
        $i = $l;
        $j = 1;
        while ($i > 1) {
            $u = unpack('S', pack('C2', $buffer['c'.$j], $buffer['c'.($j+1)] ) );

            $chksum += $u[1];

            if ( $chksum > ZKConst::USHRT_MAX )
                $chksum -= ZKConst::USHRT_MAX;
            $i-=2;
            $j+=2;
        }

        if ($i)
            $chksum = $chksum + $buffer['c'.strval(count($buffer))];

        while ($chksum > ZKConst::USHRT_MAX)
            $chksum -= ZKConst::USHRT_MAX;

        if ( $chksum > 0 )
            $chksum = -($chksum);
        else
            $chksum = abs($chksum);

        $chksum -= 1;
        while ($chksum < 0)
            $chksum += ZKConst::USHRT_MAX;

        return pack('S', $chksum);
    }

    function checkValid($reply) {
        /*Checks a returned packet to see if it returned CMD_ACK_OK,
        indicating success*/
        $u = unpack('H2h1/H2h2', substr($reply, 0, 8) );

        $command = hexdec( $u['h2'].$u['h1'] );
        if ($command == CMD_ACK_OK)
            return TRUE;
        else
            return FALSE;
    }

    public function connect()
    {
        try {
            $this->socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
            socket_set_option($this->socket, SOL_SOCKET, SO_RCVTIMEO, $this->timeout);

            $this->reply_id = (-1 + ZKConst::USHRT_MAX);
            return $this->execute(ZKConst::CMD_CONNECT, '');

        } catch (\Exception $ex) {
            return false;
        }
    }

    public function disconnect()
    {
        if($this->socket) {

            $this->execute(ZKConst::CMD_EXIT, '');

            socket_close($this->socket);
        }
    }

    private function unpackSession()
    {
        $u = unpack('H2h1/H2h2/H2h3/H2h4/H2h5/H2h6', substr( $this->data, 0, 8 ) );

        $this->session_id =  hexdec( $u['h6'].$u['h5'] );
    }

    private function unpackReply()
    {
        $u = unpack('H2h1/H2h2/H2h3/H2h4/H2h5/H2h6/H2h7/H2h8', substr( $this->data, 0, 8) );

        $this->reply_id = hexdec( $u['h8'].$u['h7'] );
    }

    private function execute($command, $command_string)
    {
        try {
            $buf = $this->createHeader($command, $command_string);

            socket_sendto($this->socket, $buf, strlen($buf), 0, $this->ip, $this->port);
            @socket_recvfrom($this->socket, $this->data, 1024, 0, $this->ip, $this->port);

            if ( strlen( $this->data ) > 0 ) {
                $this->unpackSession();
                $this->unpackReply();

                if ($this->checkValid($this->data) ) {
                    return substr( $this->data, 8 );
                }
            }

        } catch (\Exception $ex) {
            print_r($ex->getMessage());
        }

        return false;
    }

    public function getDeviceName()
    {
        return $this->execute(ZKConst::CMD_DEVICE, '~DeviceName');
    }

    public function enable()
    {
        return $this->execute(ZKConst::CMD_ENABLEDEVICE, '');
    }

    public function disable()
    {
        return $this->execute(ZKConst::CMD_DISABLEDEVICE,  chr(0).chr(0));
    }

    public function getOs()
    {
        return $this->execute(ZKConst::CMD_DEVICE,  '~OS');
    }

    public function getPlatform()
    {
        return $this->execute(ZKConst::CMD_DEVICE,  '~Platform');
    }

    public function getPlatformVersion()
    {
        return $this->execute(ZKConst::CMD_DEVICE,  '~ZKFPVersion');
    }

    public function getSerialNumber()
    {
        return $this->execute(ZKConst::CMD_DEVICE,  '~SerialNumber');
    }

    public function getSsr()
    {
        return $this->execute(ZKConst::CMD_DEVICE,  '~SSR');
    }

    public function getWorkCode()
    {
        return $this->execute(ZKConst::CMD_DEVICE,  'WorkCode');
    }

    public function getPinWidth()
    {
        return $this->execute(ZKConst::CMD_DEVICE,  '~PIN2Width');
    }

    public function getFaceOn()
    {
        return $this->execute(ZKConst::CMD_DEVICE,  'FaceFunOn');
    }

    public function getVersion()
    {
        return $this->execute(ZKConst::CMD_VERSION,  '');
    }

    public function getTime()
    {
        $data = $this->execute(ZKConst::CMD_GET_TIME,  '');

        $hex = bin2hex( substr( $data, 8 ) ) ;
        $tmp = '';
        for ( $i = strlen($hex); $i>=0; $i-- ) {
            $tmp .= substr($hex, $i, 2);
            $i--;
        }

        $t = hexdec( $tmp );
        /*Decode a timestamp retrieved from the timeclock

        copied from zkemsdk.c - DecodeTime*/
        $second = $t % 60;
        $t = $t / 60;

        $minute = $t % 60;
        $t = $t / 60;

        $hour = $t % 24;
        $t = $t / 24;

        $day = $t % 31+1;
        $t = $t / 31;

        $month = $t % 12+1;
        $t = $t / 12;

        $year = floor( $t + 2000 );

        return date("Y-m-d H:i:s", strtotime( $year.'-'.$month.'-'.$day.' '.$hour.':'.$minute.':'.$second) );
    }

    public function setTime(\DateTime $dateTime)
    {
        /*Encode a timestamp send at the timeclock

        copied from zkemsdk.c - EncodeTime*/
        $timestamp = ( ($dateTime->format('Y') % 100) * 12 * 31 + (($dateTime->format('m') - 1) * 31) + $dateTime->format('d') - 1) *
            (24 * 60 * 60) + ($dateTime->format('H') * 60 + $dateTime->format('i')) * 60 + $dateTime->format('s');

        return $this->execute(ZKConst::CMD_SET_TIME,  pack('I', $timestamp));
    }

    public function getAttendance()
    {
        try {
            $data = null;
            $attendance_data = null;
            $this->execute(ZKConst::CMD_ATTLOG_RRQ,  '');

            $size = $this->getSizeAttendance();
            if($size) {
                while ( $size > 0 ) {
                    @socket_recvfrom($this->socket, $data, 1032, 0, $this->ip, $this->port);
                    array_push( $attendance_data, $data);
                    $size -= 1024;
                }
                @socket_recvfrom($this->socket, $data, 1024, 0, $this->ip, $this->port);
            }


            $attendance = [];
            if ( count($attendance_data) > 0 ) {
                # The first 4 bytes don't seem to be related to the user
                for ( $x=0; $x<count($attendance_data); $x++) {
                    if ( $x > 0 )
                        $attendance_data[$x] = substr( $attendance_data[$x], 8 );
                }

                $attendancedata = implode( '', $attendance_data );
                $attendancedata = substr( $attendancedata, 10 );

                while ( strlen($attendancedata) > 40 ) {

                    $u = unpack( 'H78', substr( $attendancedata, 0, 39 ) );
                    //24s1s4s11s
                    //print_r($u);

                    //$uid = hexdec( substr( $u[1], 0, 6 ) );
                    //$uid = explode(chr(0), $uid);
                    //$uid = intval( $uid[0] );
                    $u1 = hexdec( substr($u[1], 4, 2) );
                    $u2 = hexdec( substr($u[1], 6, 2) );
                    $uid = $u1+($u2*256);
                    $id = intval( str_replace("\0", '', hex2bin( substr($u[1], 6, 8) ) ) );
                    $state = hexdec( substr( $u[1], 56, 2 ) );
                    $timestamp = decode_time( hexdec( reverseHex( substr($u[1], 58, 8) ) ) );

                    # Clean up some messy characters from the user name
                    #uid = unicode(uid.strip('\x00|\x01\x10x'), errors='ignore')
                    #uid = uid.split('\x00', 1)[0]
                    #print "%s, %s, %s" % (uid, state, decode_time( int( reverseHex( timestamp.encode('hex') ), 16 ) ) )

                    array_push( $attendance, array( $uid, $id, $state, $timestamp ) );

                    $attendancedata = substr( $attendancedata, 40 );
                }

            }
            return $attendance;
        } catch (\Exception $ex) {
            var_dump($ex->getMessage());
        }
    }

    private function getSizeAttendance()
    {
    /* Checks a returned packet to see if it returned CMD_PREPARE_DATA,
     * indicating that data packets are to be sent
     * Returns the amount of bytes that are going to be sent
     **/
        $u = unpack('H2h1/H2h2/H2h3/H2h4/H2h5/H2h6/H2h7/H2h8', substr( $this->data, 0, 8) );
        $command = hexdec( $u['h2'].$u['h1'] );

        if ( $command == CMD_PREPARE_DATA ) {
            $u = unpack('H2h1/H2h2/H2h3/H2h4', substr( $this->data, 8, 4 ) );
            $size = hexdec($u['h4'].$u['h3'].$u['h2'].$u['h1']);
            return $size;
        } else
            return false;
    }

}
