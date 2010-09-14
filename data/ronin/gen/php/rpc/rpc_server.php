class RPCServer
{
  var $_msgpack;

  var $methods;

  var $services;

  function RPCServer()
  {
    $this->_msgpack = new MsgPack_Coder();
    $this->methods = array();
    $this->services = array();
  }

  function error_msg($message)
  {
    return $this->_msgpack->encode(array(
      'type' => 'error',
      'message' => $message
    ));
  }

  function response_msg($state,$output,$return_value)
  {
    return $this->_msgpack->encode(array(
      'type' => 'response',
      'state' => $state,
      'output' => $output,
      'return_value' => $return_value,
    ));
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

  function call_method($msg)
  {
    $call_msg = $this->_msgpack->decode($msg);

    if (!is_array($call_msg))
    {
      return error_msg('Invalid Request message');
    }

    if (!$call_msg['name'])
    {
      return error_msg('Invalid Method Call');
    }

    $method_name = $call_msg['name'];

    if (!$this->methods[$method_name])
    {
      return error_msg('Unknown method: ' + $method_name);
    }

    $state = $call_msg['state'];

    if ($state)
    {
      $server->load_state($state);
    }

    $func = $server->methods[$method];
    $arguments = $call_msg['arguments'];

    if (!$arguments)
    {
      $arguments = array();
    }

    ob_start();

    $return_value = call_user_func_array($func,$arguments);

    $output = ob_get_contents();
    ob_end_clean();

    $updated_state = $this->save_state();

    return response_msg($updated_state,$output,$return_value);
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
