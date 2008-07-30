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
require 'ronin/rpc/client'
require 'ronin/web'

require 'xmlrpc/client'

module Ronin
  module RPC
    module PHP
      class Client < RPC::Client

        # URL of RPC Server
        attr_reader :url

        def initialize(url,options={})
          @url = url

          @agent = Web.agent(options)
          @parser = XMLRPC::XMLParser::REXMLStreamParser.new
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
          @agent.get_file(call_url(call_obj))
        end

        def return_value(page)
          response = page[/<rpc>.*<\/rpc>/m]

          unless response
            raise(ResponseMissing,"failed to receive a valid RPC method response",caller)
          end

          status, params = @parser.parseMethodResponse(response)

          unless status
            raise(params)
          end

          return params
        end

      end
    end
  end
end
