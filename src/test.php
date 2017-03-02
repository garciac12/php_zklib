<?php
/**
 * Created by PhpStorm.
 * User: cgarcia
 * Date: 14/02/17
 * Time: 8:15
 */
include 'ZKConst.php';
include 'ZkSocket.php';
$o = new ZkSocket('192.168.100.89');
$o->connect();
var_dump( $o->getDeviceName() );
//var_dump( $o->getAttendance() );
$o->disconnect();