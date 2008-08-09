#
#--
# Ronin PHP - A Ruby library for Ronin that provides support for PHP
# related security tasks.
#
# Copyright (c) 2007 Hal Brodigan (postmodern.mod3 at gmail.com)
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
#++
#

require 'ronin/rpc/call'
require 'ronin/formatting/binary'

require 'xmlrpc/client'

module Ronin
  module RPC
    module PHP
      class Call < RPC::Call

        #
        # Creates a new Call object with the specified _name_ and the given
        # _arguments_.
        #
        def initialize(name,*arguments)
          super(name,*arguments)
        end

        #
        # Encodes the call and the given _session_ variables into a base64
        # encoded XMLRPC call message.
        #
        def encode(session={})
          XMLRPC::Create.new.methodCall(@name,session,*(@arguments)).base64_encode
        end

      end
    end
  end
end