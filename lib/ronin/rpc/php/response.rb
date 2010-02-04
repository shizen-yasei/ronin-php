#
# Ronin PHP - A Ruby library for Ronin that provides support for PHP
# related security tasks.
#
# Copyright (c) 2007-2010 Hal Brodigan (postmodern.mod3 at gmail.com)
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

require 'ronin/rpc/exceptions/response_missing'
require 'ronin/rpc/response'

require 'xmlrpc/client'

module Ronin
  module RPC
    module PHP
      class Response < RPC::Response

        #
        # The default XML parser to use for parsing XMLRPC responses.
        #
        # @return [XMLRPC::XMLParser::REXMLStreamParser]
        #   The default XML parser.
        #
        def Response.parser
          @@parser ||= XMLRPC::XMLParser::REXMLStreamParser.new
        end

        #
        # Sets the XML parser used for parsing XMLRPC responses.
        #
        # @param [XMLRPC::XMLParser::AbstractStreamParser] new_parser
        #   The new parser to use.
        #
        # @return [XMLRPC::XMLParser::AbstractStreamParser]
        #   The new parser.
        #
        def Response.parser=(new_parser)
          @@parser = new_parser
        end

        #
        # Decodes the XMLRPC response message embedded in the response
        # from the server.
        #
        # @return [Array]
        #   The status and additional parameters in the response.
        #
        # @raise [ResponseMissing]
        #   The response does not contain any information from the
        #   RPC Server.
        #
        def decode
          response = @contents[/<rpc>.*<\/rpc>/m]

          unless response
            raise(ResponseMissing,"failed to receive a valid RPC method response",caller)
          end

          return Response.parser.parseMethodResponse(response)
        end

      end
    end
  end
end
