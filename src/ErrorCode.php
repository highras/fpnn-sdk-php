<?php 

namespace highras\fpnn;

class ErrorCode {
	public static $FPNN_EC_OK = 0;
	public static $FPNN_EC_PROTO_UNKNOWN_ERROR			= 10001;
	public static $FPNN_EC_PROTO_NOT_SUPPORTED			= 10002;
	public static $FPNN_EC_PROTO_INVALID_PACKAGE	    	= 10003;
	public static $FPNN_EC_PROTO_JSON_CONVERT		    	= 10004;
	public static $FPNN_EC_PROTO_STRING_KEY		    	= 10005;
	public static $FPNN_EC_PROTO_MAP_VALUE				= 10006;
	public static $FPNN_EC_PROTO_METHOD_TYPE		    	= 10007;
	public static $FPNN_EC_PROTO_PROTO_TYPE		    	= 10008;
	public static $FPNN_EC_PROTO_KEY_NOT_FOUND			= 10009;
	public static $FPNN_EC_PROTO_TYPE_CONVERT		    	= 10010;
	public static $FPNN_EC_PROTO_FILE_SIGN				= 10011;
	public static $FPNN_EC_PROTO_FILE_NOT_EXIST           = 10012;

	//for core
	public static $FPNN_EC_CORE_UNKNOWN_ERROR			    = 20001;
	public static $FPNN_EC_CORE_CONNECTION_CLOSED		    = 20002;
	public static $FPNN_EC_CORE_TIMEOUT				    = 20003;
	public static $FPNN_EC_CORE_UNKNOWN_METHOD			= 20004;
	public static $FPNN_EC_CORE_ENCODING				    = 20005;
	public static $FPNN_EC_CORE_DECODING				    = 20006;
	public static $FPNN_EC_CORE_SEND_ERROR				= 20007;
	public static $FPNN_EC_CORE_RECV_ERROR				= 20008;
	public static $FPNN_EC_CORE_INVALID_PACKAGE		    = 20009;
	public static $FPNN_EC_CORE_HTTP_ERROR				= 20010;
	public static $FPNN_EC_CORE_WORK_QUEUE_FULL		    = 20011;
	public static $FPNN_EC_CORE_INVALID_CONNECTION		= 20012;
	public static $FPNN_EC_CORE_FORBIDDEN				    = 20013;
	public static $FPNN_EC_CORE_SERVER_STOPPING           = 20014;

	//for other
	public static $FPNN_EC_ZIP_COMPRESS                   = 30001;
	public static $FPNN_EC_ZIP_DECOMPRESS                 = 30002;	
}
