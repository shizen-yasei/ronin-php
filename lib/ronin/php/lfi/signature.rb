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

module Ronin
  module PHP
    class LFI
      class Signature

        # Hash of OS specific paths for the signature
        attr_reader :paths

        # Hash of patterns to recognize the signature by
        attr_accessor :recognizor

        # Hash of extractor rules
        attr_reader :extractors

        #
        # Creates a new Signature object.
        #
        # @yield [signature]
        #   If a block is given, it will be passed the newly created
        #   signature object.
        #
        # @yieldparam [Signature] signature
        #   The newly created signature object.
        #
        def initialize(&block)
          @paths = Hash.new { |hash,key| hash[key] = [] }

          @recognizor = nil
          @extractors = {}

          block.call(self) if block
        end

        #
        # The supported OSes.
        #
        # @return [Array]
        #   OSes that the file can be found on.
        #
        def oses
          @paths.keys
        end

        #
        # All the paths of the file.
        #
        # @return [Array]
        #   Paths of the file.
        #
        def all_paths
          @paths.values.flatten.uniq
        end

        #
        # Finds the paths for the file commonly found on a given OS.
        #
        # @param [String] os
        #   The OS to search for.
        #
        # @return [String]
        #   The path that the file can be found at on the given OS.
        #
        def paths_for(os)
          @paths[os]
        end

        #
        # Iterates over each path.
        #
        # @yield [path]
        #   A given block will be passed each known path of the file.
        #
        # @yieldparam [String] path
        #   A path that the file is known to reside at.
        #
        def each_path(&block)
          @paths.each_value do |os_paths|
            os_paths.each(&block)
          end
        end

        #
        # Determines if the given body of text contains the file.
        #
        # @param [String] body
        #   The body of text to examine.
        #
        # @return [Boolean]
        #   Specifies whether the a given body of text has the file
        #   included in it.
        #
        def included_in?(body)
          if @recognizor
            return !((body =~ @recognizor).nil?)
          else
            return false
          end
        end

        #
        # Add an extraction rule to the file.
        #
        # @param [Symbol] name
        #   The name of the rule.
        #
        # @param [Regexp] pattern
        #   The pattern to extract.
        #
        def extract(name,pattern)
          @extractors[name] = pattern
        end

        #
        # Extracts data from a given body of text.
        #
        # @param [String] body
        #   The body of text to extract data from.
        #
        # @return [Hash]
        #   The extracted data, grouped by the name of the extract rule
        #   and the extracted data.
        #
        def extract_from(body)
          data = {}

          @extractors.each do |name,pattern|
            match = pattern.match(body)

            if match
              if match.length > 2
                data[name] = match[1..-1]
              elsif match.length == 2
                data[name] = match[1]
              else
                data[name] = match[0]
              end
            end
          end

          return data
        end

        #
        # All file signature categories.
        #
        # @return [Hash]
        #   All categories of signature files.
        #
        def Signature.categories
          @@categories ||= Hash.new { |hash,key| hash[key] = [] }
        end

        #
        # Determines if any signature files have been defined in a given
        # category.
        #
        # @param [Symbol] name
        #   The category to search for.
        #
        # @return [Boolean]
        #   Specifies whether there is a category with the given name.
        #
        def Signature.has_category?(name)
          Signature.categories.has_key?(name)
        end

        #
        # Finds the signature files within the category with a given name.
        #
        # @param [Symbol] name
        #   The category to search for.
        #
        # @return [Array<Signature>]
        #   The signature files within the category.
        #
        def Signature.category(name)
          Signature.categories[name]
        end

        #
        # All signature files.
        #
        # @return [Array<Signature>]
        #   All signature files.
        #
        def Signature.all
          Signature.categories.values.flatten
        end

        #
        # Iterates over all file signatures.
        #
        # @yield [signature]
        #   The given block will be passed each registered file signatures.
        #
        # @yieldparam [Signature] signature
        #   A file signature.
        #
        def Signature.each(&block)
          Signature.categories.each_value do |sigs|
            sigs.each(&block)
          end
        end

        #
        # Defines a new signature within the test category, used to test
        # for LFI.
        #
        def Signature.test(&block)
          Signature.define(:test,&block)
        end

        #
        # Defines a new signature within the config category of
        # configuration file signatures.
        #
        def Signature.config(&block)
          Signature.define(:config,&block)
        end

        #
        # Defines a new signature within the log category of log file
        # signatures.
        #
        def Signature.log(&block)
          Signature.define(:log,&block)
        end

        #
        # The file signatures used in testing for LFI.
        #
        # @return [Array<Signature>]
        #   The file signatures used to identify LFI vulnerabilities.
        #
        def Signature.tests
          Signature.category(:test)
        end

        #
        # The configuration file signatures.
        #
        # @return [Array<Signature>]
        #   The configuration file signatures.
        #
        def Signature.configs
          Signature.category(:config)
        end

        #
        # The log file signatures.
        #
        # @return [Array<Signature>]
        #   The log file signatures.
        #
        def Signature.logs
          Signature.category(:log)
        end

        #
        # All file signatures for a given OS.
        #
        # @param [String] os
        #   The OS to find file signatures for.
        #
        # @return [Array<Signature>]
        #   All file signatures for the given OS.
        #
        def Signature.signatures_for(os)
          Signature.each do |sig|
            if sig.oses.include?(os)
              return sig
            end
          end
        end

        #
        # All file signatures with extractors.
        #
        # @return [Array<Signature>]
        #   All file signatures with data extraction rules.
        #
        def Signature.with_extractors
          sigs = []

          Signature.each do |sig|
            unless sig.extractors.empty?
              sigs << sig
            end
          end

          return sigs
        end

        #
        # All file signatures with a matching file-name.
        #
        # @param [String] name
        #   The file-name to search for.
        #
        # @return [Array<Signature>]
        #   The file signatures with the matching file-name.
        #
        def Signature.with_file(name)
          Signature.each do |sig|
            sig.each_path do |path|
              return sig if path =~ /#{name}$/
            end
          end
        end

        protected

        #
        # Defines a new file signature in a given category.
        #
        # @param [Symbol] name
        #   The category to define the file signature within.
        #
        # @return [Signature]
        #   The newly defined file signature.
        #
        def self.define(name,&block)
          new_sig = Signature.new(&block)

          .categories[name] << new_sig
          return new_sig
        end

      end
    end
  end
end
