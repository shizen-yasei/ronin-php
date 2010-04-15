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
