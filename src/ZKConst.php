<?php

/**
 * Created by PhpStorm.
 * User: cgarcia
 * Date: 2/02/17
 * Time: 13:31
 */
class ZKConst
{
    const USHRT_MAX = 65535;
    const CMD_CONNECT = 1000;
    const CMD_EXIT = 1001;
    const CMD_ENABLEDEVICE = 1002;
    const CMD_DISABLEDEVICE = 1003;
    const CMD_ACK_OK = 2000;
    const CMD_ACK_ERROR = 2001;
    const CMD_ACK_DATA = 2002;
    const CMD_PREPARE_DATA = 1500;
    const CMD_DATA = 1501;
    const CMD_SET_USER = 8;
    const CMD_USERTEMP_RRQ = 9;
    const CMD_DEVICE = 11;
    const CMD_ATTLOG_RRQ = 13;
    const CMD_CLEAR_DATA = 14;
    const CMD_CLEAR_ATTLOG = 15;
    const CMD_DEL_USER = 18;
    const CMD_CLEAR_ADMIN = 20;
    const CMD_WRITE_LCD = 66;
    const CMD_GET_TIME = 201;
    const CMD_SET_TIME = 202;
    const CMD_VERSION = 1100;
    const CMD_GET_FREE_SIZES = 50;
    const LEVEL_USER = 0;
    const LEVEL_ADMIN = 14;
    const DEVICE_GENERAL_INFO_STRING_LENGTH = 184;
}