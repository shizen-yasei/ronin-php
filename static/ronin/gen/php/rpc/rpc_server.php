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
