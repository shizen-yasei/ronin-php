<html>
  <head>
    <title>Ronin::PHP - AJAX PHP-RPC Console</title>
    <%= css_min read_template('ajax/css/layout.css') %>
    <%= js_min read_template('ajax/js/base64.js') %>
    <%= js_min read_template('ajax/js/jquery.min.js') %>
    <%= js_min read_template('ajax/js/jquery-ui-personalized.min.js') %>
    <%= js_min read_template('ajax/js/jquery.terminal.js') %>
    <%= js_min read_template('ajax/js/jquery.phprpc.js') %>
    <%= js_min read_template('ajax/js/ui.js') %>
    <script type="text/javascript">
      $(document).ready(function() {
        $("#console_shell").terminal(function(input) {
          shell.exec(input);
        });

        $("#console_php").terminal(function(input) {
          php.inspect(input);
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
