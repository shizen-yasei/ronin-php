function rpc_method_proxy($method,$arguments,$server)
{
  $session = array_shift($arguments);
  $func = $server->methods[$method];

  $server->load_session($session);

  ob_start();

  if (is_array($func))
  {
    $ret = $func[0]->$func[1]($arguments);
  }
  else
  {
    $ret = $func($arguments);
  }

  $output = ob_get_contents();
  ob_end_clean();

  $new_session = $server->save_session();

  return array(
    'session' => $new_session,
    'output' => $output,
    'return_value' => $ret
  );
}

class RPCServer
{
  var $_server;

  var $methods;

  var $services;

  function RPCServer()
  {
    $this->_server = xmlrpc_server_create();
    $this->methods = array();
    $this->services = array();
  }

  function load_session($session)
  {
    foreach ($session as $name => $values)
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

    return xmlrpc_server_register_method($this->_server, $name, 'rpc_method_proxy');
  }

  function register_service($name,&$service)
  {
    $this->services[$name] =& $service;

    foreach ($service->methods as $rpc_name => $method)
    {
      $this->register_method("{$name}.{$rpc_name}",array(&$service, $method));
    }
  }

  function call_method($xml)
  {
    return xmlrpc_server_call_method($this->_server, $xml, $this);
  }

  function rpc_services($method)
  {
    return array_keys($this->services);
  }

  function save_session()
  {
    $session = array();

    foreach ($this->services as $name => $service)
    {
      $session[$name] = array();

      foreach ($service->persistant as $var)
      {
        $session[$name][$var] = $service->$var;
      }
    }

    return $session;
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
