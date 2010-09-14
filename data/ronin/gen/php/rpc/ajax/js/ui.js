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

var Shell = {
  clear: function() {
    $("#console_shell").terminalClear();
  },

  print: function(message) {
    $("#console_shell").terminalPrint(message);
  },

  exec: function(command) {
    PHP_RPC.callService('shell','exec',new Array(command),function(output) {
      if (output.error != null)
      {
        Shell.print(output.error);
      }
      else
      {
        var text = '$ ' + command + "\n";

        if (output.returnValue != null && output.returnValue.length > 0)
        {
          text += output.returnValue;
        }

        Shell.print(text);
      }
    });
  }
};

var PHP = {
  clear: function() {
    $("#console_php").terminalClear();
  },

  print: function(message) {
    $("#console_php").terminalPrint(message);
  },

  inspect: function(code) {
    PHP_RPC.callService('console','inspect',new Array(code),function(response) {
      if (response.error != null)
      {
        PHP.print(response.error);
      }
      else
      {
        var text = '>> ' + code + "\n";

        if (response.output != null)
        {
          text = text + response.output;
        }

        PHP.print(text + "=> " + response.returnValue + "\n");
      }
    });
  }
};
