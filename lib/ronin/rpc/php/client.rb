#
#--
# Ronin PHP - A Ruby library for Ronin that provides support for PHP
# related security tasks.
#
# Copyright (c) 2007-2008 Hal Brodigan (postmodern.mod3 at gmail.com)
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

require 'ronin/rpc/php/call'
require 'ronin/rpc/php/response'
require 'ronin/rpc/php/console'
require 'ronin/rpc/client'
require 'ronin/rpc/shell'
require 'ronin/network/http'

module Ronin
  module RPC
    module PHP
      class Client < RPC::Client

        # URL of RPC Server
        attr_reader :url

        # Proxy to send requests through
        attr_accessor :proxy

        # User-Agent string to send with each request
        attr_accessor :user_agent

        # Provides a console service
        service :console, Console

        # Provides a shell service
        service :shell, Shell

        def initialize(url,options={})
          @url = url

          @proxy = options[:proxy]

          if options[:user_agent_alias]
            @user_agent = Web.user_agent_alias[options[:user_agent_alias]]
          else
            @user_agent = options[:user_agent]
          end

          @cookie = nil
        end

        protected

        def call_url(call_obj)
          new_url = URI(@url.to_s)
          new_url.query_params['rpc_call'] = call_obj.encode

          return new_url
        end

        def create_call(func,*args)
          Call.new(func,*args)
        end

        def send_call(call_obj)
          resp = Net.http_get(:url => call_url(call_obj),
                              :cookie => @cookie,
                              :proxy => @proxy,
                              :user_agent => @user_agent)

          new_cookie = resp['Set-Cookie']
          @cookie = new_cookie if new_cookie

          return Response.new(resp.body)
        end

        def return_value(response)
          status, params = response.decode

          unless status
            raise(params)
          end

          return params
        end

      end
    end
  end
end
