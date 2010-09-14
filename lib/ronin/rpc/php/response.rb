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
require 'ronin/rpc/exceptions/invalid_response'
require 'ronin/rpc/response'

require 'set'
require 'base64'
require 'ffi/msgpack'

module Ronin
  module RPC
    module PHP
      class Response < RPC::Response

        # Valid response types
        VALID_TYPES = Set['error', 'return']

        # Valid response keys for the response types
        VALID_KEYS = {
          'error' => Set['message'],
          'return' => Set[
            'state',
            'output',
            'return_value'
          ]
        }

        #
        # Decodes the Base64 / MessagePack response embedded in the
        # response from the server.
        #
        # @return [Hash]
        #   The type and additional parameters in the response.
        #
        # @raise [ResponseMissing]
        #   The response does not contain any information.
        #
        # @raise [InvalidResponse]
        #   The decoded response could not be decoded or was malformed.
        #
        def decode
          match = @contents.match(/<rpc-response>(.*)<\/rpc-response>/m)

          unless (match && match[1])
            raise(ResponseMissing,"failed to receive a valid RPC response",caller)
          end

          response = FFI::MsgPack.unpack(Base64.decode64(match[1]))

          unless response.kind_of?(Hash)
            raise(InvalidResponse,"decoded RPC response was not a Hash",caller)
          end

          unless response['type']
            raise(InvalidResponse,"decoded RPC response does not have a 'type' key",caller)
          end

          unless VALID_TYPES.include?(response['type'])
            raise(InvalidResponse,"invalid RPC response type #{response['type'].dump}",caller)
          end

          VALID_KEYS[response['type']].each do |key|
            unless response.has_key?(key)
              raise(InvalidResponse,"decoded RPC response does not have a #{key.dump} key",caller)
            end
          end

          return response
        end

      end
    end
  end
end
