/*
 * Ronin AJAX PHP-RPC Console - A jQuery based XMLRPC console designed to
 * work in hostile environments.
 *
 * Copyright (c) 2007-2009 Hal Brodigan (postmodern.mod3 at gmail.com)
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

var UI = {
  catchExceptions: function(callback) {
    try
    {
      callback();
    }
    catch(exception)
    {
      var mesg = $('<p class="exception"/>').text(exception);

      mesg.insertBefore("input.terminal_textarea").hide();
      mesg.slideDown('slow').delay(3000).fadeOut('slow',mesg.remove);
    }
  },

  Shell: {
    clear: function() {
      $("#console_shell").terminalClear();
    },

    print: function(message) {
      $("#console_shell").terminalPrint(message);
    },

    exec: function(command) {
      UI.catchExceptions(function() {
        PHP_RPC.callService('shell','exec',new Array(command),function(output) {
          if (output.error != null)
          {
            UI.Shell.print(output.error);
          }
          else
          {
            var text = '$ ' + command + "\n";

            if (output.returnValue != null && output.returnValue.length > 0)
            {
              text += output.returnValue;
            }

            UI.Shell.print(text);
          }
        });
      });
    }
  },

  PHP: {
    clear: function() {
      $("#console_php").terminalClear();
    },

    print: function(message) {
      $("#console_php").terminalPrint(message);
    },

    inspect: function(code) {
      UI.catchExceptions(function() {
        PHP_RPC.callService('console','inspect',new Array(code),function(response) {
          if (response.error != null)
          {
            UI.PHP.print(response.error);
          }
          else
          {
            var text = '>> ' + code + "\n";

            if (response.output != null)
            {
              text = text + response.output;
            }

            UI.PHP.print(text + "=> " + response.returnValue + "\n");
          }
        });
      });
    }
  }
};
