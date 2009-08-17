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

        # Session data
        attr_reader :session

        # Provides a console service
        service :console, Console

        # Provides a shell service
        service :shell, Shell

        #
        # Creates a new Client object with the specified _url_ and the
        # given _options_.
        #
        # _options_ may contain the following keys:
        # <tt>:proxy</tt>:: The proxy settings to use when communicating
        #                   with the server.
        # <tt>:user_agent</tt>:: The User-Agent to send to the server.
        # <tt>:user_agent_alias</tt>:: The User-Agent alias to send to
        #                              the server.
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
          @session = {}
        end

        def call_url(call_object)
          new_url = URI(@url.to_s)
          new_url.query_params['rpc_call'] = call_object.encode(@session)

          return new_url
        end

        #
        # Returns +true+ if the RPC server is running and responding to
        # function calls, returns +false+ otherwise.
        #
        def running?
          call(:running)
        end

        #
        # Returns a finger-print of the PHP server.
        #
        def fingerprint
          call(:fingerprint)
        end

        protected

        #
        # Creates a new Call object for the specified _funtion_ and
        # _arguments_.
        #
        def create_call(function,*arguments)
          Call.new(function,*arguments)
        end

        #
        # Sends the specified _call_object_ to the RPC server. Returns
        # a new Response object that represents the server's response.
        #
        def send_call(call_object)
          resp = Net.http_get(:url => call_url(call_object),
                              :cookie => @cookie,
                              :proxy => @proxy,
                              :user_agent => @user_agent)

          new_cookie = resp['Set-Cookie']
          @cookie = new_cookie if new_cookie

          return Response.new(resp.body)
        end

        #
        # Returns the return-value of a previous function call encoded
        # into the specified _response_. If the _response_ contains
        # a fault message, the fault exception will be raised.
        #
        def return_value(response)
          status, params = response.decode

          unless status
            raise(params)
          end

          @session.merge!(params['session'])

          if params.has_key?('output')
            print(params['output'])
          end

          return params['return_value']
        end

      end
    end
  end
end
