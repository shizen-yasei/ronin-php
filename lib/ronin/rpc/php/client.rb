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

require 'ronin/rpc/php/call'
require 'ronin/rpc/php/response'
require 'ronin/rpc/php/console'
require 'ronin/rpc/php/shell'
require 'ronin/rpc/client'
require 'ronin/network/http'

module Ronin
  module RPC
    module PHP
      class Client < RPC::Client

        # URL of RPC server
        attr_reader :url

        # Proxy to send requests through
        attr_accessor :proxy

        # User-Agent string to send with each request
        attr_accessor :user_agent

        # State data
        attr_reader :state

        # Provides a console service
        service :console, Console

        # Provides a shell service
        service :shell, Shell

        #
        # Creates a new Client object.
        #
        # @param [URI::HTTP, String] url
        #   The url that the server is located at.
        #
        # @param [Hash] options
        #   Additional options.
        #
        # @option options [Hash] :proxy
        #   The proxy settings to use when communicating with the server.
        #
        # @option options [String] :user_agent
        #   The User-Agent to send to the server.
        #
        # @option options [String] :user_agent_alias
        #   The User-Agent alias to send to the server.
        #
        def initialize(url,options={})
          @url = url

          @proxy = options[:proxy]

          if options[:user_agent_alias]
            @user_agent = Web.user_agent_alias[options[:user_agent_alias]]
          else
            @user_agent = options[:user_agent]
          end

          @cookie = nil
          @state = {}
        end

        def call_url(call_object)
          new_url = URI(@url.to_s)
          new_url.query_params['rpcrequest'] = call_object.encode(@state)

          return new_url
        end

        #
        # Determines if the RPC Server is running and responding to
        # function calls.
        #
        # @return [Boolean]
        #   Specifies whether the RPC Server is running.
        #
        def running?
          call(:running)
        end

        #
        # Finger-prints of the PHP server.
        #
        # @return [Hash]
        #   Finger-print information.
        #
        def fingerprint
          call(:fingerprint)
        end

        protected

        #
        # Creates a new {Call} object.
        #
        # @param [Symbol] function
        #   Name of the function to call.
        #
        # @param [Array] arguments
        #   Additional arguments to pass to the function.
        #
        # @return [Call]
        #   The newly created Call object for the function call.
        #
        def create_call(function,*arguments)
          Call.new(function,*arguments)
        end

        #
        # Sends a function call to the RPC server.
        #
        # @param [Call] call_object
        #   The function call to send to the RPC Server.
        #
        # @return [Response]
        #   The response from the RPC Server.
        #
        def send_call(call_object)
          resp = Net.http_get(
            :url => call_url(call_object),
            :cookie => @cookie,
            :proxy => @proxy,
            :user_agent => @user_agent
          )

          new_cookie = resp['Set-Cookie']
          @cookie = new_cookie if new_cookie

          return Response.new(resp.body)
        end

        #
        # Decodes the return-value of a previous function from a given
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
        def return_value(response)
          response = response.decode

          if response['type'] == 'error'
            raise(response['message'])
          end

          @state.merge!(response['state'])

          unless response['output'].empty?
            print(response['output'])
          end

          return response['return_value']
        end

      end
    end
  end
end
